<?php

declare(strict_types=1);

namespace App\Livewire\Despesas;

use App\Models\Despesa;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rotina 3060 — despesas de viagem.
 *
 * Gastos de estrada por viagem. Só a despesa aprovada entra no custo da viagem;
 * a aprovação aqui dispara o recálculo do custo e da margem. O EmpresaScope
 * isola por empresa.
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
        $this->authorize('viewAny', Despesa::class);
    }

    public function updated(string $campo): void
    {
        if (in_array($campo, ['busca', 'situacao'], true)) {
            $this->resetPage();
        }
    }

    /** Aprova a despesa e recalcula os custos da viagem. */
    public function aprovar(int $id): void
    {
        $despesa = Despesa::findOrFail($id);
        $this->authorize('update', $despesa);

        $despesa->update([
            'aprovada' => true,
            'aprovada_por' => Auth::id(),
            'aprovada_em' => now(),
        ]);

        $despesa->viagem?->recalcularCustosDeDespesas();

        session()->flash('sucesso', 'Despesa aprovada — custo da viagem atualizado.');
    }

    /** Volta a despesa para pendente e recalcula os custos da viagem. */
    public function reabrir(int $id): void
    {
        $despesa = Despesa::findOrFail($id);
        $this->authorize('update', $despesa);

        $despesa->update(['aprovada' => false, 'aprovada_por' => null, 'aprovada_em' => null]);
        $despesa->viagem?->recalcularCustosDeDespesas();

        session()->flash('sucesso', 'Despesa reaberta — custo da viagem atualizado.');
    }

    /** @return array<string,int|float> */
    #[Computed]
    public function resumo(): array
    {
        $base = Despesa::query()->toBase();
        $mes = now()->startOfMonth();

        return [
            'total'        => (clone $base)->count(),
            'pendentes'    => (clone $base)->where('aprovada', false)->count(),
            'gasto_mes'    => (float) (clone $base)->where('aprovada', true)->where('data', '>=', $mes)->sum('valor'),
            'adiantado'    => (float) (clone $base)->where('forma_pagamento', 'adiantamento')->where('aprovada', false)->sum('valor'),
        ];
    }

    /** @return LengthAwarePaginator<Despesa> */
    #[Computed]
    public function despesas(): LengthAwarePaginator
    {
        return Despesa::query()
            ->with(['viagem', 'motorista.pessoa'])
            ->when($this->situacao === 'pendentes', fn (Builder $q) => $q->where('aprovada', false))
            ->when($this->situacao === 'aprovadas', fn (Builder $q) => $q->where('aprovada', true))
            ->when($this->busca !== '', function (Builder $q): void {
                $termo = trim($this->busca);
                $q->where(function (Builder $sub) use ($termo): void {
                    $sub->where('descricao', 'ilike', "%{$termo}%")
                        ->orWhereHas('viagem', fn (Builder $v) => $v->where('numero', 'ilike', "%{$termo}%"))
                        ->orWhereHas('motorista.pessoa', fn (Builder $p) => $p->where('razao_social', 'ilike', "%{$termo}%"));
                });
            })
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.despesas.index')
            ->layout('layouts.app', ['title' => 'Despesas de viagem']);
    }
}
