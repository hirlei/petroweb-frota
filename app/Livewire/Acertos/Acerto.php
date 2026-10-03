<?php

declare(strict_types=1);

namespace App\Livewire\Acertos;

use App\Models\AcertoViagem;
use App\Models\Adiantamento;
use App\Models\Despesa;
use App\Models\Viagem;
use App\Services\Operacao\AcertoException;
use App\Services\Operacao\Acertos;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Acerto de uma viagem (rotina 3070): adiantamentos, conferência das despesas
 * (aceita/glosa), diárias e comissão, e o saldo. Fechado, vira leitura com
 * recibo, reabertura e registro da devolução.
 */
class Acerto extends Component
{
    use AuthorizesRequests;

    public Viagem $viagem;

    public string $diaria = '0';
    public string $comissaoPct = '0';
    public string $observacoes = '';

    // Adiantamento
    public bool $novoAdiantamento = false;
    public string $adData = '';
    public string $adValor = '';
    public string $adForma = 'pix';
    public string $adFinalidade = 'geral';

    // Glosa (motivo opcional)
    public ?int $glosaId = null;
    public string $glosaMotivo = '';

    // Reabrir / devolução
    public bool $reabrindo = false;
    public string $motivoReabertura = '';
    public bool $devolvendo = false;
    public string $devForma = 'pix';
    public string $devData = '';

    public function mount(Viagem $viagem): void
    {
        $this->authorize('viewAny', AcertoViagem::class);
        $this->viagem = $viagem->load(['motorista.pessoa', 'veiculoTracao', 'municipioOrigem', 'municipioDestino']);
        $this->diaria = number_format((float) ($viagem->motorista?->valor_diaria ?? 0), 2, '.', '');
        $this->comissaoPct = rtrim(rtrim(number_format((float) ($viagem->motorista?->percentual_comissao ?? 0), 2, '.', ''), '0'), '.');
    }

    #[Computed]
    public function elegivel(): bool
    {
        return app(Acertos::class)->elegivel($this->viagem);
    }

    #[Computed]
    public function acerto(): ?AcertoViagem
    {
        return AcertoViagem::query()->with(['contaPagar.pagamentos', 'fechadoPor'])
            ->where('viagem_id', $this->viagem->id)->where('status', 'fechado')->latest('id')->first();
    }

    /** @return array<string,mixed> */
    #[Computed]
    public function resumo(): array
    {
        // Campo vazio vale 0 aqui e no fechar — a tela mostra o que vai ser gravado.
        return app(Acertos::class)->resumo($this->viagem, $this->numero($this->diaria) ?? 0.0, $this->numero($this->comissaoPct) ?? 0.0);
    }

    /** @return Collection<int, Adiantamento> */
    #[Computed]
    public function adiantamentos(): Collection
    {
        return Adiantamento::query()->where('viagem_id', $this->viagem->id)->orderBy('data')->orderBy('id')->get();
    }

    /** @return Collection<int, Despesa> */
    #[Computed]
    public function despesas(): Collection
    {
        return Despesa::query()->where('viagem_id', $this->viagem->id)->orderBy('data')->orderBy('id')->get();
    }

    /** @return Collection<int, AcertoViagem> */
    #[Computed]
    public function historico(): Collection
    {
        return AcertoViagem::query()->with(['fechadoPor'])->where('viagem_id', $this->viagem->id)->orderByDesc('id')->get();
    }

    /* ── Adiantamentos ── */

    public function abrirAdiantamento(): void
    {
        $this->authorize('gerenciar', AcertoViagem::class);
        $this->resetErrorBag();
        $this->adData = Carbon::today()->toDateString();
        $this->adValor = '';
        $this->adForma = 'pix';
        $this->adFinalidade = 'geral';
        $this->novoAdiantamento = true;
    }

    public function salvarAdiantamento(Acertos $servico): void
    {
        $this->authorize('gerenciar', AcertoViagem::class);
        try {
            $servico->adicionarAdiantamento($this->viagem, $this->adData, (float) ($this->numero($this->adValor) ?? 0), $this->adForma, $this->adFinalidade);
        } catch (AcertoException $e) {
            $this->addError('adValor', $e->getMessage());

            return;
        }
        $this->novoAdiantamento = false;
        $this->limpar();
    }

