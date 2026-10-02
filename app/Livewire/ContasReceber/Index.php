<?php

declare(strict_types=1);

namespace App\Livewire\ContasReceber;

use App\Livewire\Concerns\RegistraRecebimento;
use App\Models\TituloReceber;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rotina 5020 — Contas a receber. Parcelas das faturas por vencimento, com as
 * faixas de atraso no topo e o "Receber" na linha (mockup de 02/10/2026).
 */
class Index extends Component
{
    use AuthorizesRequests;
    use RegistraRecebimento;
    use WithPagination;

    public const FAIXAS = ['abertas', 'a_vencer', 'hoje', 'vencidas', 'vencidas_30', 'vencidas_mais', 'recebidas'];

    #[Url(as: 'faixa', except: 'abertas')]
    public string $faixa = 'abertas';

    #[Url(as: 'q', except: '')]
    public string $busca = '';

    #[Url(as: 'mes', except: '')]
    public string $mes = '';

    public function mount(): void
    {
        $this->authorize('viewAny', TituloReceber::class);
        if (! in_array($this->faixa, self::FAIXAS, true)) {
            $this->faixa = 'abertas';
        }
    }

    public function updated(string $campo): void
    {
        if (in_array($campo, ['faixa', 'busca', 'mes'], true)) {
            $this->resetPage();
        }
    }

    private function aplicarFaixa(Builder $q, string $faixa): Builder
    {
        $hoje = Carbon::today();

        return match ($faixa) {
            'a_vencer' => $q->emAberto()->whereDate('vencimento', '>', $hoje),
            'hoje' => $q->emAberto()->whereDate('vencimento', $hoje),
            'vencidas' => $q->vencidos(),
            'vencidas_30' => $q->vencidos()->whereDate('vencimento', '>=', $hoje->copy()->subDays(30)),
            'vencidas_mais' => $q->vencidos()->whereDate('vencimento', '<', $hoje->copy()->subDays(30)),
            'recebidas' => $q->where('status', 'recebido'),
            default => $q->emAberto(),
        };
    }

    /** @return array<string,array{valor:float, qtd:int}> */
    #[Computed]
    public function faixas(): array
    {
        $r = [];
        foreach (['abertas', 'a_vencer', 'hoje', 'vencidas_30', 'vencidas_mais'] as $f) {
            $q = $this->aplicarFaixa(TituloReceber::query(), $f);
            $r[$f] = [
                'valor' => (float) (clone $q)->sum(DB::raw('valor - valor_baixado')),
                'qtd' => (clone $q)->count(),
            ];
        }

        return $r;
    }

    /** @return LengthAwarePaginator<TituloReceber> */
    #[Computed]
    public function titulos(): LengthAwarePaginator
    {
        $q = $this->aplicarFaixa(TituloReceber::query(), $this->faixa)
            ->with(['tomador', 'fatura']);

        if (preg_match('/^\d{4}-\d{2}$/', $this->mes)) {
            $inicio = Carbon::createFromFormat('Y-m', $this->mes)->startOfMonth();
            $q->whereBetween('vencimento', [$inicio->toDateString(), $inicio->copy()->endOfMonth()->toDateString()]);
        }

        if (trim($this->busca) !== '') {
            $t = trim($this->busca);
            $digitos = preg_replace('/\D/', '', $t) ?? '';
            $q->where(function (Builder $s) use ($t, $digitos): void {
                $s->where('numero', 'ilike', "%{$t}%")
                    ->orWhereHas('tomador', function (Builder $p) use ($t, $digitos): void {
                        $p->where('razao_social', 'ilike', "%{$t}%")->orWhere('nome_fantasia', 'ilike', "%{$t}%");
                        if (strlen($digitos) >= 3) {
                            $p->orWhere('documento', 'like', "%{$digitos}%");
                        }
                    });
            });
        }

        return $q->orderBy($this->faixa === 'recebidas' ? 'updated_at' : 'vencimento', $this->faixa === 'recebidas' ? 'desc' : 'asc')
            ->orderBy('id')
            ->paginate(20);
    }

    protected function depoisDoRecebimento(): void
    {
        unset($this->faixas, $this->titulos);
    }

    public function render(): View
    {
        return view('livewire.contas-receber.index')
            ->layout('layouts.app', ['title' => 'Contas a receber']);
    }
}
