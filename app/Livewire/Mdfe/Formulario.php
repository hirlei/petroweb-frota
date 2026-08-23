<?php

declare(strict_types=1);

namespace App\Livewire\Mdfe;

use App\Models\Mdfe;
use App\Models\Municipio;
use App\Models\Viagem;
use App\Services\Fiscal\EmissorFiscal;
use App\Services\Fiscal\GeradorMdfe;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Component;
use RuntimeException;

/**
 * Rotina 4020 — painel do MDF-e. Gera o rascunho a partir da viagem (RN-03),
 * emite e encerra (110112) via gateway SEFAZ (fake em homologação).
 */
class Formulario extends Component
{
    use AuthorizesRequests;

    public ?Mdfe $mdfe = null;

    public ?int $viagem_id = null;
    public ?int $municipio_encerramento_id = null;

    public function mount(?Mdfe $mdfe = null): void
    {
        if ($mdfe?->exists) {
            $this->authorize('view', $mdfe);
            $this->mdfe = $mdfe->load(['viagem.municipioDestino', 'veiculoTracao', 'documentos', 'eventos', 'valesPedagio.veiculo']);
            $this->municipio_encerramento_id = $mdfe->viagem?->municipio_destino_id;

            return;
        }

        $this->authorize('create', Mdfe::class);
    }

    /** Viagens sem MDF-e ainda. */
    #[Computed]
    public function viagens()
    {
        return Viagem::query()
            ->with(['veiculoTracao', 'municipioOrigem', 'municipioDestino'])
            ->whereNotExists(function ($q): void {
                $q->selectRaw('1')->from('mdfes')->whereColumn('mdfes.viagem_id', 'viagens.id')->whereIn('mdfes.status', Mdfe::ABERTOS);
            })
            ->orderByDesc('saida_prevista')->get();
    }

    #[Computed]
    public function municipios()
    {
        return Municipio::query()->orderBy('uf')->orderBy('nome')->get(['id', 'nome', 'uf']);
    }

    #[Computed]
    public function ambiente(): int
    {
        return (int) config('fiscal.sefaz.ambiente', 2);
    }

    public function gerarRascunho(GeradorMdfe $gerador): void
    {
        $this->authorize('create', Mdfe::class);

        if ($this->viagem_id === null) {
            session()->flash('erro', 'Selecione uma viagem.');

            return;
        }

        try {
            $mdfe = $gerador->aPartirDaViagem(Viagem::query()->findOrFail($this->viagem_id));
        } catch (RuntimeException $e) {
            session()->flash('erro', $e->getMessage());

            return;
        }

        session()->flash('sucesso', 'Rascunho de MDF-e gerado a partir da viagem.');
        $this->redirect(route('mdfe.editar', $mdfe), navigate: true);
    }

    /** Repuxa os CT-e vinculados à viagem para os documentos do MDF-e (rascunho). */
    public function sincronizarDocumentos(): void
    {
        $this->authorize('update', $this->mdfe);

        if ($this->mdfe->status !== 'rascunho') {
            session()->flash('erro', 'Só rascunho pode ter os documentos sincronizados.');

            return;
        }

        $ctes = $this->mdfe->viagem?->ctes()->get() ?? collect();

        $this->mdfe->documentos()->delete();
        foreach ($ctes as $cte) {
            $this->mdfe->documentos()->create([
                'tipo' => 'cte',
                'chave' => $cte->chave,
                'cte_id' => $cte->id,
                'municipio_descarregamento_id' => $cte->municipio_fim_id,
                'peso' => $cte->peso_bruto,
                'valor' => $cte->valor_total_servico,
            ]);
        }

        $this->mdfe->update([
            'peso_bruto_total' => (float) $ctes->sum('peso_bruto'),
            'valor_carga_total' => (float) $ctes->sum('valor_mercadoria'),
        ]);
        $this->mdfe->refresh();

        session()->flash('sucesso', $ctes->count() . ' CT-e sincronizado(s) no manifesto.');
    }

    public function emitir(EmissorFiscal $emissor): void
    {
        $this->authorize('emitir', $this->mdfe);

        if ($this->mdfe->status !== 'rascunho') {
            session()->flash('erro', 'Só rascunho pode ser emitido.');

            return;
        }

        $r = $emissor->emitirMdfe($this->mdfe);
        $this->mdfe->refresh();

        session()->flash($r->autorizado ? 'sucesso' : 'erro',
            $r->autorizado ? "MDF-e autorizado (cStat {$r->codigo}). Protocolo {$r->protocolo}." : "Rejeitado: {$r->motivo}");
    }

    public function encerrar(EmissorFiscal $emissor): void
    {
        $this->authorize('encerrar', $this->mdfe);

        if (! $this->mdfe->encerravel()) {
            session()->flash('erro', 'Só MDF-e autorizado e não encerrado pode ser encerrado.');

            return;
        }

        if ($this->municipio_encerramento_id === null) {
            session()->flash('erro', 'Informe o município de encerramento.');

            return;
        }

        $r = $emissor->encerrarMdfe($this->mdfe, $this->municipio_encerramento_id);
        $this->mdfe->refresh();

        session()->flash($r->autorizado ? 'sucesso' : 'erro',
            $r->autorizado ? 'MDF-e encerrado (evento 110112 registrado).' : "Falha ao encerrar: {$r->motivo}");
    }

    public function render(): View
    {
        return view('livewire.mdfe.formulario')
            ->layout('layouts.app', ['title' => $this->mdfe?->exists ? 'MDF-e ' . ($this->mdfe->numero ?? 'rascunho') : 'Novo MDF-e']);
    }
}
