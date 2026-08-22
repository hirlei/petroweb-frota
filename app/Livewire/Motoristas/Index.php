<?php

declare(strict_types=1);

namespace App\Livewire\Motoristas;

use App\Domain\Frota\RegrasMotorista;
use App\Models\Motorista;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rotina 2030 — lista de motoristas.
 *
 * O motorista é um PAPEL sobre `pessoas`, não uma pessoa paralela. RN-12: o
 * vínculo decide se há jornada — a coluna existe para deixar isso à vista.
 * Nenhuma query filtra por `empresa_id`: quem faz é o EmpresaScope.
 */
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $busca = '';

    #[Url(as: 'vinculo', except: '')]
    public string $vinculo = '';

    #[Url(except: 'ativos')]
    public string $situacao = 'ativos';

    public ?int $selecionado = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Motorista::class);
    }

    public function updated(string $campo): void
    {
        if (in_array($campo, ['busca', 'vinculo', 'situacao'], true)) {
            $this->resetPage();
            $this->selecionado = null;
        }
    }

    public function selecionar(int $id): void
    {
        $this->selecionado = $this->selecionado === $id ? null : $id;
    }

    public function filtrarPor(string $vinculo): void
    {
        $this->vinculo = $this->vinculo === $vinculo ? '' : $vinculo;
        $this->resetPage();
        $this->selecionado = null;
    }

    /** @return array<string,int> */
    #[Computed]
    public function totaisPorVinculo(): array
    {
        $totais = Motorista::query()
            ->selectRaw('vinculo, count(*) as total')
            ->groupBy('vinculo')
            ->pluck('total', 'vinculo')
            ->all();

        $saida = [];

        foreach (RegrasMotorista::VINCULOS as $vinculo) {
            $saida[$vinculo] = (int) ($totais[$vinculo] ?? 0);
        }

        return $saida;
    }

    #[Computed]
    public function total(): int
    {
        return Motorista::query()->count();
    }

    #[Computed]
    public function motorista(): ?Motorista
    {
        if ($this->selecionado === null) {
            return null;
        }

        return Motorista::with(['pessoa'])->find($this->selecionado);
    }

    /** @return LengthAwarePaginator<Motorista> */
    #[Computed]
    public function motoristas(): LengthAwarePaginator
    {
        return Motorista::query()
            ->with(['pessoa'])
            ->when($this->vinculo !== '', fn (Builder $q) => $q->where('vinculo', $this->vinculo))
            ->when($this->situacao === 'ativos', fn (Builder $q) => $q->where('status', 'ativo'))
            ->when($this->situacao === 'inativos', fn (Builder $q) => $q->where('status', '!=', 'ativo'))
            ->when($this->busca !== '', function (Builder $q): void {
                $termo = trim($this->busca);
                $digitos = preg_replace('/\D/', '', $termo) ?? '';

                $q->where(function (Builder $sub) use ($termo, $digitos): void {
                    $sub->whereHas('pessoa', fn (Builder $p) => $p->where('razao_social', 'ilike', "%{$termo}%"));

                    if ($digitos !== '') {
                        $sub->orWhere('cnh_numero', 'like', "%{$digitos}%");
                    }
                });
            })
            ->orderBy('cnh_validade')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.motoristas.index')
            ->layout('layouts.app', ['title' => 'Motoristas']);
    }
}