    public function removerAdiantamento(int $id, Acertos $servico): void
    {
        $this->authorize('gerenciar', AcertoViagem::class);
        $ad = Adiantamento::query()->where('viagem_id', $this->viagem->id)->findOrFail($id);
        try {
            $servico->removerAdiantamento($ad);
        } catch (AcertoException $e) {
            session()->flash('erro', $e->getMessage());
        }
        $this->limpar();
    }

    /* ── Conferência ── */

    public function conferir(int $despesaId, string $conferencia, Acertos $servico): void
    {
        $this->authorize('gerenciar', AcertoViagem::class);
        $d = Despesa::query()->where('viagem_id', $this->viagem->id)->findOrFail($despesaId);

        if ($conferencia === 'glosa' && $this->glosaId !== $despesaId) {
            // Primeiro clique em "Glosa" abre o campo de motivo.
            $this->glosaId = $despesaId;
            $this->glosaMotivo = (string) ($d->motivo_glosa ?? '');

            return;
        }

        try {
            $servico->conferir($d, $conferencia, $conferencia === 'glosa' ? $this->glosaMotivo : null);
        } catch (AcertoException $e) {
            session()->flash('erro', $e->getMessage());
        }
        $this->glosaId = null;
        $this->glosaMotivo = '';
        $this->limpar();
    }

    public function cancelarGlosa(): void
    {
        $this->glosaId = null;
        $this->glosaMotivo = '';
    }

    /* ── Fechar, reabrir, devolução ── */

    public function fechar(Acertos $servico): void
    {
        $this->authorize('gerenciar', AcertoViagem::class);
        try {
            $a = $servico->fechar($this->viagem, (float) ($this->numero($this->diaria) ?? 0), (float) ($this->numero($this->comissaoPct) ?? 0), $this->observacoes);
        } catch (AcertoException $e) {
            session()->flash('erro', $e->getMessage());

            return;
        }

        session()->flash('sucesso', match (true) {
            $a->empresaPaga() => 'Acerto fechado. A conta de R$ ' . number_format((float) $a->saldo, 2, ',', '.') . ' foi lançada no 5030.',
            $a->motoristaDevolve() => 'Acerto fechado. O motorista devolve R$ ' . number_format(-(float) $a->saldo, 2, ',', '.') . '.',
            default => 'Acerto fechado, zerado.',
        });
        $this->limpar();
    }

    public function reabrir(Acertos $servico): void
    {
        $this->authorize('gerenciar', AcertoViagem::class);
        if ($this->acerto === null) {
            $this->reabrindo = false;

            return;
        }
        try {
            $servico->reabrir($this->acerto, $this->motivoReabertura);
        } catch (AcertoException $e) {
            $this->addError('motivoReabertura', $e->getMessage());

            return;
        }
        $this->reabrindo = false;
        $this->motivoReabertura = '';
        session()->flash('sucesso', 'Acerto reaberto. Confira e feche de novo.');
        $this->limpar();
    }

    public function abrirDevolucao(): void
    {
        $this->authorize('gerenciar', AcertoViagem::class);
        $this->resetErrorBag();
        $this->devForma = 'pix';
        $this->devData = Carbon::today()->toDateString();
        $this->devolvendo = true;
    }

    public function registrarDevolucao(Acertos $servico): void
    {
        $this->authorize('gerenciar', AcertoViagem::class);
        if ($this->acerto === null) {
            $this->devolvendo = false;

            return;
        }
        try {
            $servico->registrarDevolucao($this->acerto, $this->devForma, $this->devData);
        } catch (AcertoException $e) {
            $this->addError('devForma', $e->getMessage());

            return;
        }
        $this->devolvendo = false;
        $this->limpar();
    }

    public function desfazerDevolucao(Acertos $servico): void
    {
        $this->authorize('gerenciar', AcertoViagem::class);
        if ($this->acerto) {
            $servico->desfazerDevolucao($this->acerto);
        }
        $this->limpar();
    }

    public function fecharJanelas(): void
    {
        $this->novoAdiantamento = false;
        $this->reabrindo = false;
        $this->devolvendo = false;
        $this->resetErrorBag();
    }

    private function limpar(): void
    {
        unset($this->acerto, $this->resumo, $this->adiantamentos, $this->despesas, $this->historico);
    }

    private function numero(string $v): ?float
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        if (str_contains($v, ',')) {
            $v = str_replace(['.', ','], ['', '.'], $v);
        }

        return is_numeric($v) ? (float) $v : null;
    }

    public function render(): View
    {
        return view('livewire.acertos.acerto')->layout('layouts.app', ['title' => 'Acerto · ' . $this->viagem->numero]);
    }
}
