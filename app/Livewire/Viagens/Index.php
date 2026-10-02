<?php

declare(strict_types=1);

namespace App\Livewire\Viagens;

use App\Models\Viagem;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rotina 3020 — viagens.
 *
 * A execução física do transporte. Filtra por status e busca por número,
 * motorista ou placa de tração; o EmpresaScope isola por empresa.
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
        $this->authorize('viewAny', Viagem::class);
    }

    public function updated(string $campo): void
    {
        if (in_array($campo, ['busca', 'status'], true)) {
            $this->resetPage();
        }
    }

    /** @return array<string,int|float> */
    #[Computed]
    public function resumo(): array
    {
        $base = Viagem::query()->toBase();

        return [
            'total'       => (clone $base)->count(),
            'planejada'   => (clone $base)->where('status', 'planejada')->count(),
            'em_transito' => (clone $base)->where('status', 'em_transito')->count(),
            'entregue'    => (clone $base)->where('status', 'entregue')->count(),
            'encerrada'   => (clone $base)->where('status', 'encerrada')->count(),
            'margem_aberta' => (float) (clone $base)->whereNotIn('status', ['encerrada', 'cancelada'])->sum('margem'),
        ];
    }

    /** @return LengthAwarePaginator<Viagem> */
    #[Computed]
    public function viagens(): LengthAwarePaginator
    {
        return Viagem::query()
            ->with(['veiculoTracao', 'motorista.pessoa', 'rota'])
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->busca !== '', function (Builder $q): void {
                $termo = trim($this->busca);
                $q->where(function (Builder $sub) use ($termo): void {
                    $sub->where('numero', 'ilike', "%{$termo}%")
                        ->orWhereHas('veiculoTracao', fn (Builder $v) => $v->where('placa', 'ilike', "%{$termo}%"))
                        ->orWhereHas('motorista.pessoa', fn (Builder $p) => $p->where('razao_social', 'ilike', "%{$termo}%"));
                });
            })
            ->orderByDesc('saida_prevista')
            ->orderByDesc('id')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.viagens.index')
            ->layout('layouts.app', ['title' => 'Viagens']);
    }
}
