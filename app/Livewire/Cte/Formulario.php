<?php

declare(strict_types=1);

namespace App\Livewire\Cte;

use App\Domain\Fiscal\RegrasCce;
use App\Models\Cte;
use App\Models\OrdemColeta;
use App\Services\Fiscal\CartaCorrecao;
use App\Services\Fiscal\CartaCorrecaoException;
use App\Services\Fiscal\EmissorFiscal;
use App\Services\Fiscal\GeradorCte;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Rotina 4010 — painel do CT-e. Gera o rascunho a partir da ordem de coleta,
 * emite e cancela via gateway SEFAZ (fake em homologação). Documento autorizado
 * é imutável — só eventos: carta de correção (110110) e cancelamento (110111).
 */
class Formulario extends Component
{
    use AuthorizesRequests;

    public ?Cte $cte = null;

    public ?int $ordem_coleta_id = null;
    public string $justificativa = '';

    // Carta de correção: cada linha = {chave, grupo, campo, valor, item, origem}.
    public bool $cceAberta = false;
    /** @var list<array<string,mixed>> */
    public array $cceLinhas = [];
    /** @var array<int|string,string> */
    public array $cceErros = [];

    public function mount(?Cte $cte = null): void
    {
        if ($cte?->exists) {
            $this->authorize('view', $cte);
            $this->cte = $cte->load(['tomador', 'remetente.enderecoPrincipal', 'destinatario.enderecoPrincipal', 'expedidor.enderecoPrincipal',
                'recebedor.enderecoPrincipal', 'filial', 'municipioInicio', 'municipioFim', 'componentes', 'documentos', 'eventos.criadoPor', 'ordemColeta']);

            return;
        }

        $this->authorize('create', Cte::class);
    }

    /** OCs sem CT-e ainda, prontas para gerar. */
    #[Computed]
    public function ordens()
    {
        return OrdemColeta::query()
            ->with(['cliente', 'municipioInicio', 'municipioFim'])
            ->whereNotIn('status', ['cancelada'])
            ->whereNotExists(function ($q): void {
                $q->selectRaw('1')->from('ctes')->whereColumn('ctes.ordem_coleta_id', 'ordens_coleta.id');
            })
            ->orderByDesc('data')->get();
    }

    #[Computed]
    public function ambiente(): int
    {
        return (int) config('fiscal.sefaz.ambiente', 2);
    }

    public function gerarRascunho(GeradorCte $gerador): void
    {
        $this->authorize('create', Cte::class);

        if ($this->ordem_coleta_id === null) {
            session()->flash('erro', 'Selecione uma ordem de coleta.');

            return;
        }

        $ordem = OrdemColeta::query()->findOrFail($this->ordem_coleta_id);
        $cte = $gerador->aPartirDaOrdem($ordem);

        session()->flash('sucesso', 'Rascunho de CT-e gerado a partir da ordem ' . $ordem->numero . '.');

        $this->redirect(route('cte.editar', $cte), navigate: true);
    }

    public function emitir(EmissorFiscal $emissor): void
    {
        $this->authorize('emitir', $this->cte);

        if (! $this->cte->editavel()) {
            session()->flash('erro', 'Só rascunho ou rejeitado pode ser emitido.');

            return;
        }

        $r = $emissor->emitirCte($this->cte);
        $this->cte->refresh();

        session()->flash($r->autorizado ? 'sucesso' : 'erro',
            $r->autorizado
                ? "CT-e autorizado (cStat {$r->codigo}). Protocolo {$r->protocolo}."
                : "Rejeitado (cStat {$r->codigo}): {$r->motivo}");
    }

    public function cancelar(EmissorFiscal $emissor): void
    {
        $this->authorize('cancelar', $this->cte);

        if (! $this->cte->autorizado()) {
            session()->flash('erro', 'Só CT-e autorizado pode ser cancelado.');

            return;
        }

        if (! $this->cte->noPrazoDeCancelamento()) {
            session()->flash('erro', 'Passou o prazo de cancelamento (' . $this->cte->cancelavelAte()?->format('d/m/Y H:i') . '). Para corrigir o valor, emita um CT-e complementar ou de substituição.');

            return;
        }

        if (mb_strlen(trim($this->justificativa)) > 255) {
            session()->flash('erro', 'A justificativa vai até 255 caracteres.');

            return;
        }

        if (mb_strlen(trim($this->justificativa)) < 15) {
            session()->flash('erro', 'A justificativa de cancelamento precisa de ao menos 15 caracteres.');

            return;
        }

        $r = $emissor->cancelarCte($this->cte, trim($this->justificativa));
        $this->cte->refresh();
        $this->justificativa = '';

        session()->flash($r->autorizado ? 'sucesso' : 'erro',
            $r->autorizado ? 'CT-e cancelado (evento 110111 registrado).' : "Falha ao cancelar: {$r->motivo}");
    }

    /* ── Carta de correção (110110) ── */

    /** @return list<array{grupo:string,campo:string,valor:string,item:int|string|null}> */
    #[Computed]
    public function cceVigentes(): array
    {
        return $this->cte?->exists ? app(CartaCorrecao::class)->vigentes($this->cte) : [];
    }

    #[Computed]
    public function cceEmitidas(): int
    {
        return $this->cte?->exists ? $this->cte->cartasCorrecao()->count() : 0;
    }

