<?php

declare(strict_types=1);

namespace App\Livewire\Entregas;

use App\Models\Entrega;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rotina 3050 — entregas (POD).
 *
 * A prova de entrega por viagem/ordem. Filtra pelo que ainda falta comprovar; o
 * EmpresaScope isola por empresa.
 */
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $busca = '';

    #[Url(as: 'situacao', except: '')]
    public string $situacao = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Entrega::class);
    }

    public function updated(string $campo): void
    {
        if (in_array($campo, ['busca', 'situacao'], true)) {
            $this->resetPage();
        }
    }

    /** @return array<string,int> */
    #[Computed]
    public function resumo(): array
    {
        $base = Entrega::query();

        return [
            'total'       => (clone $base)->count(),
            'comprovadas' => (clone $base)->comprovadas()->count(),
            'a_comprovar' => (clone $base)->count() - (clone $base)->comprovadas()->count(),
        ];
    }

    /** @return LengthAwarePaginator<Entrega> */
    #[Computed]
    public function entregas(): LengthAwarePaginator
    {
        return Entrega::query()
            ->with(['viagem', 'ordemColeta.destinatario', 'ordemColeta.municipioFim'])
            ->when($this->situacao === 'comprovadas', fn (Builder $q) => $q->comprovadas())
            ->when($this->situacao === 'a_comprovar', function (Builder $q): void {
                $q->whereNull('canhoto_path')->whereNull('assinatura_path')->where('tipo_comprovacao', '!=', 'evento_eletronico');
            })
            ->when($this->busca !== '', function (Builder $q): void {
                $termo = trim($this->busca);
                $q->where(function (Builder $sub) use ($termo): void {
                    $sub->where('recebedor_nome', 'ilike', "%{$termo}%")
                        ->orWhereHas('viagem', fn (Builder $v) => $v->where('numero', 'ilike', "%{$termo}%"))
                        ->orWhereHas('ordemColeta', fn (Builder $o) => $o->where('numero', 'ilike', "%{$termo}%"));
                });
            })
            ->orderByDesc('data_hora')
            ->orderByDesc('id')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.entregas.index')
            ->layout('layouts.app', ['title' => 'Entregas']);
    }
}
