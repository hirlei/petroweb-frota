<?php

declare(strict_types=1);

namespace App\Services\Financeiro;

use App\Domain\Financeiro\Parcelamento;
use App\Domain\Fiscal\RegrasCiot;
use App\Models\Abastecimento;
use App\Models\Ciot;
use App\Models\ContaPagar;
use App\Models\ContaPagarOrigem;
use App\Models\OrdemServico;
use App\Models\PagamentoConta;
use App\Models\Pessoa;
use App\Support\TenantContext;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Casos de uso do contas a pagar (rotina 5030, mockup de 03/10/2026). Toda
 * mudança de valor e status de conta e pagamento passa por aqui.
 *
 *  - Lançamentos esperando: abastecimento com posto (uma conta por posto), OS
 *    encerrada em oficina externa (uma por OS) e frete de TAC do CIOT (um por
 *    CIOT). Uma origem só vira UMA conta ativa (índice parcial do banco).
 *  - Vencimento pelo prazo do favorecido no cadastro (pessoas.prazo_faturamento);
 *    sem prazo, 30 dias. CIOT vence no prazo de quitação do próprio CIOT.
 *  - Pagamento menor que o saldo deixa a conta parcial; juros e multa saem do
 *    caixa mas não abatem saldo; estorno marca, não apaga.
 *  - Conta de CIOT não se paga aqui: espelha os pagamentos do 4050.
 *  - Cancelar só sem pagamento válido; as origens voltam a ficar esperando.
 */
final class ContasPagar
{
    public const PRAZO_PADRAO = '30';

    /* ── Lançamentos esperando ── */

    /**
     * @return array{
     *   abastecimentos: Collection<int, array{favorecido: Pessoa, itens: Collection<int, Abastecimento>, total: float, condicao: string}>,
     *   ordens: Collection<int, OrdemServico>,
     *   ciots: Collection<int, Ciot>
     * }
     */
    public function sugestoes(): array
    {
        $desde = Carbon::today()->subDays((int) config('financeiro.pagar.sugestoes_dias', 90));

        $abastecimentos = Abastecimento::query()
            ->with(['posto', 'veiculo'])
            ->whereNotNull('posto_id')->where('valor_total', '>', 0)
            ->where('data_hora', '>=', $desde)
            ->whereNotExists($this->semContaAtiva('abastecimento', 'abastecimentos'))
            ->orderBy('data_hora')
            ->get()
            ->groupBy('posto_id')
            ->map(fn (Collection $itens) => [
                'favorecido' => $itens->first()->posto,
                'itens' => $itens->values(),
                'total' => round((float) $itens->sum(fn (Abastecimento $a) => (float) $a->valor_total), 2),
                'condicao' => $this->condicaoDe($itens->first()->posto),
            ])
            ->filter(fn ($g) => $g['favorecido'] !== null)
            ->values();

        $ordens = OrdemServico::query()
            ->with(['oficina', 'veiculo'])
            ->where('status', 'encerrada')->where('interna', false)
            ->whereNotNull('oficina_id')->where('valor_total', '>', 0)
            ->where(fn (Builder $q) => $q->whereNull('encerramento')->orWhere('encerramento', '>=', $desde))
            ->whereNotExists($this->semContaAtiva('ordem_servico', 'ordens_servico'))
            ->orderBy('encerramento')
            ->get();

        $ciots = Ciot::query()
            ->with(['viagem.veiculoTracao', 'pagamentos'])
            ->where('modalidade', RegrasCiot::IPEF)
            ->whereIn('status', ['registrado', 'quitado'])
            ->whereNotNull('contratado_id')->where('valor_frete', '>', 0)
            ->where('registrado_em', '>=', $desde)
            ->whereNotExists($this->semContaAtiva('ciot', 'ciots'))
            ->orderBy('registrado_em')
            ->get();

        return ['abastecimentos' => $abastecimentos, 'ordens' => $ordens, 'ciots' => $ciots];
    }

