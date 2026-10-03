<?php

declare(strict_types=1);

namespace App\Services\Operacao;

use App\Domain\Fiscal\RegrasCiot;
use App\Domain\Operacao\CustoViagem;
use App\Models\Abastecimento;
use App\Models\AcertoViagem;
use App\Models\Despesa;
use App\Models\OrdemServico;
use App\Models\ValePedagio;
use App\Models\Viagem;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Custo e margem da viagem (rotina 3080). Monta cada componente a partir dos
 * lançamentos ligados à viagem e grava o resultado denormalizado nas colunas
 * custo_* da viagem (a lista, o painel e o Dashboard leem de lá).
 *
 *   Combustível   abastecimentos da viagem (2060)
 *   Terceiros     frete do CIOT de agregado/autônomo/ETC (4050)
 *   Pedágio       despesas de pedágio aceitas (3060) + vale pago pela transportadora (4030)
 *   Motorista     despesas aceitas + diárias/comissão do acerto (3070) — estimadas enquanto aberto
 *   Manutenção    OS ligadas à viagem (2050)
 *   Outros        demais despesas aceitas
 *
 * Despesa glosada não é custo (o motorista devolve). Componente sem nenhum
 * lançamento usa o valor digitado antes da 3080 (`custos_digitados`).
 */
final class CustosDaViagem
{
    /** @var array<int, true> */
    private static array $pendentes = [];

    /**
     * Agenda o recálculo para depois do commit (várias mudanças na mesma
     * transação viram um recálculo só por viagem). Nunca derruba quem chamou.
     */
    public static function agendar(?int ...$viagemIds): void
    {
        // Fora de transação não há callback pendente: o que ficou marcado veio de
        // uma transação desfeita (rollback descarta o callback) — recomeça limpo.
        if (DB::transactionLevel() === 0) {
            self::$pendentes = [];
        }
        $novos = false;
        foreach ($viagemIds as $id) {
            if ($id !== null && $id > 0 && ! isset(self::$pendentes[$id])) {
                self::$pendentes[$id] = true;
                $novos = true;
            }
        }
        if (! $novos) {
            return;
        }

        DB::afterCommit(function (): void {
            $ids = array_keys(self::$pendentes);
            self::$pendentes = [];
            foreach ($ids as $id) {
                try {
                    if ($v = Viagem::query()->find($id)) {
                        app(self::class)->recalcular($v);
                    }
                } catch (Throwable $e) {
                    report($e);
                }
            }
        });
    }

    /** Grava os custos, a receita e a margem da viagem. */
    public function recalcular(Viagem $viagem): void
    {
        $d = $this->detalhar($viagem);
        $c = $d['componentes'];

        $viagem->forceFill([
            'custo_combustivel' => $c['combustivel']['valor'],
            'custo_terceiro' => $c['terceiro']['valor'],
            'custo_pedagio' => $c['pedagio']['valor'],
            'custo_motorista' => $c['motorista']['valor'],
            'custo_manutencao' => $c['manutencao']['valor'],
            'custo_outros' => $c['outros']['valor'],
            'custo_total' => $d['custo'],
            'receita_total' => $d['receita'],
            'margem' => $d['resultado']['margem'],
            'custo_por_km' => $d['resultado']['custo_km'],
            'custos_recalculados_em' => now(),
        ])->saveQuietly();
    }

