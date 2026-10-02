<?php

declare(strict_types=1);

namespace App\Livewire\Cte;

use App\Models\Cte;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rotina 4010 — CT-e (modelo 57).
 */
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $busca = '';

    #[Url(as: 'status', except: '')]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Cte::class);
    }

    public function updated(string $campo): void
    {
        if (in_array($campo, ['busca', 'status'], true)) {
            $this->resetPage();
        }
    }

    /** @return array<string,int> */
    #[Computed]
    public function contagem(): array
    {
        $base = Cte::query()->toBase();

        return [
            'total' => (clone $base)->count(),
            'rascunho' => (clone $base)->where('status', 'rascunho')->count(),
            'autorizado' => (clone $base)->where('status', 'autorizado')->count(),
            'rejeitado' => (clone $base)->where('status', 'rejeitado')->count(),
            'cancelado' => (clone $base)->where('status', 'cancelado')->count(),
        ];
    }

    #[Computed]
    public function ambiente(): int
    {
        return (int) config('fiscal.sefaz.ambiente', 2);
    }

    /** @return LengthAwarePaginator<Cte> */
    #[Computed]
    public function ctes(): LengthAwarePaginator
    {
        return Cte::query()
            ->with(['tomador', 'municipioInicio', 'municipioFim'])
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->busca !== '', function (Builder $q): void {
                $termo = trim($this->busca);
                $q->where(function (Builder $s) use ($termo): void {
                    $s->where('numero', 'ilike', "%{$termo}%")
                        ->orWhere('chave', 'ilike', "%{$termo}%")
                        ->orWhereHas('tomador', fn (Builder $t) => $t->where('razao_social', 'ilike', "%{$termo}%"));
                });
            })
            ->orderByDesc('emissao')->orderByDesc('id')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.cte.index')->layout('layouts.app', ['title' => 'CT-e']);
    }
}
