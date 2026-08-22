<?php

declare(strict_types=1);

namespace App\Livewire\Rotas;

use App\Models\Municipio;
use App\Models\Rota;
use App\Models\RotaPonto;
use App\Services\Roteirizacao\RoteirizacaoException;
use App\Services\Roteirizacao\Roteirizador;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Rotina 3030 — ficha da rota.
 *
 * Cabeçalho com trecho e estimativas; pontos intermediários na ordem em que
 * aparecem. As restrições (altura, peso, janela de horário) vão no jsonb
 * `restricoes`. `empresa_id` vem do TenantContext.
 */
class Formulario extends Component
{
    use AuthorizesRequests;

    public ?Rota $rota = null;

    public string $descricao = '';
    public ?int $municipio_origem_id = null;
    public ?int $municipio_destino_id = null;
    public string $distancia_km = '';
    public string $tempo_estimado_min = '';
    public string $valor_pedagio_estimado = '';
    public bool $ativa = true;

    // Restrições (viram o jsonb `restricoes`)
    public string $restr_altura_m = '';
    public string $restr_peso_t = '';
    public string $restr_janela = '';

    /** Traçado pela estrada calculado pelo motor de rotas. */
    public ?array $geometria = null;

    /** @var list<array<string,mixed>> */
    public array $pontos = [];

    public function mount(?Rota $rota = null): void
    {
        if ($rota?->exists) {
            $this->authorize('update', $rota);
            $this->rota = $rota->load('pontos');
            $this->preencherDe($rota);

            return;
        }

        $this->authorize('create', Rota::class);
    }

    private function preencherDe(Rota $rota): void
    {
        $this->descricao = (string) $rota->descricao;
        $this->municipio_origem_id = $rota->municipio_origem_id;
        $this->municipio_destino_id = $rota->municipio_destino_id;
        $this->distancia_km = $rota->distancia_km === null ? '' : (string) $rota->distancia_km;
        $this->tempo_estimado_min = $rota->tempo_estimado_min === null ? '' : (string) $rota->tempo_estimado_min;
        $this->valor_pedagio_estimado = $rota->valor_pedagio_estimado === null ? '' : (string) $rota->valor_pedagio_estimado;
        $this->ativa = (bool) $rota->ativa;

        $restricoes = $rota->restricoes ?? [];
        $this->restr_altura_m = (string) ($restricoes['altura_m'] ?? '');
        $this->restr_peso_t = (string) ($restricoes['peso_t'] ?? '');
        $this->restr_janela = (string) ($restricoes['janela'] ?? '');
        $this->geometria = $rota->geometria;

        $this->pontos = $rota->pontos->map(fn (RotaPonto $p): array => [
            'id' => $p->id,
            'tipo' => $p->tipo,
            'municipio_id' => $p->municipio_id,
            'descricao' => (string) $p->descricao,
            'distancia_acumulada_km' => $p->distancia_acumulada_km === null ? '' : (string) $p->distancia_acumulada_km,
            'valor_pedagio' => $p->valor_pedagio === null ? '' : (string) $p->valor_pedagio,
            'latitude' => $p->latitude === null ? '' : (string) $p->latitude,
            'longitude' => $p->longitude === null ? '' : (string) $p->longitude,
        ])->all();
    }

    public function adicionarPonto(): void
    {
        $this->pontos[] = ['id' => null, 'tipo' => 'passagem', 'municipio_id' => null,
            'descricao' => '', 'distancia_acumulada_km' => '', 'valor_pedagio' => '',
            'latitude' => '', 'longitude' => ''];
    }

    public function removerPonto(int $i): void
    {
        unset($this->pontos[$i]);
        $this->pontos = array_values($this->pontos);
    }

    /**
     * Calcula o traçado pela estrada a partir dos pontos com coordenada, via
     * motor de rotas. Guarda em memória; persiste no salvar.
     */
    public function calcularTracado(Roteirizador $roteirizador): void
    {
        $coords = array_map(fn (array $p): array => [$p['lat'], $p['lng']], $this->pontosMapa);

        if (count($coords) < 2) {
            session()->flash('erro_tracado', 'Informe coordenadas em pelo menos dois pontos para traçar a rota.');

            return;
        }

        try {
            $resultado = $roteirizador->rotear($coords);
        } catch (RoteirizacaoException $e) {
            session()->flash('erro_tracado', $e->getMessage());

            return;
        }

        $this->geometria = [
            'pontos' => $resultado['pontos'],
            'distancia_km' => $resultado['distancia_km'],
            'duracao_min' => $resultado['duracao_min'],
        ];

        // Preenche distância/tempo do cabeçalho quando ainda vazios.
        if ($this->distancia_km === '') {
            $this->distancia_km = (string) $resultado['distancia_km'];
        }
        if ($this->tempo_estimado_min === '') {
            $this->tempo_estimado_min = (string) $resultado['duracao_min'];
        }

        session()->flash('sucesso_tracado', "Traçado calculado: {$resultado['distancia_km']} km · {$resultado['duracao_min']} min. Salve a rota para guardar.");
    }

    #[Computed]
    public function municipios()
    {
        return Municipio::orderBy('uf')->orderBy('nome')->get(['id', 'nome', 'uf']);
    }

