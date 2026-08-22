<?php

declare(strict_types=1);

namespace App\Livewire\Composicoes;

use App\Models\Composicao;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rotina 2020 — combinações veiculares.
 *
 * Cada composição é um cavalo mais reboques NA ORDEM em que rodam. A categoria
 * (bitrem, rodotrem, treminhão) e a AET saem da soma dos eixos — nunca digitadas.
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
        $this->authorize('viewAny', Composicao::class);
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
        return Composicao::query()->count();
    }

    /** @return LengthAwarePaginator<Composicao> */
    #[Computed]
    public function composicoes(): LengthAwarePaginator
    {
        return Composicao::query()
            ->with(['tracao', 'veiculos'])
            ->when($this->situacao === 'ativas', fn (Builder $q) => $q->where('ativa', true))
            ->when($this->situacao === 'inativas', fn (Builder $q) => $q->where('ativa', false))
            ->when($this->busca !== '', function (Builder $q): void {
                $termo = trim($this->busca);

                $q->where(function (Builder $sub) use ($termo): void {
                    $sub->where('descricao', 'ilike', "%{$termo}%")
                        ->orWhereHas('tracao', fn (Builder $t) => $t->where('placa', 'ilike', '%' . strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $termo)) . '%'));
                });
            })
            ->orderBy('descricao')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.composicoes.index')
            ->layout('layouts.app', ['title' => 'Composições']);
    }
}
