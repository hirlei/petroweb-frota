<?php

declare(strict_types=1);

namespace App\Livewire\ContasPagar;

use App\Domain\Financeiro\Parcelamento;
use App\Models\ContaPagar;
use App\Models\Pessoa;
use App\Services\Financeiro\ContasPagar;
use App\Services\Financeiro\ContasPagarException;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Rotina 5030 — Nova conta a pagar, para o que não nasce de abastecimento, OS
 * ou CIOT (aluguel, contabilidade, seguro, imposto…).
 */
class Formulario extends Component
{
    use AuthorizesRequests;

    public ?int $favorecido_id = null;
    public string $buscaFavorecido = '';
    public string $categoria = 'servicos';
    public string $documento = '';
    public string $emissao = '';
    public string $valor = '';
    public string $condicao = '30';
    public string $observacoes = '';

    public function mount(): void
    {
        $this->authorize('create', ContaPagar::class);
        $this->emissao = Carbon::today()->toDateString();
    }

    #[Computed]
    public function favorecido(): ?Pessoa
    {
        return $this->favorecido_id ? Pessoa::query()->find($this->favorecido_id) : null;
    }

    /** @return Collection<int, Pessoa> */
    #[Computed]
    public function encontrados(): Collection
    {
        $t = trim($this->buscaFavorecido);
        if (mb_strlen($t) < 2) {
            return collect();
        }
        $digitos = preg_replace('/\D/', '', $t) ?? '';

        return Pessoa::query()->where('ativo', true)
            ->where(function ($q) use ($t, $digitos): void {
                $q->where('razao_social', 'ilike', "%{$t}%")->orWhere('nome_fantasia', 'ilike', "%{$t}%");
                if (strlen($digitos) >= 3) {
                    $q->orWhere('documento', 'like', "%{$digitos}%");
                }
            })
            ->orderBy('razao_social')->limit(8)->get();
    }

    public function escolher(int $id): void
    {
        $p = Pessoa::query()->findOrFail($id);
        $this->favorecido_id = $p->id;
        $this->buscaFavorecido = '';
        $this->condicao = app(ContasPagar::class)->condicaoDe($p);
        unset($this->favorecido);
    }

    public function trocar(): void
    {
        $this->favorecido_id = null;
        unset($this->favorecido);
    }

    /** @return array{parcelas: list<array<string,mixed>>, erro: ?string} */
    #[Computed]
    public function previa(): array
    {
        $valor = (float) str_replace(',', '.', $this->valor);
        if ($valor <= 0) {
            return ['parcelas' => [], 'erro' => null];
        }

        $valor = round($valor, 2);
        if ($valor <= 0) {
            return ['parcelas' => [], 'erro' => null];
        }

        try {
            $prazos = Parcelamento::prazos($this->condicao);
            $emissao = new DateTimeImmutable($this->emissao !== '' ? $this->emissao : 'today');
            if ((int) round($valor * 100) < count($prazos)) {
                return ['parcelas' => [], 'erro' => 'Valor pequeno demais para tantas parcelas.'];
            }

            return ['parcelas' => Parcelamento::dividir($valor, $prazos, $emissao), 'erro' => null];
        } catch (\Throwable $e) {
            return ['parcelas' => [], 'erro' => $e instanceof \InvalidArgumentException ? $e->getMessage() : 'Data de emissão inválida.'];
        }
    }

    public function salvar(ContasPagar $servico)
    {
        $this->authorize('create', ContaPagar::class);

        $this->validate([
            'favorecido_id' => ['required', 'integer', 'exists:pessoas,id'],
            'categoria' => ['required', 'in:' . implode(',', array_diff(ContaPagar::CATEGORIAS, ['frete_terceiro']))],
            'documento' => ['nullable', 'string', 'max:60'],
            'emissao' => ['required', 'date'],
            'valor' => ['required', 'numeric', 'gt:0'],
            'condicao' => ['required', 'string', 'max:40'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ], [
            'favorecido_id.required' => 'Escolha o favorecido.',
            'valor.required' => 'Informe o valor.',
            'valor.gt' => 'O valor precisa ser maior que zero.',
        ]);

        try {
            $contas = $servico->lancarManual(
                Pessoa::query()->findOrFail($this->favorecido_id), $this->categoria, (float) $this->valor,
                $this->condicao, Carbon::parse($this->emissao), $this->documento, $this->observacoes,
            );
        } catch (ContasPagarException $e) {
            $this->addError('condicao', $e->getMessage());

            return null;
        }

        session()->flash('sucesso', $contas->count() === 1 ? 'Conta lançada.' : $contas->count() . ' parcelas lançadas.');

        return $this->redirect(route('contas-pagar.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.contas-pagar.formulario')->layout('layouts.app', ['title' => 'Nova conta a pagar']);
    }
}
