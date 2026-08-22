<?php

declare(strict_types=1);

namespace App\Livewire\Ocorrencias;

use App\Models\Municipio;
use App\Models\Ocorrencia;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Rotina 3040 — ficha da ocorrência.
 *
 * Registra o fato, o responsável apurado e o prejuízo. `registrada_por` é o
 * usuário logado; `empresa_id` vem do TenantContext.
 */
class Formulario extends Component
{
    use AuthorizesRequests;

    public ?Ocorrencia $ocorrencia = null;

    public string $tipo = 'avaria';
    public string $data_hora = '';
    public ?int $municipio_id = null;
    public string $descricao = '';
    public string $responsavel = 'indeterminado';
    public string $valor_prejuizo = '';
    public string $tratamento = '';
    public string $status = 'aberta';

    public function mount(?Ocorrencia $ocorrencia = null): void
    {
        if ($ocorrencia?->exists) {
            $this->authorize('update', $ocorrencia);
            $this->ocorrencia = $ocorrencia;
            $this->tipo = (string) $ocorrencia->tipo;
            $this->data_hora = $ocorrencia->data_hora?->format('Y-m-d\TH:i') ?? '';
            $this->municipio_id = $ocorrencia->municipio_id;
            $this->descricao = (string) $ocorrencia->descricao;
            $this->responsavel = (string) $ocorrencia->responsavel;
            $this->valor_prejuizo = $ocorrencia->valor_prejuizo === null ? '' : (string) $ocorrencia->valor_prejuizo;
            $this->tratamento = (string) ($ocorrencia->tratamento ?? '');
            $this->status = (string) $ocorrencia->status;

            return;
        }

        $this->authorize('create', Ocorrencia::class);
    }

    #[Computed]
    public function municipios()
    {
        return Municipio::orderBy('uf')->orderBy('nome')->get(['id', 'nome', 'uf']);
    }

    protected function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(Ocorrencia::TIPOS)],
            'data_hora' => ['required', 'date'],
            'municipio_id' => ['nullable', 'integer', 'exists:municipios,id'],
            'descricao' => ['required', 'string'],
            'responsavel' => ['required', Rule::in(Ocorrencia::RESPONSAVEIS)],
            'valor_prejuizo' => ['nullable', 'numeric', 'min:0'],
            'tratamento' => ['nullable', 'string'],
            'status' => ['required', Rule::in(Ocorrencia::STATUS)],
        ];
    }

    public function salvar()
    {
        $this->validate();

        $dados = [
            'tipo' => $this->tipo,
            'data_hora' => $this->data_hora,
            'municipio_id' => $this->municipio_id,
            'descricao' => trim($this->descricao),
            'responsavel' => $this->responsavel,
            'valor_prejuizo' => $this->valor_prejuizo === '' ? null : (float) $this->valor_prejuizo,
            'tratamento' => trim($this->tratamento) ?: null,
            'status' => $this->status,
        ];

        DB::transaction(function () use ($dados): void {
            if ($this->ocorrencia?->exists) {
                $this->ocorrencia->update($dados);
                $ocorrencia = $this->ocorrencia;
            } else {
                $dados['registrada_por'] = auth()->id();
                $ocorrencia = Ocorrencia::create($dados);
            }

            $this->ocorrencia = $ocorrencia->fresh();
        });

        session()->flash('sucesso', 'Ocorrência salva.');

        return $this->redirect(route('ocorrencias.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.ocorrencias.formulario')
            ->layout('layouts.app', ['title' => $this->ocorrencia?->exists ? 'Ocorrência' : 'Nova ocorrência']);
    }
}
