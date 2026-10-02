<?php

declare(strict_types=1);

namespace App\Livewire\OrdensColeta;

use App\Domain\Operacao\CalculadoraFrete;
use App\Models\Filial;
use App\Models\Mercadoria;
use App\Models\Municipio;
use App\Models\OcItem;
use App\Models\OrdemColeta;
use App\Models\Pessoa;
use App\Models\TabelaFrete;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Rotina 3010 — ficha da ordem de coleta.
 *
 * Cabeçalho (quem, quando, status) + participantes + trecho + carga + itens
 * (cada um com sua NF-e) + frete calculado a partir da tabela vigente. O
 * `empresa_id` vem do TenantContext; o frete é recalculado sob demanda pela
 * CalculadoraFrete, nunca digitado à mão.
 */
class Formulario extends Component
{
    use AuthorizesRequests;

    public ?OrdemColeta $ordem = null;

    // Cabeçalho
    public string $numero = '';
    public string $data = '';
    public ?int $filial_id = null;
    public ?int $cliente_id = null;
    public string $tomador_tipo = 'remetente';
    public string $status = 'aberta';

    // Participantes
    public ?int $tomador_id = null;
    public ?int $remetente_id = null;
    public ?int $destinatario_id = null;
    public ?int $expedidor_id = null;
    public ?int $recebedor_id = null;

    // Trecho
    public ?int $municipio_inicio_id = null;
    public ?int $municipio_fim_id = null;
    public string $previsao_coleta = '';
    public string $previsao_entrega = '';

    // Carga
    public string $peso_bruto = '';
    public string $peso_cubado = '';
    public string $volumes = '';
    public string $valor_mercadoria = '';

    // Frete
    public ?int $tabela_frete_id = null;
    public ?float $valor_frete_calculado = null;
    /** @var list<array{componente:string,base:string,valor:float}> */
    public array $freteComponentes = [];

    public string $observacoes = '';

    /** @var list<array<string,mixed>> */
    public array $itens = [];

    public function mount(?OrdemColeta $ordem = null): void
    {
        if ($ordem?->exists) {
            $this->authorize('update', $ordem);
            $this->ordem = $ordem->load('itens');
            $this->preencherDe($ordem);

            return;
        }

        $this->authorize('create', OrdemColeta::class);
        $this->data = now()->format('Y-m-d');
        $this->numero = $this->proximoNumero();
        $this->filial_id = Filial::query()->orderBy('id')->value('id');
        $this->itens = [$this->itemVazio()];
    }

    private function preencherDe(OrdemColeta $ordem): void
    {
        $this->numero = (string) $ordem->numero;
        $this->data = $ordem->data?->format('Y-m-d') ?? '';
        $this->filial_id = $ordem->filial_id;
        $this->cliente_id = $ordem->cliente_id;
        $this->tomador_tipo = (string) $ordem->tomador_tipo;
        $this->status = (string) $ordem->status;
        $this->tomador_id = $ordem->tomador_id;
        $this->remetente_id = $ordem->remetente_id;
        $this->destinatario_id = $ordem->destinatario_id;
        $this->expedidor_id = $ordem->expedidor_id;
        $this->recebedor_id = $ordem->recebedor_id;
        $this->municipio_inicio_id = $ordem->municipio_inicio_id;
        $this->municipio_fim_id = $ordem->municipio_fim_id;
        $this->previsao_coleta = $ordem->previsao_coleta?->format('Y-m-d\TH:i') ?? '';
        $this->previsao_entrega = $ordem->previsao_entrega?->format('Y-m-d\TH:i') ?? '';
        $this->peso_bruto = $this->str($ordem->peso_bruto);
        $this->peso_cubado = $this->str($ordem->peso_cubado);
        $this->volumes = $ordem->volumes === null ? '' : (string) $ordem->volumes;
        $this->valor_mercadoria = $this->str($ordem->valor_mercadoria);
        $this->tabela_frete_id = $ordem->tabela_frete_id;
        $this->valor_frete_calculado = $ordem->valor_frete_calculado !== null ? (float) $ordem->valor_frete_calculado : null;
        $this->observacoes = (string) ($ordem->observacoes ?? '');

        $this->itens = $ordem->itens->map(fn (OcItem $i): array => [
            'id'         => $i->id,
            'mercadoria_id' => $i->mercadoria_id,
            'descricao'  => (string) $i->descricao,
            'quantidade' => $this->str($i->quantidade),
            'unidade'    => (string) ($i->unidade ?? ''),
            'peso'       => $this->str($i->peso),
            'volume'     => $this->str($i->volume),
            'valor'      => $this->str($i->valor),
            'nfe_chave'  => (string) ($i->nfe_chave ?? ''),
            'nfe_numero' => (string) ($i->nfe_numero ?? ''),
            'nfe_serie'  => (string) ($i->nfe_serie ?? ''),
        ])->all();

        if ($this->itens === []) {
            $this->itens = [$this->itemVazio()];
        }
    }

