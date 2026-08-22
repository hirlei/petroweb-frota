<?php

declare(strict_types=1);

namespace App\Livewire\Veiculos;

use App\Domain\Frota\RegrasVeiculo;
use App\Models\Veiculo;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rotina 2010 — lista da frota.
 *
 * Uma UNIDADE por linha (cavalo, reboque, semirreboque, dolly). A combinação é
 * outra tela (2020). Nenhuma query filtra por `empresa_id`: quem faz é o
 * EmpresaScope, a partir do TenantContext.
 */
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $busca = '';

    #[Url(as: 'tipo', except: '')]
    public string $tipo = '';

    #[Url(except: 'ativos')]
    public string $situacao = 'ativos';

    public ?int $selecionado = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Veiculo::class);
    }

    public function updated(string $campo): void
    {
        if (in_array($campo, ['busca', 'tipo', 'situacao'], true)) {
            $this->resetPage();
            $this->selecionado = null;
        }
    }

    public function selecionar(int $id): void
    {
        $this->selecionado = $this->selecionado === $id ? null : $id;
    }

    public function filtrarPor(string $tipo): void
    {
        $this->tipo = $this->tipo === $tipo ? '' : $tipo;
        $this->resetPage();
        $this->selecionado = null;
    }

    /**
     * Contagem por tipo para os chips do topo. Uma query agrupada, não quatro
     * count() separados.
     *
     * @return array<string,int>
     */
    #[Computed]
    public function totaisPorTipo(): array
    {
        $totais = Veiculo::query()
            ->selectRaw('tipo, count(*) as total')
            ->groupBy('tipo')
            ->pluck('total', 'tipo')
            ->all();

        $saida = [];

        foreach (RegrasVeiculo::TIPOS as $tipo) {
            $saida[$tipo] = (int) ($totais[$tipo] ?? 0);
        }

        return $saida;
    }

    #[Computed]
    public function total(): int
    {
        return Veiculo::query()->count();
    }

    #[Computed]
    public function veiculo(): ?Veiculo
    {
        if ($this->selecionado === null) {
            return null;
        }

        return Veiculo::with(['carroceria', 'proprietario', 'municipioLicenciamento', 'filial'])
            ->find($this->selecionado);
    }

    /** @return LengthAwarePaginator<Veiculo> */
    #[Computed]
    public function veiculos(): LengthAwarePaginator
    {
        return Veiculo::query()
            ->with(['carroceria', 'proprietario'])
            ->when($this->tipo !== '', fn (Builder $q) => $q->where('tipo', $this->tipo))
            ->when($this->situacao === 'ativos', fn (Builder $q) => $q->where('status', 'ativo'))
            ->when($this->situacao === 'inativos', fn (Builder $q) => $q->where('status', '!=', 'ativo'))
            ->when($this->busca !== '', function (Builder $q): void {
                $termo = trim($this->busca);
                $placa = RegrasVeiculo::limparPlaca($termo);

                $q->where(function (Builder $sub) use ($termo, $placa): void {
                    $sub->where('placa', 'ilike', "%{$placa}%")
                        ->orWhere('modelo', 'ilike', "%{$termo}%")
                        ->orWhere('marca', 'ilike', "%{$termo}%")
                        ->orWhere('renavam', 'like', "%{$termo}%");
                });
            })
            ->orderBy('placa')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.veiculos.index')
            ->layout('layouts.app', ['title' => 'Veículos']);
    }
}
