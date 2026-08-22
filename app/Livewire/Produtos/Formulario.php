<?php

declare(strict_types=1);

namespace App\Livewire\Produtos;

use App\Models\Carroceria;
use App\Models\Mercadoria;
use App\Models\NaturezaCargaTabela;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Rotina 1020 — ficha da mercadoria.
 *
 * O bloco de produto perigoso só aparece quando `eh_perigoso` está marcado, e
 * alimenta o grupo `peri` do MDF-e (não do CT-e rodoviário). O volume, quando
 * em branco, é calculado das dimensões. `empresa_id` vem do TenantContext.
 */
class Formulario extends Component
{
    use AuthorizesRequests;

    public ?Mercadoria $mercadoria = null;

    public string $aba = 'identificacao';

    // Identificação
    public string $codigo_interno = '';
    public string $descricao = '';
    public string $descricao_complementar = '';
    public string $marca = '';
    public string $ncm = '';
    public string $cest = '';
    public string $gtin = '';
    public string $unidade_comercial = '';
    public string $cunid_cte = '';
    public string $cunid_mdfe = '';
    public ?int $natureza_carga_id = null;
    public ?int $carroceria_recomendada_id = null;
    public bool $ativo = true;

    // Físico
    public string $peso_bruto_kg = '';
    public string $peso_liquido_kg = '';
    public string $comprimento_m = '';
    public string $largura_m = '';
    public string $altura_m = '';
    public string $volume_m3 = '';
    public string $densidade_kg_m3 = '';
    public string $fator_cubagem_kg_m3 = '';
    public bool $permite_empilhar = true;
    public string $empilhamento_max_camadas = '';
    public bool $fragil = false;
    public bool $sentido_obrigatorio = false;

    // Temperatura
    public bool $exige_temp_controlada = false;
    public string $temp_min_c = '';
    public string $temp_max_c = '';
    public bool $exige_registro_continuo = false;

    // Perigoso
    public bool $eh_perigoso = false;
    public string $num_onu = '';
    public string $nome_embarque = '';
    public string $classe_risco = '';
    public string $risco_subsidiario = '';
    public string $num_risco = '';
    public string $grupo_embalagem = '';
    public string $ponto_fulgor_c = '';
    public bool $risco_ambiental = false;
    public bool $exige_mopp = false;
    public bool $exige_kit_9735 = false;

    // Controles setoriais
    public bool $pce_exercito = false;
    public bool $controlado_pf = false;
    public bool $exige_mapa_siproquim = false;
    public bool $origem_animal = false;
    public bool $eh_agrotoxico = false;
    public string $registro_mapa = '';

    public function mount(?Mercadoria $mercadoria = null): void
    {
        if ($mercadoria?->exists) {
            $this->authorize('update', $mercadoria);
            $this->mercadoria = $mercadoria;
            $this->preencherDe($mercadoria);

            return;
        }

        $this->authorize('create', Mercadoria::class);
    }

    private function preencherDe(Mercadoria $m): void
    {
        foreach (['codigo_interno', 'descricao'] as $c) {
            $this->{$c} = (string) $m->{$c};
        }

        foreach (['descricao_complementar', 'marca', 'ncm', 'cest', 'gtin', 'unidade_comercial',
            'cunid_cte', 'cunid_mdfe', 'nome_embarque', 'num_onu', 'classe_risco',
            'risco_subsidiario', 'num_risco', 'grupo_embalagem', 'registro_mapa'] as $c) {
            $this->{$c} = (string) ($m->{$c} ?? '');
        }

        foreach (['peso_bruto_kg', 'peso_liquido_kg', 'comprimento_m', 'largura_m', 'altura_m',
            'volume_m3', 'densidade_kg_m3', 'fator_cubagem_kg_m3', 'empilhamento_max_camadas',
            'temp_min_c', 'temp_max_c', 'ponto_fulgor_c'] as $c) {
            $this->{$c} = $m->{$c} === null ? '' : (string) $m->{$c};
        }

        $this->natureza_carga_id = $m->natureza_carga_id;
        $this->carroceria_recomendada_id = $m->carroceria_recomendada_id;

        foreach (['ativo', 'permite_empilhar', 'fragil', 'sentido_obrigatorio',
            'exige_temp_controlada', 'exige_registro_continuo', 'eh_perigoso',
            'risco_ambiental', 'exige_mopp', 'exige_kit_9735', 'pce_exercito',
            'controlado_pf', 'exige_mapa_siproquim', 'origem_animal', 'eh_agrotoxico'] as $c) {
            $this->{$c} = (bool) $m->{$c};
        }
    }

    #[Computed]
    public function volumePrevisto(): ?float
    {
        if ($this->comprimento_m === '' || $this->largura_m === '' || $this->altura_m === '') {
            return null;
        }

        return round((float) $this->comprimento_m * (float) $this->largura_m * (float) $this->altura_m, 4);
    }

    #[Computed]
    public function naturezas()
    {
        return NaturezaCargaTabela::query()->ativas()->orderBy('nome')->get(['id', 'nome']);
    }

    #[Computed]
    public function carrocerias()
    {
        return Carroceria::query()->ativas()->orderBy('nome')->get(['id', 'nome']);
    }

