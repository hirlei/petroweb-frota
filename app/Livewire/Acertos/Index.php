<?php

declare(strict_types=1);

namespace App\Livewire\Acertos;

use App\Models\AcertoViagem;
use App\Models\Adiantamento;
use App\Models\Viagem;
use App\Services\Operacao\Acertos;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rotina 3070 — Acerto de viagem (mockup aprovado em 03/10/2026). Viagens
 * entregues com motorista CLT, a acertar ou já fechadas.
 */
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'situacao', except: 'a_acertar')]
    public string $situacao = 'a_acertar';

    #[Url(as: 'q', except: '')]
    public string $busca = '';

    public function mount(): void
    {
        $this->authorize('viewAny', AcertoViagem::class);
    }

    public function updated(string $campo): void
    {
        if (in_array($campo, ['situacao', 'busca'], true)) {
            $this->resetPage();
        }
    }

    private function base(): Builder
    {
        return Acertos::acertaveis(Viagem::query());
    }

    private function semAcerto(Builder $q): Builder
    {
        return $q->whereDoesntHave('acerto');
    }

    /** @return array<string,mixed> */
    #[Computed]
    public function resumo(): array
    {
        $servico = app(Acertos::class);
        $pendentes = $this->semAcerto($this->base())->with('motorista')->limit(200)->get();
        $paga = 0.0;
        $pagaQtd = 0;
        $devolve = 0.0;
        $devolveQtd = 0;
        foreach ($pendentes as $v) {
            $s = $servico->resumo($v)['saldo'];
            if ($s > 0) {
                $paga += $s;
                $pagaQtd++;
            } elseif ($s < 0) {
                $devolve += -$s;
                $devolveQtd++;
            }
        }

        return [
            'a_acertar' => $this->semAcerto($this->base())->count(),
            'fechados' => $this->base()->whereHas('acerto')->count(),
            'adiantado' => (float) Adiantamento::query()->whereIn('viagem_id', $this->semAcerto(Viagem::query()->where('status', '!=', 'cancelada'))->select('id'))->sum('valor'),
            'paga' => round($paga, 2), 'paga_qtd' => $pagaQtd,
            'devolve' => round($devolve, 2), 'devolve_qtd' => $devolveQtd,
        ];
    }

    /** @return LengthAwarePaginator<Viagem> */
    #[Computed]
    public function viagens(): LengthAwarePaginator
    {
        $q = $this->base()->with(['motorista.pessoa', 'veiculoTracao', 'municipioOrigem', 'municipioDestino', 'acerto']);

        match ($this->situacao) {
            'fechados' => $q->whereHas('acerto'),
            'todos' => null,
            default => $this->semAcerto($q),
        };

        if (trim($this->busca) !== '') {
            $t = trim($this->busca);
            $placa = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $t) ?? '');
            $q->where(function (Builder $s) use ($t, $placa): void {
                $s->where('numero', 'ilike', "%{$t}%")
                    ->orWhereHas('motorista.pessoa', fn (Builder $p) => $p->where('razao_social', 'ilike', "%{$t}%"))
                    ->when($placa !== '', fn (Builder $s) => $s->orWhereHas('veiculoTracao', fn (Builder $v) => $v->where('placa', 'like', "%{$placa}%")));
            });
        }

        return $q->orderByDesc('chegada_real')->orderByDesc('id')->paginate(15);
    }

    /**
     * Resumo de cada viagem SEM acerto da página — calculado uma vez por render.
     *
     * @return array<int, array<string,mixed>>
     */
    #[Computed]
    public function linhas(): array
    {
        $servico = app(Acertos::class);

        return $this->viagens->getCollection()->filter(fn (Viagem $v) => $v->acerto === null)
            ->mapWithKeys(fn (Viagem $v) => [$v->id => $servico->resumo($v)])->all();
    }

    public function render(): View
    {
        return view('livewire.acertos.index')->layout('layouts.app', ['title' => 'Acerto de viagem']);
    }
}
