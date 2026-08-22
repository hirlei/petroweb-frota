<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Abastecimento;
use App\Models\Motorista;
use App\Models\Ocorrencia;
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
        ];
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
