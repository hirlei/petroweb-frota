<?php

declare(strict_types=1);

namespace App\Livewire\Mdfe;

use App\Domain\Fiscal\RegrasCiot;
use App\Models\Ciot;
use App\Models\FornecedorVpo;
use App\Models\Mdfe;
use App\Models\Municipio;
use App\Models\ValePedagio;
use App\Models\Viagem;
use App\Services\Fiscal\Ciot\ServicoCiot;
use App\Services\Fiscal\EmissaoMdfeCompleta;
use App\Services\Fiscal\EmissorFiscal;
use App\Services\Fiscal\GeradorMdfe;
use App\Services\Fiscal\ValePedagio\ServicoValePedagio;
use DateTimeImmutable;
use Throwable;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Component;
use RuntimeException;

/**
 * Rotina 4020 — painel do MDF-e. Gera o rascunho a partir da viagem (RN-03),
 * emite e encerra (110112) via gateway SEFAZ (fake em homologação).
 *
 * Emitir faz três coisas num clique (mockup aprovado em 02/10/2026): registra o
 * CIOT, compra/informa o vale-pedágio e envia o MDF-e — App\Services\Fiscal\
 * EmissaoMdfeCompleta. CIOT e vale ficam presos à viagem e são reaproveitados
 * na reemissão; consulta, saldo, cancelamento e reenvio ficam no 4050 e no 4030.
 */
class Formulario extends Component
{
    use AuthorizesRequests;

    public ?Mdfe $mdfe = null;

    public ?int $viagem_id = null;
    public ?int $municipio_encerramento_id = null;

    // CIOT — preenchido com a sugestão da viagem.
    public string $ciotFrete = '';
    public string $ciotPercentual = '50';
    public string $ciotPrazo = '';
    public string $ciotForma = 'pix';
    public string $ciotChave = '';
    public string $ciotNumero = '';
    public string $ciotResponsavel = '';

    // Vale-pedágio.
    public string $valeModo = 'comprar';      // comprar | informar | dispensar
    public ?int $valeFornecedorId = null;
    public string $valeIdvpo = '';
    public string $valeValor = '';
    public string $valeTipo = '01';
    public string $valeMotivo = 'sem_pracas';

    /** @var array{autorizado: bool, etapas: list<array<string,string>>}|null */
    public ?array $resultado = null;

    public function mount(?Mdfe $mdfe = null): void
    {
        if ($mdfe?->exists) {
            $this->authorize('view', $mdfe);
            $this->mdfe = $mdfe->load(['viagem.municipioDestino', 'veiculoTracao', 'documentos', 'eventos', 'valesPedagio.veiculo']);
            $this->municipio_encerramento_id = $mdfe->viagem?->municipio_destino_id;
            $this->preencherCiotEVale();

            return;
        }

        $this->authorize('create', Mdfe::class);
    }

    /** Viagens sem MDF-e ainda. */
    #[Computed]
    public function viagens()
    {
        return Viagem::query()
            ->with(['veiculoTracao', 'municipioOrigem', 'municipioDestino'])
            ->whereNotExists(function ($q): void {
                $q->selectRaw('1')->from('mdfes')->whereColumn('mdfes.viagem_id', 'viagens.id')->whereIn('mdfes.status', Mdfe::ABERTOS);
            })
            ->orderByDesc('saida_prevista')->get();
    }

    #[Computed]
    public function municipios()
    {
        return Municipio::query()->orderBy('uf')->orderBy('nome')->get(['id', 'nome', 'uf']);
    }

    #[Computed]
    public function ambiente(): int
    {
        return (int) config('fiscal.sefaz.ambiente', 2);
    }

    public function gerarRascunho(GeradorMdfe $gerador): void
    {
        $this->authorize('create', Mdfe::class);

        if ($this->viagem_id === null) {
            session()->flash('erro', 'Selecione uma viagem.');

            return;
        }

        try {
            $mdfe = $gerador->aPartirDaViagem(Viagem::query()->findOrFail($this->viagem_id));
        } catch (RuntimeException $e) {
            session()->flash('erro', $e->getMessage());

            return;
        }

        session()->flash('sucesso', 'Rascunho de MDF-e gerado a partir da viagem.');
        $this->redirect(route('mdfe.editar', $mdfe), navigate: true);
    }

