<?php

declare(strict_types=1);

namespace App\Livewire\OrdensColeta;

use App\Models\OrdemColeta;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rotina 3010 — ordens de coleta.
 *
 * O documento de entrada da operação. Filtra por status e busca por número ou
 * cliente; o EmpresaScope isola por empresa.
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
        $this->authorize('viewAny', OrdemColeta::class);
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
        $base = OrdemColeta::query()->toBase();

        return [
            'total'    => (clone $base)->count(),
            'aberta'   => (clone $base)->where('status', 'aberta')->count(),
            'coletada' => (clone $base)->where('status', 'coletada')->count(),
            'faturada' => (clone $base)->where('status', 'faturada')->count(),
        ];
    }

    /** @return LengthAwarePaginator<OrdemColeta> */
    #[Computed]
    public function ordens(): LengthAwarePaginator
    {
        return OrdemColeta::query()
            ->with(['cliente', 'municipioInicio', 'municipioFim'])
            ->withCount('itens')
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->busca !== '', function (Builder $q): void {
                $termo = trim($this->busca);
                $q->where(function (Builder $sub) use ($termo): void {
                    $sub->where('numero', 'ilike', "%{$termo}%")
                        ->orWhereHas('cliente', fn (Builder $c) => $c->where('razao_social', 'ilike', "%{$termo}%"));
                });
            })
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.ordens-coleta.index')
            ->layout('layouts.app', ['title' => 'Ordens de coleta']);
    }
}