    public function totalEsperando(): array
    {
        $s = $this->sugestoes();

        return [
            'itens' => (int) $s['abastecimentos']->sum(fn ($g) => $g['itens']->count()) + $s['ordens']->count() + $s['ciots']->count(),
            'abastecimentos' => (int) $s['abastecimentos']->sum(fn ($g) => $g['itens']->count()),
            'ordens' => $s['ordens']->count(),
            'ciots' => $s['ciots']->count(),
            'contas' => $s['abastecimentos']->count() + $s['ordens']->count() + $s['ciots']->count(),
            'valor' => round((float) $s['abastecimentos']->sum('total')
                + (float) $s['ordens']->sum(fn (OrdemServico $o) => (float) $o->valor_total)
                + (float) $s['ciots']->sum(fn (Ciot $c) => (float) $c->valor_frete), 2),
        ];
    }

    /**
     * Lança os itens marcados. Abastecimentos viram uma conta por posto.
     *
     * @param  array{abastecimento?: list<int>, ordem_servico?: list<int>, ciot?: list<int>}  $selecao
     * @return int quantas contas (parcelas contam como uma) foram criadas
     *
     * @throws ContasPagarException
     */
    public function lancarSugestoes(array $selecao): int
    {
        $abastIds = array_values(array_unique(array_map('intval', $selecao['abastecimento'] ?? [])));
        $osIds = array_values(array_unique(array_map('intval', $selecao['ordem_servico'] ?? [])));
        $ciotIds = array_values(array_unique(array_map('intval', $selecao['ciot'] ?? [])));

        if ($abastIds === [] && $osIds === [] && $ciotIds === []) {
            throw new ContasPagarException('Marque ao menos um lançamento.');
        }

        return DB::transaction(function () use ($abastIds, $osIds, $ciotIds): int {
            $criadas = 0;
            $hoje = Carbon::today();

            if ($abastIds !== []) {
                $linhas = Abastecimento::query()->with('posto')->whereIn('id', $abastIds)
                    ->whereNotNull('posto_id')->where('valor_total', '>', 0)->lockForUpdate()->get();
                if ($linhas->count() !== count($abastIds)) {
                    throw new ContasPagarException('Algum abastecimento marcado não pode ser lançado. Atualize a tela.');
                }
                foreach ($linhas->groupBy('posto_id') as $itens) {
                    $this->exigirSemConta('abastecimento', $itens->pluck('id')->all());
                    $posto = $itens->first()->posto;
                    if ($posto === null) {
                        throw new ContasPagarException('O posto de algum abastecimento não existe mais no cadastro.');
                    }
                    $notas = $itens->pluck('nota_fiscal')->filter()->unique()->values();
                    $this->criar(
                        favorecido: $posto,
                        categoria: 'combustivel',
                        valor: round((float) $itens->sum(fn (Abastecimento $a) => (float) $a->valor_total), 2),
                        condicao: $this->condicaoDe($posto),
                        emissao: $hoje,
                        documento: $notas->count() === 1 ? 'NF ' . $notas->first() : null,
                        descricao: $itens->count() . ' ' . ($itens->count() === 1 ? 'abastecimento' : 'abastecimentos'),
                        origem: 'abastecimento',
                        origens: $itens->map(fn (Abastecimento $a) => ['abastecimento', $a->id, (float) $a->valor_total])->all(),
                        filialId: $itens->first()->filial_id,
                    );
                    $criadas++;
                }
            }

            $ordens = OrdemServico::query()->with('oficina')->whereIn('id', $osIds)->lockForUpdate()->get();
            if ($ordens->count() !== count($osIds)) {
                throw new ContasPagarException('Alguma OS marcada não foi encontrada. Atualize a tela.');
            }
            foreach ($ordens as $os) {
                if ($os->oficina === null || $os->status !== 'encerrada' || $os->interna || (float) $os->valor_total <= 0) {
                    throw new ContasPagarException("A OS {$os->numero} não está encerrada em oficina externa com valor.");
                }
                $this->exigirSemConta('ordem_servico', [$os->id]);
                $this->criar(
                    favorecido: $os->oficina,
                    categoria: 'manutencao',
                    valor: round((float) $os->valor_total, 2),
                    condicao: $this->condicaoDe($os->oficina),
                    emissao: $hoje,
                    documento: 'OS ' . $os->numero,
                    descricao: 'Ordem de serviço ' . $os->numero,
                    origem: 'ordem_servico',
                    origens: [['ordem_servico', $os->id, (float) $os->valor_total]],
                    filialId: $os->filial_id,
                );
                $criadas++;
            }

            $ciots = Ciot::query()->with(['contratado', 'viagem'])->whereIn('id', $ciotIds)->lockForUpdate()->get();
            if ($ciots->count() !== count($ciotIds)) {
                throw new ContasPagarException('Algum CIOT marcado não foi encontrado. Atualize a tela.');
            }
            foreach ($ciots as $ciot) {
                if ($ciot->contratado === null || ! $ciot->valido() || $ciot->modalidade !== RegrasCiot::IPEF || (float) $ciot->valor_frete <= 0) {
                    throw new ContasPagarException('Só CIOT de TAC, registrado, vira conta a pagar.');
                }
                $this->exigirSemConta('ciot', [$ciot->id]);
                $vencimento = $ciot->prazo_quitacao ?? $hoje->copy()->addDays(30);
                $conta = $this->criar(
                    favorecido: $ciot->contratado,
                    categoria: 'frete_terceiro',
                    valor: round((float) $ciot->valor_frete, 2),
                    condicao: '0',
                    emissao: $hoje,
                    documento: $ciot->viagem?->numero,
                    descricao: 'Frete TAC · CIOT ' . $ciot->numeroFormatado(),
                    origem: 'ciot',
                    origens: [['ciot', $ciot->id, (float) $ciot->valor_frete]],
                    filialId: $ciot->filial_id,
                    vencimentoFixo: Carbon::parse($vencimento),
                )->first();
                $this->espelharPagamentosCiot($ciot, $conta);
                $criadas++;
            }

            return $criadas;
        });
    }

