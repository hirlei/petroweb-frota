<?php

declare(strict_types=1);

namespace App\Livewire\Manutencao;

use App\Models\OrdemServico;
use App\Models\OrdemServicoItem;
use App\Models\Pessoa;
use App\Models\Veiculo;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Rotina 2050 — ficha da ordem de serviço.
 *
 * O total é a soma dos itens: peças e serviços entram em `valor_pecas` e
 * `valor_mao_obra` conforme o tipo, e o `valor_total` é recalculado ao salvar.
 * `empresa_id` vem do TenantContext.
 */
class Formulario extends Component
{
    use AuthorizesRequests;

    public ?OrdemServico $os = null;

    public ?int $veiculo_id = null;
    public string $numero = '';
    public string $tipo = 'preventiva';
    public ?int $oficina_id = null;
    public bool $interna = false;
    public string $abertura = '';
    public string $previsao = '';
    public string $encerramento = '';
    public string $odometro = '';
    public string $status = 'aberta';
    public string $observacoes = '';

    /** @var list<array<string,mixed>> */
    public array $itens = [];

    public function mount(?OrdemServico $os = null): void
    {
        if ($os?->exists) {
            $this->authorize('update', $os);
            $this->os = $os->load('itens');
            $this->preencherDe($os);

            return;
        }

        $this->authorize('create', OrdemServico::class);
        $this->abertura = now()->format('Y-m-d');
        $this->itens = [$this->itemVazio()];
    }

    private function preencherDe(OrdemServico $os): void
    {
        $this->veiculo_id = $os->veiculo_id;
        $this->numero = (string) $os->numero;
        $this->tipo = (string) $os->tipo;
        $this->oficina_id = $os->oficina_id;
        $this->interna = (bool) $os->interna;
        $this->abertura = $os->abertura?->format('Y-m-d') ?? '';
        $this->previsao = $os->previsao?->format('Y-m-d') ?? '';
        $this->encerramento = $os->encerramento?->format('Y-m-d') ?? '';
        $this->odometro = $os->odometro === null ? '' : (string) $os->odometro;
        $this->status = (string) $os->status;
        $this->observacoes = (string) ($os->observacoes ?? '');

        $this->itens = $os->itens->map(fn (OrdemServicoItem $i): array => [
            'id' => $i->id,
            'tipo' => $i->tipo,
            'descricao' => $i->descricao,
            'codigo' => (string) ($i->codigo ?? ''),
            'quantidade' => (string) $i->quantidade,
            'valor_unitario' => (string) $i->valor_unitario,
            'garantia_ate' => $i->garantia_ate?->format('Y-m-d') ?? '',
        ])->all();

        if ($this->itens === []) {
            $this->itens = [$this->itemVazio()];
        }
    }

    private function itemVazio(): array
    {
        return ['id' => null, 'tipo' => 'peca', 'descricao' => '', 'codigo' => '',
            'quantidade' => '1', 'valor_unitario' => '', 'garantia_ate' => ''];
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
    public function totais(): array
    {
        $pecas = 0.0;
        $maoObra = 0.0;

        foreach ($this->itens as $item) {
            $subtotal = (($item['quantidade'] ?? '') !== '' ? (float) $item['quantidade'] : 0)
                * (($item['valor_unitario'] ?? '') !== '' ? (float) $item['valor_unitario'] : 0);

            if (($item['tipo'] ?? 'peca') === 'servico') {
                $maoObra += $subtotal;
            } else {
                $pecas += $subtotal;
            }
        }

        return ['pecas' => $pecas, 'mao_obra' => $maoObra, 'total' => $pecas + $maoObra];
    }

    #[Computed]
    public function veiculos()
    {
        return Veiculo::query()->orderBy('placa')->get(['id', 'placa']);
    }

    #[Computed]
    public function oficinas()
    {
        return Pessoa::query()->comPapel('oficina')->orderBy('razao_social')->get(['id', 'razao_social']);
    }

    protected function rules(): array
    {
        return [
            'veiculo_id' => ['required', 'integer', 'exists:veiculos,id'],
            'numero' => [
                'required', 'string', 'max:20',
                Rule::unique('ordens_servico', 'numero')->ignore($this->os?->id),
            ],
            'tipo' => ['required', Rule::in(OrdemServico::TIPOS)],
            'oficina_id' => ['nullable', 'integer', 'exists:pessoas,id'],
            'abertura' => ['required', 'date'],
            'previsao' => ['nullable', 'date'],
            'encerramento' => ['nullable', 'date'],
            'odometro' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(OrdemServico::STATUS)],
            'itens.*.tipo' => ['required', Rule::in(['peca', 'servico'])],
            'itens.*.descricao' => ['required', 'string', 'max:150'],
        ];
    }

    protected function messages(): array
    {
        return [
            'numero.unique' => 'Já existe uma OS com este número.',
            'itens.*.descricao.required' => 'Descreva a peça ou o serviço.',
        ];
    }

    public function salvar()
    {
        $this->validate();

        $totais = $this->totais;

        $dados = [
            'filial_id' => TenantContext::filialId(),
            'veiculo_id' => $this->veiculo_id,
            'numero' => trim($this->numero),
            'tipo' => $this->tipo,
            'oficina_id' => $this->oficina_id,
            'interna' => $this->interna,
            'abertura' => $this->abertura,
            'previsao' => $this->nulo($this->previsao),
            'encerramento' => $this->nulo($this->encerramento),
            'odometro' => $this->odometro === '' ? null : (float) $this->odometro,
            'status' => $this->status,
            'valor_pecas' => $totais['pecas'],
            'valor_mao_obra' => $totais['mao_obra'],
            'valor_total' => $totais['total'],
            'observacoes' => trim($this->observacoes) ?: null,
        ];

        DB::transaction(function () use ($dados): void {
            $os = $this->os?->exists
                ? tap($this->os)->update($dados)
                : OrdemServico::create($dados);

            $mantidos = [];

            foreach ($this->itens as $item) {
                if (($item['descricao'] ?? '') === '') {
                    continue;
                }

                $qtd = ($item['quantidade'] ?? '') !== '' ? (float) $item['quantidade'] : 1;
                $unit = ($item['valor_unitario'] ?? '') !== '' ? (float) $item['valor_unitario'] : 0;

                $atributos = [
                    'tipo' => $item['tipo'],
                    'descricao' => $item['descricao'],
                    'codigo' => $this->nulo((string) ($item['codigo'] ?? '')),
                    'quantidade' => $qtd,
                    'valor_unitario' => $unit,
                    'valor_total' => round($qtd * $unit, 2),
                    'garantia_ate' => $this->nulo((string) ($item['garantia_ate'] ?? '')),
                ];

                $modelo = ($item['id'] ?? null) !== null
                    ? tap(OrdemServicoItem::findOrFail($item['id']))->update($atributos)
                    : $os->itens()->create($atributos);

                $mantidos[] = $modelo->id;
            }

            $os->itens()->whereNotIn('id', $mantidos ?: [0])->delete();

            $this->os = $os->fresh('itens');
        });

        session()->flash('sucesso', 'Ordem de serviço salva.');

        return $this->redirect(route('manutencao.index'), navigate: true);
    }

    private function nulo(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    public function render(): View
    {
        return view('livewire.manutencao.formulario')
            ->layout('layouts.app', ['title' => $this->os?->exists ? 'OS ' . $this->os->numero : 'Nova ordem de serviço']);
    }
}
