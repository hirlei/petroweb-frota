<?php

declare(strict_types=1);

namespace App\Services\Financeiro;

use App\Domain\Financeiro\Parcelamento;
use App\Models\Cte;
use App\Models\Fatura;
use App\Models\FaturaCte;
use App\Models\Pessoa;
use App\Models\Recebimento;
use App\Models\TituloReceber;
use App\Support\TenantContext;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Casos de uso do faturamento (rotinas 5010 e 5020). Toda mudança de valor e
 * de status de fatura, título e recebimento passa por aqui — as telas só
 * chamam estes métodos.
 *
 * Regras:
 *  - fatura agrupa CT-e AUTORIZADOS de UM tomador, que ainda não estão em
 *    fatura ativa (o índice parcial do banco é a última barreira);
 *  - cada parcela vira um título; recebimento menor que o saldo deixa o
 *    título parcial; juros e multa entram no caixa mas não abatem saldo;
 *  - cancelar só sem recebimento válido — com recebimento, estorne antes;
 *  - estorno não apaga o recebimento: marca e recalcula.
 */
final class Faturamento
{
    /**
     * @param  list<int>  $cteIds
     *
     * @throws FaturamentoException
     */
    public function gerar(
        Pessoa $tomador,
        array $cteIds,
        string $condicao,
        float $desconto = 0.0,
        float $acrescimo = 0.0,
        ?string $observacoes = null,
        ?Carbon $emissao = null,
    ): Fatura {
        $cteIds = array_values(array_unique(array_map('intval', $cteIds)));
        if ($cteIds === []) {
            throw new FaturamentoException('Marque ao menos um CT-e.');
        }
        if ($desconto < 0 || $acrescimo < 0) {
            throw new FaturamentoException('Desconto e acréscimo não podem ser negativos.');
        }

        try {
            $prazos = Parcelamento::prazos($condicao);
        } catch (InvalidArgumentException $e) {
            throw new FaturamentoException($e->getMessage());
        }

        $emissao = ($emissao ?? Carbon::today())->copy()->startOfDay();

        return DB::transaction(function () use ($tomador, $cteIds, $prazos, $desconto, $acrescimo, $observacoes, $emissao): Fatura {
            // Trava os CT-e: duas pessoas faturando ao mesmo tempo não pegam o mesmo documento.
            $ctes = Cte::query()->whereIn('id', $cteIds)->lockForUpdate()->get();

            if ($ctes->count() !== count($cteIds)) {
                throw new FaturamentoException('Algum CT-e marcado não foi encontrado. Atualize a tela e tente de novo.');
            }

            foreach ($ctes as $cte) {
                $num = number_format((int) $cte->numero, 0, ',', '.');
                if ($cte->status !== 'autorizado') {
                    throw new FaturamentoException("O CT-e {$num} não está autorizado e não pode ser faturado.");
                }
                if ((int) $cte->tipo_cte === 2) {
                    throw new FaturamentoException("O CT-e {$num} é de anulação e não entra em fatura.");
                }
                if ((int) $cte->tomador_id !== (int) $tomador->id) {
                    throw new FaturamentoException("O CT-e {$num} é de outro tomador. Uma fatura junta CT-e de um cliente só.");
                }
                if ($cte->faturaItens()->where('ativo', true)->exists()) {
                    throw new FaturamentoException("O CT-e {$num} já está em outra fatura.");
                }
            }

            $valorCtes = round((float) $ctes->sum(fn (Cte $c) => (float) $c->valor_total_servico), 2);
            $total = round($valorCtes - $desconto + $acrescimo, 2);
            if ($total <= 0) {
                throw new FaturamentoException('O total da fatura precisa ser maior que zero. Confira o desconto.');
            }

            $sequencia = $this->proximaSequencia();
            $numero = str_pad((string) $sequencia, 6, '0', STR_PAD_LEFT);

            $fatura = Fatura::create([
                'filial_id' => TenantContext::filial()?->id ?? $ctes->first()->filial_id,
                'sequencia' => $sequencia,
                'numero' => $numero,
                'tomador_id' => $tomador->id,
                'emissao' => $emissao->toDateString(),
                'valor_ctes' => $valorCtes,
                'desconto' => $desconto,
                'acrescimo' => $acrescimo,
                'valor_total' => $total,
                'valor_recebido' => 0,
                'condicao' => Parcelamento::condicao($prazos),
                'status' => 'aberta',
                'observacoes' => $observacoes !== null && trim($observacoes) !== '' ? trim($observacoes) : null,
                'criada_por' => Auth::id(),
            ]);

            foreach ($ctes as $cte) {
                FaturaCte::create([
                    'fatura_id' => $fatura->id,
                    'cte_id' => $cte->id,
                    'valor' => (float) $cte->valor_total_servico,
                    'ativo' => true,
                ]);
            }

            $parcelas = Parcelamento::dividir($total, $prazos, new DateTimeImmutable($emissao->toDateString()));
            foreach ($parcelas as $p) {
                TituloReceber::create([
                    'fatura_id' => $fatura->id,
                    'tomador_id' => $tomador->id,
                    'numero' => "{$numero}-{$p['parcela']}/{$p['parcelas']}",
                    'parcela' => $p['parcela'],
                    'parcelas' => $p['parcelas'],
                    'vencimento' => $p['vencimento']->format('Y-m-d'),
                    'valor' => $p['valor'],
                    'valor_baixado' => 0,
                    'status' => 'aberto',
                ]);
            }

            return $fatura->fresh(['titulos', 'tomador']);
        });
    }