    private function itemVazio(): array
    {
        return ['id' => null, 'mercadoria_id' => null, 'descricao' => '', 'quantidade' => '',
            'unidade' => 'UN', 'peso' => '', 'volume' => '', 'valor' => '',
            'nfe_chave' => '', 'nfe_numero' => '', 'nfe_serie' => ''];
    }

    /** Sugestão de número: sequência simples por empresa. Editável, valida unicidade. */
    private function proximoNumero(): string
    {
        $ultimo = OrdemColeta::query()->max(DB::raw('CAST(numero AS INTEGER)'));

        return str_pad((string) (((int) $ultimo) + 1), 6, '0', STR_PAD_LEFT);
    }

    public function adicionarItem(): void
    {
        $this->itens[] = $this->itemVazio();
    }

    public function removerItem(int $i): void
    {
        unset($this->itens[$i]);
        $this->itens = array_values($this->itens);
        $this->somarCarga();
    }

    /** Consolida peso, volumes e valor da carga a partir dos itens. */
    public function somarCarga(): void
    {
        $peso = 0.0;
        $volumes = 0.0;
        $valor = 0.0;

        foreach ($this->itens as $item) {
            $peso += (float) ($item['peso'] ?? 0);
            $volumes += (float) ($item['volume'] ?? 0);
            $valor += (float) ($item['valor'] ?? 0);
        }

        if ($peso > 0) {
            $this->peso_bruto = (string) $peso;
        }
        if ($volumes > 0) {
            $this->volumes = (string) (int) $volumes;
        }
        if ($valor > 0) {
            $this->valor_mercadoria = (string) $valor;
        }
    }

    /** Recalcula o frete pela tabela escolhida ou pela vigente do cliente. */
    public function recalcularFrete(): void
    {
        $tabela = $this->tabelaAplicavel();

        if ($tabela === null) {
            $this->valor_frete_calculado = null;
            $this->freteComponentes = [];
            $this->tabela_frete_id = null;
            session()->flash('aviso_frete', 'Nenhuma tabela de frete vigente para este cliente. Cadastre uma tabela ou informe o valor na observação.');

            return;
        }

        $resultado = CalculadoraFrete::calcular(
            $tabela->itens->map(fn ($i): array => [
                'componente'   => $i->componente,
                'base_calculo' => $i->base_calculo,
                'valor'        => $i->valor,
                'faixa_de'     => $i->faixa_de,
                'faixa_ate'    => $i->faixa_ate,
                'minimo'       => $i->minimo,
                'maximo'       => $i->maximo,
            ])->all(),
            [
                'peso_kg'          => (float) ($this->peso_bruto !== '' ? $this->peso_bruto : 0),
                'valor_mercadoria' => (float) ($this->valor_mercadoria !== '' ? $this->valor_mercadoria : 0),
                'volumes'          => (float) ($this->volumes !== '' ? $this->volumes : 0),
            ],
        );

        $this->tabela_frete_id = $tabela->id;
        $this->valor_frete_calculado = $resultado['total'];
        $this->freteComponentes = $resultado['componentes'];
    }

