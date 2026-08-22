<?php

declare(strict_types=1);

namespace App\Livewire\TabelasFrete;

use App\Models\Municipio;
use App\Models\Pessoa;
use App\Models\TabelaFrete;
use App\Models\TabelaFreteItem;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Rotina 1030 — ficha da tabela de frete.
 *
 * O cabeçalho define para quem e quando a tabela vale; os itens definem o
 * preço, um componente por linha. `empresa_id` vem do TenantContext.
 */
class Formulario extends Component
{
    use AuthorizesRequests;

    public ?TabelaFrete $tabela = null;

    public string $descricao = '';
    public ?int $pessoa_id = null;
    public string $vigencia_inicio = '';
    public string $vigencia_fim = '';
    public string $uf_origem = '';
    public string $uf_destino = '';
    public ?int $municipio_origem_id = null;
    public ?int $municipio_destino_id = null;
    public string $tipo_veiculo = '';
    public bool $ativo = true;

    /** @var list<array<string,mixed>> */
    public array $itens = [];

    public function mount(?TabelaFrete $tabela = null): void
    {
        if ($tabela?->exists) {
            $this->authorize('update', $tabela);
            $this->tabela = $tabela->load('itens');
            $this->preencherDe($tabela);

            return;
        }

        $this->authorize('create', TabelaFrete::class);
        $this->itens = [$this->itemVazio()];
    }

    private function preencherDe(TabelaFrete $tabela): void
    {
        $this->descricao = (string) $tabela->descricao;
        $this->pessoa_id = $tabela->pessoa_id;
        $this->vigencia_inicio = $tabela->vigencia_inicio?->format('Y-m-d') ?? '';
        $this->vigencia_fim = $tabela->vigencia_fim?->format('Y-m-d') ?? '';
        $this->uf_origem = (string) ($tabela->uf_origem ?? '');
        $this->uf_destino = (string) ($tabela->uf_destino ?? '');
        $this->municipio_origem_id = $tabela->municipio_origem_id;
        $this->municipio_destino_id = $tabela->municipio_destino_id;
        $this->tipo_veiculo = (string) ($tabela->tipo_veiculo ?? '');
        $this->ativo = (bool) $tabela->ativo;

        $this->itens = $tabela->itens->map(fn (TabelaFreteItem $i): array => [
            'id' => $i->id,
            'componente' => $i->componente,
            'base_calculo' => $i->base_calculo,
            'faixa_de' => $i->faixa_de === null ? '' : (string) $i->faixa_de,
            'faixa_ate' => $i->faixa_ate === null ? '' : (string) $i->faixa_ate,
            'valor' => (string) $i->valor,
            'minimo' => $i->minimo === null ? '' : (string) $i->minimo,
            'maximo' => $i->maximo === null ? '' : (string) $i->maximo,
        ])->all();

        if ($this->itens === []) {
            $this->itens = [$this->itemVazio()];
        }
    }

    private function itemVazio(): array
    {
        return ['id' => null, 'componente' => 'peso', 'base_calculo' => 'por_kg',
            'faixa_de' => '', 'faixa_ate' => '', 'valor' => '', 'minimo' => '', 'maximo' => ''];
    }

    public function adicionarItem(): void
    {
        $this->itens[] = $this->itemVazio();
    }

    public function removerItem(int $i): void
    {
        unset($this->itens[$i]);
        $this->itens = array_values($this->itens);
    }

    #[Computed]
    public function clientes()
    {
        return Pessoa::query()->comPapel('cliente')->orderBy('razao_social')->get(['id', 'razao_social']);
    }

    #[Computed]
    public function municipios()
    {
        return Municipio::orderBy('uf')->orderBy('nome')->get(['id', 'nome', 'uf']);
    }

    protected function rules(): array
    {
        return [
            'descricao' => ['required', 'string', 'max:150'],
            'pessoa_id' => ['nullable', 'integer', 'exists:pessoas,id'],
            'vigencia_inicio' => ['required', 'date'],
            'vigencia_fim' => ['nullable', 'date', 'after_or_equal:vigencia_inicio'],
            'uf_origem' => ['nullable', 'string', 'size:2'],
            'uf_destino' => ['nullable', 'string', 'size:2'],
            'municipio_origem_id' => ['nullable', 'integer', 'exists:municipios,id'],
            'municipio_destino_id' => ['nullable', 'integer', 'exists:municipios,id'],
            'tipo_veiculo' => ['nullable', 'string', 'max:30'],
            'itens' => ['array', 'min:1'],
            'itens.*.componente' => ['required', Rule::in(TabelaFrete::COMPONENTES)],
            'itens.*.base_calculo' => ['required', Rule::in(TabelaFrete::BASES_CALCULO)],
            'itens.*.valor' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function messages(): array
    {
        return [
            'itens.min' => 'A tabela precisa de ao menos um componente de preço.',
            'vigencia_fim.after_or_equal' => 'A vigência final não pode ser antes do início.',
        ];
    }

    public function salvar()
    {
        $this->validate();

        $dados = [
            'descricao' => trim($this->descricao),
            'pessoa_id' => $this->pessoa_id,
            'vigencia_inicio' => $this->vigencia_inicio,
            'vigencia_fim' => $this->nulo($this->vigencia_fim),
            'uf_origem' => $this->uf_origem !== '' ? strtoupper($this->uf_origem) : null,
            'uf_destino' => $this->uf_destino !== '' ? strtoupper($this->uf_destino) : null,
            'municipio_origem_id' => $this->municipio_origem_id,
            'municipio_destino_id' => $this->municipio_destino_id,
            'tipo_veiculo' => $this->nulo($this->tipo_veiculo),
            'ativo' => $this->ativo,
        ];

        DB::transaction(function () use ($dados): void {
            $tabela = $this->tabela?->exists
                ? tap($this->tabela)->update($dados)
                : TabelaFrete::create($dados);

            $mantidos = [];
            $ordem = 0;

            foreach ($this->itens as $item) {
                if (($item['valor'] ?? '') === '') {
                    continue;
                }

                $atributos = [
                    'componente' => $item['componente'],
                    'base_calculo' => $item['base_calculo'],
                    'faixa_de' => $this->nuloNum((string) ($item['faixa_de'] ?? '')),
                    'faixa_ate' => $this->nuloNum((string) ($item['faixa_ate'] ?? '')),
                    'valor' => (float) $item['valor'],
                    'minimo' => $this->nuloNum((string) ($item['minimo'] ?? '')),
                    'maximo' => $this->nuloNum((string) ($item['maximo'] ?? '')),
                    'ordem' => $ordem++,
                ];

                $modelo = ($item['id'] ?? null) !== null
                    ? tap(TabelaFreteItem::findOrFail($item['id']))->update($atributos)
                    : $tabela->itens()->create($atributos);

                $mantidos[] = $modelo->id;
            }

            $tabela->itens()->whereNotIn('id', $mantidos ?: [0])->delete();

            $this->tabela = $tabela->fresh('itens');
        });

        session()->flash('sucesso', 'Tabela de frete salva.');

        return $this->redirect(route('tabelas-frete.index'), navigate: true);
    }

    private function nulo(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    private function nuloNum(string $valor): ?float
    {
        return $valor === '' ? null : (float) $valor;
    }

    public function render(): View
    {
        return view('livewire.tabelas-frete.formulario')
            ->layout('layouts.app', ['title' => $this->tabela?->descricao ?? 'Nova tabela de frete']);
    }
}
