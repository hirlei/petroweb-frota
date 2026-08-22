<?php

declare(strict_types=1);

namespace App\Livewire\Veiculos;

use App\Domain\Frota\RegrasVeiculo;
use App\Models\Carroceria;
use App\Models\CvcConfiguracao;
use App\Models\Filial;
use App\Models\Municipio;
use App\Models\Pessoa;
use App\Models\Veiculo;
use App\Models\VeiculoDocumento;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Rotina 2010 — ficha do veículo.
 *
 * `empresa_id` não existe neste formulário — a trait PertenceAEmpresa carimba
 * a partir do TenantContext. `categCombVeic` NÃO é campo: deriva dos eixos.
 * O bloco de proprietário só aparece quando a propriedade é de terceiro, e é
 * o RNTRC dele (não o da empresa) que vai ao MDF-e.
 */
class Formulario extends Component
{
    use AuthorizesRequests;

    public ?Veiculo $veiculo = null;

    public string $aba = 'identificacao';

    // Identificação
    public string $placa = '';
    public string $renavam = '';
    public string $chassi = '';
    public string $tipo = RegrasVeiculo::TIPO_TRACAO;
    public string $marca = '';
    public string $modelo = '';
    public string $ano_fabricacao = '';
    public string $ano_modelo = '';
    public string $cor = '';
    public string $uf_licenciamento = '';
    public ?int $municipio_licenciamento_id = null;
    public ?int $filial_id = null;
    public string $status = 'ativo';

    // Ficha técnica
    public string $eixos = '2';
    public string $tp_rod = '';
    public string $tp_car = '';
    public ?int $carroceria_id = null;
    public ?int $cvc_configuracao_id = null;
    public string $tracao = '';
    public string $tara_kg = '';
    public string $pbt_kg = '';
    public string $pbtc_kg = '';
    public string $capacidade_kg = '';
    public string $capacidade_m3 = '';
    public string $comprimento_m = '';
    public string $largura_m = '';
    public string $altura_m = '';
    public bool $exige_aet = false;
    public string $combustivel = '';
    public string $capacidade_tanque_l = '';
    public string $media_referencia_kml = '';

    // Propriedade
    public string $propriedade = RegrasVeiculo::PROPRIEDADE_PROPRIA;
    public ?int $proprietario_id = null;
    public string $proprietario_rntrc = '';
    public string $proprietario_tp_transp = '';

    // Operação e custo
    public string $odometro_atual = '';
    public string $horimetro_atual = '';
    public string $custo_km_alvo = '';
    public string $rastreador_id = '';

    /** @var list<array<string,mixed>> */
    public array $documentos = [];

    public function mount(?Veiculo $veiculo = null): void
    {
        if ($veiculo?->exists) {
            $this->authorize('update', $veiculo);
            $this->veiculo = $veiculo->load('documentos');
            $this->preencherDe($veiculo);

            return;
        }

        $this->authorize('create', Veiculo::class);
        $this->filial_id = TenantContext::filialId();
    }

    private function preencherDe(Veiculo $veiculo): void
    {
        foreach (['placa', 'tipo', 'status', 'propriedade'] as $campo) {
            $this->{$campo} = (string) $veiculo->{$campo};
        }

        foreach (['renavam', 'chassi', 'marca', 'modelo', 'cor', 'uf_licenciamento',
            'tp_rod', 'tp_car', 'tracao', 'combustivel', 'rastreador_id',
            'proprietario_rntrc', 'proprietario_tp_transp'] as $campo) {
            $this->{$campo} = (string) ($veiculo->{$campo} ?? '');
        }

        foreach (['ano_fabricacao', 'ano_modelo', 'eixos', 'tara_kg', 'pbt_kg', 'pbtc_kg',
            'capacidade_kg', 'capacidade_m3', 'comprimento_m', 'largura_m', 'altura_m',
            'capacidade_tanque_l', 'media_referencia_kml', 'odometro_atual',
            'horimetro_atual', 'custo_km_alvo'] as $campo) {
            $valor = $veiculo->{$campo};
            $this->{$campo} = $valor === null ? '' : (string) $valor;
        }

        $this->filial_id = $veiculo->filial_id;
        $this->municipio_licenciamento_id = $veiculo->municipio_licenciamento_id;
        $this->carroceria_id = $veiculo->carroceria_id;
        $this->cvc_configuracao_id = $veiculo->cvc_configuracao_id;
        $this->proprietario_id = $veiculo->proprietario_id;
        $this->exige_aet = (bool) $veiculo->exige_aet;

        $this->documentos = $veiculo->documentos->map(fn (VeiculoDocumento $d): array => [
            'id' => $d->id,
            'tipo' => $d->tipo,
            'numero' => (string) $d->numero,
            'emissao' => $d->emissao?->format('Y-m-d') ?? '',
            'vencimento' => $d->vencimento?->format('Y-m-d') ?? '',
            'valor' => $d->valor === null ? '' : (string) $d->valor,
            'bloqueia_operacao' => (bool) $d->bloqueia_operacao,
            'observacoes' => (string) $d->observacoes,
        ])->all();
    }