    private function tabelaAplicavel(): ?TabelaFrete
    {
        // Tabela escolhida manualmente ganha; senão a vigente do cliente; senão a geral.
        if ($this->tabela_frete_id !== null) {
            return TabelaFrete::query()->with('itens')->find($this->tabela_frete_id);
        }

        return TabelaFrete::query()
            ->with('itens')
            ->vigentes()
            ->where(function ($q): void {
                $q->where('pessoa_id', $this->cliente_id)->orWhereNull('pessoa_id');
            })
            ->orderByRaw('pessoa_id IS NULL') // cliente-específica antes da geral
            ->first();
    }

    #[Computed]
    public function clientes()
    {
        return Pessoa::query()->comPapel('cliente')->orderBy('razao_social')->get(['id', 'razao_social']);
    }

    #[Computed]
    public function pessoas()
    {
        return Pessoa::query()->orderBy('razao_social')->get(['id', 'razao_social']);
    }

    #[Computed]
    public function municipios()
    {
        return Municipio::query()->orderBy('uf')->orderBy('nome')->get(['id', 'nome', 'uf']);
    }

    #[Computed]
    public function mercadorias()
    {
        return Mercadoria::query()->where('ativo', true)->orderBy('descricao')->get(['id', 'descricao']);
    }

    #[Computed]
    public function tabelas()
    {
        return TabelaFrete::query()->vigentes()->orderBy('descricao')->get(['id', 'descricao', 'pessoa_id']);
    }

    #[Computed]
    public function filiais()
    {
        return Filial::query()->orderBy('nome_fantasia')->get(['id', 'nome_fantasia', 'razao_social']);
    }

    protected function rules(): array
    {
        return [
            'numero' => ['required', 'string', 'max:20', Rule::unique('ordens_coleta', 'numero')->ignore($this->ordem?->id)],
            'data' => ['required', 'date'],
            'filial_id' => ['required', 'integer', 'exists:filiais,id'],
            'cliente_id' => ['required', 'integer', 'exists:pessoas,id'],
            'tomador_tipo' => ['required', Rule::in(OrdemColeta::TOMADOR_TIPOS)],
            'status' => ['required', Rule::in(OrdemColeta::STATUSES)],
            'tomador_id' => ['nullable', 'integer', 'exists:pessoas,id'],
            'remetente_id' => ['nullable', 'integer', 'exists:pessoas,id'],
            'destinatario_id' => ['nullable', 'integer', 'exists:pessoas,id'],
            'expedidor_id' => ['nullable', 'integer', 'exists:pessoas,id'],
            'recebedor_id' => ['nullable', 'integer', 'exists:pessoas,id'],
            'municipio_inicio_id' => ['nullable', 'integer', 'exists:municipios,id'],
            'municipio_fim_id' => ['nullable', 'integer', 'exists:municipios,id'],
            'previsao_coleta' => ['nullable', 'date'],
            'previsao_entrega' => ['nullable', 'date'],
            'peso_bruto' => ['nullable', 'numeric', 'min:0'],
            'peso_cubado' => ['nullable', 'numeric', 'min:0'],
            'volumes' => ['nullable', 'integer', 'min:0'],
            'valor_mercadoria' => ['nullable', 'numeric', 'min:0'],
            'tabela_frete_id' => ['nullable', 'integer', 'exists:tabelas_frete,id'],
            'observacoes' => ['nullable', 'string'],
            'itens' => ['array', 'min:1'],
            'itens.*.descricao' => ['required', 'string', 'max:200'],
            'itens.*.nfe_chave' => ['nullable', 'string', 'size:44'],
        ];
    }

