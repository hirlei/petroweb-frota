<?php

declare(strict_types=1);

namespace App\Livewire\Ciots;

use App\Domain\Fiscal\RegrasCiot;
use App\Models\Ciot;
use App\Models\Mdfe;
use App\Services\Fiscal\Ciot\CiotException;
use App\Services\Fiscal\Ciot\ServicoCiot;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Detalhe do CIOT (rotina 4050): pagamentos ao TAC, documentos da viagem e
 * histórico. Pagar saldo, reenviar e cancelar passam por ServicoCiot.
 */
class Detalhe extends Component
{
    use AuthorizesRequests;

    public Ciot $ciot;

    public bool $pagando = false;
    public string $pagForma = 'pix';
    public string $pagData = '';

    public bool $cancelando = false;
    public string $motivoCancelamento = '';

    public function mount(Ciot $ciot): void
    {
        $this->authorize('view', $ciot);
        $this->ciot = $ciot;
    }

    #[Computed]
    public function dados(): Ciot
    {
        return $this->ciot->fresh(['viagem.veiculoTracao', 'viagem.municipioOrigem', 'viagem.municipioDestino', 'viagem.ctes.tomador', 'pagamentos.criadoPor', 'criadoPor', 'mdfe']);
    }

    /** @return Collection<int, Mdfe> */
    #[Computed]
    public function mdfes(): Collection
    {
        return Mdfe::query()->where('viagem_id', $this->ciot->viagem_id)->latest('id')->get();
    }

    #[Computed]
    public function diasUteisRestantes(): ?int
    {
        $prazo = $this->dados->prazo_quitacao;

        return $prazo === null ? null : RegrasCiot::diasUteisAte(new DateTimeImmutable('today'), new DateTimeImmutable($prazo->toDateString()));
    }

    public function abrirPagamento(): void
    {
        $this->authorize('gerenciar', $this->ciot);
        $this->pagForma = (string) ($this->dados->forma_pagamento ?? 'pix');
        $this->pagData = Carbon::today()->toDateString();
        $this->resetErrorBag();
        $this->pagando = true;
    }

    public function pagarSaldo(ServicoCiot $servico): void
    {
        $this->authorize('gerenciar', $this->ciot);

        try {
            $p = $servico->pagarSaldo($this->ciot->fresh('pagamentos'), $this->pagForma, $this->pagData !== '' ? Carbon::parse($this->pagData) : null);
        } catch (CiotException $e) {
            $this->addError('pagamento', $e->getMessage());

            return;
        }

        $this->pagando = false;
        unset($this->dados);
        session()->flash('sucesso', 'Saldo de R$ ' . number_format((float) $p->valor, 2, ',', '.') . ' pago.');
    }

    public function reenviar(ServicoCiot $servico): void
    {
        $this->authorize('gerenciar', $this->ciot);
        $c = $servico->reenviar($this->ciot->fresh('pagamentos'));
        unset($this->dados);

        session()->flash($c->valido() ? 'sucesso' : 'erro', $c->valido()
            ? 'CIOT ' . $c->numeroFormatado() . ($c->adiantamentoPendente() ? ' registrado; o adiantamento continua pendente.' : ' em dia.')
            : 'Recusado de novo: ' . $c->motivo . ' Se o problema for o frete, corrija na emissão do MDF-e.');
    }

    public function cancelar(ServicoCiot $servico): void
    {
        $this->authorize('gerenciar', $this->ciot);

        try {
            $servico->cancelar($this->ciot->fresh('pagamentos'), $this->motivoCancelamento);
        } catch (CiotException $e) {
            $this->addError('motivoCancelamento', $e->getMessage());

            return;
        }

        $this->cancelando = false;
        unset($this->dados);
        session()->flash('sucesso', 'CIOT cancelado. Na próxima emissão do MDF-e desta viagem, um novo é registrado.');
    }

    public function render(): View
    {
        return view('livewire.ciots.detalhe')
            ->layout('layouts.app', ['title' => 'CIOT ' . ($this->ciot->numero ? $this->ciot->numeroFormatado() : '')]);
    }
}
