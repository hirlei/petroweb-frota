<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Domain\Financeiro\Encargos;
use App\Models\Recebimento;
use App\Models\TituloReceber;
use App\Services\Financeiro\Faturamento;
use App\Services\Financeiro\FaturamentoException;
use DateTimeImmutable;
use Illuminate\Support\Carbon;

/**
 * Janela "Registrar recebimento" e "Estornar" — usada na Fatura (5010) e em
 * Contas a receber (5020). A view é livewire/financeiro/modal-recebimento.
 *
 * Quem usa precisa ter AuthorizesRequests e pode sobrescrever
 * `depoisDoRecebimento()` para limpar os #[Computed] da tela.
 */
trait RegistraRecebimento
{
    public ?int $recTituloId = null;

    public string $recData = '';

    public string $recForma = 'pix';

    public string $recConta = '';

    public string $recPrincipal = '';

    public string $recJuros = '0';

    public string $recDesconto = '0';

    public string $recObs = '';

    /** @var array<string,mixed> */
    public array $recInfo = [];

    public ?int $estornoId = null;

    public string $estornoMotivo = '';

    public function abrirRecebimento(int $tituloId): void
    {
        $titulo = TituloReceber::query()->with(['tomador', 'fatura'])->findOrFail($tituloId);
        $this->authorize('receber', $titulo);

        if (! $titulo->emAberto()) {
            session()->flash('erro', 'Este título não está em aberto.');

            return;
        }

        $this->resetErrorBag();
        $this->recTituloId = $titulo->id;
        $this->recData = Carbon::today()->format('Y-m-d');
        $this->recForma = 'pix';
        $this->recConta = '';
        $this->recPrincipal = number_format($titulo->saldo(), 2, '.', '');
        $this->recDesconto = '0';
        $this->recObs = '';
        $this->recInfo = [
            'numero' => $titulo->numero,
            'cliente' => $titulo->tomador?->razao_social ?? '—',
            'vencimento' => $titulo->vencimento?->format('d/m/Y'),
            'saldo' => $titulo->saldo(),
            'multa' => $titulo->tomador?->multa_percentual !== null ? (float) $titulo->tomador->multa_percentual : null,
            'juros' => $titulo->tomador?->juros_mes_percentual !== null ? (float) $titulo->tomador->juros_mes_percentual : null,
        ];
        $this->sugerirEncargos();
    }

    public function updatedRecData(): void
    {
        $this->sugerirEncargos();
    }

    /** Multa e juros sugeridos para a data escolhida (o usuário pode mudar). */
    private function sugerirEncargos(): void
    {
        $titulo = $this->recTituloId ? TituloReceber::query()->find($this->recTituloId) : null;
        if ($titulo === null || $this->recData === '') {
            return;
        }

        try {
            $pagamento = new DateTimeImmutable($this->recData);
        } catch (\Exception) {
            return;
        }

        $e = Encargos::calcular(
            $titulo->saldo(),
            new DateTimeImmutable($titulo->vencimento->format('Y-m-d')),
            $pagamento,
            $this->recInfo['multa'] ?? null,
            $this->recInfo['juros'] ?? null,
        );

        $this->recJuros = number_format($e['total'], 2, '.', '');
        $this->recInfo['dias_atraso'] = $e['dias_atraso'];
        $this->recInfo['multa_valor'] = $e['multa'];
        $this->recInfo['juros_valor'] = $e['juros'];
    }

    public function confirmarRecebimento(Faturamento $faturamento): void
    {
        $this->validate([
            'recData' => ['required', 'date'],
            'recForma' => ['required', 'in:' . implode(',', Recebimento::FORMAS)],
            'recConta' => ['nullable', 'string', 'max:80'],
            'recPrincipal' => ['required', 'numeric', 'min:0'],
            'recJuros' => ['nullable', 'numeric', 'min:0'],
            'recDesconto' => ['nullable', 'numeric', 'min:0'],
            'recObs' => ['nullable', 'string', 'max:255'],
        ], [
            'recPrincipal.required' => 'Informe o valor recebido.',
        ]);

        $titulo = TituloReceber::query()->findOrFail($this->recTituloId);
        $this->authorize('receber', $titulo);

        try {
            $faturamento->receber($titulo, [
                'data' => $this->recData,
                'forma' => $this->recForma,
                'conta' => $this->recConta,
                'valor_principal' => (float) $this->recPrincipal,
                'juros_multa' => (float) ($this->recJuros !== '' ? $this->recJuros : 0),
                'desconto' => (float) ($this->recDesconto !== '' ? $this->recDesconto : 0),
                'observacao' => $this->recObs,
            ]);
        } catch (FaturamentoException $e) {
            $this->addError('recPrincipal', $e->getMessage());

            return;
        }

        session()->flash('sucesso', "Recebimento do título {$titulo->numero} registrado.");
        $this->fecharRecebimento();
        $this->depoisDoRecebimento();
    }

    public function fecharRecebimento(): void
    {
        $this->recTituloId = null;
        $this->recInfo = [];
        $this->resetErrorBag();
    }

    public function abrirEstorno(int $recebimentoId): void
    {
        $recebimento = Recebimento::query()->with('titulo')->findOrFail($recebimentoId);
        $this->authorize('estornar', $recebimento->titulo);
        $this->resetErrorBag();
        $this->estornoId = $recebimento->id;
        $this->estornoMotivo = '';
    }

    public function confirmarEstorno(Faturamento $faturamento): void
    {
        $recebimento = Recebimento::query()->with('titulo')->findOrFail($this->estornoId);
        $this->authorize('estornar', $recebimento->titulo);

        try {
            $faturamento->estornar($recebimento, $this->estornoMotivo);
        } catch (FaturamentoException $e) {
            $this->addError('estornoMotivo', $e->getMessage());

            return;
        }

        session()->flash('sucesso', "Recebimento do título {$recebimento->titulo->numero} estornado.");
        $this->estornoId = null;
        $this->depoisDoRecebimento();
    }

    public function fecharEstorno(): void
    {
        $this->estornoId = null;
        $this->resetErrorBag();
    }

    protected function depoisDoRecebimento(): void
    {
    }

    public function totalRecebido(): float
    {
        return round((float) ($this->recPrincipal !== '' ? $this->recPrincipal : 0) + (float) ($this->recJuros !== '' ? $this->recJuros : 0), 2);
    }
}