    protected function messages(): array
    {
        return [
            'itens.min' => 'A ordem precisa de ao menos um item.',
            'itens.*.descricao.required' => 'Descreva o item.',
            'itens.*.nfe_chave.size' => 'A chave da NF-e tem 44 dígitos.',
            'cliente_id.required' => 'Selecione o cliente contratante.',
        ];
    }

    public function salvar()
    {
        $this->validate();
        $this->recalcularFrete();

        $dados = [
            'numero' => trim($this->numero),
            'data' => $this->data,
            'filial_id' => $this->filial_id,
            'cliente_id' => $this->cliente_id,
            'tomador_tipo' => $this->tomador_tipo,
            'status' => $this->status,
            'tomador_id' => $this->tomador_id,
            'remetente_id' => $this->remetente_id,
            'destinatario_id' => $this->destinatario_id,
            'expedidor_id' => $this->expedidor_id,
            'recebedor_id' => $this->recebedor_id,
            'municipio_inicio_id' => $this->municipio_inicio_id,
            'municipio_fim_id' => $this->municipio_fim_id,
            'previsao_coleta' => $this->nulo($this->previsao_coleta),
            'previsao_entrega' => $this->nulo($this->previsao_entrega),
            'peso_bruto' => $this->nuloNum($this->peso_bruto),
            'peso_cubado' => $this->nuloNum($this->peso_cubado),
            'volumes' => $this->volumes !== '' ? (int) $this->volumes : null,
            'valor_mercadoria' => $this->nuloNum($this->valor_mercadoria),
            'tabela_frete_id' => $this->tabela_frete_id,
            'valor_frete_calculado' => $this->valor_frete_calculado,
            'observacoes' => $this->nulo($this->observacoes),
        ];

        DB::transaction(function () use ($dados): void {
            $ordem = $this->ordem?->exists
                ? tap($this->ordem)->update($dados)
                : OrdemColeta::create($dados);

            $mantidos = [];

            foreach ($this->itens as $item) {
                if (trim((string) ($item['descricao'] ?? '')) === '') {
                    continue;
                }

                $atributos = [
                    'mercadoria_id' => $item['mercadoria_id'] ?: null,
                    'descricao' => trim((string) $item['descricao']),
                    'quantidade' => $this->nuloNum((string) ($item['quantidade'] ?? '')),
                    'unidade' => $this->nulo((string) ($item['unidade'] ?? '')),
                    'peso' => $this->nuloNum((string) ($item['peso'] ?? '')),
                    'volume' => $this->nuloNum((string) ($item['volume'] ?? '')),
                    'valor' => $this->nuloNum((string) ($item['valor'] ?? '')),
                    'nfe_chave' => $this->nulo((string) ($item['nfe_chave'] ?? '')),
                    'nfe_numero' => $this->nulo((string) ($item['nfe_numero'] ?? '')),
                    'nfe_serie' => $this->nulo((string) ($item['nfe_serie'] ?? '')),
                ];

                $modelo = ($item['id'] ?? null) !== null
                    ? tap(OcItem::findOrFail($item['id']))->update($atributos)
                    : $ordem->itens()->create($atributos);

                $mantidos[] = $modelo->id;
            }

            $ordem->itens()->whereNotIn('id', $mantidos ?: [0])->delete();

            $this->ordem = $ordem->fresh('itens');
        });

        session()->flash('sucesso', 'Ordem de coleta salva.');

        return $this->redirect(route('ordens-coleta.index'), navigate: true);
    }

    private function str(mixed $valor): string
    {
        return $valor === null ? '' : (string) $valor;
    }

    private function nulo(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    private function nuloNum(string $valor): ?float
    {
        return trim($valor) === '' ? null : (float) $valor;
    }

    public function render(): View
    {
        return view('livewire.ordens-coleta.formulario')
            ->layout('layouts.app', ['title' => $this->ordem?->exists ? 'Ordem ' . $this->ordem->numero : 'Nova ordem de coleta']);
    }
}
