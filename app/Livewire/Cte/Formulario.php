<?php

declare(strict_types=1);

namespace App\Livewire\Cte;

use App\Models\Cte;
use App\Models\OrdemColeta;
use App\Services\Fiscal\EmissorFiscal;
use App\Services\Fiscal\GeradorCte;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Rotina 4010 — painel do CT-e. Gera o rascunho a partir da ordem de coleta,
 * emite e cancela via gateway SEFAZ (fake em homologação). Documento autorizado
 * é imutável — só eventos.
 */
class Formulario extends Component
{
    use AuthorizesRequests;

    public ?Cte $cte = null;

    public ?int $ordem_coleta_id = null;
    public string $justificativa = '';

    public function mount(?Cte $cte = null): void
    {
        if ($cte?->exists) {
            $this->authorize('view', $cte);
            $this->cte = $cte->load(['tomador', 'remetente', 'destinatario', 'municipioInicio', 'municipioFim', 'componentes', 'documentos', 'eventos', 'ordemColeta']);

            return;
        }

        $this->authorize('create', Cte::class);
    }

    /** OCs sem CT-e ainda, prontas para gerar. */
    #[Computed]
    public function ordens()
    {
        return OrdemColeta::query()
            ->with(['cliente', 'municipioInicio', 'municipioFim'])
            ->whereNotIn('status', ['cancelada'])
            ->whereNotExists(function ($q): void {
                $q->selectRaw('1')->from('ctes')->whereColumn('ctes.ordem_coleta_id', 'ordens_coleta.id');
            })
            ->orderByDesc('data')->get();
    }

    #[Computed]
    public function ambiente(): int
    {
        return (int) config('fiscal.sefaz.ambiente', 2);
    }

    public function gerarRascunho(GeradorCte $gerador): void
    {
        $this->authorize('create', Cte::class);

        if ($this->ordem_coleta_id === null) {
            session()->flash('erro', 'Selecione uma ordem de coleta.');

            return;
        }

        $ordem = OrdemColeta::query()->findOrFail($this->ordem_coleta_id);
        $cte = $gerador->aPartirDaOrdem($ordem);

        session()->flash('sucesso', 'Rascunho de CT-e gerado a partir da ordem ' . $ordem->numero . '.');

        $this->redirect(route('cte.editar', $cte), navigate: true);
    }

    public function emitir(EmissorFiscal $emissor): void
    {
        $this->authorize('emitir', $this->cte);

        if (! $this->cte->editavel()) {
            session()->flash('erro', 'Só rascunho ou rejeitado pode ser emitido.');

            return;
        }

        $r = $emissor->emitirCte($this->cte);
        $this->cte->refresh();

        session()->flash($r->autorizado ? 'sucesso' : 'erro',
            $r->autorizado
                ? "CT-e autorizado (cStat {$r->codigo}). Protocolo {$r->protocolo}."
                : "Rejeitado (cStat {$r->codigo}): {$r->motivo}");
    }

    public function cancelar(EmissorFiscal $emissor): void
    {
        $this->authorize('cancelar', $this->cte);

        if (! $this->cte->autorizado()) {
            session()->flash('erro', 'Só CT-e autorizado pode ser cancelado.');

            return;
        }

        if (mb_strlen(trim($this->justificativa)) < 15) {
            session()->flash('erro', 'A justificativa de cancelamento precisa de ao menos 15 caracteres.');

            return;
        }

        $r = $emissor->cancelarCte($this->cte, trim($this->justificativa));
        $this->cte->refresh();
        $this->justificativa = '';

        session()->flash($r->autorizado ? 'sucesso' : 'erro',
            $r->autorizado ? 'CT-e cancelado (evento 110111 registrado).' : "Falha ao cancelar: {$r->motivo}");
    }

    public function render(): View
    {
        return view('livewire.cte.formulario')
            ->layout('layouts.app', ['title' => $this->cte?->exists ? 'CT-e ' . ($this->cte->numero ?? 'rascunho') : 'Novo CT-e']);
    }
}
