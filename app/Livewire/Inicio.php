<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Cte;
use App\Models\Despesa;
use App\Models\Entrega;
use App\Models\Mdfe;
use App\Models\Motorista;
use App\Models\Ocorrencia;
use App\Models\OrdemColeta;
use App\Models\OrdemServico;
use App\Models\Veiculo;
use App\Models\VeiculoDocumento;
use App\Models\Viagem;
use App\Support\Navegacao;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * Dashboard — a mesma tela inicial do ERP (02/10/2026, mockup "Frota igual ao
 * ERP"), com os números da transportadora. Três andares, uma pergunta cada:
 *
 *   1. COMO ESTOU AGORA?  cartões curtos: faturamento, margem, custo/km, frota
 *      em uso e entregas — cada número aparece UMA vez.
 *   2. VIAGENS EM ANDAMENTO  (no lugar do "Nível dos tanques" do ERP).
 *   3. ANÁLISES + ATENÇÃO  barras horizontais, "Precisam de atenção" à direita,
 *      top motoristas, vencimentos, faturamento de 7 dias e atividade recente.
 *
 * Componente Livewire (não `Route::view`): rota de classe passa pela mesma
 * pilha de middleware que o resto. Tudo isolado pelo EmpresaScope.
 */
class Inicio extends Component
{
    private const HORIZONTE_DIAS = 30;

    #[Url(as: 'periodo')]
    public string $periodo = 'hoje';

    public function mount(): void
    {
        if (! array_key_exists($this->periodo, $this->periodos())) {
            $this->periodo = 'hoje';
        }
    }

    /** @return array<string,string> */
    public function periodos(): array
    {
        return ['hoje' => 'Hoje', 'ontem' => 'Ontem', 'semana' => 'Semana', 'mes' => 'Mês'];
    }

    public function usarPeriodo(string $p): void
    {
        $this->periodo = array_key_exists($p, $this->periodos()) ? $p : 'hoje';
        unset($this->janela, $this->cartoes, $this->receitaPorCliente);
    }

    /**
     * Janela do período escolhido e a janela anterior de mesmo tamanho (para o "vs").
     *
     * @return array{de:Carbon,ate:Carbon,ant_de:Carbon,ant_ate:Carbon,rotulo:string,vs:string}
     */
    #[Computed]
    public function janela(): array
    {
        $hoje = Carbon::today();

        return match ($this->periodo) {
            'ontem' => ['de' => $hoje->copy()->subDay(), 'ate' => $hoje->copy()->subDay()->endOfDay(),
                'ant_de' => $hoje->copy()->subDays(8), 'ant_ate' => $hoje->copy()->subDays(8)->endOfDay(), 'rotulo' => 'Ontem', 'vs' => 'vs semana ant.'],
            'semana' => ['de' => $hoje->copy()->subDays(6), 'ate' => now(),
                'ant_de' => $hoje->copy()->subDays(13), 'ant_ate' => $hoje->copy()->subDays(7)->endOfDay(), 'rotulo' => 'Últimos 7 dias', 'vs' => 'vs 7 dias ant.'],
            'mes' => ['de' => $hoje->copy()->startOfMonth(), 'ate' => now(),
                'ant_de' => $hoje->copy()->subMonthNoOverflow()->startOfMonth(), 'ant_ate' => now()->subMonthNoOverflow(), 'rotulo' => 'Mês', 'vs' => 'vs mês ant.'],
            default => ['de' => $hoje->copy(), 'ate' => now(),
                'ant_de' => $hoje->copy()->subDays(7), 'ant_ate' => now()->subDays(7), 'rotulo' => 'Hoje', 'vs' => 'vs semana ant.'],
        };
    }

    /* ── Andar 1: cartões ──────────────────────────────────── */

    /** @return array<string,mixed> */
    #[Computed]
    public function cartoes(): array
    {
        $j = $this->janela;

        $fat = $this->seguro(function () use ($j): array {
            $q = fn (Carbon $de, Carbon $ate) => Cte::query()->where('status', 'autorizado')->whereBetween('data_autorizacao', [$de, $ate]);
            $valor = (float) $q($j['de'], $j['ate'])->sum('valor_total_servico');
            $antes = (float) $q($j['ant_de'], $j['ant_ate'])->sum('valor_total_servico');

            return ['valor' => $valor, 'qtd' => $q($j['de'], $j['ate'])->count(), 'comp' => $antes > 0 ? ($valor - $antes) / $antes * 100 : null];
        }, ['valor' => 0.0, 'qtd' => 0, 'comp' => null]);

        // Margem e custo/km: viagens que SAÍRAM no período.
        $vg = $this->seguro(function () use ($j): array {
            $q = Viagem::query()->whereBetween('saida_real', [$j['de'], $j['ate']])->whereNotIn('status', ['cancelada']);
            $receita = (float) (clone $q)->sum('receita_total');
            $custo = (float) (clone $q)->sum('custo_total');
            $km = (float) (clone $q)->sum('km_percorrido');
            $comp = [
                'Combustível' => (float) (clone $q)->sum('custo_combustivel'),
                'Pedágio' => (float) (clone $q)->sum('custo_pedagio'),
                'Motorista' => (float) (clone $q)->sum('custo_motorista'),
                'Manutenção' => (float) (clone $q)->sum('custo_manutencao'),
                'Outros' => (float) (clone $q)->sum('custo_outros'),
            ];
            arsort($comp);

            return ['receita' => $receita, 'custo' => $custo, 'km' => $km, 'componentes' => $comp, 'qtd' => (clone $q)->count()];
        }, ['receita' => 0.0, 'custo' => 0.0, 'km' => 0.0, 'componentes' => [], 'qtd' => 0]);

        $frota = $this->seguro(fn (): array => [
            'total' => Veiculo::query()->where('tipo', 'tracao')->whereIn('status', ['ativo', 'manutencao'])->count(),
            'em_uso' => Viagem::query()->whereIn('status', ['carregando', 'em_transito'])->distinct()->count('veiculo_tracao_id'),
            'manutencao' => Veiculo::query()->where('tipo', 'tracao')->where('status', 'manutencao')->count(),
        ], ['total' => 0, 'em_uso' => 0, 'manutencao' => 0]);

        $ent = $this->seguro(function () use ($j): array {
            $q = Entrega::query()->whereBetween('data_hora', [$j['de'], $j['ate']]);
            $total = (clone $q)->count();

            return ['total' => $total, 'ok' => (clone $q)->comprovadas()->count()];
        }, ['total' => 0, 'ok' => 0]);

        $componentes = array_filter($vg['componentes']);
        $topo = array_slice($componentes, 0, 2, true);

        return [
            'faturamento' => $fat,
            'margem' => [
                'pct' => $vg['receita'] > 0 ? ($vg['receita'] - $vg['custo']) / $vg['receita'] * 100 : null,
                'valor' => $vg['receita'] - $vg['custo'],
                'qtd' => $vg['qtd'],
            ],
            'custo_km' => [
                'valor' => $vg['km'] > 0 ? $vg['custo'] / $vg['km'] : null,
                'partes' => collect($topo)->map(fn ($v, $k) => $k . ' ' . number_format($vg['custo'] > 0 ? $v / $vg['custo'] * 100 : 0, 0) . '%')->implode(' · '),
            ],
            'frota' => $frota,
            'entregas' => $ent,
        ];
    }

    /* ── Andar 2: viagens em andamento ─────────────────────── */

    /** @return Collection<int,array<string,mixed>> */
    #[Computed]
    public function viagensAndamento(): Collection
    {
        return $this->seguro(fn () => Viagem::query()
            ->with(['veiculoTracao', 'motorista.pessoa', 'municipioOrigem', 'municipioDestino'])
            ->whereIn('status', ['carregando', 'em_transito'])
            ->orderByRaw('chegada_prevista is null, chegada_prevista')
            ->limit(6)->get()
            ->map(function (Viagem $v): array {
                $agora = now();
                $ini = $v->saida_real ?? $v->saida_prevista;
                $fim = $v->chegada_prevista;
                $pct = 0;
                if ($v->status === 'em_transito' && $ini && $fim && $fim->gt($ini)) {
                    $pct = (int) max(3, min(97, round($ini->diffInSeconds($agora, false) / $ini->diffInSeconds($fim) * 100)));
                }
                $atrasada = $fim !== null && $agora->gt($fim);
                [$sit, $cor] = match (true) {
                    $v->status === 'carregando' => ['Carregando', 'wa'],
                    $atrasada => ['Atrasada', 'dn'],
                    default => ['No horário', 'ok'],
                };

                return [
                    'url' => route('viagens.editar', $v),
                    'numero' => $v->numero,
                    'placa' => $v->veiculoTracao?->placaFormatada() ?? '—',
                    'motorista' => $v->motorista?->pessoa?->razao_social ?? '—',
                    'rota' => ($v->municipioOrigem?->nomeComUf() ?? '—') . ' → ' . ($v->municipioDestino?->nomeComUf() ?? '—'),
                    'pct' => $pct,
                    'situacao' => $sit,
                    'cor' => $cor,
                    'chegada' => $fim?->format($fim->isToday() ? 'H:i' : 'd/m H:i'),
                ];
            }), collect());
    }

    #[Computed]
    public function totalAndamento(): int
    {
        return $this->seguro(fn () => Viagem::query()->whereIn('status', ['carregando', 'em_transito'])->count(), 0);
    }

    /* ── Andar 3: análises ─────────────────────────────────── */

    /** @return array<string,float> */
    #[Computed]
    public function custoComponentes(): array
    {
        return $this->seguro(function (): array {
            $q = Viagem::query()->where('saida_real', '>=', now()->startOfMonth())->whereNotIn('status', ['cancelada']);
            $r = [
                'Combustível' => (float) (clone $q)->sum('custo_combustivel'),
                'Pedágio' => (float) (clone $q)->sum('custo_pedagio'),
                'Motorista' => (float) (clone $q)->sum('custo_motorista'),
                'Manutenção' => (float) (clone $q)->sum('custo_manutencao'),
                'Outros' => (float) (clone $q)->sum('custo_outros'),
            ];
            arsort($r);

            return array_filter($r);
        }, []);
    }

    /** @return list<array{nome:string,valor:float}> */
    #[Computed]
    public function receitaPorCliente(): array
    {
        $j = $this->janela;

        return $this->seguro(function () use ($j): array {
            $linhas = Cte::query()->with('tomador')->where('status', 'autorizado')
                ->whereBetween('data_autorizacao', [$j['de'], $j['ate']])
                ->get(['id', 'tomador_id', 'valor_total_servico'])
                ->groupBy('tomador_id')
                ->map(fn ($g) => ['nome' => $g->first()->tomador?->nome_fantasia ?: ($g->first()->tomador?->razao_social ?? 'Sem tomador'), 'valor' => (float) $g->sum('valor_total_servico')])
                ->sortByDesc('valor')->values();

            $top = $linhas->take(4)->all();
            if ($linhas->count() > 4) {
                $top[] = ['nome' => 'Outros (' . ($linhas->count() - 4) . ')', 'valor' => (float) $linhas->slice(4)->sum('valor')];
            }

            return $top;
        }, []);
    }

    /** @return list<array<string,mixed>> */
    #[Computed]
    public function topMotoristas(): array
    {
        return $this->seguro(fn (): array => Viagem::query()->with('motorista.pessoa')
            ->where('saida_real', '>=', now()->startOfMonth())->whereNotIn('status', ['cancelada'])
            ->get(['id', 'motorista_id', 'km_percorrido', 'receita_total'])
            ->groupBy('motorista_id')
            ->map(fn ($g) => [
                'nome' => $g->first()->motorista?->pessoa?->razao_social ?? 'Motorista',
                'viagens' => $g->count(),
                'km' => (float) $g->sum('km_percorrido'),
                'receita' => (float) $g->sum('receita_total'),
            ])
            ->sortByDesc('receita')->take(3)->values()->all(), []);
    }

    /** @return Collection<int,array<string,mixed>> */
    #[Computed]
    public function vencimentos(): Collection
    {
        return $this->seguro(function (): Collection {
            $hoje = Carbon::today();
            $limite = $hoje->copy()->addDays(self::HORIZONTE_DIAS);
            $itens = collect();

            VeiculoDocumento::query()->with('documentavel')->whereDate('vencimento', '<=', $limite)->get()
                ->each(function (VeiculoDocumento $d) use ($itens, $hoje): void {
                    $itens->push([
                        'titulo' => strtoupper((string) $d->tipo) . ' · ' . ($d->documentavel?->placaFormatada() ?? 'Veículo'),
                        'sub' => 'Documento do veículo',
                        'dias' => (int) $hoje->diffInDays($d->vencimento, false),
                    ]);
                });

            Motorista::query()->with('pessoa')->where('status', 'ativo')->get()
                ->each(function (Motorista $m) use ($itens, $hoje, $limite): void {
                    foreach ([['CNH', $m->cnh_validade], ['Toxicológico', $m->toxicologico_validade]] as [$doc, $data]) {
                        if ($data !== null && $data->lte($limite)) {
                            $itens->push([
                                'titulo' => $doc . ' · ' . ($m->pessoa?->razao_social ?? 'Motorista'),
                                'sub' => 'Documento do motorista',
                                'dias' => (int) $hoje->diffInDays($data, false),
                            ]);
                        }
                    }
                });

            return $itens->sortBy('dias')->take(5)->values();
        }, collect());
    }

    /** @return array{labels:list<string>,valores:list<float>,total:float} */
    #[Computed]
    public function faturamento7Dias(): array
    {
        return $this->seguro(function (): array {
            $labels = [];
            $valores = [];
            for ($i = 6; $i >= 0; $i--) {
                $d = Carbon::today()->subDays($i);
                $labels[] = $d->locale('pt_BR')->isoFormat('D/MMM');
                $valores[] = (float) Cte::query()->where('status', 'autorizado')->whereDate('data_autorizacao', $d)->sum('valor_total_servico');
            }

            return ['labels' => $labels, 'valores' => $valores, 'total' => array_sum($valores)];
        }, ['labels' => [], 'valores' => [], 'total' => 0.0]);
    }

    /** @return Collection<int,array<string,mixed>> */
    #[Computed]
    public function atividade(): Collection
    {
        return $this->seguro(function (): Collection {
            $ev = collect();

            Entrega::query()->with('viagem.veiculoTracao')->latest('data_hora')->limit(4)->get()
                ->each(fn (Entrega $e) => $ev->push(['quando' => $e->data_hora, 'cor' => $e->comprovada() ? '#22c55e' : '#f59e0b',
                    'titulo' => $e->comprovada() ? 'Entrega confirmada' : 'Entrega sem comprovante',
                    'sub' => trim(($e->viagem?->veiculoTracao?->placaFormatada() ?? '') . ($e->recebedor_nome ? ' · ' . $e->recebedor_nome : ''), ' ·')]));

            Cte::query()->with('tomador')->where('status', 'autorizado')->whereNotNull('data_autorizacao')->latest('data_autorizacao')->limit(4)->get()
                ->each(fn (Cte $c) => $ev->push(['quando' => $c->data_autorizacao, 'cor' => 'var(--h6-azul)',
                    'titulo' => 'CT-e ' . number_format((int) $c->numero, 0, ',', '.') . ' autorizado',
                    'sub' => ($c->tomador?->razao_social ?? '') . ' · R$ ' . number_format((float) $c->valor_total_servico, 2, ',', '.')]));

            Viagem::query()->with(['veiculoTracao', 'municipioOrigem', 'municipioDestino'])->whereNotNull('saida_real')->latest('saida_real')->limit(4)->get()
                ->each(fn (Viagem $v) => $ev->push(['quando' => $v->saida_real, 'cor' => 'var(--h6-azul)',
                    'titulo' => 'Viagem ' . $v->numero . ' iniciada',
                    'sub' => ($v->veiculoTracao?->placaFormatada() ?? '') . ' · ' . ($v->municipioOrigem?->nomeComUf() ?? '—') . ' → ' . ($v->municipioDestino?->nomeComUf() ?? '—')]));

            Despesa::query()->with('viagem.veiculoTracao')->latest('created_at')->limit(4)->get()
                ->each(fn (Despesa $d) => $ev->push(['quando' => $d->created_at, 'cor' => '#22c55e',
                    'titulo' => 'Despesa lançada · ' . (config('despesas.tipos.' . $d->tipo) ?? ucfirst((string) $d->tipo)),
                    'sub' => ($d->viagem?->veiculoTracao?->placaFormatada() ?? '') . ' · R$ ' . number_format((float) $d->valor, 2, ',', '.')]));

            return $ev->filter(fn ($e) => $e['quando'] !== null)->sortByDesc('quando')->take(5)->values();
        }, collect());
    }

    /** @return array<string,int> */
    #[Computed]
    public function contadores(): array
    {
        return [
            'ordens abertas' => $this->seguro(fn () => OrdemColeta::query()->where('status', 'aberta')->count(), 0),
            'MDF-e abertos' => $this->seguro(fn () => Mdfe::query()->where('status', 'autorizado')->count(), 0),
            'OS de manutenção abertas' => $this->seguro(fn () => OrdemServico::query()->whereNotIn('status', ['encerrada', 'cancelada'])->count(), 0),
            'ocorrências abertas' => $this->seguro(fn () => Ocorrencia::query()->where('status', '!=', 'resolvida')->count(), 0),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function alertas(): array
    {
        return Navegacao::montar()['alertas'];
    }

    /**
     * Um bloco com problema não derruba o painel inteiro: devolve o valor
     * padrão e a tela continua.
     *
     * @template T
     *
     * @param  callable():T  $fn
     * @param  T  $padrao
     * @return T
     */
    private function seguro(callable $fn, mixed $padrao): mixed
    {
        if (TenantContext::empresaId() === null) {
            return $padrao;
        }

        try {
            return $fn();
        } catch (Throwable $e) {
            report($e);

            return $padrao;
        }
    }

    public function render(): View
    {
        return view('livewire.inicio')
            ->layout('layouts.app', ['title' => 'Dashboard']);
    }
}
