<?php

declare(strict_types=1);

namespace App\Livewire\Ocorrencias;

use App\Models\Ocorrencia;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rotina 3040 — ocorrências operacionais. O EmpresaScope isola por empresa.
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
        $this->authorize('viewAny', Ocorrencia::class);
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

    #[Computed]
    public function total(): int
    {
        return Ocorrencia::query()->count();
    }

    /** @return array<string,int|float> */
    #[Computed]
    public function resumo(): array
    {
        return [
            'abertas' => Ocorrencia::query()->where('status', '!=', 'resolvida')->count(),
            'prejuizo' => (float) Ocorrencia::query()->where('status', '!=', 'resolvida')->sum('valor_prejuizo'),
        ];
    }

    /** @return LengthAwarePaginator<Ocorrencia> */
    #[Computed]
    public function ocorrencias(): LengthAwarePaginator
    {
        return Ocorrencia::query()
            ->with(['municipio'])
            ->when($this->tipo !== '', fn (Builder $q) => $q->where('tipo', $this->tipo))
            ->when($this->situacao === 'abertas', fn (Builder $q) => $q->where('status', '!=', 'resolvida'))
            ->when($this->situacao === 'resolvidas', fn (Builder $q) => $q->where('status', 'resolvida'))
            ->when($this->busca !== '', fn (Builder $q) => $q->where('descricao', 'ilike', '%' . trim($this->busca) . '%'))
            ->orderByDesc('data_hora')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.ocorrencias.index')
            ->layout('layouts.app', ['title' => 'Ocorrências']);
    }
}