    /* ── Lançamento manual ── */

    /**
     * @return Collection<int, ContaPagar> as parcelas criadas
     *
     * @throws ContasPagarException
     */
    public function lancarManual(Pessoa $favorecido, string $categoria, float $valor, string $condicao, Carbon $emissao, ?string $documento = null, ?string $observacoes = null): Collection
    {
        if (! in_array($categoria, ContaPagar::CATEGORIAS, true) || $categoria === 'frete_terceiro') {
            throw new ContasPagarException('Escolha a categoria. Frete de TAC nasce do CIOT, nos lançamentos esperando.');
        }
        if ($valor <= 0) {
            throw new ContasPagarException('Informe o valor da conta.');
        }

        return DB::transaction(fn () => $this->criar(
            favorecido: $favorecido,
            categoria: $categoria,
            valor: round($valor, 2),
            condicao: $condicao,
            emissao: $emissao,
            documento: $documento,
            descricao: null,
            origem: 'manual',
            origens: [],
            filialId: TenantContext::filial()?->id,
            observacoes: $observacoes,
        ));
    }

    /* ── Pagar, estornar, cancelar ── */

    /**
     * @param  array{data:string, forma:string, conta?:?string, valor_principal:float, juros_multa?:float, desconto?:float, observacao?:?string}  $dados
     *
     * @throws ContasPagarException
     */
    public function pagar(ContaPagar $conta, array $dados): PagamentoConta
    {
        $principal = round((float) $dados['valor_principal'], 2);
        $juros = round((float) ($dados['juros_multa'] ?? 0), 2);
        $desconto = round((float) ($dados['desconto'] ?? 0), 2);

        if (! in_array($dados['forma'], PagamentoConta::FORMAS, true)) {
            throw new ContasPagarException('Escolha a forma do pagamento.');
        }
        if ($principal < 0 || $juros < 0 || $desconto < 0) {
            throw new ContasPagarException('Os valores não podem ser negativos.');
        }
        if ($principal + $desconto <= 0) {
            throw new ContasPagarException('Informe o valor pago.');
        }
        if (Carbon::parse($dados['data'])->isAfter(Carbon::today())) {
            throw new ContasPagarException('A data do pagamento não pode ser no futuro.');
        }

        return DB::transaction(function () use ($conta, $dados, $principal, $juros, $desconto): PagamentoConta {
            $conta = ContaPagar::query()->lockForUpdate()->findOrFail($conta->id);

            if ($conta->pagaNoCiot()) {
                throw new ContasPagarException('Frete de TAC é pago pela instituição do CIOT, no 4050.');
            }
            if (! $conta->emAberto()) {
                throw new ContasPagarException('Esta conta não está em aberto.');
            }
            if ($principal + $desconto > $conta->saldo() + 0.004) {
                throw new ContasPagarException(sprintf('O valor mais o desconto passam do saldo da conta (R$ %s).', number_format($conta->saldo(), 2, ',', '.')));
            }

            $pagamento = PagamentoConta::create([
                'conta_pagar_id' => $conta->id,
                'data' => Carbon::parse($dados['data'])->toDateString(),
                'forma' => $dados['forma'],
                'conta' => $this->texto($dados['conta'] ?? null, 80),
                'valor_principal' => $principal,
                'juros_multa' => $juros,
                'desconto' => $desconto,
                'valor_total' => round($principal + $juros, 2),
                'observacao' => $this->texto($dados['observacao'] ?? null, 255),
                'registrado_por' => Auth::id(),
            ]);

            $this->recalcular($conta);

            return $pagamento;
        });
    }

