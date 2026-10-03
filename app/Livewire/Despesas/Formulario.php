<?php

declare(strict_types=1);

namespace App\Livewire\Despesas;

use App\Models\Despesa;
use App\Models\Motorista;
use App\Models\Viagem;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Rotina 3060 — ficha da despesa de viagem.
 *
 * Amarrada a uma viagem e a um motorista. Ao salvar aprovada, entra no custo da
 * viagem (no componente do tipo) e a margem é recalculada. O `empresa_id` vem
 * do TenantContext.
 */
class Formulario extends Component
{
    use AuthorizesRequests;

    public ?Despesa $despesa = null;

    public ?int $viagem_id = null;
    public ?int $motorista_id = null;
    public string $tipo = 'pedagio';
    public string $data = '';
    public string $valor = '';
    public string $forma_pagamento = 'adiantamento';
    public string $origem = 'manual';
    public string $descricao = '';
    public bool $aprovada = false;

    public function mount(?Despesa $despesa = null): void
    {
        if ($despesa?->exists) {
            $this->authorize('update', $despesa);
            $this->despesa = $despesa;
            $this->preencherDe($despesa);

            return;
        }

        $this->authorize('create', Despesa::class);
        $this->data = now()->format('Y-m-d');
    }

    private function preencherDe(Despesa $despesa): void
    {
        $this->viagem_id = $despesa->viagem_id;
        $this->motorista_id = $despesa->motorista_id;
        $this->tipo = (string) $despesa->tipo;
        $this->data = $despesa->data?->format('Y-m-d') ?? '';
        $this->valor = (string) $despesa->valor;
        $this->forma_pagamento = (string) $despesa->forma_pagamento;
        $this->origem = (string) $despesa->origem;
        $this->descricao = (string) ($despesa->descricao ?? '');
        $this->aprovada = (bool) $despesa->aprovada;
    }

    /** Ao escolher a viagem, sugere o motorista dela. */
    public function updatedViagemId(mixed $valor): void
    {
        if (! $valor) {
            return;
        }

        $viagem = Viagem::query()->find($valor);
        $this->motorista_id ??= $viagem?->motorista_id;
    }

    #[Computed]
    public function viagens()
    {
        return Viagem::query()->with(['veiculoTracao', 'municipioOrigem', 'municipioDestino'])
            ->orderByDesc('saida_prevista')->orderByDesc('id')->get();
    }

    #[Computed]
    public function motoristas()
    {
        return Motorista::query()->where('status', 'ativo')->with('pessoa')->get()
            ->sortBy(fn (Motorista $m) => $m->pessoa?->razao_social)->values();
    }

    /** Prévia do impacto no custo da viagem escolhida. */
    #[Computed]
    public function impacto(): ?array
    {
        if ($this->viagem_id === null) {
            return null;
        }

        $viagem = Viagem::query()->find($this->viagem_id);

        if ($viagem === null) {
            return null;
        }

        $componente = Despesa::COMPONENTE_CUSTO[$this->tipo] ?? 'custo_outros';

        return [
            'componente'   => $componente,
            'atual'        => (float) $viagem->{$componente},
            'valor'        => (float) ($this->valor !== '' ? $this->valor : 0),
            'margem_atual' => (float) $viagem->margem,
        ];
    }

    protected function rules(): array
    {
        return [
            'viagem_id' => ['required', 'integer', 'exists:viagens,id'],
            'motorista_id' => ['nullable', 'integer', 'exists:motoristas,id'],
            'tipo' => ['required', Rule::in(Despesa::TIPOS)],
            'data' => ['required', 'date'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'forma_pagamento' => ['required', Rule::in(Despesa::FORMAS_PAGAMENTO)],
            'origem' => ['required', Rule::in(Despesa::ORIGENS)],
            'descricao' => ['nullable', 'string', 'max:200'],
        ];
    }

    protected function messages(): array
    {
        return [
            'viagem_id.required' => 'Selecione a viagem.',
            'valor.min' => 'Informe um valor maior que zero.',
        ];
    }

    public function salvar()
    {
        $this->validate();

        // Acerto fechado (3070): as despesas da viagem não mudam mais.
        $travada = \App\Models\AcertoViagem::query()->where('status', 'fechado')
            ->whereIn('viagem_id', array_filter([$this->viagem_id, $this->despesa?->viagem_id]))->exists();
        if ($travada) {
            $this->addError('viagem_id', 'O acerto desta viagem está fechado (3070). Reabra o acerto para mudar despesas.');

            return null;
        }

        $dados = [
            'viagem_id' => $this->viagem_id,
            'motorista_id' => $this->motorista_id,
            'tipo' => $this->tipo,
            'data' => $this->data,
            'valor' => (float) $this->valor,
            'forma_pagamento' => $this->forma_pagamento,
            'origem' => $this->origem,
            'descricao' => trim($this->descricao) !== '' ? trim($this->descricao) : null,
            'aprovada' => $this->aprovada,
            'glosada' => $this->aprovada ? false : (bool) ($this->despesa?->glosada ?? false),
            'motivo_glosa' => $this->aprovada ? null : ($this->despesa?->motivo_glosa ?? null),
            'aprovada_por' => $this->aprovada ? ($this->despesa?->aprovada_por ?? Auth::id()) : null,
            'aprovada_em' => $this->aprovada ? ($this->despesa?->aprovada_em ?? now()) : null,
        ];

        $viagem = DB::transaction(function () use ($dados): Viagem {
            $despesa = $this->despesa?->exists
                ? tap($this->despesa)->update($dados)
                : Despesa::create($dados);

            $this->despesa = $despesa;

            return $despesa->viagem;
        });

        // Recalcula fora da transação de escrita da despesa (lê o conjunto atualizado).
        $viagem?->recalcularCustosDeDespesas();

        session()->flash('sucesso', 'Despesa salva.');

        return $this->redirect(route('despesas.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.despesas.formulario')
            ->layout('layouts.app', ['title' => $this->despesa?->exists ? 'Despesa de viagem' : 'Nova despesa']);
    }
}