    /* ── Reações ───────────────────────────────────────────── */

    public function updatedPlaca(): void
    {
        $this->placa = RegrasVeiculo::limparPlaca($this->placa);
    }

    public function updatedTipo(): void
    {
        // Só a tração leva rodado. Trocar para reboque limpa o campo.
        if (! RegrasVeiculo::exigeRodado($this->tipo)) {
            $this->tp_rod = '';
        }
    }

    public function updatedPropriedade(): void
    {
        // Voltar para própria zera os dados do proprietário de terceiro.
        if (! RegrasVeiculo::exigeProprietario($this->propriedade)) {
            $this->proprietario_id = null;
            $this->proprietario_rntrc = '';
            $this->proprietario_tp_transp = '';
        }
    }

    public function updatedProprietarioId(): void
    {
        // Puxa o RNTRC do cadastro do proprietário como sugestão.
        if ($this->proprietario_id === null) {
            return;
        }

        $pessoa = Pessoa::find($this->proprietario_id);

        if ($pessoa !== null && $this->proprietario_rntrc === '') {
            $this->proprietario_rntrc = (string) ($pessoa->rntrc ?? '');
            $this->proprietario_tp_transp = (string) ($pessoa->tp_transp ?? '');
        }
    }

    #[Computed]
    public function exigeRodado(): bool
    {
        return RegrasVeiculo::exigeRodado($this->tipo);
    }

    #[Computed]
    public function ehTerceiro(): bool
    {
        return RegrasVeiculo::exigeProprietario($this->propriedade);
    }

    #[Computed]
    public function categoriaCombinacao(): string
    {
        $eixos = (int) $this->eixos;

        if ($eixos < 1) {
            return '—';
        }

        $categoria = RegrasVeiculo::categoriaPorEixos($eixos);

        return $categoria->value . ' — ' . $categoria->descricao();
    }

    #[Computed]
    public function cargaUtil(): ?float
    {
        return RegrasVeiculo::cargaUtilKg(
            $this->pbtc_kg !== '' ? (float) $this->pbtc_kg : null,
            $this->tara_kg !== '' ? (float) $this->tara_kg : 0.0,
        );
    }

    #[Computed]
    public function pendenciasFiscais(): array
    {
        return RegrasVeiculo::pendenciasParaEmissao([
            'tipo' => $this->tipo,
            'propriedade' => $this->propriedade,
            'eixos' => (int) $this->eixos,
            'tp_rod' => $this->tp_rod !== '' ? $this->tp_rod : null,
            'proprietario_id' => $this->proprietario_id,
            'proprietario_rntrc' => $this->proprietario_rntrc !== '' ? $this->proprietario_rntrc : null,
            'renavam' => $this->renavam !== '' ? $this->renavam : null,
        ]);
    }

    /* ── Dropdowns ─────────────────────────────────────────── */

    #[Computed]
    public function carrocerias()
    {
        return Carroceria::ativas()->orderBy('nome')->get(['id', 'nome']);
    }

    #[Computed]
    public function configuracoesCvc()
    {
        return CvcConfiguracao::ativas()->orderBy('nome_popular')->get(['id', 'nome_popular', 'eixos']);
    }

    #[Computed]
    public function filiais()
    {
        return Filial::orderBy('nome_fantasia')->get(['id', 'nome_fantasia', 'razao_social']);
    }

    #[Computed]
    public function proprietarios()
    {
        return Pessoa::query()
            ->comPapel('proprietario')
            ->orderBy('razao_social')
            ->get(['id', 'razao_social', 'documento']);
    }

    #[Computed]
    public function municipios()
    {
        return Municipio::orderBy('uf')->orderBy('nome')->get(['id', 'nome', 'uf']);
    }

    /* ── Documentos ────────────────────────────────────────── */