    /** Repuxa os CT-e vinculados à viagem para os documentos do MDF-e (rascunho). */
    public function sincronizarDocumentos(): void
    {
        $this->authorize('update', $this->mdfe);

        if ($this->mdfe->status !== 'rascunho') {
            session()->flash('erro', 'Só rascunho pode ter os documentos sincronizados.');

            return;
        }

        $ctes = $this->mdfe->viagem?->ctes()->get() ?? collect();

        $this->mdfe->documentos()->delete();
        foreach ($ctes as $cte) {
            $this->mdfe->documentos()->create([
                'tipo' => 'cte',
                'chave' => $cte->chave,
                'cte_id' => $cte->id,
                'municipio_descarregamento_id' => $cte->municipio_fim_id,
                'peso' => $cte->peso_bruto,
                'valor' => $cte->valor_total_servico,
            ]);
        }

        $this->mdfe->update([
            'peso_bruto_total' => (float) $ctes->sum('peso_bruto'),
            'valor_carga_total' => (float) $ctes->sum('valor_mercadoria'),
        ]);
        $this->mdfe->refresh();

        session()->flash('sucesso', $ctes->count() . ' CT-e sincronizado(s) no manifesto.');
    }

    public function emitivel(): bool
    {
        return in_array($this->mdfe?->status, ['rascunho', 'rejeitado'], true);
    }

    #[Computed]
    public function modalidadeCiot(): string
    {
        return $this->mdfe?->viagem?->modalidadeCiot() ?? RegrasCiot::DISPENSADO;
    }

    #[Computed]
    public function ciotAtual(): ?Ciot
    {
        return $this->mdfe?->viagem?->ciot()->with('pagamentos')->first();
    }

    #[Computed]
    public function valeAtual(): ?ValePedagio
    {
        $viagem = $this->mdfe?->viagem;

        return $viagem !== null ? app(ServicoValePedagio::class)->ativo($viagem) : null;
    }

    /** @return array<string,mixed> */
    #[Computed]
    public function sugestaoCiot(): array
    {
        $viagem = $this->mdfe?->viagem;

        return $viagem !== null ? app(ServicoCiot::class)->sugestao($viagem) : [];
    }

    #[Computed]
    public function cotacaoVale(): ?float
    {
        $viagem = $this->mdfe?->viagem;
        if ($viagem === null) {
            return null;
        }

        try {
            return app(ServicoValePedagio::class)->cotar($viagem);
        } catch (Throwable) {
            return null;
        }
    }

    #[Computed]
    public function eixosVale(): int
    {
        $viagem = $this->mdfe?->viagem;

        return $viagem !== null ? app(ServicoValePedagio::class)->eixos($viagem) : 0;
    }

    #[Computed]
    public function papelVale(): string
    {
        $viagem = $this->mdfe?->viagem;

        return $viagem !== null ? app(ServicoValePedagio::class)->papel($viagem) : 'recebido';
    }

    #[Computed]
    public function fornecedoresVpo()
    {
        return FornecedorVpo::query()->where('ativo', true)->orderBy('razao_social')->get(['id', 'razao_social', 'cnpj']);
    }

