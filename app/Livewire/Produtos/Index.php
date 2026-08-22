<?php

declare(strict_types=1);

namespace App\Livewire\Produtos;

use App\Models\Mercadoria;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rotina 1020 — catálogo de mercadorias. O EmpresaScope isola por empresa.
 */
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $busca = '';

    #[Url(as: 'filtro', except: '')]
    public string $filtro = '';

    #[Url(except: 'ativos')]
    public string $situacao = 'ativos';

    public function mount(): void
    {
        $this->authorize('viewAny', Mercadoria::class);
    }

    public function updated(string $campo): void
    {
        if (in_array($campo, ['busca', 'filtro', 'situacao'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function total(): int
    {
        return Mercadoria::query()->count();
    }

    #[Computed]
    public function totalPerigosas(): int
    {
        return Mercadoria::query()->where('eh_perigoso', true)->count();
    }

    /** @return LengthAwarePaginator<Mercadoria> */
    #[Computed]
    public function mercadorias(): LengthAwarePaginator
    {
        return Mercadoria::query()
            ->with('naturezaCarga')
            ->when($this->filtro === 'perigosas', fn (Builder $q) => $q->where('eh_perigoso', true))
            ->when($this->filtro === 'refrigeradas', fn (Builder $q) => $q->where('exige_temp_controlada', true))
            ->when($this->situacao === 'ativos', fn (Builder $q) => $q->where('ativo', true))
            ->when($this->situacao === 'inativos', fn (Builder $q) => $q->where('ativo', false))
            ->when($this->busca !== '', function (Builder $q): void {
                $termo = trim($this->busca);
                $q->where(function (Builder $sub) use ($termo): void {
                    $sub->where('descricao', 'ilike', "%{$termo}%")
                        ->orWhere('codigo_interno', 'ilike', "%{$termo}%")
                        ->orWhere('ncm', 'like', "%{$termo}%")
                        ->orWhere('num_onu', 'like', "%{$termo}%");
                });
            })
            ->orderBy('descricao')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.produtos.index')
            ->layout('layouts.app', ['title' => 'Produtos']);
    }
}
