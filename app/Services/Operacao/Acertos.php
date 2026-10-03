<?php

declare(strict_types=1);

namespace App\Services\Operacao;

use App\Domain\Operacao\CalculoAcerto;
use App\Models\AcertoViagem;
use App\Models\Adiantamento;
use App\Models\Despesa;
use App\Models\Motorista;
use App\Models\PagamentoConta;
use App\Models\Viagem;
use App\Services\Financeiro\ContasPagar;
use App\Services\Financeiro\ContasPagarException;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Casos de uso do acerto de viagem (rotina 3070, mockup de 03/10/2026). Toda
 * mudança em adiantamento, conferência de despesa e acerto passa por aqui.
 *
 *  - Só motorista CLT (RN-12): agregado e autônomo são pagos pelo CIOT.
 *  - Fecha só viagem entregue/encerrada e com todas as despesas conferidas.
 *  - Fechado: despesas e adiantamentos da viagem não mudam mais. Saldo a favor
 *    do motorista vira conta a pagar no 5030; a favor da empresa, devolução.
 *  - Reabrir só com a conta do 5030 sem pagamento — ela é cancelada junto.
 */
final class Acertos
{
    public const STATUS_ACERTAVEIS = ['entregue', 'encerrada'];

    public function __construct(private readonly ContasPagar $contas)
    {
    }

    /**
     * Viagens que entram na fila do 3070: entregues/encerradas, com motorista
     * CLT, a partir de `financeiro.acerto.desde` (o histórico de antes da
     * rotina não vira pendência).
     *
     * @param  Builder<Viagem>  $q
     * @return Builder<Viagem>
     */
    public static function acertaveis(Builder $q): Builder
    {
        $desde = (string) config('financeiro.acerto.desde', '');

        return $q->whereIn('status', self::STATUS_ACERTAVEIS)
            ->whereHas('motorista', fn (Builder $m) => $m->where('vinculo', Motorista::VINCULO_CLT))
            ->when($desde !== '', fn (Builder $w) => $w->whereRaw('COALESCE(chegada_real, chegada_prevista, saida_real, saida_prevista, created_at) >= ?', [$desde]));
    }

    public function elegivel(Viagem $viagem): bool
    {
        $viagem->loadMissing('motorista');

        return (bool) $viagem->motorista?->controlaJornada();
    }

    public function fechado(Viagem $viagem): ?AcertoViagem
    {
        return AcertoViagem::query()->where('viagem_id', $viagem->id)->where('status', 'fechado')->latest('id')->first();
    }

    public function dias(Viagem $viagem): int
    {
        $saida = $viagem->saida_real ?? $viagem->saida_prevista;
        $chegada = $viagem->chegada_real ?? $viagem->chegada_prevista ?? $saida;
        if ($saida === null) {
            return 1;
        }

        return CalculoAcerto::dias(new DateTimeImmutable($saida->toDateTimeString()), new DateTimeImmutable(($chegada ?? $saida)->toDateTimeString()));
    }

    /**
     * Conta do acerto com o que está lançado agora.
     *
     * @return array<string,mixed>
     */
    public function resumo(Viagem $viagem, ?float $diaria = null, ?float $comissaoPct = null): array
    {
        $viagem->loadMissing('motorista');
        $diaria = round($diaria ?? (float) ($viagem->motorista?->valor_diaria ?? 0), 2);
        $comissaoPct = round($comissaoPct ?? (float) ($viagem->motorista?->percentual_comissao ?? 0), 2);
        $dias = $this->dias($viagem);
        $base = (float) $viagem->receita_total;

        $r = CalculoAcerto::calcular(
            Adiantamento::query()->where('viagem_id', $viagem->id)->pluck('valor')->map(fn ($v) => (float) $v)->all(),
            Despesa::query()->where('viagem_id', $viagem->id)->get()
                ->map(fn (Despesa $d) => ['valor' => (float) $d->valor, 'forma' => (string) $d->forma_pagamento, 'conferencia' => $d->conferencia()])
                ->all(),
            $dias, max(0, $diaria), max(0, min(100, $comissaoPct)), max(0, $base),
        );

        return $r + ['dias' => $dias, 'diaria' => $diaria, 'comissao_pct' => $comissaoPct, 'base_comissao' => $base];
    }

