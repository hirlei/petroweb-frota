<?php

declare(strict_types=1);

namespace App\Livewire\Viagens;

use App\Models\Composicao;
use App\Models\Filial;
use App\Models\Motorista;
use App\Models\Municipio;
use App\Models\Rota;
use App\Models\Veiculo;
use App\Models\Viagem;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Rotina 3020 — ficha/painel da viagem.
 *
 * Cabeçalho + composição (com snapshot congelado) + condução + trecho + custos
 * denormalizados. Margem, custo total e custo por km derivam dos custos e são
 * recalculados ao salvar. O vínculo com CT-e (N:N) aparece no painel, mas fica
 * pendente até o módulo fiscal (4010) existir.
 */
class Formulario extends Component
{
    use AuthorizesRequests;

    public ?Viagem $viagem = null;

    // Cabeçalho
    public string $numero = '';
    public string $tipo = 'carga_lotacao';
    public ?int $filial_id = null;
    public string $status = 'planejada';

    // Composição / condução
    public ?int $composicao_id = null;
    public ?int $veiculo_tracao_id = null;
    /** @var list<string> */
    public array $composicao_snapshot = [];
    public ?int $motorista_id = null;
    public ?int $motorista_2_id = null;

    // Trecho
    public ?int $rota_id = null;
    public ?int $municipio_origem_id = null;
    public ?int $municipio_destino_id = null;
    public string $saida_prevista = '';
    public string $saida_real = '';
    public string $chegada_prevista = '';
    public string $chegada_real = '';
    public string $km_inicial = '';
    public string $km_final = '';

    // Consolidados
    public string $peso_total = '';
    public string $valor_carga = '';

    // Custos denormalizados
    public string $custo_combustivel = '';
    public string $custo_pedagio = '';
    public string $custo_motorista = '';
    public string $custo_manutencao = '';
    public string $custo_outros = '';
    public string $receita_total = '';

    public string $observacoes = '';

    public function mount(?Viagem $viagem = null): void
    {
        if ($viagem?->exists) {
            $this->authorize('update', $viagem);
            $this->viagem = $viagem;
            $this->preencherDe($viagem);

            return;
        }

        $this->authorize('create', Viagem::class);
        $this->numero = $this->proximoNumero();
        $this->filial_id = Filial::query()->orderBy('id')->value('id');
    }

    private function preencherDe(Viagem $viagem): void
    {
        $this->numero = (string) $viagem->numero;
        $this->tipo = (string) $viagem->tipo;
        $this->filial_id = $viagem->filial_id;
        $this->status = (string) $viagem->status;
        $this->veiculo_tracao_id = $viagem->veiculo_tracao_id;
        $this->composicao_snapshot = is_array($viagem->composicao_snapshot) ? $viagem->composicao_snapshot : [];
        $this->motorista_id = $viagem->motorista_id;
        $this->motorista_2_id = $viagem->motorista_2_id;
        $this->rota_id = $viagem->rota_id;
        $this->municipio_origem_id = $viagem->municipio_origem_id;
        $this->municipio_destino_id = $viagem->municipio_destino_id;
        $this->saida_prevista = $this->dt($viagem->saida_prevista);
        $this->saida_real = $this->dt($viagem->saida_real);
        $this->chegada_prevista = $this->dt($viagem->chegada_prevista);
        $this->chegada_real = $this->dt($viagem->chegada_real);
        $this->km_inicial = $this->str($viagem->km_inicial);
        $this->km_final = $this->str($viagem->km_final);
        $this->peso_total = $this->str($viagem->peso_total);
        $this->valor_carga = $this->str($viagem->valor_carga);
        $this->custo_combustivel = $this->str($viagem->custo_combustivel);
        $this->custo_pedagio = $this->str($viagem->custo_pedagio);
        $this->custo_motorista = $this->str($viagem->custo_motorista);
        $this->custo_manutencao = $this->str($viagem->custo_manutencao);
        $this->custo_outros = $this->str($viagem->custo_outros);
        $this->receita_total = $this->str($viagem->receita_total);
        $this->observacoes = (string) ($viagem->observacoes ?? '');
    }

