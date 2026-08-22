<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Abastecimento;
use App\Models\Motorista;
use App\Models\Ocorrencia;
use App\Models\OrdemServico;
use App\Models\Veiculo;
use App\Models\VeiculoDocumento;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Tela inicial — dashboard operacional do tenant.
 *
 * Componente Livewire (não `Route::view`): rota de classe passa pela mesma
 * pilha de middleware que o resto, com a tenancy antes do StartSession. Todas
 * as consultas são isoladas pelo EmpresaScope a partir do TenantContext.
 */
class Inicio extends Component
{
    private const HORIZONTE_DIAS = 60;

    /* ── KPIs ──────────────────────────────────────────────── */

    /** @return array<string,int> */
    #[Computed]
    public function frota(): array
    {
        return [
            'total' => Veiculo::query()->count(),
            'ativos' => Veiculo::query()->where('status', 'ativo')->count(),
            'tracao' => Veiculo::query()->where('tipo', 'tracao')->count(),
            'manutencao' => Veiculo::query()->where('status', 'manutencao')->count(),
            'terceiro' => Veiculo::query()->where('propriedade', '!=', 'propria')->count(),
        ];
    }

    /**
     * Distribuição para a rosca de status, em porcentagens (r=15.9 → circunf. ≈ 100,
     * então a % vira direto o stroke-dasharray).
     *
     * @return array<int,array{label:string,cor:string,qtd:int,pct:float}>
     */
    #[Computed]
    public function frotaSegmentos(): array
    {
        $f = $this->frota;
        $total = max($f['total'], 1);
        $outros = max($f['total'] - $f['ativos'] - $f['manutencao'], 0);

        return [
            ['label' => 'Ativos', 'cor' => 'rgb(var(--color-success))', 'qtd' => $f['ativos'], 'pct' => round($f['ativos'] / $total * 100, 1)],
            ['label' => 'Em manutenção', 'cor' => 'rgb(var(--color-warning))', 'qtd' => $f['manutencao'], 'pct' => round($f['manutencao'] / $total * 100, 1)],
            ['label' => 'Inativos / outros', 'cor' => 'rgb(var(--color-text-muted))', 'qtd' => $outros, 'pct' => round($outros / $total * 100, 1)],
        ];
    }

    /**
     * Série de consumo (km/L) do veículo com mais leituras, para as barras.
     *
     * @return array{veiculo:?string,meta:?float,serie:array<int,array{label:string,media:float,alerta:bool}>}
     */
    #[Computed]
    public function consumo(): array
    {
        $leituras = Abastecimento::query()
            ->whereNotNull('media_calculada')
            ->with('veiculo')
            ->orderBy('data_hora')
            ->get();

        if ($leituras->isEmpty()) {
            return ['veiculo' => null, 'meta' => null, 'serie' => []];
        }

        // Veículo com mais leituras.
        $veiculoId = $leituras->groupBy('veiculo_id')->map(fn ($g) => $g->count())->sortDesc()->keys()->first();
        $doVeiculo = $leituras->where('veiculo_id', $veiculoId)->values()->take(-4);
        $veiculo = $doVeiculo->first()?->veiculo;

        $serie = $doVeiculo->map(fn (Abastecimento $a): array => [
            'label' => $a->data_hora?->translatedFormat('d/m') ?? '',
            'media' => (float) $a->media_calculada,
            'alerta' => (bool) $a->alerta,
        ])->values()->all();

        return [
            'veiculo' => $veiculo?->placaFormatada(),
            'meta' => $veiculo?->media_referencia_kml !== null ? (float) $veiculo->media_referencia_kml : null,
            'serie' => $serie,
        ];
    }

    /** @return array{combustivel:float,manutencao:float,total:float,rs_km:?float} */
    #[Computed]
    public function custoMes(): array
    {
        $mes = now()->startOfMonth();

        $combustivel = (float) Abastecimento::query()->where('data_hora', '>=', $mes)->sum('valor_total');
        $manutencao = (float) OrdemServico::query()
            ->where(function ($q) use ($mes): void {
                $q->where('abertura', '>=', $mes)->orWhere('encerramento', '>=', $mes);
            })
            ->sum('valor_total');

        $total = $combustivel + $manutencao;
        // Km rodado do mês por proxy: soma do km_percorrido entre tanques cheios.
        $km = (float) Abastecimento::query()->where('data_hora', '>=', $mes)->sum('km_percorrido');

        return [
            'combustivel' => $combustivel,
            'manutencao' => $manutencao,
            'total' => $total,
            'rs_km' => $km > 0 ? round($total / $km, 2) : null,
        ];
    }