    /** @throws AcertoException */
    public function adicionarAdiantamento(Viagem $viagem, string $data, float $valor, string $forma, string $finalidade, ?string $obs = null): Adiantamento
    {
        if (! $this->elegivel($viagem)) {
            throw new AcertoException('Adiantamento de acerto é só para motorista CLT.');
        }
        if (round($valor, 2) <= 0) {
            throw new AcertoException('Informe o valor do adiantamento.');
        }
        if (! in_array($forma, Adiantamento::FORMAS, true) || ! in_array($finalidade, Adiantamento::FINALIDADES, true)) {
            throw new AcertoException('Escolha a forma e a finalidade do adiantamento.');
        }
        if (Carbon::parse($data)->isAfter(Carbon::today())) {
            throw new AcertoException('A data do adiantamento não pode ser no futuro.');
        }

        return DB::transaction(fn (): Adiantamento => $this->comViagemTravada($viagem, fn () => Adiantamento::create([
            'viagem_id' => $viagem->id,
            'motorista_id' => $viagem->motorista_id,
            'data' => Carbon::parse($data)->toDateString(),
            'valor' => round($valor, 2),
            'forma' => $forma,
            'finalidade' => $finalidade,
            'observacao' => $obs !== null && trim($obs) !== '' ? mb_substr(trim($obs), 0, 255) : null,
            'criado_por' => Auth::id(),
        ])));
    }

    /** @throws AcertoException */
    public function removerAdiantamento(Adiantamento $adiantamento): void
    {
        DB::transaction(fn () => $this->comViagemTravada($adiantamento->viagem()->firstOrFail(), fn () => $adiantamento->delete()));
    }

    /**
     * @param  'aceita'|'glosa'|'pendente'  $conferencia
     *
     * @throws AcertoException
     */
    public function conferir(Despesa $despesa, string $conferencia, ?string $motivo = null): void
    {
        $viagem = $despesa->viagem()->firstOrFail();
        if (! in_array($conferencia, [CalculoAcerto::ACEITA, CalculoAcerto::GLOSA, CalculoAcerto::PENDENTE], true)) {
            throw new AcertoException('Conferência inválida.');
        }

        DB::transaction(function () use ($despesa, $viagem, $conferencia, $motivo): void {
            $viagem = $this->comViagemTravada($viagem, fn (Viagem $v) => $v);
            $aceita = $conferencia === CalculoAcerto::ACEITA;
            $despesa->update([
                'aprovada' => $aceita,
                'aprovada_por' => $aceita ? Auth::id() : null,
                'aprovada_em' => $aceita ? now() : null,
                'glosada' => $conferencia === CalculoAcerto::GLOSA,
                'motivo_glosa' => $conferencia === CalculoAcerto::GLOSA && $motivo !== null && trim($motivo) !== '' ? mb_substr(trim($motivo), 0, 255) : null,
            ]);
            $viagem->recalcularCustosDeDespesas();
        });
    }

    /** @throws AcertoException */
    public function fechar(Viagem $viagem, float $diaria, float $comissaoPct, ?string $observacoes = null): AcertoViagem
    {
        if ($diaria < 0 || $comissaoPct < 0 || $comissaoPct > 100) {
            throw new AcertoException('Diária não pode ser negativa e a comissão vai de 0% a 100%.');
        }
        $diaria = round($diaria, 2);
        $comissaoPct = round($comissaoPct, 2);

        return DB::transaction(function () use ($viagem, $diaria, $comissaoPct, $observacoes): AcertoViagem {
            $viagem = Viagem::query()->with('motorista.pessoa')->lockForUpdate()->findOrFail($viagem->id);

            if (! $this->elegivel($viagem)) {
                throw new AcertoException('Acerto de viagem é só para motorista CLT. Agregado e autônomo são pagos pelo CIOT (4050).');
            }
            if (! in_array($viagem->status, self::STATUS_ACERTAVEIS, true)) {
                throw new AcertoException('A viagem precisa estar entregue para fechar o acerto.');
            }
            $this->exigirAberto($viagem);

            $r = $this->resumo($viagem, $diaria, $comissaoPct);
            if ($r['pendentes'] > 0) {
                throw new AcertoException($r['pendentes'] === 1 ? 'Falta conferir 1 despesa.' : "Faltam conferir {$r['pendentes']} despesas.");
            }

            $acerto = AcertoViagem::create([
                'viagem_id' => $viagem->id,
                'motorista_id' => $viagem->motorista_id,
                'status' => 'fechado',
                'total_adiantado' => $r['adiantado'],
                'gasto_adiantamento' => $r['gasto_adiantamento'],
                'bolso_aceito' => $r['bolso_aceito'],
                'glosado' => $r['glosado'],
                'dias' => $r['dias'],
                'diaria_valor' => round($diaria, 2),
                'diarias_total' => $r['diarias'],
                'comissao_percentual' => round($comissaoPct, 2),
                'comissao_base' => round($r['base_comissao'], 2),
                'comissao_valor' => $r['comissao'],
                'saldo' => $r['saldo'],
                'observacoes' => $observacoes !== null && trim($observacoes) !== '' ? trim($observacoes) : null,
                'fechado_em' => now(),
                'fechado_por' => Auth::id(),
            ]);

            if ($r['saldo'] > 0) {
                if ($viagem->motorista?->pessoa === null) {
                    throw new AcertoException('O motorista está sem cadastro de pessoa — não dá para lançar a conta a pagar.');
                }
                try {
                    $conta = $this->contas->lancarAcerto($acerto, $viagem->motorista->pessoa, $viagem->numero);
                } catch (ContasPagarException $e) {
                    throw new AcertoException($e->getMessage());
                }
                $acerto->update(['conta_pagar_id' => $conta->id]);
            }

            return $acerto;
        });
    }

