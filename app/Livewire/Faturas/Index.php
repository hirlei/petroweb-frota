<?php

declare(strict_types=1);

namespace App\Livewire\Faturas;

use App\Models\Cte;
use App\Models\Fatura;
use App\Models\Recebimento;
use App\Models\TituloReceber;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rotina 5010 — Faturas. CT-e autorizados agrupados por cliente, com
 * vencimento e parcelas (mockup aprovado em 02/10/2026).
 */
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $busca = '';

    #[Url(as: 'situacao', except: '')]
    public string $situacao = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Fatura::class);
    }

    public function updated(string $campo): void
    {
        if (in_array($campo, ['busca', 'situacao'], true)) {
            $this->resetPage();
        }
    }

    /** @return array<string,mixed> */
    #[Computed]
    public function resumo(): array
    {
        $abertos = TituloReceber::query()->emAberto();
        $vencidos = TituloReceber::query()->vencidos();
        $mes = now()->startOfMonth();
        $mesAnt = now()->subMonthNoOverflow()->startOfMonth();

        $recebidoMes = (float) Recebimento::query()->validos()->where('data', '>=', $mes)->sum('valor_total');
        $recebidoAnt = (float) Recebimento::query()->validos()->whereBetween('data', [$mesAnt, now()->subMonthNoOverflow()])->sum('valor_total');

        $faturaveis = Cte::query()->faturaveis();

        return [
            'a_receber' => (float) (clone $abertos)->sum(DB::raw('valor - valor_baixado')),
            'a_receber_qtd' => (clone $abertos)->count(),
            'vencido' => (float) (clone $vencidos)->sum(DB::raw('valor - valor_baixado')),
            'vencido_qtd' => (clone $vencidos)->count(),
            'vencido_mais_antigo' => (clone $vencidos)->min('vencimento'),
            'recebido_mes' => $recebidoMes,
            'recebido_mes_qtd' => Recebimento::query()->validos()->where('data', '>=', $mes)->count(),
            'recebido_comp' => $recebidoAnt > 0 ? ($recebidoMes - $recebidoAnt) / $recebidoAnt * 100 : null,
            'sem_fatura_qtd' => (clone $faturaveis)->count(),
            'sem_fatura_valor' => (float) (clone $faturaveis)->sum('valor_total_servico'),
            'contagem' => [
                '' => Fatura::query()->count(),
                'abertas' => Fatura::query()->whereIn('status', ['aberta', 'parcial'])->count(),
                'vencidas' => Fatura::query()->vencidas()->count(),
                'pagas' => Fatura::query()->where('status', 'paga')->count(),
                'canceladas' => Fatura::query()->where('status', 'cancelada')->count(),
            ],
        ];
    }

    /** @return LengthAwarePaginator<Fatura> */
    #[Computed]
    public function faturas(): LengthAwarePaginator
    {
        return Fatura::query()
            ->with(['tomador', 'titulos'])
            ->withCount(['itens as ctes_qtd' => fn (Builder $q) => $q->where('ativo', true)])
            ->withCount('itens as ctes_total')
            ->when($this->situacao === 'abertas', fn (Builder $q) => $q->whereIn('status', ['aberta', 'parcial']))
            ->when($this->situacao === 'vencidas', fn (Builder $q) => $q->vencidas())
            ->when($this->situacao === 'pagas', fn (Builder $q) => $q->where('status', 'paga'))
            ->when($this->situacao === 'canceladas', fn (Builder $q) => $q->where('status', 'cancelada'))
            ->when(trim($this->busca) !== '', function (Builder $q): void {
                $termo = trim($this->busca);
                $digitos = preg_replace('/\D/', '', $termo) ?? '';
                $q->where(function (Builder $sub) use ($termo, $digitos): void {
                    $sub->where('numero', 'ilike', "%{$termo}%")
                        ->orWhereHas('tomador', function (Builder $p) use ($termo, $digitos): void {
                            $p->where('razao_social', 'ilike', "%{$termo}%")->orWhere('nome_fantasia', 'ilike', "%{$termo}%");
                            if (strlen($digitos) >= 3) {
                                $p->orWhere('documento', 'like', "%{$digitos}%");
                            }
                        });
                });
            })
            ->orderByDesc('sequencia')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.faturas.index')
            ->layout('layouts.app', ['title' => 'Faturas']);
    }
}