    /**
     * Adiantamento e saldo do que está digitado — só para mostrar.
     *
     * @return array{adiantamento: float, saldo: float}|null
     */
    #[Computed]
    public function divisaoCiot(): ?array
    {
        $frete = $this->decimal($this->ciotFrete);
        $pct = $this->decimal($this->ciotPercentual);
        if ($frete === null || $frete <= 0 || $pct === null) {
            return null;
        }

        try {
            return RegrasCiot::dividir($frete, $pct);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /** Sem CIOT válido, o que acontece com o MDF-e: ok | avisa | bloqueia. */
    #[Computed]
    public function travaCiot(): string
    {
        return RegrasCiot::trava(
            $this->modalidadeCiot,
            (bool) $this->ciotAtual?->valido(),
            (int) ($this->mdfe?->ambiente ?: config('fiscal.sefaz.ambiente', 2)),
            new DateTimeImmutable('today'),
            new DateTimeImmutable((string) config('ciot.obrigatorio_desde', '2026-11-23')),
        );
    }

    public function definirPercentual(string $pct): void
    {
        $this->ciotPercentual = $pct;
    }

    public function emitir(EmissaoMdfeCompleta $emissao): void
    {
        $this->authorize('emitir', $this->mdfe);

        if (! $this->emitivel()) {
            session()->flash('erro', 'Só rascunho ou MDF-e rejeitado pode ser emitido.');

            return;
        }

        $this->resultado = $emissao->emitir(
            $this->mdfe,
            [
                'frete' => $this->ciotFrete, 'percentual' => $this->ciotPercentual, 'prazo' => $this->ciotPrazo,
                'forma' => $this->ciotForma, 'chave' => $this->ciotChave,
                'numero' => $this->ciotNumero, 'responsavel' => $this->ciotResponsavel,
            ],
            [
                'modo' => $this->valeModo, 'fornecedor_id' => $this->valeFornecedorId, 'idvpo' => $this->valeIdvpo,
                'valor' => $this->decimal($this->valeValor), 'tipo' => $this->valeTipo, 'motivo' => $this->valeMotivo,
            ],
        );

        $this->mdfe->refresh()->load(['viagem.municipioDestino', 'veiculoTracao', 'documentos', 'eventos', 'valesPedagio.veiculo']);
        unset($this->ciotAtual, $this->valeAtual, $this->travaCiot);
    }

    public function fecharResultado(): void
    {
        $this->resultado = null;
    }

    private function preencherCiotEVale(): void
    {
        if (! $this->emitivel() || $this->mdfe?->viagem === null) {
            return;
        }

        $sug = $this->sugestaoCiot;
        $atual = $this->ciotAtual;

        if ($atual !== null && $atual->status === 'recusado') {
            $this->ciotFrete = number_format((float) $atual->valor_frete, 2, '.', '');
            $this->ciotPercentual = rtrim(rtrim(number_format((float) $atual->percentual_adiantamento, 2, '.', ''), '0'), '.');
            $this->ciotPrazo = $atual->prazo_quitacao?->toDateString() ?? (string) ($sug['prazo'] ?? '');
            $this->ciotForma = (string) ($atual->forma_pagamento ?? 'pix');
            $this->ciotChave = (string) ($atual->chave_pagamento ?? '');
        } else {
            $this->ciotFrete = ($sug['frete'] ?? 0) > 0 ? number_format((float) $sug['frete'], 2, '.', '') : '';
            $this->ciotPercentual = (string) config('ciot.adiantamento_padrao', 50);
            $this->ciotPrazo = (string) ($sug['prazo'] ?? '');
            $this->ciotForma = (string) ($sug['forma'] ?? 'pix');
            $this->ciotChave = (string) ($sug['chave'] ?? '');
        }
        $this->ciotResponsavel = (string) ($sug['responsavel_informado'] ?? '');

        // Embarcador compra (recebido) → informar; transportadora compra (fornecido) → comprar.
        $this->valeModo = $this->papelVale === 'fornecido' ? 'comprar' : 'informar';
        $this->valeFornecedorId = $this->fornecedoresVpo->first()?->id;
    }

    private function decimal(string $v): ?float
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

    public function encerrar(EmissorFiscal $emissor): void
    {
        $this->authorize('encerrar', $this->mdfe);

        if (! $this->mdfe->encerravel()) {
            session()->flash('erro', 'Só MDF-e autorizado e não encerrado pode ser encerrado.');

            return;
        }

        if ($this->municipio_encerramento_id === null) {
            session()->flash('erro', 'Informe o município de encerramento.');

            return;
        }

        $r = $emissor->encerrarMdfe($this->mdfe, $this->municipio_encerramento_id);
        $this->mdfe->refresh();

        session()->flash($r->autorizado ? 'sucesso' : 'erro',
            $r->autorizado ? 'MDF-e encerrado (evento 110112 registrado).' : "Falha ao encerrar: {$r->motivo}");
    }

    public function render(): View
    {
        return view('livewire.mdfe.formulario')
            ->layout('layouts.app', ['title' => $this->mdfe?->exists ? 'MDF-e ' . ($this->mdfe->numero ?? 'rascunho') : 'Novo MDF-e']);
    }
}
