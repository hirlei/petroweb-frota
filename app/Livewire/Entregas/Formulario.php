<?php

declare(strict_types=1);

namespace App\Livewire\Entregas;

use App\Models\Entrega;
use App\Models\OrdemColeta;
use App\Models\Viagem;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Rotina 3050 — ficha da entrega (POD).
 *
 * Quem recebeu, quando e o comprovante. `cte_id`/`evento_cte_id` ficam
 * reservados até o módulo fiscal (4010); por ora amarra viagem e ordem de
 * coleta. O `empresa_id` vem do TenantContext.
 */
class Formulario extends Component
{
    use AuthorizesRequests;

    public ?Entrega $entrega = null;

    public ?int $viagem_id = null;
    public ?int $ordem_coleta_id = null;
    public string $recebedor_nome = '';
    public string $recebedor_documento = '';
    public string $data_hora = '';
    public string $tipo_comprovacao = 'foto';
    public string $latitude = '';
    public string $longitude = '';
    public string $observacoes = '';

    public function mount(?Entrega $entrega = null): void
    {
        if ($entrega?->exists) {
            $this->authorize('update', $entrega);
            $this->entrega = $entrega;
            $this->preencherDe($entrega);

            return;
        }

        $this->authorize('create', Entrega::class);
        $this->data_hora = now()->format('Y-m-d\TH:i');
    }

    private function preencherDe(Entrega $entrega): void
    {
        $this->viagem_id = $entrega->viagem_id;
        $this->ordem_coleta_id = $entrega->ordem_coleta_id;
        $this->recebedor_nome = (string) ($entrega->recebedor_nome ?? '');
        $this->recebedor_documento = (string) ($entrega->recebedor_documento ?? '');
        $this->data_hora = $entrega->data_hora?->format('Y-m-d\TH:i') ?? '';
        $this->tipo_comprovacao = (string) $entrega->tipo_comprovacao;
        $this->latitude = $entrega->latitude === null ? '' : (string) $entrega->latitude;
        $this->longitude = $entrega->longitude === null ? '' : (string) $entrega->longitude;
        $this->observacoes = (string) ($entrega->observacoes ?? '');
    }

    #[Computed]
    public function viagens()
    {
        return Viagem::query()->with(['veiculoTracao', 'municipioOrigem', 'municipioDestino'])
            ->orderByDesc('saida_prevista')->orderByDesc('id')->get();
    }

    #[Computed]
    public function ordens()
    {
        return OrdemColeta::query()->with(['cliente', 'municipioFim'])
            ->orderByDesc('data')->orderByDesc('id')->get();
    }

    protected function rules(): array
    {
        return [
            'viagem_id' => ['nullable', 'integer', 'exists:viagens,id'],
            'ordem_coleta_id' => ['nullable', 'integer', 'exists:ordens_coleta,id'],
            'recebedor_nome' => ['nullable', 'string', 'max:150'],
            'recebedor_documento' => ['nullable', 'string', 'max:20'],
            'data_hora' => ['required', 'date'],
            'tipo_comprovacao' => ['required', Rule::in(Entrega::TIPOS_COMPROVACAO)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'observacoes' => ['nullable', 'string'],
        ];
    }

    protected function messages(): array
    {
        return [
            'data_hora.required' => 'Informe a data e hora da entrega.',
        ];
    }

    public function salvar()
    {
        $this->validate();

        if ($this->viagem_id === null && $this->ordem_coleta_id === null) {
            $this->addError('viagem_id', 'Vincule a entrega a uma viagem ou a uma ordem de coleta.');

            return null;
        }

        $dados = [
            'viagem_id' => $this->viagem_id,
            'ordem_coleta_id' => $this->ordem_coleta_id,
            'recebedor_nome' => $this->nulo($this->recebedor_nome),
            'recebedor_documento' => $this->nulo($this->recebedor_documento),
            'data_hora' => $this->data_hora,
            'tipo_comprovacao' => $this->tipo_comprovacao,
            'latitude' => $this->nuloNum($this->latitude),
            'longitude' => $this->nuloNum($this->longitude),
            'observacoes' => $this->nulo($this->observacoes),
        ];

        if ($this->entrega?->exists) {
            $this->entrega->update($dados);
        } else {
            $dados['registrada_por'] = Auth::id();
            $this->entrega = Entrega::create($dados);
        }

        session()->flash('sucesso', 'Entrega registrada.');

        return $this->redirect(route('entregas.index'), navigate: true);
    }

    private function nulo(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    private function nuloNum(string $valor): ?float
    {
        return trim($valor) === '' ? null : (float) $valor;
    }

    public function render(): View
    {
        return view('livewire.entregas.formulario')
            ->layout('layouts.app', ['title' => $this->entrega?->exists ? 'Entrega' : 'Registrar entrega']);
    }
}
