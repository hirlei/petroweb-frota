<?php

declare(strict_types=1);

namespace App\Livewire\Rotas;

use App\Models\Rota;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rotina 3030 — rotas planejadas. O EmpresaScope isola por empresa.
 */
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $busca = '';

    #[Url(except: 'ativas')]
    public string $situacao = 'ativas';

    public function mount(): void
    {
        $this->authorize('viewAny', Rota::class);
    }

    public function updated(string $campo): void
    {
        if (in_array($campo, ['busca', 'situacao'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function total(): int
    {
        return Rota::query()->count();
    }

    /** @return LengthAwarePaginator<Rota> */
    #[Computed]
    public function rotas(): LengthAwarePaginator
    {
        return Rota::query()
            ->with(['municipioOrigem', 'municipioDestino'])
            ->withCount('pontos')
            ->when($this->situacao === 'ativas', fn (Builder $q) => $q->where('ativa', true))
            ->when($this->situacao === 'inativas', fn (Builder $q) => $q->where('ativa', false))
            ->when($this->busca !== '', function (Builder $q): void {
                $termo = trim($this->busca);
                $q->where(function (Builder $sub) use ($termo): void {
                    $sub->where('descricao', 'ilike', "%{$termo}%")
                        ->orWhereHas('municipioOrigem', fn (Builder $m) => $m->where('nome', 'ilike', "%{$termo}%"))
                        ->orWhereHas('municipioDestino', fn (Builder $m) => $m->where('nome', 'ilike', "%{$termo}%"));
                });
            })
            ->orderBy('descricao')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.rotas.index')
            ->layout('layouts.app', ['title' => 'Rotas']);
    }
}