    /** @throws FaturamentoException */
    public function cancelar(Fatura $fatura, string $motivo): void
    {
        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 5) {
            throw new FaturamentoException('Escreva o motivo do cancelamento (pelo menos 5 letras).');
        }

        DB::transaction(function () use ($fatura, $motivo): void {
            $fatura = Fatura::query()->lockForUpdate()->findOrFail($fatura->id);

            if ($fatura->cancelada()) {
                throw new FaturamentoException('Esta fatura já está cancelada.');
            }

            $temRecebimento = Recebimento::query()->validos()
                ->whereIn('titulo_id', $fatura->titulos()->pluck('id'))
                ->exists();
            if ($temRecebimento) {
                throw new FaturamentoException('A fatura tem recebimento registrado. Estorne o recebimento antes de cancelar.');
            }

            $fatura->update([
                'status' => 'cancelada',
                'cancelada_em' => now(),
                'cancelada_por' => Auth::id(),
                'motivo_cancelamento' => $motivo,
            ]);
            $fatura->itens()->update(['ativo' => false]);
            $fatura->titulos()->update(['status' => 'cancelado']);
        });
    }

    /**
     * @param  array{data:string, forma:string, conta?:?string, valor_principal:float, juros_multa?:float, desconto?:float, observacao?:?string}  $dados
     *
     * @throws FaturamentoException
     */
    public function receber(TituloReceber $titulo, array $dados): Recebimento
    {
        $principal = round((float) $dados['valor_principal'], 2);
        $juros = round((float) ($dados['juros_multa'] ?? 0), 2);
        $desconto = round((float) ($dados['desconto'] ?? 0), 2);

        if (! in_array($dados['forma'], Recebimento::FORMAS, true)) {
            throw new FaturamentoException('Escolha a forma do recebimento.');
        }
        if ($principal < 0 || $juros < 0 || $desconto < 0) {
            throw new FaturamentoException('Os valores não podem ser negativos.');
        }
        if ($principal + $desconto <= 0) {
            throw new FaturamentoException('Informe o valor recebido.');
        }
        if (Carbon::parse($dados['data'])->isAfter(Carbon::today())) {
            throw new FaturamentoException('A data do recebimento não pode ser no futuro.');
        }

        return DB::transaction(function () use ($titulo, $dados, $principal, $juros, $desconto): Recebimento {
            $titulo = TituloReceber::query()->lockForUpdate()->findOrFail($titulo->id);

            if (! $titulo->emAberto()) {
                throw new FaturamentoException('Este título não está em aberto.');
            }
            if ($principal + $desconto > $titulo->saldo() + 0.004) {
                throw new FaturamentoException(sprintf(
                    'O valor mais o desconto passam do saldo do título (R$ %s).',
                    number_format($titulo->saldo(), 2, ',', '.'),
                ));
            }

            $recebimento = Recebimento::create([
                'titulo_id' => $titulo->id,
                'data' => Carbon::parse($dados['data'])->toDateString(),
                'forma' => $dados['forma'],
                'conta' => isset($dados['conta']) && trim((string) $dados['conta']) !== '' ? trim((string) $dados['conta']) : null,
                'valor_principal' => $principal,
                'juros_multa' => $juros,
                'desconto' => $desconto,
                'valor_total' => round($principal + $juros, 2),
                'observacao' => isset($dados['observacao']) && trim((string) $dados['observacao']) !== '' ? trim((string) $dados['observacao']) : null,
                'registrado_por' => Auth::id(),
            ]);

            $this->recalcularTitulo($titulo);

            return $recebimento;
        });
    }

    /** @throws FaturamentoException */
    public function estornar(Recebimento $recebimento, string $motivo): void
    {
        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 5) {
            throw new FaturamentoException('Escreva o motivo do estorno (pelo menos 5 letras).');
        }

        DB::transaction(function () use ($recebimento, $motivo): void {
            $recebimento = Recebimento::query()->lockForUpdate()->findOrFail($recebimento->id);
            if ($recebimento->estornado()) {
                throw new FaturamentoException('Este recebimento já foi estornado.');
            }

            $titulo = TituloReceber::query()->lockForUpdate()->findOrFail($recebimento->titulo_id);
            if ($titulo->status === 'cancelado') {
                throw new FaturamentoException('O título está cancelado.');
            }

            $recebimento->update([
                'estornado_em' => now(),
                'estornado_por' => Auth::id(),
                'motivo_estorno' => $motivo,
            ]);

            $this->recalcularTitulo($titulo);
        });
    }

    /** Recalcula saldo e status do título e, em seguida, da fatura dele. */
    private function recalcularTitulo(TituloReceber $titulo): void
    {
        $baixado = (float) Recebimento::query()->validos()->where('titulo_id', $titulo->id)
            ->selectRaw('COALESCE(SUM(valor_principal + desconto), 0) AS total')->value('total');
        $baixado = round($baixado, 2);

        $status = match (true) {
            $baixado >= (float) $titulo->valor - 0.004 => 'recebido',
            $baixado > 0 => 'parcial',
            default => 'aberto',
        };

        $titulo->update(['valor_baixado' => $baixado, 'status' => $status]);

        $this->recalcularFatura(Fatura::query()->lockForUpdate()->findOrFail($titulo->fatura_id));
    }

    private function recalcularFatura(Fatura $fatura): void
    {
        if ($fatura->cancelada()) {
            return;
        }

        $titulos = TituloReceber::query()->where('fatura_id', $fatura->id)->get();
        $baixado = round((float) $titulos->sum(fn (TituloReceber $t) => (float) $t->valor_baixado), 2);

        $status = match (true) {
            $titulos->every(fn (TituloReceber $t) => $t->status === 'recebido') => 'paga',
            $baixado > 0 => 'parcial',
            default => 'aberta',
        };

        $fatura->update(['valor_recebido' => $baixado, 'status' => $status]);
    }

    /**
     * Próximo número da fatura na empresa. Trava consultiva do Postgres por
     * empresa durante a transação: duas faturas geradas ao mesmo tempo não
     * pegam o mesmo número (o UNIQUE do banco é a última barreira).
     */
    private function proximaSequencia(): int
    {
        $empresaId = (int) TenantContext::empresaId();
        DB::select('SELECT pg_advisory_xact_lock(?, ?)', [5010, $empresaId]);

        return (int) Fatura::query()->max('sequencia') + 1;
    }
}