    /** @return Collection<int,OrdemServico> */
    #[Computed]
    public function proximasManutencoes(): Collection
    {
        return OrdemServico::query()
            ->with('veiculo')
            ->whereNotIn('status', ['encerrada', 'cancelada'])
            ->orderBy('abertura')
            ->limit(4)
            ->get();
    }

    /** @return array<string,int> */
    #[Computed]
    public function motoristas(): array
    {
        $ativos = Motorista::query()->where('status', 'ativo')->get();

        return [
            'total' => $ativos->count(),
            'aptos' => $ativos->filter(fn (Motorista $m) => $m->podeViajar())->count(),
        ];
    }

    /** @return array<string,int|float> */
    #[Computed]
    public function ocorrencias(): array
    {
        return [
            'abertas' => Ocorrencia::query()->where('status', '!=', 'resolvida')->count(),
            'prejuizo' => (float) Ocorrencia::query()->where('status', '!=', 'resolvida')->sum('valor_prejuizo'),
        ];
    }

    /** @return array<string,int|float> */
    #[Computed]
    public function abastecimento(): array
    {
        $mes = now()->startOfMonth();

        return [
            'alertas' => Abastecimento::query()->where('alerta', true)->count(),
            'gasto_mes' => (float) Abastecimento::query()->where('data_hora', '>=', $mes)->sum('valor_total'),
        ];
    }

    /* ── Vencimentos (unifica documentos de veículo e motorista) ── */

    /** @return Collection<int,array<string,mixed>> */
    #[Computed]
    public function vencimentos(): Collection
    {
        $hoje = Carbon::today();
        $limite = $hoje->copy()->addDays(self::HORIZONTE_DIAS);
        $itens = collect();

        VeiculoDocumento::query()
            ->with('documentavel')
            ->whereDate('vencimento', '<=', $limite)
            ->get()
            ->each(function (VeiculoDocumento $d) use ($itens, $hoje): void {
                $itens->push([
                    'categoria' => 'veiculo',
                    'referencia' => $d->documentavel?->placaFormatada() ?? 'Veículo',
                    'documento' => strtoupper((string) $d->tipo),
                    'vencimento' => $d->vencimento,
                    'dias' => (int) $hoje->diffInDays($d->vencimento, false),
                    'bloqueia' => (bool) $d->bloqueia_operacao,
                ]);
            });

        Motorista::query()->with('pessoa')->get()->each(function (Motorista $m) use ($itens, $hoje, $limite): void {
            $nome = $m->pessoa?->razao_social ?? 'Motorista';
            $candidatos = [['CNH', $m->cnh_validade, true], ['Toxicológico', $m->toxicologico_validade, true]];

            if ($m->ehTac()) {
                $candidatos[] = ['RNTRC', $m->rntrc_validade, true];
            }

            foreach ($candidatos as [$doc, $data, $bloqueia]) {
                if ($data !== null && $data->lte($limite)) {
                    $itens->push([
                        'categoria' => 'motorista',
                        'referencia' => $nome,
                        'documento' => $doc,
                        'vencimento' => $data,
                        'dias' => (int) $hoje->diffInDays($data, false),
                        'bloqueia' => $bloqueia,
                    ]);
                }
            }
        });

        return $itens->sortBy('dias')->values();
    }

    /** @return array<string,int> */
    #[Computed]
    public function vencimentosResumo(): array
    {
        $itens = $this->vencimentos;

        return [
            'vencidos' => $itens->where('dias', '<', 0)->count(),
            'ate30' => $itens->filter(fn ($i) => $i['dias'] >= 0 && $i['dias'] <= 30)->count(),
            'ate60' => $itens->filter(fn ($i) => $i['dias'] > 30 && $i['dias'] <= 60)->count(),
        ];
    }

    /** @return Collection<int,array<string,mixed>> */
    #[Computed]
    public function proximosVencimentos(): Collection
    {
        return $this->vencimentos->take(6);
    }

    /* ── Painéis ───────────────────────────────────────────── */

    /** @return Collection<int,Ocorrencia> */
    #[Computed]
    public function ocorrenciasRecentes(): Collection
    {
        return Ocorrencia::query()->with('municipio')->orderByDesc('data_hora')->limit(5)->get();
    }

    /** @return Collection<int,Abastecimento> */
    #[Computed]
    public function abastecimentosRecentes(): Collection
    {
        return Abastecimento::query()->with('veiculo')->orderByDesc('data_hora')->limit(4)->get();
    }

    public function render(): View
    {
        return view('livewire.inicio')
            ->layout('layouts.app', ['title' => 'Início']);
    }
}