    #[Computed]
    public function pedagioDosPontos(): float
    {
        return array_reduce($this->pontos, function (float $soma, array $p): float {
            return $soma + (($p['valor_pedagio'] ?? '') !== '' ? (float) $p['valor_pedagio'] : 0);
        }, 0.0);
    }

    /**
     * Pontos prontos para o mapa: coordenada do próprio ponto ou, na falta, a do
     * município. Só entram os que têm coordenada.
     *
     * @return list<array<string,mixed>>
     */
    #[Computed]
    public function pontosMapa(): array
    {
        $ids = collect($this->pontos)->pluck('municipio_id')->filter()->unique()->all();
        $coordsMunicipio = $ids === []
            ? collect()
            : Municipio::whereIn('id', $ids)->get(['id', 'nome', 'uf', 'latitude', 'longitude'])->keyBy('id');

        $mapa = [];

        foreach ($this->pontos as $p) {
            $lat = ($p['latitude'] ?? '') !== '' ? (float) $p['latitude'] : null;
            $lng = ($p['longitude'] ?? '') !== '' ? (float) $p['longitude'] : null;
            $municipio = $p['municipio_id'] ? $coordsMunicipio->get($p['municipio_id']) : null;

            if ($lat === null && $municipio?->latitude !== null) {
                $lat = (float) $municipio->latitude;
                $lng = (float) $municipio->longitude;
            }

            if ($lat === null || $lng === null) {
                continue;
            }

            $rotulo = trim((string) ($p['descricao'] ?? '')) !== ''
                ? $p['descricao']
                : ($municipio ? $municipio->nome . '/' . $municipio->uf : ucfirst((string) $p['tipo']));

            $mapa[] = ['lat' => $lat, 'lng' => $lng, 'label' => $rotulo, 'tipo' => $p['tipo']];
        }

        return $mapa;
    }

    protected function rules(): array
    {
        return [
            'descricao' => ['required', 'string', 'max:150'],
            'municipio_origem_id' => ['required', 'integer', 'exists:municipios,id'],
            'municipio_destino_id' => ['required', 'integer', 'exists:municipios,id'],
            'distancia_km' => ['nullable', 'numeric', 'min:0'],
            'tempo_estimado_min' => ['nullable', 'integer', 'min:0'],
            'valor_pedagio_estimado' => ['nullable', 'numeric', 'min:0'],
            'pontos.*.tipo' => ['required', Rule::in(Rota::TIPOS_PONTO)],
            'pontos.*.municipio_id' => ['nullable', 'integer', 'exists:municipios,id'],
            'pontos.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'pontos.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    protected function messages(): array
    {
        return [
            'municipio_origem_id.required' => 'Escolha o município de origem.',
            'municipio_destino_id.required' => 'Escolha o município de destino.',
        ];
    }

    public function salvar()
    {
        $this->validate();

        $restricoes = array_filter([
            'altura_m' => $this->restr_altura_m !== '' ? (float) $this->restr_altura_m : null,
            'peso_t' => $this->restr_peso_t !== '' ? (float) $this->restr_peso_t : null,
            'janela' => $this->nulo($this->restr_janela),
        ], fn ($v) => $v !== null);

        $dados = [
            'descricao' => trim($this->descricao),
            'municipio_origem_id' => $this->municipio_origem_id,
            'municipio_destino_id' => $this->municipio_destino_id,
            'distancia_km' => $this->nuloNum($this->distancia_km),
            'tempo_estimado_min' => $this->tempo_estimado_min === '' ? null : (int) $this->tempo_estimado_min,
            'valor_pedagio_estimado' => $this->nuloNum($this->valor_pedagio_estimado),
            'restricoes' => $restricoes === [] ? null : $restricoes,
            'geometria' => $this->geometria,
            'ativa' => $this->ativa,
        ];

        DB::transaction(function () use ($dados): void {
            $rota = $this->rota?->exists
                ? tap($this->rota)->update($dados)
                : Rota::create($dados);

            $mantidos = [];
            $ordem = 1;

            foreach ($this->pontos as $ponto) {
                $atributos = [
                    'ordem' => $ordem++,
                    'tipo' => $ponto['tipo'],
                    'municipio_id' => $ponto['municipio_id'] ?: null,
                    'descricao' => $this->nulo((string) ($ponto['descricao'] ?? '')),
                    'distancia_acumulada_km' => $this->nuloNum((string) ($ponto['distancia_acumulada_km'] ?? '')),
                    'valor_pedagio' => $this->nuloNum((string) ($ponto['valor_pedagio'] ?? '')),
                    'latitude' => $this->nuloNum((string) ($ponto['latitude'] ?? '')),
                    'longitude' => $this->nuloNum((string) ($ponto['longitude'] ?? '')),
                ];

                $modelo = ($ponto['id'] ?? null) !== null
                    ? tap(RotaPonto::findOrFail($ponto['id']))->update($atributos)
                    : $rota->pontos()->create($atributos);

                $mantidos[] = $modelo->id;
            }

            $rota->pontos()->whereNotIn('id', $mantidos ?: [0])->delete();

            $this->rota = $rota->fresh('pontos');
        });

        session()->flash('sucesso', 'Rota salva.');

        return $this->redirect(route('rotas.index'), navigate: true);
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
        return view('livewire.rotas.formulario')
            ->layout('layouts.app', ['title' => $this->rota?->descricao ?? 'Nova rota']);
    }
}