    public function adicionarDocumento(): void
    {
        $this->documentos[] = [
            'id' => null, 'tipo' => 'crlv', 'numero' => '', 'emissao' => '',
            'vencimento' => '', 'valor' => '', 'bloqueia_operacao' => true, 'observacoes' => '',
        ];
    }

    public function removerDocumento(int $i): void
    {
        unset($this->documentos[$i]);
        $this->documentos = array_values($this->documentos);
    }

    /* ── Persistência ──────────────────────────────────────── */

    protected function rules(): array
    {
        return [
            'placa' => [
                'required', 'string', 'size:7',
                function (string $atributo, mixed $valor, callable $falhar): void {
                    if (! RegrasVeiculo::placaValida((string) $valor)) {
                        $falhar('Informe uma placa válida (padrão antigo ou Mercosul).');
                    }
                },
                Rule::unique('veiculos', 'placa')
                    ->ignore($this->veiculo?->id)
                    ->where(fn ($q) => $q->whereNull('deleted_at')),
            ],
            'renavam' => ['nullable', 'string', 'max:11'],
            'chassi' => ['nullable', 'string', 'size:17'],
            'tipo' => ['required', Rule::in(RegrasVeiculo::TIPOS)],
            'marca' => ['nullable', 'string', 'max:60'],
            'modelo' => ['nullable', 'string', 'max:60'],
            'ano_fabricacao' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'ano_modelo' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'uf_licenciamento' => ['required', 'string', 'size:2'],
            'municipio_licenciamento_id' => ['nullable', 'integer', 'exists:municipios,id'],
            'filial_id' => ['nullable', 'integer', 'exists:filiais,id'],
            'status' => ['required', Rule::in(RegrasVeiculo::STATUS)],

            'eixos' => ['required', 'integer', 'min:1', 'max:12'],
            'tp_rod' => [Rule::requiredIf($this->exigeRodado), 'nullable', 'string', 'size:2'],
            'carroceria_id' => ['nullable', 'integer', 'exists:carrocerias,id'],
            'cvc_configuracao_id' => ['nullable', 'integer', 'exists:cvc_configuracoes,id'],
            'tara_kg' => ['required', 'numeric', 'min:0'],
            'pbt_kg' => ['nullable', 'numeric', 'min:0'],
            'pbtc_kg' => ['nullable', 'numeric', 'min:0'],
            'capacidade_kg' => ['nullable', 'numeric', 'min:0'],
            'capacidade_m3' => ['nullable', 'numeric', 'min:0'],

            'propriedade' => ['required', Rule::in(RegrasVeiculo::PROPRIEDADES)],
            'proprietario_id' => [Rule::requiredIf($this->ehTerceiro), 'nullable', 'integer', 'exists:pessoas,id'],
            'proprietario_rntrc' => ['nullable', 'string', 'max:10'],
            'proprietario_tp_transp' => ['nullable', Rule::in(['1', '2', '3'])],

            'documentos.*.tipo' => ['required', 'string', 'max:40'],
            'documentos.*.vencimento' => ['required', 'date'],
        ];
    }

    protected function messages(): array
    {
        return [
            'placa.unique' => 'Já existe um veículo com esta placa nesta empresa.',
            'eixos.required' => 'Os eixos são a base do categCombVeic — sem eles o MDF-e é rejeitado (731).',
            'tp_rod.required' => 'Tração precisa de tipo de rodado (tpRod).',
            'proprietario_id.required' => 'Veículo de terceiro precisa de um proprietário cadastrado.',
            'documentos.*.vencimento.required' => 'Todo documento precisa de vencimento — é o que alimenta o painel 2040.',
        ];
    }

