<?php

declare(strict_types=1);

namespace App\Livewire\Faturas;

use App\Domain\Financeiro\Parcelamento;
use App\Models\Cte;
use App\Models\Fatura;
use App\Models\Pessoa;
use App\Services\Financeiro\Faturamento;
use App\Services\Financeiro\FaturamentoException;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Rotina 5010 — Nova fatura, em três passos (mockup aprovado em 02/10/2026):
 *  1. o cliente (tomador dos CT-e);
 *  2. os CT-e autorizados dele que ainda não estão em fatura;
 *  3. resumo, desconto/acréscimo e condição de pagamento, com as parcelas.
 *
 * Quem grava é o App\Services\Financeiro\Faturamento.
 */
class Formulario extends Component
{
    use AuthorizesRequests;

    #[Url(as: 'cliente', except: null)]
    public ?int $tomador_id = null;

    /** @var list<int> */
    public array $selecionados = [];

    public string $busca = '';

    public string $de = '';

    public string $ate = '';

    public string $desconto = '0';

    public string $acrescimo = '0';

    public string $condicao = '';

    public string $observacoes = '';

    public function mount(): void
    {
        $this->authorize('create', Fatura::class);

        if ($this->tomador_id !== null) {
            $this->escolherTomador($this->tomador_id);
        }
    }

    /** Clientes com CT-e a faturar, com a quantidade e o valor pendentes. */
    #[Computed]
    public function tomadores(): Collection
    {
        $pendentes = Cte::query()->faturaveis()->whereNotNull('tomador_id')
            ->selectRaw('tomador_id, COUNT(*) AS qtd, SUM(valor_total_servico) AS valor')
            ->groupBy('tomador_id')->get()->keyBy('tomador_id');

        return Pessoa::query()->whereIn('id', $pendentes->keys())->orderBy('razao_social')->get()
            ->map(fn (Pessoa $p) => [
                'id' => $p->id,
                'nome' => $p->razao_social,
                'documento' => $p->documentoFormatado(),
                'qtd' => (int) $pendentes[$p->id]->qtd,
                'valor' => (float) $pendentes[$p->id]->valor,
            ]);
    }

    #[Computed]
    public function tomador(): ?Pessoa
    {
        return $this->tomador_id ? Pessoa::query()->find($this->tomador_id) : null;
    }

    public function escolherTomador(?int $id): void
    {
        $this->tomador_id = $id;
        $this->selecionados = [];
        unset($this->tomador, $this->ctes);

        $prazo = $this->tomador?->prazo_faturamento;
        $this->condicao = $prazo !== null && trim($prazo) !== '' ? trim($prazo) : (string) config('financeiro.condicao_padrao', '30');

        // Já chega com todos os CT-e do cliente marcados — é o caso mais comum.
        $this->selecionados = $this->ctes->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function trocarTomador(): void
    {
        $this->tomador_id = null;
        $this->selecionados = [];
        unset($this->tomador, $this->ctes);
    }

    /** CT-e faturáveis do cliente escolhido, com filtro de período e busca. */
    #[Computed]
    public function ctes(): Collection
    {
        if ($this->tomador_id === null) {
            return collect();
        }

        return Cte::query()->faturaveis()
            ->where('tomador_id', $this->tomador_id)
            ->with(['municipioInicio', 'municipioFim', 'viagens'])
            ->when($this->de !== '', fn (Builder $q) => $q->whereDate('emissao', '>=', $this->de))
            ->when($this->ate !== '', fn (Builder $q) => $q->whereDate('emissao', '<=', $this->ate))
            ->when(trim($this->busca) !== '', function (Builder $q): void {
                $t = trim($this->busca);
                $q->where(function (Builder $s) use ($t): void {
                    if (ctype_digit(str_replace('.', '', $t))) {
                        $s->orWhere('numero', (int) str_replace('.', '', $t));
                    }
                    $s->orWhereHas('municipioInicio', fn (Builder $m) => $m->where('nome', 'ilike', "%{$t}%"))
                        ->orWhereHas('municipioFim', fn (Builder $m) => $m->where('nome', 'ilike', "%{$t}%"))
                        ->orWhereHas('viagens', fn (Builder $v) => $v->where('numero', 'ilike', "%{$t}%"));
                });
            })
            ->orderBy('emissao')->orderBy('numero')
            ->get();
    }

    public function marcarTodos(): void
    {
        $ids = $this->ctes->pluck('id')->map(fn ($id) => (int) $id)->all();
        $todos = array_diff($ids, $this->selecionados) === [];
        $this->selecionados = $todos
            ? array_values(array_diff($this->selecionados, $ids))
            : array_values(array_unique(array_merge($this->selecionados, $ids)));
    }

    public function usarCondicao(string $condicao): void
    {
        $this->condicao = $condicao;
    }

    /** @return array{qtd:int, valor:float, total:float, parcelas:list<array<string,mixed>>, erro:?string} */
    #[Computed]
    public function resumo(): array
    {
        $ids = array_map('intval', $this->selecionados);
        $marcados = Cte::query()->faturaveis()->where('tomador_id', $this->tomador_id)->whereIn('id', $ids)->get();
        $valor = round((float) $marcados->sum(fn (Cte $c) => (float) $c->valor_total_servico), 2);
        $total = round($valor - (float) ($this->desconto ?: 0) + (float) ($this->acrescimo ?: 0), 2);

        $parcelas = [];
        $erro = null;
        if ($total > 0) {
            try {
                $parcelas = Parcelamento::dividir($total, Parcelamento::prazos($this->condicao), new DateTimeImmutable('today'));
            } catch (\InvalidArgumentException $e) {
                $erro = $e->getMessage();
            }
        }

        return ['qtd' => $marcados->count(), 'valor' => $valor, 'total' => $total, 'parcelas' => $parcelas, 'erro' => $erro];
    }

    public function gerar(Faturamento $faturamento)
    {
        $this->authorize('create', Fatura::class);
        $this->validate([
            'tomador_id' => ['required', 'integer'],
            'desconto' => ['nullable', 'numeric', 'min:0'],
            'acrescimo' => ['nullable', 'numeric', 'min:0'],
            'condicao' => ['required', 'string', 'max:40'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ], ['tomador_id.required' => 'Escolha o cliente.']);

        $tomador = $this->tomador;
        if ($tomador === null) {
            $this->addError('tomador_id', 'Escolha o cliente.');

            return null;
        }

        try {
            $fatura = $faturamento->gerar(
                $tomador,
                array_map('intval', $this->selecionados),
                $this->condicao,
                (float) ($this->desconto ?: 0),
                (float) ($this->acrescimo ?: 0),
                $this->observacoes,
            );
        } catch (FaturamentoException $e) {
            $this->addError('gerar', $e->getMessage());

            return null;
        }

        session()->flash('sucesso', "Fatura {$fatura->numero} gerada com {$fatura->titulos->count()} " . ($fatura->titulos->count() === 1 ? 'parcela' : 'parcelas') . '.');

        return $this->redirect(route('faturas.ver', $fatura), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.faturas.formulario')
            ->layout('layouts.app', ['title' => 'Nova fatura']);
    }
}
