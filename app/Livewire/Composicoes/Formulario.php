<?php

declare(strict_types=1);

namespace App\Livewire\Composicoes;

use App\Domain\Fiscal\Enums\CategoriaCombinacaoVeicular;
use App\Models\Composicao;
use App\Models\ComposicaoItem;
use App\Models\Veiculo;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Rotina 2020 — montagem da combinação veicular.
 *
 * Cavalo + reboques NA ORDEM em que rodam. Eixos totais, PBTC, capacidade,
 * categoria (categCombVeic) e AET são CALCULADOS a partir das unidades — o
 * operador troca um reboque e tudo se recompõe. Nada disso é digitado.
 */
class Formulario extends Component
{
    use AuthorizesRequests;

    public ?Composicao $composicao = null;

    public string $descricao = '';
    public ?int $veiculo_tracao_id = null;
    public bool $ativa = true;

    /** Reboques na ordem em que rodam. @var list<int> */
    public array $reboques = [];

    public ?int $reboqueParaAdicionar = null;

    public function mount(?Composicao $composicao = null): void
    {
        if ($composicao?->exists) {
            $this->authorize('update', $composicao);
            $this->composicao = $composicao->load('veiculos');
            $this->descricao = (string) $composicao->descricao;
            $this->veiculo_tracao_id = $composicao->veiculo_tracao_id;
            $this->ativa = (bool) $composicao->ativa;

            // Itens em ordem, menos a tração (que é o primeiro).
            $this->reboques = $composicao->veiculos
                ->where('id', '!=', $composicao->veiculo_tracao_id)
                ->pluck('id')
                ->values()
                ->all();

            return;
        }

        $this->authorize('create', Composicao::class);
    }

    /* ── Montagem ──────────────────────────────────────────── */

    public function adicionarReboque(): void
    {
        if ($this->reboqueParaAdicionar === null) {
            return;
        }

        if (! in_array($this->reboqueParaAdicionar, $this->reboques, true)
            && $this->reboqueParaAdicionar !== $this->veiculo_tracao_id) {
            $this->reboques[] = $this->reboqueParaAdicionar;
        }

        $this->reboqueParaAdicionar = null;
    }

    public function removerReboque(int $i): void
    {
        unset($this->reboques[$i]);
        $this->reboques = array_values($this->reboques);
    }

    public function moverCima(int $i): void
    {
        if ($i <= 0) {
            return;
        }

        [$this->reboques[$i - 1], $this->reboques[$i]] = [$this->reboques[$i], $this->reboques[$i - 1]];
        $this->reboques = array_values($this->reboques);
    }

    public function moverBaixo(int $i): void
    {
        if ($i >= count($this->reboques) - 1) {
            return;
        }

        [$this->reboques[$i + 1], $this->reboques[$i]] = [$this->reboques[$i], $this->reboques[$i + 1]];
        $this->reboques = array_values($this->reboques);
    }

    /* ── Dropdowns e cálculos ──────────────────────────────── */

    #[Computed]
    public function tracoes()
    {
        return Veiculo::query()->tracao()->ativos()->orderBy('placa')
            ->get(['id', 'placa', 'eixos', 'modelo']);
    }

    #[Computed]
    public function reboquesDisponiveis()
    {
        return Veiculo::query()
            ->whereIn('tipo', ['reboque', 'semirreboque', 'dolly'])
            ->ativos()
            ->whereNotIn('id', $this->reboques)
            ->orderBy('placa')
            ->get(['id', 'placa', 'eixos', 'tipo', 'modelo']);
    }

    /** Unidades montadas, na ordem: tração primeiro, depois reboques. */
    #[Computed]
    public function unidades()
    {
        $ids = array_filter([$this->veiculo_tracao_id, ...$this->reboques]);

        if ($ids === []) {
            return collect();
        }

        $veiculos = Veiculo::query()->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $veiculos->get($id))->filter()->values();
    }

    #[Computed]
    public function eixosTotal(): int
    {
        return (int) $this->unidades->sum('eixos');
    }

    #[Computed]
    public function taraTotal(): float
    {
        return (float) $this->unidades->sum(fn (Veiculo $v) => (float) $v->tara_kg);
    }

    #[Computed]
    public function pbtcTotal(): float
    {
        // O PBTC da combinação é o da tração (limite do conjunto), quando houver.
        $tracao = $this->unidades->firstWhere('id', $this->veiculo_tracao_id);

        return (float) ($tracao?->pbtc_kg ?? 0);
    }

    #[Computed]
    public function capacidadeTotal(): float
    {
        return (float) $this->unidades->sum(fn (Veiculo $v) => (float) ($v->capacidade_kg ?? 0));
    }

    #[Computed]
    public function categoria(): CategoriaCombinacaoVeicular
    {
        return CategoriaCombinacaoVeicular::paraEixos(max($this->eixosTotal, 2));
    }

    #[Computed]
    public function precisaAet(): bool
    {
        // Acima de 57 t ou com mais de duas unidades acopladas (art. 17 CONTRAN).
        return $this->pbtcTotal > 57_000 || count($this->reboques) >= 2;
    }

    /* ── Persistência ──────────────────────────────────────── */

    protected function rules(): array
    {
        return [
            'descricao' => ['required', 'string', 'max:120'],
            'veiculo_tracao_id' => [
                'required', 'integer',
                Rule::exists('veiculos', 'id')->where(fn ($q) => $q->where('tipo', 'tracao')),
            ],
            'reboques' => ['array', 'min:1'],
            'reboques.*' => ['integer', 'exists:veiculos,id'],
        ];
    }

    protected function messages(): array
    {
        return [
            'veiculo_tracao_id.required' => 'Escolha o veículo de tração (cavalo).',
            'reboques.min' => 'Uma combinação tem ao menos uma unidade rebocada.',
        ];
    }

    public function salvar()
    {
        $this->validate();

        $dados = [
            'descricao' => trim($this->descricao),
            'veiculo_tracao_id' => $this->veiculo_tracao_id,
            'eixos_total' => $this->eixosTotal,
            'tara_total_kg' => $this->taraTotal,
            'pbtc_kg' => $this->pbtcTotal ?: null,
            'capacidade_kg' => $this->capacidadeTotal ?: null,
            'categ_comb_veic' => $this->categoria->value,
            'exige_aet' => $this->precisaAet,
            'ativa' => $this->ativa,
        ];

        DB::transaction(function () use ($dados): void {
            $composicao = $this->composicao?->exists
                ? tap($this->composicao)->update($dados)
                : Composicao::create($dados);

            // Itens na ordem: tração (1), reboques (2..n).
            ComposicaoItem::where('composicao_id', $composicao->id)->delete();

            $ordem = 1;
            foreach ([$this->veiculo_tracao_id, ...$this->reboques] as $veiculoId) {
                ComposicaoItem::create([
                    'composicao_id' => $composicao->id,
                    'veiculo_id' => $veiculoId,
                    'ordem' => $ordem++,
                ]);
            }

            $this->composicao = $composicao->fresh('veiculos');
        });

        session()->flash('sucesso', 'Composição salva.');

        return $this->redirect(route('composicoes.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.composicoes.formulario')
            ->layout('layouts.app', ['title' => $this->composicao?->descricao ?? 'Nova composição']);
    }
}
