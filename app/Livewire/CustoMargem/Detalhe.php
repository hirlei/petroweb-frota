<?php

declare(strict_types=1);

namespace App\Livewire\CustoMargem;

use App\Models\Viagem;
use App\Services\Operacao\CustosDaViagem;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

/** Rotina 3080 — de onde vem cada valor do custo de uma viagem. */
class Detalhe extends Component
{
    public Viagem $viagem;

    public function mount(Viagem $viagem): void
    {
        Gate::authorize('custo-viagem.consultar');
        $this->viagem = $viagem->load(['motorista.pessoa', 'veiculoTracao', 'municipioOrigem', 'municipioDestino']);
    }

    /** @return array<string,mixed> */
    #[Computed]
    public function detalhe(): array
    {
        return app(CustosDaViagem::class)->detalhar($this->viagem);
    }

    /** Refaz e grava agora (o que a rotina noturna faria). */
    public function recalcular(CustosDaViagem $custos): void
    {
        Gate::authorize('custo-viagem.consultar');
        $custos->recalcular($this->viagem);
        $this->viagem->refresh();
        unset($this->detalhe);
        session()->flash('sucesso', 'Custo recalculado.');
    }

    public function render(): View
    {
        return view('livewire.custo-margem.detalhe')->layout('layouts.app', ['title' => 'Custo · ' . $this->viagem->numero]);
    }
}
