<?php

declare(strict_types=1);

namespace App\Livewire\ContasPagar;

use App\Domain\Financeiro\Parcelamento;
use App\Models\ContaPagar;
use App\Services\Financeiro\ContasPagar;
use App\Services\Financeiro\ContasPagarException;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Rotina 5030 — Lançamentos esperando: abastecimentos com posto, OS de oficina
 * externa e fretes de TAC do CIOT. Tudo vem marcado; quem confere desmarca.
 */
class Lancar extends Component
{
    use AuthorizesRequests;

    /** @var list<int> */
    public array $abastecimentos = [];

    /** @var list<int> */
    public array $ordens = [];

    /** @var list<int> */
    public array $ciots = [];

    public function mount(): void
    {
        $this->authorize('create', ContaPagar::class);
        $s = $this->sugestoes;
        $this->abastecimentos = $s['abastecimentos']->flatMap(fn ($g) => $g['itens']->pluck('id'))->map(fn ($id) => (string) $id)->values()->all();
        $this->ordens = $s['ordens']->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->ciots = $s['ciots']->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    /** @return array<string,mixed> */
    #[Computed]
    public function sugestoes(): array
    {
        return app(ContasPagar::class)->sugestoes();
    }

    /** Vencimento da 1ª parcela pela condição (só para mostrar). */
    public function vencimentoDe(string $condicao): string
    {
        try {
            $prazos = Parcelamento::prazos($condicao);
        } catch (\InvalidArgumentException) {
            return '—';
        }
        $p = Parcelamento::dividir(1.0, $prazos, new DateTimeImmutable('today'));

        return $p[0]['vencimento']->format('d/m') . (count($p) > 1 ? ' (+' . (count($p) - 1) . ')' : '');
    }

    /** @return array{abast: float, os: float, ciot: float, total: float, contas: int} */
    #[Computed]
    public function resumo(): array
    {
        $s = $this->sugestoes;
        $abastSel = array_map('intval', $this->abastecimentos);
        $osSel = array_map('intval', $this->ordens);
        $ciotSel = array_map('intval', $this->ciots);

        $abast = 0.0;
        $contas = 0;
        foreach ($s['abastecimentos'] as $g) {
            $marcados = $g['itens']->filter(fn ($a) => in_array($a->id, $abastSel, true));
            if ($marcados->isNotEmpty()) {
                $contas++;
                $abast += (float) $marcados->sum(fn ($a) => (float) $a->valor_total);
            }
        }
        $os = (float) $s['ordens']->filter(fn ($o) => in_array($o->id, $osSel, true))->sum(fn ($o) => (float) $o->valor_total);
        $ciot = (float) $s['ciots']->filter(fn ($c) => in_array($c->id, $ciotSel, true))->sum(fn ($c) => (float) $c->valor_frete);
        $contas += count(array_intersect($osSel, $s['ordens']->pluck('id')->all())) + count(array_intersect($ciotSel, $s['ciots']->pluck('id')->all()));

        return ['abast' => round($abast, 2), 'os' => round($os, 2), 'ciot' => round($ciot, 2), 'total' => round($abast + $os + $ciot, 2), 'contas' => $contas];
    }

    /** Marca/desmarca todos os abastecimentos de um posto. */
    public function alternarPosto(int $postoId): void
    {
        $grupo = $this->sugestoes['abastecimentos']->first(fn ($g) => $g['favorecido']->id === $postoId);
        if ($grupo === null) {
            return;
        }
        $ids = $grupo['itens']->pluck('id')->map(fn ($id) => (string) $id)->all();
        $todos = array_diff($ids, $this->abastecimentos) === [];
        $this->abastecimentos = $todos
            ? array_values(array_diff($this->abastecimentos, $ids))
            : array_values(array_unique(array_merge($this->abastecimentos, $ids)));
    }

    public function lancar(ContasPagar $servico)
    {
        $this->authorize('create', ContaPagar::class);

        try {
            $n = $servico->lancarSugestoes([
                'abastecimento' => array_map('intval', $this->abastecimentos),
                'ordem_servico' => array_map('intval', $this->ordens),
                'ciot' => array_map('intval', $this->ciots),
            ]);
        } catch (ContasPagarException $e) {
            $this->addError('lancar', $e->getMessage());
            unset($this->sugestoes, $this->resumo);
            // Tira da seleção o que já não está mais esperando.
            $s = $this->sugestoes;
            $validos = fn ($ids) => array_map(fn ($id) => (string) $id, $ids);
            $this->abastecimentos = array_values(array_intersect($this->abastecimentos, $validos($s['abastecimentos']->flatMap(fn ($g) => $g['itens']->pluck('id'))->all())));
            $this->ordens = array_values(array_intersect($this->ordens, $validos($s['ordens']->pluck('id')->all())));
            $this->ciots = array_values(array_intersect($this->ciots, $validos($s['ciots']->pluck('id')->all())));

            return null;
        }

        session()->flash('sucesso', $n === 1 ? '1 conta lançada.' : "{$n} contas lançadas.");

        return $this->redirect(route('contas-pagar.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.contas-pagar.lancar')->layout('layouts.app', ['title' => 'Lançamentos esperando']);
    }
}
