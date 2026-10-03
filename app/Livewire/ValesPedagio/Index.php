<?php

declare(strict_types=1);

namespace App\Livewire\ValesPedagio;

use App\Models\FornecedorVpo;
use App\Models\ValePedagio;
use App\Services\Fiscal\Ciot\CiotException;
use App\Services\Fiscal\ValePedagio\ServicoValePedagio;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rotina 4030 — vale-pedágio (grupo valePed do MDF-e). Um registro por veículo.
 *
 * A compra/registro normal acontece na emissão do MDF-e (4020); aqui é consulta,
 * cancelamento de compra não usada e lançamento manual.
 */
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $busca = '';

    public ?int $cancelandoId = null;

    public string $motivoCancelamento = '';

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
            'valor' => (float) (clone $base)->where('dispensado', false)->where('situacao', 'ativo')->sum('valor'),
        ];
    }

    /** @return LengthAwarePaginator<ValePedagio> */
    #[Computed]
    public function vales(): LengthAwarePaginator
    {
        return ValePedagio::query()
            ->with(['viagem', 'veiculo', 'fornecedorVpo', 'mdfe'])
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

    /** Sem fornecedora ativa o vale não sai — o aviso do topo explica. */
    #[Computed]
    public function semFornecedora(): bool
    {
        return ! FornecedorVpo::query()->where('ativo', true)->exists();
    }

    #[On('fornecedoras-atualizadas')]
    public function fornecedorasAtualizadas(): void
    {
        unset($this->semFornecedora);
    }

    public function abrirCancelamento(int $id): void
    {
        abort_unless(Auth::user()?->can('mdfe.emitir') ?? false, 403);
        $this->cancelandoId = $id;
        $this->motivoCancelamento = '';
        $this->resetErrorBag();
    }

    public function cancelar(ServicoValePedagio $servico): void
    {
        abort_unless(Auth::user()?->can('mdfe.emitir') ?? false, 403);
        $vale = ValePedagio::query()->findOrFail($this->cancelandoId);

        try {
            $servico->cancelar($vale, $this->motivoCancelamento);
        } catch (CiotException $e) {
            $this->addError('motivoCancelamento', $e->getMessage());

            return;
        }

        $this->cancelandoId = null;
        unset($this->vales);
        session()->flash('sucesso', 'Vale-pedágio cancelado. Na próxima emissão do MDF-e da viagem, outro é comprado ou informado.');
    }

    public function render(): View
    {
        return view('livewire.vales-pedagio.index')->layout('layouts.app', ['title' => 'Vale-pedágio']);
    }
}
