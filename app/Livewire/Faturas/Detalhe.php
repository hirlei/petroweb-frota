<?php

declare(strict_types=1);

namespace App\Livewire\Faturas;

use App\Livewire\Concerns\RegistraRecebimento;
use App\Models\Fatura;
use App\Services\Financeiro\Faturamento;
use App\Services\Financeiro\FaturamentoException;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Rotina 5010 — ficha da fatura: valor, recebido e saldo; parcelas com
 * "Receber" e "Estornar"; CT-e incluídos; histórico; cancelar.
 */
class Detalhe extends Component
{
    use AuthorizesRequests;
    use RegistraRecebimento;

    public Fatura $fatura;

    public bool $cancelando = false;

    public string $motivoCancelamento = '';

    public function mount(Fatura $fatura): void
    {
        $this->authorize('view', $fatura);
        $this->fatura = $fatura;
    }

    #[Computed]
    public function dados(): Fatura
    {
        return Fatura::query()
            ->with(['tomador', 'criadaPor', 'canceladaPor', 'titulos.recebimentos.registradoPor', 'titulos.recebimentos.estornadoPor',
                'itens.cte.municipioInicio', 'itens.cte.municipioFim', 'itens.cte.viagens'])
            ->findOrFail($this->fatura->id);
    }

    /** Linha do tempo: geração, recebimentos, estornos e cancelamento. */
    #[Computed]
    public function historico(): Collection
    {
        $f = $this->dados;
        $ev = collect([[
            'quando' => $f->created_at, 'cor' => 'var(--h6-azul)',
            'titulo' => 'Fatura gerada com ' . $f->itens->count() . ' CT-e',
            'sub' => $f->criadaPor?->name,
        ]]);

        foreach ($f->titulos as $t) {
            foreach ($t->recebimentos as $r) {
                $ev->push([
                    'quando' => $r->created_at, 'cor' => '#16a34a',
                    'titulo' => "Parcela {$t->parcela}/{$t->parcelas} · " . config('financeiro.formas.' . $r->forma, $r->forma) . ' · R$ ' . number_format((float) $r->valor_total, 2, ',', '.'),
                    'sub' => 'Recebido em ' . $r->data?->format('d/m/Y') . ($r->registradoPor ? ' · ' . $r->registradoPor->name : ''),
                ]);
                if ($r->estornado()) {
                    $ev->push([
                        'quando' => $r->estornado_em, 'cor' => '#dc2626',
                        'titulo' => "Recebimento da parcela {$t->parcela}/{$t->parcelas} estornado",
                        'sub' => trim(($r->motivo_estorno ?? '') . ($r->estornadoPor ? ' · ' . $r->estornadoPor->name : '')),
                    ]);
                }
            }
        }

        if ($f->cancelada()) {
            $ev->push([
                'quando' => $f->cancelada_em, 'cor' => '#dc2626',
                'titulo' => 'Fatura cancelada · CT-e liberados',
                'sub' => trim(($f->motivo_cancelamento ?? '') . ($f->canceladaPor ? ' · ' . $f->canceladaPor->name : '')),
            ]);
        }

        return $ev->filter(fn ($e) => $e['quando'] !== null)->sortByDesc('quando')->values();
    }

    public function abrirCancelamento(): void
    {
        $this->authorize('cancelar', $this->fatura);
        $this->resetErrorBag();
        $this->motivoCancelamento = '';
        $this->cancelando = true;
    }

    public function cancelar(Faturamento $faturamento): void
    {
        $this->authorize('cancelar', $this->fatura);

        try {
            $faturamento->cancelar($this->fatura, $this->motivoCancelamento);
        } catch (FaturamentoException $e) {
            $this->addError('motivoCancelamento', $e->getMessage());

            return;
        }

        $this->cancelando = false;
        session()->flash('sucesso', "Fatura {$this->fatura->numero} cancelada. Os CT-e voltaram para a lista de faturáveis.");
        $this->depoisDoRecebimento();
    }

    protected function depoisDoRecebimento(): void
    {
        $this->fatura->refresh();
        unset($this->dados, $this->historico);
    }


    public function render(): View
    {
        return view('livewire.faturas.detalhe')
            ->layout('layouts.app', ['title' => 'Fatura ' . $this->fatura->numero]);
    }
}