    /** @throws AcertoException */
    public function reabrir(AcertoViagem $acerto, string $motivo): void
    {
        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 5) {
            throw new AcertoException('Escreva o motivo da reabertura (pelo menos 5 letras).');
        }

        DB::transaction(function () use ($acerto, $motivo): void {
            $acerto = AcertoViagem::query()->with('contaPagar')->lockForUpdate()->findOrFail($acerto->id);
            if ($acerto->status !== 'fechado') {
                throw new AcertoException('Este acerto não está fechado.');
            }
            if ($acerto->devolvido_em !== null) {
                throw new AcertoException('A devolução do motorista já foi registrada. Desfaça antes de reabrir.');
            }
            if ($acerto->contaPagar !== null && $acerto->contaPagar->status !== 'cancelado') {
                if (PagamentoConta::query()->validos()->where('conta_pagar_id', $acerto->contaPagar->id)->exists()) {
                    throw new AcertoException('A conta do acerto já tem pagamento no 5030. Estorne lá antes de reabrir.');
                }
                try {
                    $this->contas->cancelar($acerto->contaPagar, 'Acerto da viagem reaberto: ' . $motivo, peloAcerto: true);
                } catch (ContasPagarException $e) {
                    throw new AcertoException($e->getMessage());
                }
            }

            $acerto->update(['status' => 'reaberto', 'reaberto_em' => now(), 'reaberto_por' => Auth::id(), 'motivo_reabertura' => $motivo]);
        });
    }

    /** @throws AcertoException */
    public function registrarDevolucao(AcertoViagem $acerto, string $forma, string $data): void
    {
        if ($acerto->status !== 'fechado' || ! $acerto->motoristaDevolve()) {
            throw new AcertoException('Só acerto fechado com saldo a devolver.');
        }
        if (! in_array($forma, ['dinheiro', 'pix', 'deposito', 'desconto_folha'], true)) {
            throw new AcertoException('Escolha como o motorista devolveu.');
        }
        if (Carbon::parse($data)->isAfter(Carbon::today())) {
            throw new AcertoException('A data não pode ser no futuro.');
        }
        $acerto->update(['devolvido_em' => Carbon::parse($data)->toDateString(), 'devolvido_forma' => $forma]);
    }

    public function desfazerDevolucao(AcertoViagem $acerto): void
    {
        $acerto->update(['devolvido_em' => null, 'devolvido_forma' => null]);
    }

    /**
     * Trava a viagem (mesma trava do fechar) e só então confere que o acerto
     * está aberto — nada entra entre o resumo do fechamento e o commit.
     * Chamar dentro de uma transação.
     *
     * @template T
     * @param  callable(Viagem): T  $acao
     * @return T
     *
     * @throws AcertoException
     */
    private function comViagemTravada(Viagem $viagem, callable $acao): mixed
    {
        $travada = Viagem::query()->lockForUpdate()->findOrFail($viagem->id);
        $this->exigirAberto($travada);

        return $acao($travada);
    }

    /** @throws AcertoException */
    private function exigirAberto(Viagem $viagem): void
    {
        if (AcertoViagem::query()->where('viagem_id', $viagem->id)->where('status', 'fechado')->exists()) {
            throw new AcertoException('O acerto desta viagem está fechado. Reabra para mudar.');
        }
    }
}
