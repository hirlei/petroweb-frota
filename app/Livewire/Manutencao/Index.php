<?php

declare(strict_types=1);

namespace App\Livewire\Manutencao;

use App\Models\OrdemServico;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rotina 2050 — manutenção (ordens de serviço). O EmpresaScope isola por empresa.
 */
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $busca = '';

    #[Url(as: 'tipo', except: '')]
    public string $tipo = '';

    #[Url(except: 'abertas')]
    public string $situacao = 'abertas';

    public function mount(): void
    {
        $this->authorize('viewAny', OrdemServico::class);
    }

    public function updated(string $campo): void
    {
        if (in_array($campo, ['busca', 'tipo', 'situacao'], true)) {
            $this->resetPage();
        }
    }

    public function filtrarPor(string $tipo): void
    {
        $this->tipo = $this->tipo === $tipo ? '' : $tipo;
        $this->resetPage();
    }

    /** @return array<string,int|float> */
    #[Computed]
    public function resumo(): array
    {
        return [
            'abertas' => OrdemServico::query()->whereNotIn('status', ['encerrada', 'cancelada'])->count(),
            'custo_mes' => (float) OrdemServico::query()
                ->where('encerramento', '>=', now()->startOfMonth())
                ->sum('valor_total'),
        ];
    }

    /** @return LengthAwarePaginator<OrdemServico> */
    #[Computed]
    public function ordens(): LengthAwarePaginator
    {
        return OrdemServico::query()
            ->with(['veiculo', 'oficina'])
            ->when($this->tipo !== '', fn (Builder $q) => $q->where('tipo', $this->tipo))
            ->when($this->situacao === 'abertas', fn (Builder $q) => $q->whereNotIn('status', ['encerrada', 'cancelada']))
            ->when($this->situacao === 'encerradas', fn (Builder $q) => $q->where('status', 'encerrada'))
            ->when($this->busca !== '', function (Builder $q): void {
                $termo = trim($this->busca);
                $placa = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $termo) ?? '');
                $q->where(function (Builder $sub) use ($termo, $placa): void {
                    $sub->where('numero', 'ilike', "%{$termo}%")
                        ->orWhereHas('veiculo', fn (Builder $v) => $v->where('placa', 'ilike', "%{$placa}%"));
                });
            })
            ->orderByDesc('abertura')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.manutencao.index')
            ->layout('layouts.app', ['title' => 'Manutenção']);
    }
}