    private function proximoNumero(): string
    {
        $ultimo = Viagem::query()->max(DB::raw('CAST(numero AS INTEGER)'));

        return str_pad((string) (((int) $ultimo) + 1), 6, '0', STR_PAD_LEFT);
    }

    /** Ao escolher a composição, congela o snapshot dos reboques e fixa a tração. */
    public function updatedComposicaoId(mixed $valor): void
    {
        if (! $valor) {
            return;
        }

        $composicao = Composicao::query()->with('veiculos')->find($valor);

        if ($composicao === null) {
            return;
        }

        $this->veiculo_tracao_id = $composicao->veiculo_tracao_id;
        $this->composicao_snapshot = $composicao->veiculos
            ->where('tipo', '!=', Veiculo::TIPO_TRACAO)
            ->map(fn (Veiculo $v) => $v->placaFormatada())
            ->values()
            ->all();
    }

    /** Ao escolher a rota, sugere origem/destino e km estimado. */
    public function updatedRotaId(mixed $valor): void
    {
        if (! $valor) {
            return;
        }

        $rota = Rota::query()->find($valor);

        if ($rota === null) {
            return;
        }

        $this->municipio_origem_id ??= $rota->municipio_origem_id;
        $this->municipio_destino_id ??= $rota->municipio_destino_id;
    }

    /** @return array{total:float,margem:float,por_km:?float,percentual:?float} */
    #[Computed]
    public function calculo(): array
    {
        $total = (float) ($this->custo_combustivel ?: 0)
            + (float) ($this->custo_pedagio ?: 0)
            + (float) ($this->custo_motorista ?: 0)
            + (float) ($this->custo_manutencao ?: 0)
            + (float) ($this->custo_outros ?: 0);

        $receita = (float) ($this->receita_total ?: 0);
        $margem = $receita - $total;

        $km = (float) ($this->km_final ?: 0) - (float) ($this->km_inicial ?: 0);

        return [
            'total'      => round($total, 2),
            'margem'     => round($margem, 2),
            'por_km'     => $km > 0 ? round($total / $km, 2) : null,
            'percentual' => $receita > 0 ? round($margem / $receita * 100, 1) : null,
        ];
    }

    #[Computed]
    public function composicoes()
    {
        return Composicao::query()->where('ativa', true)->with('tracao')->get(['id', 'descricao', 'veiculo_tracao_id']);
    }

    #[Computed]
    public function veiculosTracao()
    {
        return Veiculo::query()->where('tipo', Veiculo::TIPO_TRACAO)->orderBy('placa')->get(['id', 'placa']);
    }

    #[Computed]
    public function motoristas()
    {
        return Motorista::query()->where('status', 'ativo')->with('pessoa')->get()
            ->sortBy(fn (Motorista $m) => $m->pessoa?->razao_social)
            ->values();
    }

    #[Computed]
    public function rotas()
    {
        return Rota::query()->where('ativa', true)->orderBy('descricao')->get(['id', 'descricao', 'municipio_origem_id', 'municipio_destino_id']);
    }

    #[Computed]
    public function municipios()
    {
        return Municipio::query()->orderBy('uf')->orderBy('nome')->get(['id', 'nome', 'uf']);
    }

    #[Computed]
    public function filiais()
    {
        return Filial::query()->orderBy('nome_fantasia')->get(['id', 'nome_fantasia', 'razao_social']);
    }

