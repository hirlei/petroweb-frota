<?php

declare(strict_types=1);

namespace App\Livewire\ValesPedagio;

use App\Models\FornecedorVpo;
use App\Models\ValePedagio;
use App\Models\Veiculo;
use App\Models\Viagem;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Rotina 4030 — lançamento de vale-pedágio, um por veículo da composição.
 *
 * Valida o fornecedor contra o catálogo da ANTT (fornecedores_vpo) antes de
 * gravar — é o que evita a rejeição 733 na transmissão do MDF-e. `tipo` só aceita
 * 01 (TAG) e 04 (leitura de placa).
 */
class Formulario extends Component
{
    public ?ValePedagio $vale = null;

    public ?int $viagem_id = null;
    public ?int $veiculo_id = null;
    public string $papel = 'recebido';
    public ?int $fornecedor_vpo_id = null;
    public string $pagador_documento = '';
    public string $pagador_tipo = 'J';
    public string $idvpo = '';
    public string $valor = '';
    public string $tipo = '01';

    public function mount(?ValePedagio $vale = null): void
    {
        abort_unless(Auth::user()?->can('mdfe.emitir') ?? false, 403);

        if ($vale?->exists) {
            $this->vale = $vale;
            $this->viagem_id = $vale->viagem_id;
            $this->veiculo_id = $vale->veiculo_id;
            $this->papel = (string) $vale->papel;
            $this->fornecedor_vpo_id = $vale->fornecedor_vpo_id;
            $this->pagador_documento = (string) ($vale->pagador_documento ?? '');
            $this->pagador_tipo = (string) ($vale->pagador_tipo ?? 'J');
            $this->idvpo = (string) ($vale->idvpo ?? '');
            $this->valor = $vale->valor === null ? '' : (string) $vale->valor;
            $this->tipo = (string) ($vale->tipo ?? '01');
        }
    }

    #[Computed]
    public function viagens()
    {
        return Viagem::query()->with(['veiculoTracao', 'municipioOrigem', 'municipioDestino'])
            ->orderByDesc('saida_prevista')->get();
    }

    /** Veículos da composição da viagem escolhida (tração + reboques do snapshot). */
    #[Computed]
    public function veiculos()
    {
        if ($this->viagem_id === null) {
            return collect();
        }

        $viagem = Viagem::query()->with('veiculoTracao')->find($this->viagem_id);

        if ($viagem === null) {
            return collect();
        }

        $veiculos = collect([$viagem->veiculoTracao])->filter();
        $placas = collect($viagem->composicao_snapshot ?? [])
            ->map(fn ($p) => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $p)));

        if ($placas->isNotEmpty()) {
            $veiculos = $veiculos->merge(Veiculo::query()->whereIn('placa', $placas)->get());
        }

        return $veiculos->unique('id')->values();
    }

    #[Computed]
    public function fornecedores()
    {
        return FornecedorVpo::query()->where('ativo', true)->orderBy('razao_social')->get();
    }

    protected function rules(): array
    {
        return [
            'viagem_id' => ['required', 'integer', 'exists:viagens,id'],
            'veiculo_id' => ['required', 'integer', 'exists:veiculos,id'],
            'papel' => ['required', Rule::in(ValePedagio::PAPEIS)],
            'fornecedor_vpo_id' => ['required', 'integer', 'exists:fornecedores_vpo,id'],
            'pagador_documento' => ['nullable', 'string', 'max:14'],
            'pagador_tipo' => ['required', Rule::in(['J', 'F'])],
            'idvpo' => ['required', 'string', 'max:20'],
            'valor' => ['nullable', 'numeric', 'min:0'],
            'tipo' => ['required', Rule::in(ValePedagio::TIPOS_VALIDOS)],
        ];
    }

    protected function messages(): array
    {
        return [
            'tipo.in' => 'Tipo inválido: só 01 (TAG) e 04 (leitura de placa) são aceitos — 02 e 03 foram descontinuados.',
            'idvpo.required' => 'O IDVPO (nCompra) é obrigatório.',
        ];
    }

    public function salvar()
    {
        $this->validate();

        $fornecedor = FornecedorVpo::query()->findOrFail($this->fornecedor_vpo_id);

        if (! $fornecedor->ativo) {
            $this->addError('fornecedor_vpo_id', 'Fornecedor não está ativo no catálogo da ANTT (risco de rejeição 733).');

            return null;
        }

        $viagem = Viagem::query()->findOrFail($this->viagem_id);

        $dados = [
            'viagem_id' => $this->viagem_id,
            'filial_id' => $viagem->filial_id,
            'veiculo_id' => $this->veiculo_id,
            'papel' => $this->papel,
            'fornecedor_vpo_id' => $fornecedor->id,
            'cnpj_forn' => $fornecedor->cnpj,
            'pagador_documento' => $this->pagador_documento !== '' ? $this->pagador_documento : null,
            'pagador_tipo' => $this->pagador_tipo,
            'idvpo' => trim($this->idvpo),
            'valor' => $this->valor !== '' ? (float) $this->valor : null,
            'tipo' => $this->tipo,
        ];

        if ($this->vale?->exists) {
            $this->vale->update($dados);
        } else {
            ValePedagio::create($dados);
        }

        session()->flash('sucesso', 'Vale-pedágio salvo.');

        return $this->redirect(route('vale-pedagio.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.vales-pedagio.formulario')
            ->layout('layouts.app', ['title' => $this->vale?->exists ? 'Vale-pedágio' : 'Novo vale-pedágio']);
    }
}
