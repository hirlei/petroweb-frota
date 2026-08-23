<?php

declare(strict_types=1);

namespace App\Livewire\ValesPedagio;

use App\Models\ValePedagio;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rotina 4030 — vale-pedágio (grupo valePed do MDF-e). Um registro por veículo.
 */
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $busca = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->can('mdfe.consultar') ?? false, 403);
    }

    public function updated(string $campo): void
    {
        if ($campo === 'busca') {
            $this->resetPage();
        }
    }

    /** @return array<string,int|float> */
    #[Computed]
    public function resumo(): array
    {
        $base = ValePedagio::query()->toBase();

        return [
            'total' => (clone $base)->count(),
            'valor' => (float) (clone $base)->where('dispensado', false)->sum('valor'),
        ];
    }

    /** @return LengthAwarePaginator<ValePedagio> */
    #[Computed]
    public function vales(): LengthAwarePaginator
    {
        return ValePedagio::query()
            ->with(['viagem', 'veiculo', 'fornecedorVpo'])
            ->when($this->busca !== '', function (Builder $q): void {
                $termo = trim($this->busca);
                $q->where(function (Builder $s) use ($termo): void {
                    $s->where('idvpo', 'ilike', "%{$termo}%")
                        ->orWhere('cnpj_forn', 'ilike', "%{$termo}%")
                        ->orWhereHas('veiculo', fn (Builder $v) => $v->where('placa', 'ilike', "%{$termo}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.vales-pedagio.index')->layout('layouts.app', ['title' => 'Vale-pedágio']);
    }
}