    protected function rules(): array
    {
        return [
            'numero' => ['required', 'string', 'max:20', Rule::unique('viagens', 'numero')->ignore($this->viagem?->id)],
            'tipo' => ['required', Rule::in(Viagem::TIPOS)],
            'filial_id' => ['required', 'integer', 'exists:filiais,id'],
            'status' => ['required', Rule::in(Viagem::STATUSES)],
            'veiculo_tracao_id' => ['required', 'integer', 'exists:veiculos,id'],
            'motorista_id' => ['required', 'integer', 'exists:motoristas,id'],
            'motorista_2_id' => ['nullable', 'integer', 'exists:motoristas,id', 'different:motorista_id'],
            'rota_id' => ['nullable', 'integer', 'exists:rotas,id'],
            'municipio_origem_id' => ['nullable', 'integer', 'exists:municipios,id'],
            'municipio_destino_id' => ['nullable', 'integer', 'exists:municipios,id'],
            'saida_prevista' => ['nullable', 'date'],
            'saida_real' => ['nullable', 'date'],
            'chegada_prevista' => ['nullable', 'date'],
            'chegada_real' => ['nullable', 'date'],
            'km_inicial' => ['nullable', 'numeric', 'min:0'],
            'km_final' => ['nullable', 'numeric', 'min:0', 'gte:km_inicial'],
            'peso_total' => ['nullable', 'numeric', 'min:0'],
            'valor_carga' => ['nullable', 'numeric', 'min:0'],
            'custo_combustivel' => ['nullable', 'numeric', 'min:0'],
            'custo_pedagio' => ['nullable', 'numeric', 'min:0'],
            'custo_motorista' => ['nullable', 'numeric', 'min:0'],
            'custo_manutencao' => ['nullable', 'numeric', 'min:0'],
            'custo_outros' => ['nullable', 'numeric', 'min:0'],
            'receita_total' => ['nullable', 'numeric', 'min:0'],
            'observacoes' => ['nullable', 'string'],
        ];
    }

    protected function messages(): array
    {
        return [
            'veiculo_tracao_id.required' => 'Selecione o veículo de tração (ou uma composição).',
            'motorista_id.required' => 'Selecione o motorista.',
            'motorista_2_id.different' => 'O segundo motorista deve ser diferente do primeiro.',
            'km_final.gte' => 'O km final não pode ser menor que o inicial.',
        ];
    }

    public function salvar()
    {
        $this->validate();

        $kmInicial = $this->nuloNum($this->km_inicial);
        $kmFinal = $this->nuloNum($this->km_final);
        $kmPercorrido = ($kmInicial !== null && $kmFinal !== null) ? $kmFinal - $kmInicial : null;

        $dados = [
            'numero' => trim($this->numero),
            'tipo' => $this->tipo,
            'filial_id' => $this->filial_id,
            'status' => $this->status,
            'veiculo_tracao_id' => $this->veiculo_tracao_id,
            'composicao_snapshot' => $this->composicao_snapshot ?: null,
            'motorista_id' => $this->motorista_id,
            'motorista_2_id' => $this->motorista_2_id,
            'rota_id' => $this->rota_id,
            'municipio_origem_id' => $this->municipio_origem_id,
            'municipio_destino_id' => $this->municipio_destino_id,
            'saida_prevista' => $this->nulo($this->saida_prevista),
            'saida_real' => $this->nulo($this->saida_real),
            'chegada_prevista' => $this->nulo($this->chegada_prevista),
            'chegada_real' => $this->nulo($this->chegada_real),
            'km_inicial' => $kmInicial,
            'km_final' => $kmFinal,
            'km_percorrido' => $kmPercorrido,
            'peso_total' => $this->nuloNum($this->peso_total),
            'valor_carga' => $this->nuloNum($this->valor_carga),
            'custo_combustivel' => (float) ($this->custo_combustivel ?: 0),
            'custo_pedagio' => (float) ($this->custo_pedagio ?: 0),
            'custo_motorista' => (float) ($this->custo_motorista ?: 0),
            'custo_manutencao' => (float) ($this->custo_manutencao ?: 0),
            'custo_outros' => (float) ($this->custo_outros ?: 0),
            'receita_total' => (float) ($this->receita_total ?: 0),
            'observacoes' => $this->nulo($this->observacoes),
        ];

        DB::transaction(function () use ($dados): void {
            $viagem = $this->viagem?->exists
                ? tap($this->viagem)->fill($dados)
                : new Viagem($dados);

            $viagem->consolidarCustos();
            $viagem->save();

            $this->viagem = $viagem->fresh();
        });

        session()->flash('sucesso', 'Viagem salva.');

        return $this->redirect(route('viagens.index'), navigate: true);
    }

    private function str(mixed $valor): string
    {
        return $valor === null ? '' : (string) $valor;
    }

    private function dt(mixed $valor): string
    {
        return $valor?->format('Y-m-d\TH:i') ?? '';
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
        return view('livewire.viagens.formulario')
            ->layout('layouts.app', ['title' => $this->viagem?->exists ? 'Viagem ' . $this->viagem->numero : 'Nova viagem']);
    }
}