    /** @throws ContasPagarException */
    public function estornar(PagamentoConta $pagamento, string $motivo): void
    {
        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 5) {
            throw new ContasPagarException('Escreva o motivo do estorno (pelo menos 5 letras).');
        }

        DB::transaction(function () use ($pagamento, $motivo): void {
            $pagamento = PagamentoConta::query()->lockForUpdate()->findOrFail($pagamento->id);
            if ($pagamento->estornado()) {
                throw new ContasPagarException('Este pagamento já foi estornado.');
            }
            if ($pagamento->doCiot()) {
                throw new ContasPagarException('Pagamento do CIOT se resolve na instituição, pelo 4050.');
            }

            $conta = ContaPagar::query()->lockForUpdate()->findOrFail($pagamento->conta_pagar_id);
            if ($conta->status === 'cancelado') {
                throw new ContasPagarException('A conta está cancelada.');
            }

            $pagamento->update(['estornado_em' => now(), 'estornado_por' => Auth::id(), 'motivo_estorno' => $motivo]);
            $this->recalcular($conta);
        });
    }

    /**
     * Cancela o LANÇAMENTO inteiro (todas as parcelas do grupo): as origens
     * ficam na 1ª parcela, e cancelar só uma deixaria o item duplicável.
     *
     * @throws ContasPagarException
     */
    public function cancelar(ContaPagar $conta, string $motivo): void
    {
        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 5) {
            throw new ContasPagarException('Escreva o motivo do cancelamento (pelo menos 5 letras).');
        }

        DB::transaction(function () use ($conta, $motivo): void {
            $parcelas = ContaPagar::query()->where('grupo', $conta->grupo)->lockForUpdate()->get();
            if ($parcelas->every(fn (ContaPagar $c) => $c->status === 'cancelado')) {
                throw new ContasPagarException('Esta conta já está cancelada.');
            }
            if (PagamentoConta::query()->validos()->whereIn('conta_pagar_id', $parcelas->pluck('id'))->exists()) {
                throw new ContasPagarException('O lançamento tem pagamento registrado. Estorne antes de cancelar.');
            }

            ContaPagar::query()->whereIn('id', $parcelas->pluck('id'))->update([
                'status' => 'cancelado', 'cancelado_em' => now(), 'cancelado_por' => Auth::id(), 'motivo_cancelamento' => $motivo,
            ]);
            ContaPagarOrigem::query()->whereIn('conta_pagar_id', $parcelas->pluck('id'))->update(['ativo' => false]);
        });
    }

    /* ── Integração com o CIOT (4050) ── */

    /**
     * Traz para a conta os pagamentos do CIOT confirmados que ainda não estão
     * nela. Idempotente — chamado pelo ServicoCiot a cada pagamento.
     */
    public function espelharPagamentosCiot(Ciot $ciot, ?ContaPagar $conta = null): void
    {
        $conta ??= ContaPagar::query()->where('origem', 'ciot')
            ->whereHas('origens', fn (Builder $q) => $q->where('origem_tipo', 'ciot')->where('origem_id', $ciot->id)->where('ativo', true))
            ->first();
        if ($conta === null || $conta->status === 'cancelado') {
            return;
        }

        DB::transaction(function () use ($ciot, $conta): void {
            $conta = ContaPagar::query()->lockForUpdate()->findOrFail($conta->id);
            $jaEspelhados = PagamentoConta::query()->where('conta_pagar_id', $conta->id)->whereNotNull('ciot_pagamento_id')->pluck('ciot_pagamento_id')->all();

            foreach ($ciot->pagamentos()->where('status', 'confirmado')->get() as $p) {
                if (in_array($p->id, $jaEspelhados, true)) {
                    continue;
                }
                $valor = min(round((float) $p->valor, 2), $conta->fresh()->saldo());
                if ($valor <= 0) {
                    continue;
                }
                PagamentoConta::create([
                    'conta_pagar_id' => $conta->id,
                    'data' => $p->data?->toDateString() ?? Carbon::today()->toDateString(),
                    'forma' => 'instituicao_ciot',
                    'conta' => $ciot->instituicao,
                    'valor_principal' => $valor,
                    'juros_multa' => 0,
                    'desconto' => 0,
                    'valor_total' => $valor,
                    'observacao' => ucfirst((string) $p->tipo) . ' do CIOT ' . $ciot->numeroFormatado(),
                    'ciot_pagamento_id' => $p->id,
                    'registrado_por' => $p->criado_por,
                ]);
                $this->recalcular($conta);
            }
        });
    }

    /** CIOT cancelado: a conta que nasceu dele (sem pagamento) é cancelada junto. */
    public function cancelarDoCiot(Ciot $ciot): void
    {
        $contas = ContaPagar::query()->where('origem', 'ciot')->whereIn('status', ContaPagar::EM_ABERTO)
            ->whereHas('origens', fn (Builder $q) => $q->where('origem_tipo', 'ciot')->where('origem_id', $ciot->id)->where('ativo', true))
            ->get();

        foreach ($contas as $conta) {
            try {
                $this->cancelar($conta, 'CIOT ' . $ciot->numeroFormatado() . ' cancelado');
            } catch (ContasPagarException) {
                // Com pagamento, a conta fica — alguém precisa olhar.
            }
        }
    }

    /* ── internos ── */

    /**
     * @param  list<array{0:string,1:int,2:float}>  $origens
     * @return Collection<int, ContaPagar>
     *
     * @throws ContasPagarException
     */
    private function criar(
        Pessoa $favorecido, string $categoria, float $valor, string $condicao, Carbon $emissao,
        ?string $documento, ?string $descricao, string $origem, array $origens, ?int $filialId,
        ?string $observacoes = null, ?Carbon $vencimentoFixo = null,
    ): Collection {
        $valor = round($valor, 2);
        if ($valor <= 0) {
            throw new ContasPagarException('Informe o valor da conta.');
        }

        try {
            $prazos = Parcelamento::prazos($condicao);
            if ((int) round($valor * 100) < count($prazos)) {
                throw new ContasPagarException('Valor pequeno demais para tantas parcelas.');
            }
            $parcelas = Parcelamento::dividir($valor, $prazos, new DateTimeImmutable($emissao->toDateString()));
        } catch (InvalidArgumentException $e) {
            throw new ContasPagarException($e->getMessage());
        }

        $grupo = (string) Str::uuid();
        $contas = collect();

        foreach ($parcelas as $p) {
            $contas->push(ContaPagar::create([
                'filial_id' => $filialId,
                'favorecido_id' => $favorecido->id,
                'categoria' => $categoria,
                'documento' => $this->texto($documento, 60),
                'descricao' => $this->texto($descricao, 200),
                'grupo' => $grupo,
                'parcela' => $p['parcela'],
                'parcelas' => $p['parcelas'],
                'emissao' => $emissao->toDateString(),
                'vencimento' => $vencimentoFixo?->toDateString() ?? $p['vencimento']->format('Y-m-d'),
                'valor' => $p['valor'],
                'valor_baixado' => 0,
                'status' => 'aberto',
                'origem' => $origem,
                'observacoes' => $this->texto($observacoes, 2000),
                'criado_por' => Auth::id(),
            ]));
        }

        // Origens penduradas na 1ª parcela (o vínculo é do lançamento).
        foreach ($origens as [$tipo, $id, $v]) {
            ContaPagarOrigem::create([
                'conta_pagar_id' => $contas->first()->id,
                'origem_tipo' => $tipo,
                'origem_id' => $id,
                'valor' => round($v, 2),
                'ativo' => true,
            ]);
        }

        return $contas;
    }

    private function recalcular(ContaPagar $conta): void
    {
        $baixado = round((float) PagamentoConta::query()->validos()->where('conta_pagar_id', $conta->id)
            ->selectRaw('COALESCE(SUM(valor_principal + desconto), 0) AS total')->value('total'), 2);

        $status = match (true) {
            $baixado >= (float) $conta->valor - 0.004 => 'pago',
            $baixado > 0 => 'parcial',
            default => 'aberto',
        };

        $conta->update(['valor_baixado' => $baixado, 'status' => $status]);
    }

    /** @param list<int> $ids @throws ContasPagarException */
    private function exigirSemConta(string $tipo, array $ids): void
    {
        if (ContaPagarOrigem::query()->where('origem_tipo', $tipo)->whereIn('origem_id', $ids)->where('ativo', true)->exists()) {
            throw new ContasPagarException('Algum item marcado já virou conta. Atualize a tela.');
        }
    }

    private function semContaAtiva(string $tipo, string $tabela): \Closure
    {
        return fn ($q) => $q->selectRaw('1')->from('conta_pagar_origens')
            ->where('conta_pagar_origens.origem_tipo', $tipo)
            ->whereColumn('conta_pagar_origens.origem_id', "{$tabela}.id")
            ->where('conta_pagar_origens.ativo', true);
    }

    /** Condição pelo prazo do favorecido; sem prazo (ou inválido), 30 dias. */
    public function condicaoDe(?Pessoa $p): string
    {
        $c = trim((string) ($p?->prazo_faturamento ?? ''));
        if ($c === '') {
            return self::PRAZO_PADRAO;
        }

        try {
            return Parcelamento::condicao(Parcelamento::prazos($c));
        } catch (InvalidArgumentException) {
            return self::PRAZO_PADRAO;
        }
    }

    private function texto(?string $v, int $max): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : mb_substr($v, 0, $max);
    }
}
