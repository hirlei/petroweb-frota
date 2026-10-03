<?php

declare(strict_types=1);

namespace App\Livewire\Viagens;

use App\Models\Composicao;
use App\Models\Cte;
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

    // CT-e a vincular (N:N)
    public ?int $cteParaVincular = null;

    // Custos denormalizados
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

    public function temCtes(): bool
    {
        return (bool) $this->viagem?->exists && $this->viagem->ctes()->exists();
    }

    /** @return array{total:float,margem:float,por_km:?float,percentual:?float,receita:float} */
    #[Computed]
    public function calculo(): array
    {
        // Custo vem dos lançamentos (3080), gravado na viagem; a receita só é
        // digitada quando a viagem não tem CT-e vinculado.
        $total = (float) ($this->viagem?->custo_total ?? 0);
        $receita = $this->temCtes() ? (float) $this->viagem->receita_total : (float) ($this->receita_total ?: 0);
        $margem = $receita - $total;

        $km = (float) ($this->km_final ?: 0) - (float) ($this->km_inicial ?: 0);

        return [
            'total'      => round($total, 2),
            'margem'     => round($margem, 2),
            'por_km'     => $km > 0 ? round($total / $km, 2) : null,
            'percentual' => $receita > 0 ? round($margem / $receita * 100, 1) : null,
            'receita'    => round($receita, 2),
        ];
    }

    /**
     * Pontos para o mapa: os pontos da rota escolhida (coordenada do ponto ou do
     * município); na falta de rota, origem e destino da viagem.
     *
     * @return list<array<string,mixed>>
     */
    #[Computed]
    public function pontosMapa(): array
    {
        if ($this->rota_id !== null) {
            $rota = Rota::query()->with(['pontos.municipio'])->find($this->rota_id);

            if ($rota !== null && $rota->pontos->isNotEmpty()) {
                return $rota->pontos->map(function ($p): ?array {
                    $lat = $p->latitude !== null ? (float) $p->latitude : ($p->municipio?->latitude !== null ? (float) $p->municipio->latitude : null);
                    $lng = $p->longitude !== null ? (float) $p->longitude : ($p->municipio?->longitude !== null ? (float) $p->municipio->longitude : null);

                    if ($lat === null || $lng === null) {
                        return null;
                    }

                    return [
                        'lat' => $lat,
                        'lng' => $lng,
                        'label' => $p->descricao ?: ($p->municipio ? $p->municipio->nome . '/' . $p->municipio->uf : ucfirst((string) $p->tipo)),
                        'tipo' => $p->tipo,
                    ];
                })->filter()->values()->all();
            }
        }

        // Sem rota: origem e destino da viagem.
        $ids = array_values(array_filter([$this->municipio_origem_id, $this->municipio_destino_id]));
        $municipios = $ids === [] ? collect() : Municipio::whereIn('id', $ids)->get()->keyBy('id');
        $pontos = [];

        foreach ([[$this->municipio_origem_id, 'origem'], [$this->municipio_destino_id, 'destino']] as [$id, $tipo]) {
            $m = $id ? $municipios->get($id) : null;

            if ($m?->latitude !== null) {
                $pontos[] = ['lat' => (float) $m->latitude, 'lng' => (float) $m->longitude, 'label' => $m->nome . '/' . $m->uf, 'tipo' => $tipo];
            }
        }

        return $pontos;
    }

    /** CT-e já vinculados a esta viagem. */
    #[Computed]
    public function ctesVinculados()
    {
        if (! $this->viagem?->exists) {
            return collect();
        }

        return $this->viagem->ctes()->with(['tomador', 'municipioFim'])->get();
    }

    /** CT-e autorizados que ainda podem ser vinculados. */
    #[Computed]
    public function ctesDisponiveis()
    {
        if (! $this->viagem?->exists) {
            return collect();
        }

        return Cte::query()->autorizados()
            ->whereDoesntHave('viagens', fn ($q) => $q->where('viagens.id', $this->viagem->id))
            ->with('tomador')->orderByDesc('emissao')->get();
    }

    public function vincularCte(int $cteId): void
    {
        $this->authorize('update', $this->viagem);

        $seq = (int) ($this->viagem->ctes()->reorder()->max('sequencia') ?? 0) + 1;
        $this->viagem->ctes()->syncWithoutDetaching([$cteId => ['sequencia' => $seq, 'papel' => 'principal']]);
        $this->viagem->recalcularReceitaDosCtes();
        $this->viagem->refresh();

        session()->flash('sucesso', 'CT-e vinculado — receita da viagem atualizada.');
    }

    public function desvincularCte(int $cteId): void
    {
        $this->authorize('update', $this->viagem);

        $this->viagem->ctes()->detach($cteId);
        $this->viagem->recalcularReceitaDosCtes();
        $this->viagem->refresh();

        session()->flash('sucesso', 'CT-e desvinculado — receita da viagem atualizada.');
    }

    /** Traçado pela estrada da rota escolhida (se calculado). */
    #[Computed]
    public function geometriaViagem(): ?array
    {
        if ($this->rota_id === null) {
            return null;
        }

        return Rota::query()->find($this->rota_id)?->geometria;
    }

    /** Caminhão na última posição de GPS conhecida do veículo de tração. */
    #[Computed]
    public function caminhaoViagem(): ?array
    {
        if ($this->veiculo_tracao_id === null) {
            return null;
        }

        $veiculo = Veiculo::query()->with('ultimaPosicao')->find($this->veiculo_tracao_id);
        $pos = $veiculo?->ultimaPosicao;

        if ($pos === null) {
            return null;
        }

        $motorista = $this->motorista_id ? Motorista::query()->with('pessoa')->find($this->motorista_id) : null;
        $velocidade = $pos->velocidade_kmh !== null ? number_format((float) $pos->velocidade_kmh, 0, ',', '.') . ' km/h' : '—';
        $quando = $pos->capturado_em?->diffForHumans() ?? '';

        $popup = '<div style="font-size:12px;line-height:1.5">'
            . '<strong>' . e($veiculo->placaFormatada()) . '</strong><br>'
            . ($motorista?->pessoa?->razao_social ? e($motorista->pessoa->razao_social) . '<br>' : '')
            . 'Velocidade: ' . e($velocidade) . '<br>'
            . '<span style="color:#8f8a82">' . e($quando) . '</span></div>';

        return [
            'lat' => (float) $pos->latitude,
            'lng' => (float) $pos->longitude,
            'popup' => $popup,
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
            'receita_total' => (float) ($this->receita_total ?: 0),
            'observacoes' => $this->nulo($this->observacoes),
        ];

        DB::transaction(function () use ($dados): void {
            $viagem = $this->viagem?->exists
                ? tap($this->viagem)->fill($dados)
                : new Viagem($dados);

            $viagem->save();
            // Custo, receita e margem vêm dos lançamentos (km e dias mudam a conta).
            app(\App\Services\Operacao\CustosDaViagem::class)->recalcular($viagem);

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