    /** Valor corrigido pela CC-e vigente, ou null. */
    public function corrigido(string $grupo, string $campo): ?string
    {
        foreach ($this->cceVigentes as $c) {
            if (strcasecmp($c['grupo'], $grupo) === 0 && strcasecmp($c['campo'], $campo) === 0) {
                return $c['valor'];
            }
        }

        return null;
    }

    /** Como o campo está no CT-e autorizado (para a coluna "Como está"). */
    public function valorAtual(string $chave): ?string
    {
        $c = $this->cte;
        $endereco = function (string $papel, string $campo) use ($c): ?string {
            $e = $c?->{$papel}?->enderecoPrincipal;

            return match ($campo) {
                'xLgr' => $e?->logradouro, 'nro' => $e?->numero, 'xCpl' => $e?->complemento,
                'xBairro' => $e?->bairro, 'CEP' => $e?->cep, default => null,
            };
        };
        $payload = (array) ($c?->payload ?? []);

        return match (true) {
            $chave === 'proPred' => $c?->produto_predominante,
            $chave === 'RNTRC' => $c?->filial?->rntrc,
            in_array($chave, ['xObs', 'xCaracAd', 'xCaracSer', 'xOutCat'], true) => isset($payload[$chave]) ? (string) $payload[$chave] : null,
            str_starts_with($chave, 'rem.') => $endereco('remetente', substr($chave, 4)),
            str_starts_with($chave, 'dest.') => $endereco('destinatario', substr($chave, 5)),
            str_starts_with($chave, 'exped.') => $endereco('expedidor', substr($chave, 6)),
            str_starts_with($chave, 'receb.') => $endereco('recebedor', substr($chave, 6)),
            default => null,
        };
    }

    /** Motivo do bloqueio de cada linha, já enquanto digita. @return array<int,string> */
    #[Computed]
    public function cceVetos(): array
    {
        $vetos = [];
        foreach ($this->cceLinhas as $i => $l) {
            $g = trim((string) ($l['grupo'] ?? ''));
            $c = trim((string) ($l['campo'] ?? ''));
            if ($g !== '' && $c !== '' && ($m = RegrasCce::vetado($g, $c)) !== null) {
                $vetos[$i] = $m;
            }
        }

        return $vetos;
    }

    public function abrirCce(): void
    {
        $this->authorize('corrigir', $this->cte);
        $this->cceLinhas = array_map(fn (array $c) => [
            'chave' => RegrasCce::chaveDoCatalogo($c['grupo'], $c['campo']) ?? 'outro',
            'grupo' => $c['grupo'], 'campo' => $c['campo'], 'valor' => $c['valor'], 'item' => $c['item'], 'origem' => true,
        ], $this->cceVigentes);
        if ($this->cceLinhas === []) {
            $this->adicionarLinhaCce();
        }
        $this->cceErros = [];
        $this->cceAberta = true;
    }

    public function adicionarLinhaCce(): void
    {
        $this->cceLinhas[] = ['chave' => '', 'grupo' => '', 'campo' => '', 'valor' => '', 'item' => null, 'origem' => false];
    }

    public function removerLinhaCce(int $i): void
    {
        unset($this->cceLinhas[$i]);
        $this->cceLinhas = array_values($this->cceLinhas);
        $this->cceErros = [];
    }

    public function updatedCceLinhas(mixed $valor, string $chave): void
    {
        // "3.chave" → preenche grupo/campo pelo catálogo.
        if (! preg_match('/^(\d+)\.chave$/', $chave, $m)) {
            return;
        }
        $i = (int) $m[1];
        $def = RegrasCce::CATALOGO[(string) $valor] ?? null;
        $this->cceLinhas[$i]['grupo'] = $def[0] ?? '';
        $this->cceLinhas[$i]['campo'] = $def[1] ?? '';
        unset($this->cceErros[$i]);
    }

    public function transmitirCce(CartaCorrecao $servico): void
    {
        $this->authorize('corrigir', $this->cte);

        $correcoes = array_map(fn (array $l) => [
            'grupo' => trim((string) ($l['grupo'] ?? '')), 'campo' => trim((string) ($l['campo'] ?? '')),
            'valor' => trim((string) ($l['valor'] ?? '')), 'item' => ($l['item'] ?? null) === '' ? null : ($l['item'] ?? null),
        ], $this->cceLinhas);

        try {
            $r = $servico->transmitir($this->cte, $correcoes);
        } catch (CartaCorrecaoException $e) {
            $this->cceErros = $e->erros !== [] ? $e->erros : ['_' => $e->getMessage()];

            return;
        }

        $this->cte->refresh();
        $this->cte->load('eventos.criadoPor');
        unset($this->cceVigentes, $this->cceEmitidas);

        if (! $r->autorizado) {
            $this->cceErros = ['_' => "A SEFAZ recusou (cStat {$r->codigo}): {$r->motivo}"];

            return;
        }

        $this->cceAberta = false;
        $this->cceLinhas = [];
        session()->flash('sucesso', 'Carta de correção nº ' . $this->cceEmitidas . " registrada. Protocolo {$r->protocolo}.");
    }

    public function fecharCce(): void
    {
        $this->cceAberta = false;
        $this->cceErros = [];
    }

    public function render(): View
    {
        return view('livewire.cte.formulario')
            ->layout('layouts.app', ['title' => $this->cte?->exists ? 'CT-e ' . ($this->cte->numero ?? 'rascunho') : 'Novo CT-e']);
    }
}
