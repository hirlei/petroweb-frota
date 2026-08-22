<?php

declare(strict_types=1);

namespace App\Livewire\Abastecimentos;

use App\Models\Abastecimento;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rotina 2060 — abastecimentos. O EmpresaScope isola por empresa.
 */
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $busca = '';

    #[Url(except: 'todos')]
    public string $filtro = 'todos';

    public function mount(): void
    {
        $this->authorize('viewAny', Abastecimento::class);
    }

    public function updated(string $campo): void
    {
        if (in_array($campo, ['busca', 'filtro'], true)) {
            $this->resetPage();
        }
    }

    /** @return array<string,int|float> */
    #[Computed]
    public function resumo(): array
    {
        $mes = now()->startOfMonth();

        return [
            'litros_mes' => (float) Abastecimento::query()->where('data_hora', '>=', $mes)->sum('litros'),
            'gasto_mes' => (float) Abastecimento::query()->where('data_hora', '>=', $mes)->sum('valor_total'),
            'alertas' => Abastecimento::query()->where('alerta', true)->count(),
        ];
    }

    /** @return LengthAwarePaginator<Abastecimento> */
    #[Computed]
    public function abastecimentos(): LengthAwarePaginator
    {
        return Abastecimento::query()
            ->with(['veiculo', 'motorista.pessoa', 'posto'])
            ->when($this->filtro === 'alertas', fn (Builder $q) => $q->where('alerta', true))
            ->when($this->busca !== '', function (Builder $q): void {
                $termo = trim($this->busca);
                $placa = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $termo) ?? '');
                $q->whereHas('veiculo', fn (Builder $v) => $v->where('placa', 'ilike', "%{$placa}%"));
            })
            ->orderByDesc('data_hora')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.abastecimentos.index')
            ->layout('layouts.app', ['title' => 'Abastecimentos']);
    }
}