    public function salvar()
    {
        $this->validate();

        // Coerência de capacidade: nunca acima do PBTC menos a tara (o banco tem
        // CHECK; aqui evitamos que o usuário descubra pela tela de erro).
        if ($this->capacidade_kg !== '' && $this->pbtc_kg !== '') {
            $limite = (float) $this->pbtc_kg - (float) ($this->tara_kg ?: 0);

            if ((float) $this->capacidade_kg > $limite) {
                $this->addError('capacidade_kg', 'A capacidade não pode passar do PBTC menos a tara (' . number_format($limite, 0, ',', '.') . ' kg).');

                return;
            }
        }

        $dados = [
            'placa' => RegrasVeiculo::limparPlaca($this->placa),
            'renavam' => $this->nulo($this->renavam),
            'chassi' => $this->nulo($this->chassi),
            'tipo' => $this->tipo,
            'marca' => $this->nulo($this->marca),
            'modelo' => $this->nulo($this->modelo),
            'ano_fabricacao' => $this->nuloInt($this->ano_fabricacao),
            'ano_modelo' => $this->nuloInt($this->ano_modelo),
            'cor' => $this->nulo($this->cor),
            'uf_licenciamento' => strtoupper($this->uf_licenciamento),
            'municipio_licenciamento_id' => $this->municipio_licenciamento_id,
            'filial_id' => $this->filial_id,
            'status' => $this->status,
            'eixos' => (int) $this->eixos,
            'tp_rod' => $this->exigeRodado ? $this->nulo($this->tp_rod) : null,
            'tp_car' => $this->nulo($this->tp_car),
            'carroceria_id' => $this->carroceria_id,
            'cvc_configuracao_id' => $this->cvc_configuracao_id,
            'tracao' => $this->nulo($this->tracao),
            'tara_kg' => (float) $this->tara_kg,
            'pbt_kg' => $this->nuloNum($this->pbt_kg),
            'pbtc_kg' => $this->nuloNum($this->pbtc_kg),
            'capacidade_kg' => $this->nuloNum($this->capacidade_kg),
            'capacidade_m3' => $this->nuloNum($this->capacidade_m3),
            'comprimento_m' => $this->nuloNum($this->comprimento_m),
            'largura_m' => $this->nuloNum($this->largura_m),
            'altura_m' => $this->nuloNum($this->altura_m),
            'exige_aet' => $this->exige_aet,
            'combustivel' => $this->nulo($this->combustivel),
            'capacidade_tanque_l' => $this->nuloNum($this->capacidade_tanque_l),
            'media_referencia_kml' => $this->nuloNum($this->media_referencia_kml),
            'odometro_atual' => $this->odometro_atual === '' ? 0 : (float) $this->odometro_atual,
            'horimetro_atual' => $this->nuloNum($this->horimetro_atual),
            'custo_km_alvo' => $this->nuloNum($this->custo_km_alvo),
            'rastreador_id' => $this->nulo($this->rastreador_id),
            'propriedade' => $this->propriedade,
            'proprietario_id' => $this->ehTerceiro ? $this->proprietario_id : null,
            'proprietario_rntrc' => $this->ehTerceiro ? $this->nulo($this->proprietario_rntrc) : null,
            'proprietario_tp_transp' => $this->ehTerceiro ? $this->nulo($this->proprietario_tp_transp) : null,
        ];

        DB::transaction(function () use ($dados): void {
            $veiculo = $this->veiculo?->exists
                ? tap($this->veiculo)->update($dados)
                : Veiculo::create($dados);

            $this->sincronizarDocumentos($veiculo);

            $this->veiculo = $veiculo->fresh('documentos');
        });

        session()->flash('sucesso', 'Veículo salvo.');

        return $this->redirect(route('veiculos.index'), navigate: true);
    }

    private function sincronizarDocumentos(Veiculo $veiculo): void
    {
        $mantidos = [];

        foreach ($this->documentos as $d) {
            if (($d['vencimento'] ?? '') === '') {
                continue;
            }

            $atributos = [
                'empresa_id' => $veiculo->empresa_id,
                'tipo' => $d['tipo'],
                'numero' => $this->nulo((string) ($d['numero'] ?? '')),
                'emissao' => $this->nulo((string) ($d['emissao'] ?? '')),
                'vencimento' => $d['vencimento'],
                'valor' => $this->nuloNum((string) ($d['valor'] ?? '')),
                'bloqueia_operacao' => (bool) ($d['bloqueia_operacao'] ?? true),
                'observacoes' => $this->nulo((string) ($d['observacoes'] ?? '')),
            ];

            $modelo = ($d['id'] ?? null) !== null
                ? tap(VeiculoDocumento::findOrFail($d['id']))->update($atributos)
                : $veiculo->documentos()->create($atributos);

            $mantidos[] = $modelo->id;
        }

        $veiculo->documentos()->whereNotIn('id', $mantidos ?: [0])->delete();
    }

    private function nulo(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    private function nuloInt(string $valor): ?int
    {
        return $valor === '' ? null : (int) $valor;
    }

    private function nuloNum(string $valor): ?float
    {
        return $valor === '' ? null : (float) $valor;
    }

    public function render(): View
    {
        return view('livewire.veiculos.formulario')
            ->layout('layouts.app', ['title' => $this->veiculo?->placa ?? 'Novo veículo']);
    }
}