    /**
     * Tudo o que a tela de detalhe mostra: cada componente com as linhas de
     * origem, a receita e o resultado.
     *
     * @return array{componentes: array<string, array{rotulo:string, valor:float, digitado:bool, linhas:list<array<string,mixed>>}>,
     *               custo:float, receita:float, receita_linhas:list<array<string,mixed>>, receita_digitada:bool,
     *               km:?float, resultado:array<string,?float>, estimado:bool}
     */
    public function detalhar(Viagem $viagem): array
    {
        $viagem->loadMissing(['motorista', 'veiculoTracao']);
        $linhas = array_fill_keys(array_keys(CustoViagem::COMPONENTES), []);
        $valores = array_fill_keys(array_keys(CustoViagem::COMPONENTES), []);
        $add = function (string $comp, array $linha) use (&$linhas, &$valores): void {
            $linhas[$comp][] = $linha;
            if (($linha['valor'] ?? null) !== null) {
                $valores[$comp][] = (float) $linha['valor'];
            }
        };

        // Receita: CT-e autorizados ligados; sem CT-e ligado, a digitada na viagem.
        $ctes = $viagem->ctes()->with('tomador:id,razao_social')->get();
        $validos = $ctes->whereIn('status', ['autorizado', 'contingencia']);
        $receitaDigitada = $ctes->isEmpty();
        $receita = $receitaDigitada ? (float) $viagem->receita_total : round((float) $validos->sum('valor_total_servico'), 2);
        $receitaLinhas = $validos->map(fn ($c) => [
            'rotina' => '4010', 'texto' => 'CT-e ' . str_pad((string) $c->numero, 6, '0', STR_PAD_LEFT),
            'detalhe' => $c->tomador?->razao_social, 'valor' => (float) $c->valor_total_servico, 'url' => route('cte.editar', $c),
        ])->values()->all();

        // Combustível.
        foreach (Abastecimento::query()->with('posto:id,razao_social,nome_fantasia')->where('viagem_id', $viagem->id)->orderBy('data_hora')->get() as $a) {
            $add('combustivel', [
                'rotina' => '2060', 'texto' => $a->posto?->nome_fantasia ?: ($a->posto?->razao_social ?? 'Abastecimento'),
                'detalhe' => number_format((float) $a->litros, 0, ',', '.') . ' L · ' . $a->data_hora?->format('d/m'),
                'valor' => (float) $a->valor_total, 'url' => route('abastecimentos.editar', $a),
            ]);
        }

        // Terceiros: só o CIOT de quem é pago pela viagem (TAC pela instituição ou ETC informado).
        $ciot = $viagem->ciot()->first();
        if ($ciot !== null && $ciot->valido() && in_array($ciot->modalidade, [RegrasCiot::IPEF, RegrasCiot::INFORMADO], true)) {
            $add('terceiro', [
                'rotina' => '4050', 'texto' => 'CIOT ' . ($ciot->numero ? RegrasCiot::formatar((string) $ciot->numero) : ''),
                'detalhe' => 'Frete ao ' . ($ciot->modalidade === RegrasCiot::IPEF ? 'transportador autônomo' : 'transportador subcontratado'),
                'valor' => (float) $ciot->valor_frete, 'url' => route('ciot.ver', $ciot),
            ]);
        }

        // Despesas aceitas → pedágio / motorista / outros.
        $mapa = ['custo_pedagio' => 'pedagio', 'custo_motorista' => 'motorista', 'custo_outros' => 'outros'];
        foreach (Despesa::query()->where('viagem_id', $viagem->id)->where('aprovada', true)->orderBy('data')->get() as $d) {
            $add($mapa[$d->componenteCusto()] ?? 'outros', [
                'rotina' => '3060', 'texto' => (string) config('despesas.tipos.' . $d->tipo, $d->tipo),
                'detalhe' => trim(($d->descricao ? $d->descricao . ' · ' : '') . ($d->data?->format('d/m') ?? '')),
                'valor' => (float) $d->valor, 'url' => route('despesas.editar', $d),
            ]);
        }
        $glosadas = (float) Despesa::query()->where('viagem_id', $viagem->id)->where('glosada', true)->sum('valor');

        // Vale-pedágio: custo só quando a transportadora paga (papel "fornecido").
        foreach (ValePedagio::query()->ativos()->where('viagem_id', $viagem->id)->get() as $vale) {
            if ($vale->dispensado) {
                continue;
            }
            // Pago pela transportadora: fornecido ao TAC ou comprado pelo sistema (pagador = filial).
            $paga = $vale->papel === 'fornecido' || $vale->origem === 'compra';
            $add('pedagio', [
                'rotina' => '4030', 'texto' => 'Vale-pedágio' . ($vale->idvpo ? ' ' . $vale->idvpo : ''),
                'detalhe' => $paga ? 'Pago pela transportadora' : 'Recebido do embarcador — não é custo da transportadora',
                'valor' => $paga && (float) $vale->valor > 0 ? (float) $vale->valor : null, 'url' => null,
            ]);
        }

        // Diárias e comissão: do acerto fechado; aberto, estimadas pelo cadastro (só CLT).
        $estimado = false;
        $acerto = AcertoViagem::query()->where('viagem_id', $viagem->id)->where('status', 'fechado')->latest('id')->first();
        if ($acerto !== null) {
            $v = round((float) $acerto->diarias_total + (float) $acerto->comissao_valor, 2);
            if ($v > 0) {
                $add('motorista', [
                    'rotina' => '3070', 'texto' => 'Diárias e comissão', 'detalhe' => $acerto->dias . ' × ' . number_format((float) $acerto->diaria_valor, 2, ',', '.') . ' · acerto fechado',
                    'valor' => $v, 'url' => route('acertos.ver', $viagem),
                ]);
            }
        } elseif ($viagem->motorista?->controlaJornada() && $viagem->status !== 'cancelada') {
            $dias = app(Acertos::class)->dias($viagem);
            $diaria = (float) ($viagem->motorista->valor_diaria ?? 0);
            $pct = (float) ($viagem->motorista->percentual_comissao ?? 0);
            $v = round($dias * $diaria + $receita * $pct / 100, 2);
            if ($v > 0) {
                $estimado = true;
                $add('motorista', [
                    'rotina' => '3070', 'texto' => 'Diárias e comissão', 'detalhe' => $dias . ' × ' . number_format($diaria, 2, ',', '.') . ' · estimado, acerto aberto',
                    'valor' => $v, 'url' => route('acertos.ver', $viagem),
                ]);
            }
        }

        // Manutenção: OS ligadas à viagem (cancelada não entra).
        foreach (OrdemServico::query()->where('viagem_id', $viagem->id)->where('status', '!=', 'cancelada')->orderBy('abertura')->get() as $os) {
            $add('manutencao', [
                'rotina' => '2050', 'texto' => $os->numero, 'detalhe' => ucfirst((string) $os->tipo) . ($os->status !== 'encerrada' ? ' · em aberto' : ''),
                'valor' => (float) $os->valor_total, 'url' => route('manutencao.editar', $os),
            ]);
        }

        $soma = CustoViagem::somar($valores, (array) ($viagem->custos_digitados ?? []));
        $componentes = [];
        foreach (CustoViagem::COMPONENTES as $comp => $rotulo) {
            $l = $linhas[$comp];
            if ($soma['componentes'][$comp]['digitado']) {
                $l[] = ['rotina' => '3020', 'texto' => 'Digitado na viagem', 'detalhe' => 'Antes da 3080 — vale até ter lançamento', 'valor' => $soma['componentes'][$comp]['valor'], 'url' => null];
            }
            $componentes[$comp] = ['rotulo' => $rotulo] + $soma['componentes'][$comp] + ['linhas' => $l];
        }

        $km = $viagem->km_percorrido !== null ? (float) $viagem->km_percorrido : null;

        return [
            'componentes' => $componentes,
            'custo' => $soma['total'],
            'receita' => $receita,
            'receita_linhas' => $receitaLinhas,
            'receita_digitada' => $receitaDigitada,
            'glosado' => round($glosadas, 2),
            'km' => $km,
            'resultado' => CustoViagem::resultado($receita, $soma['total'], $km),
            'estimado' => $estimado,
        ];
    }
}
