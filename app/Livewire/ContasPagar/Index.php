<?php

declare(strict_types=1);

namespace App\Livewire\ContasPagar;

use App\Models\ContaPagar;
use App\Models\PagamentoConta;
use App\Services\Financeiro\ContasPagar;
use App\Services\Financeiro\ContasPagarException;
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
 * Rotina 5030 — Contas a pagar (mockup aprovado em 03/10/2026): faixas por
 * vencimento, aviso dos lançamentos esperando, pagar, estornar e cancelar.
 */
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public const FAIXAS = ['abertas', 'a_vencer', 'hoje', 'vencidas', 'pagas'];

    #[Url(as: 'faixa', except: 'abertas')]
    public string $faixa = 'abertas';

    #[Url(as: 'categoria', except: '')]
    public string $categoria = '';

    #[Url(as: 'q', except: '')]
    public string $busca = '';

    // Pagar
    public ?int $pagContaId = null;
    public string $pagData = '';
    public string $pagForma = 'pix';
    public string $pagConta = '';
    public string $pagPrincipal = '';
    public string $pagJuros = '0';
    public string $pagDesconto = '0';
    public string $pagObs = '';

    // Estornar (último pagamento válido da conta) e cancelar
    public ?int $estornoId = null;
    public string $estornoMotivo = '';
    public ?int $cancelarId = null;
    public string $cancelarMotivo = '';

    public function mount(): void
    {
        $this->authorize('viewAny', ContaPagar::class);
        if (! in_array($this->faixa, self::FAIXAS, true)) {
            $this->faixa = 'abertas';
        }
    }

    public function updated(string $campo): void
    {
        if (in_array($campo, ['faixa', 'categoria', 'busca'], true)) {
            $this->resetPage();
        }
    }

    private function aplicarFaixa(Builder $q, string $faixa): Builder
    {
        $hoje = Carbon::today();

        return match ($faixa) {
            'a_vencer' => $q->emAberto()->whereDate('vencimento', '>', $hoje),
            'hoje' => $q->emAberto()->whereDate('vencimento', $hoje),
            'vencidas' => $q->vencidas(),
            'pagas' => $q->where('status', 'pago'),
            default => $q->emAberto(),
        };
    }

    /** @return array<string,array{valor:float, qtd:int}> */
    #[Computed]
    public function faixas(): array
    {
        $r = [];
        foreach (['abertas', 'a_vencer', 'hoje', 'vencidas'] as $f) {
            $q = $this->aplicarFaixa(ContaPagar::query(), $f);
            $r[$f] = ['valor' => (float) (clone $q)->sum(DB::raw('valor - valor_baixado')), 'qtd' => (clone $q)->count()];
        }

        $mes = Carbon::today()->startOfMonth()->toDateString();
        $pagos = PagamentoConta::query()->validos()->where('data', '>=', $mes);
        $r['pagas'] = ['valor' => (float) (clone $pagos)->sum('valor_total'), 'qtd' => (clone $pagos)->count()];

        return $r;
    }

    /** @return array<string,int|float> */
    #[Computed]
    public function esperando(): array
    {
        return app(ContasPagar::class)->totalEsperando();
    }

    /** @return LengthAwarePaginator<ContaPagar> */
    #[Computed]
    public function contas(): LengthAwarePaginator
    {
        $q = $this->aplicarFaixa(ContaPagar::query(), $this->faixa)->with(['favorecido', 'origens', 'pagamentos']);

        if ($this->categoria === 'outras') {
            $q->whereNotIn('categoria', ['combustivel', 'manutencao', 'frete_terceiro']);
        } elseif ($this->categoria !== '') {
            $q->where('categoria', $this->categoria);
        }

        if (trim($this->busca) !== '') {
            $t = trim($this->busca);
            $digitos = preg_replace('/\D/', '', $t) ?? '';
            $q->where(function (Builder $s) use ($t, $digitos): void {
                $s->where('documento', 'ilike', "%{$t}%")->orWhere('descricao', 'ilike', "%{$t}%")
                    ->orWhereHas('favorecido', function (Builder $p) use ($t, $digitos): void {
                        $p->where('razao_social', 'ilike', "%{$t}%")->orWhere('nome_fantasia', 'ilike', "%{$t}%");
                        if (strlen($digitos) >= 3) {
                            $p->orWhere('documento', 'like', "%{$digitos}%");
                        }
                    });
            });
        }

        return $q->orderBy($this->faixa === 'pagas' ? 'updated_at' : 'vencimento', $this->faixa === 'pagas' ? 'desc' : 'asc')
            ->orderBy('id')->paginate(20);
    }

    /* ── Pagar ── */

    public function abrirPagamento(int $id): void
    {
        $conta = ContaPagar::query()->findOrFail($id);
        $this->authorize('pagar', $conta);
        if ($conta->pagaNoCiot() || ! $conta->emAberto()) {
            session()->flash('erro', $conta->pagaNoCiot() ? 'Frete de TAC é pago no 4050.' : 'Esta conta não está em aberto.');

            return;
        }

        $this->resetErrorBag();
        $this->pagContaId = $conta->id;
        $this->pagData = Carbon::today()->toDateString();
        $this->pagForma = 'pix';
        $this->pagConta = '';
        $this->pagPrincipal = number_format($conta->saldo(), 2, '.', '');
        $this->pagJuros = '0';
        $this->pagDesconto = '0';
        $this->pagObs = '';
    }

    #[Computed]
    public function contaEmPagamento(): ?ContaPagar
    {
        return $this->pagContaId ? ContaPagar::query()->with('favorecido')->find($this->pagContaId) : null;
    }

    public function totalPago(): float
    {
        return round((float) ($this->pagPrincipal !== '' ? $this->pagPrincipal : 0) + (float) ($this->pagJuros !== '' ? $this->pagJuros : 0), 2);
    }

    public function confirmarPagamento(ContasPagar $servico): void
    {
        $this->validate([
            'pagData' => ['required', 'date'],
            'pagForma' => ['required', 'in:' . implode(',', PagamentoConta::FORMAS)],
            'pagConta' => ['nullable', 'string', 'max:80'],
            'pagPrincipal' => ['required', 'numeric', 'min:0'],
            'pagJuros' => ['nullable', 'numeric', 'min:0'],
            'pagDesconto' => ['nullable', 'numeric', 'min:0'],
            'pagObs' => ['nullable', 'string', 'max:255'],
        ], ['pagPrincipal.required' => 'Informe o valor pago.']);

        $conta = ContaPagar::query()->findOrFail($this->pagContaId);
        $this->authorize('pagar', $conta);

        try {
            $servico->pagar($conta, [
                'data' => $this->pagData, 'forma' => $this->pagForma, 'conta' => $this->pagConta,
                'valor_principal' => (float) $this->pagPrincipal,
                'juros_multa' => (float) ($this->pagJuros !== '' ? $this->pagJuros : 0),
                'desconto' => (float) ($this->pagDesconto !== '' ? $this->pagDesconto : 0),
                'observacao' => $this->pagObs,
            ]);
        } catch (ContasPagarException $e) {
            $this->addError('pagPrincipal', $e->getMessage());

            return;
        }

        session()->flash('sucesso', 'Pagamento registrado.');
        $this->pagContaId = null;
        $this->limpar();
    }

    /* ── Estornar e cancelar ── */

    public function abrirEstorno(int $contaId): void
    {
        $conta = ContaPagar::query()->findOrFail($contaId);
        $this->authorize('estornar', $conta);
        $ultimo = PagamentoConta::query()->validos()->where('conta_pagar_id', $conta->id)
            ->where('forma', '<>', 'instituicao_ciot')->latest('id')->first();
        if ($ultimo === null) {
            session()->flash('erro', 'Não há pagamento para estornar nesta conta.');

            return;
        }
        $this->resetErrorBag();
        $this->estornoId = $ultimo->id;
        $this->estornoMotivo = '';
    }

    #[Computed]
    public function pagamentoEmEstorno(): ?PagamentoConta
    {
        return $this->estornoId ? PagamentoConta::query()->with('contaPagar.favorecido')->find($this->estornoId) : null;
    }

    public function confirmarEstorno(ContasPagar $servico): void
    {
        $p = PagamentoConta::query()->with('contaPagar')->findOrFail($this->estornoId);
        $this->authorize('estornar', $p->contaPagar);

        try {
            $servico->estornar($p, $this->estornoMotivo);
        } catch (ContasPagarException $e) {
            $this->addError('estornoMotivo', $e->getMessage());

            return;
        }

        session()->flash('sucesso', 'Pagamento estornado — o saldo voltou para a conta.');
        $this->estornoId = null;
        $this->limpar();
    }

    public function abrirCancelamento(int $id): void
    {
        $conta = ContaPagar::query()->findOrFail($id);
        $this->authorize('cancelar', $conta);
        $this->resetErrorBag();
        $this->cancelarId = $conta->id;
        $this->cancelarMotivo = '';
    }

    public function confirmarCancelamento(ContasPagar $servico): void
    {
        $conta = ContaPagar::query()->findOrFail($this->cancelarId);
        $this->authorize('cancelar', $conta);

        try {
            $servico->cancelar($conta, $this->cancelarMotivo);
        } catch (ContasPagarException $e) {
            $this->addError('cancelarMotivo', $e->getMessage());

            return;
        }

        session()->flash('sucesso', 'Conta cancelada.' . ($conta->origem !== 'manual' ? ' O item voltou para os lançamentos esperando.' : ''));
        $this->cancelarId = null;
        $this->limpar();
    }

    public function fecharJanelas(): void
    {
        $this->pagContaId = null;
        $this->estornoId = null;
        $this->cancelarId = null;
        $this->resetErrorBag();
    }

    private function limpar(): void
    {
        unset($this->faixas, $this->contas, $this->esperando);
    }

    public function render(): View
    {
        return view('livewire.contas-pagar.index')->layout('layouts.app', ['title' => 'Contas a pagar']);
    }
}