    protected function rules(): array
    {
        return [
            'codigo_interno' => [
                'required', 'string', 'max:40',
                Rule::unique('mercadorias', 'codigo_interno')
                    ->ignore($this->mercadoria?->id)
                    ->where(fn ($q) => $q->whereNull('deleted_at')),
            ],
            'descricao' => ['required', 'string', 'max:150'],
            'ncm' => ['nullable', 'string', 'size:8'],
            'cest' => ['nullable', 'string', 'size:7'],
            'gtin' => ['nullable', 'string', 'max:14'],
            'natureza_carga_id' => ['nullable', 'integer', 'exists:naturezas_carga,id'],
            'carroceria_recomendada_id' => ['nullable', 'integer', 'exists:carrocerias,id'],
            'temp_min_c' => ['nullable', 'numeric'],
            'temp_max_c' => ['nullable', 'numeric'],
            'num_onu' => [Rule::requiredIf($this->eh_perigoso), 'nullable', 'string', 'size:4'],
            'classe_risco' => [Rule::requiredIf($this->eh_perigoso), 'nullable', 'string', 'max:10'],
        ];
    }

    protected function messages(): array
    {
        return [
            'codigo_interno.unique' => 'Já existe uma mercadoria com este código interno.',
            'num_onu.required' => 'Produto perigoso precisa do número ONU (4 dígitos).',
            'classe_risco.required' => 'Produto perigoso precisa da classe de risco.',
            'ncm.size' => 'O NCM tem 8 dígitos.',
        ];
    }

    public function salvar()
    {
        $this->validate();

        if ($this->temp_min_c !== '' && $this->temp_max_c !== '' && (float) $this->temp_min_c > (float) $this->temp_max_c) {
            $this->addError('temp_min_c', 'A temperatura mínima não pode ser maior que a máxima.');

            return;
        }

        $dados = [
            'codigo_interno' => trim($this->codigo_interno),
            'descricao' => trim($this->descricao),
            'descricao_complementar' => $this->nulo($this->descricao_complementar),
            'marca' => $this->nulo($this->marca),
            'ncm' => $this->nulo($this->ncm),
            'cest' => $this->nulo($this->cest),
            'gtin' => $this->nulo($this->gtin),
            'unidade_comercial' => $this->nulo($this->unidade_comercial),
            'cunid_cte' => $this->nulo($this->cunid_cte),
            'cunid_mdfe' => $this->nulo($this->cunid_mdfe),
            'natureza_carga_id' => $this->natureza_carga_id,
            'carroceria_recomendada_id' => $this->carroceria_recomendada_id,
            'ativo' => $this->ativo,

            'peso_bruto_kg' => $this->nuloNum($this->peso_bruto_kg),
            'peso_liquido_kg' => $this->nuloNum($this->peso_liquido_kg),
            'comprimento_m' => $this->nuloNum($this->comprimento_m),
            'largura_m' => $this->nuloNum($this->largura_m),
            'altura_m' => $this->nuloNum($this->altura_m),
            'volume_m3' => $this->volume_m3 !== '' ? (float) $this->volume_m3 : $this->volumePrevisto,
            'densidade_kg_m3' => $this->nuloNum($this->densidade_kg_m3),
            'fator_cubagem_kg_m3' => $this->nuloNum($this->fator_cubagem_kg_m3),
            'empilhamento_max_camadas' => $this->empilhamento_max_camadas === '' ? null : (int) $this->empilhamento_max_camadas,
            'permite_empilhar' => $this->permite_empilhar,
            'fragil' => $this->fragil,
            'sentido_obrigatorio' => $this->sentido_obrigatorio,

            'exige_temp_controlada' => $this->exige_temp_controlada,
            'temp_min_c' => $this->nuloNum($this->temp_min_c),
            'temp_max_c' => $this->nuloNum($this->temp_max_c),
            'exige_registro_continuo' => $this->exige_registro_continuo,

            'eh_perigoso' => $this->eh_perigoso,
            'num_onu' => $this->eh_perigoso ? $this->nulo($this->num_onu) : null,
            'nome_embarque' => $this->eh_perigoso ? $this->nulo($this->nome_embarque) : null,
            'classe_risco' => $this->eh_perigoso ? $this->nulo($this->classe_risco) : null,
            'risco_subsidiario' => $this->eh_perigoso ? $this->nulo($this->risco_subsidiario) : null,
            'num_risco' => $this->eh_perigoso ? $this->nulo($this->num_risco) : null,
            'grupo_embalagem' => $this->eh_perigoso ? $this->nulo($this->grupo_embalagem) : null,
            'ponto_fulgor_c' => $this->eh_perigoso ? $this->nuloNum($this->ponto_fulgor_c) : null,
            'risco_ambiental' => $this->eh_perigoso && $this->risco_ambiental,
            'exige_mopp' => $this->eh_perigoso && $this->exige_mopp,
            'exige_kit_9735' => $this->eh_perigoso && $this->exige_kit_9735,

            'pce_exercito' => $this->pce_exercito,
            'controlado_pf' => $this->controlado_pf,
            'exige_mapa_siproquim' => $this->exige_mapa_siproquim,
            'origem_animal' => $this->origem_animal,
            'eh_agrotoxico' => $this->eh_agrotoxico,
            'registro_mapa' => $this->nulo($this->registro_mapa),
        ];

        DB::transaction(function () use ($dados): void {
            $mercadoria = $this->mercadoria?->exists
                ? tap($this->mercadoria)->update($dados)
                : Mercadoria::create($dados);

            $this->mercadoria = $mercadoria->fresh();
        });

        session()->flash('sucesso', 'Mercadoria salva.');

        return $this->redirect(route('produtos.index'), navigate: true);
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
        return view('livewire.produtos.formulario')
            ->layout('layouts.app', ['title' => $this->mercadoria?->descricao ?? 'Nova mercadoria']);
    }
}
