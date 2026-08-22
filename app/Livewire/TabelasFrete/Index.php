<?php

declare(strict_types=1);

namespace App\Livewire\TabelasFrete;

use App\Models\TabelaFrete;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rotina 1030 — tabelas de frete.
 *
 * Geral (sem cliente) ou por cliente; o que responde numa data é a que está
 * vigente. O EmpresaScope isola por empresa.
 */
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $busca = '';

    #[Url(as: 'escopo', except: '')]
    public string $escopo = '';

    #[Url(except: 'vigentes')]
    public string $situacao = 'vigentes';

    public function mount(): void
    {
        $this->authorize('viewAny', TabelaFrete::class);
    }

    public function updated(string $campo): void
    {
        if (in_array($campo, ['busca', 'escopo', 'situacao'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function total(): int
    {
        return TabelaFrete::query()->count();
    }

    /** @return LengthAwarePaginator<TabelaFrete> */
    #[Computed]
    public function tabelas(): LengthAwarePaginator
    {
        return TabelaFrete::query()
            ->with(['cliente', 'municipioOrigem', 'municipioDestino'])
            ->withCount('itens')
            ->when($this->escopo === 'geral', fn (Builder $q) => $q->whereNull('pessoa_id'))
            ->when($this->escopo === 'cliente', fn (Builder $q) => $q->whereNotNull('pessoa_id'))
            ->when($this->situacao === 'vigentes', fn (Builder $q) => $q->vigentes())
            ->when($this->situacao === 'inativas', fn (Builder $q) => $q->where('ativo', false))
            ->when($this->busca !== '', function (Builder $q): void {
                $termo = trim($this->busca);
                $q->where(function (Builder $sub) use ($termo): void {
                    $sub->where('descricao', 'ilike', "%{$termo}%")
                        ->orWhereHas('cliente', fn (Builder $c) => $c->where('razao_social', 'ilike', "%{$termo}%"));
                });
            })
            ->orderByDesc('vigencia_inicio')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.tabelas-frete.index')
            ->layout('layouts.app', ['title' => 'Tabelas de frete']);
    }
}
