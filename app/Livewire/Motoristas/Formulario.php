<?php

declare(strict_types=1);

namespace App\Livewire\Motoristas;

use App\Domain\Frota\RegrasMotorista;
use App\Models\Motorista;
use App\Models\Pessoa;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Rotina 2030 — ficha do motorista.
 *
 * O motorista é um PAPEL sobre `pessoas`: primeiro escolhe-se a pessoa (que já
 * tem o papel de motorista), depois preenchem-se CNH, vínculo e documentos.
 *
 * RN-12 — jornada existe SÓ para CLT. Este formulário NÃO tem campo de jornada,
 * escala ou ponto; para agregado e autônomo isso seria fabricar prova de
 * vínculo empregatício contra o próprio cliente. O painel lateral deixa claro
 * o que o vínculo escolhido implica.
 */
class Formulario extends Component
{
    use AuthorizesRequests;

    public ?Motorista $motorista = null;

    public string $aba = 'habilitacao';

    public ?int $pessoa_id = null;

    // Habilitação
    public string $cnh_numero = '';
    public string $cnh_categoria = 'E';
    public string $cnh_validade = '';
    public string $cnh_primeira_habilitacao = '';
    public bool $cnh_ear = true;

    // Vínculo
    public string $vinculo = RegrasMotorista::VINCULO_CLT;
    public string $tp_transp = '';
    public string $rntrc = '';
    public string $rntrc_validade = '';
    public string $admissao = '';
    public string $demissao = '';

    // Documentos e cursos
    public string $toxicologico_data = '';
    public string $toxicologico_validade = '';
    public string $mopp_validade = '';
    public string $curso_carga_indivisivel_validade = '';

    // Remuneração
    public string $valor_diaria = '';
    public string $percentual_comissao = '';
    public string $valor_por_km = '';

    public string $status = 'ativo';

    public function mount(?Motorista $motorista = null): void
    {
        if ($motorista?->exists) {
            $this->authorize('update', $motorista);
            $this->motorista = $motorista->load('pessoa');
            $this->preencherDe($motorista);

            return;
        }

        $this->authorize('create', Motorista::class);
    }

    private function preencherDe(Motorista $motorista): void
    {
        $this->pessoa_id = $motorista->pessoa_id;
        $this->cnh_numero = (string) $motorista->cnh_numero;
        $this->cnh_categoria = (string) $motorista->cnh_categoria;
        $this->cnh_ear = (bool) $motorista->cnh_ear;
        $this->vinculo = (string) $motorista->vinculo;
        $this->status = (string) $motorista->status;

        foreach (['tp_transp', 'rntrc'] as $campo) {
            $this->{$campo} = (string) ($motorista->{$campo} ?? '');
        }

        foreach (['cnh_validade', 'cnh_primeira_habilitacao', 'rntrc_validade', 'admissao',
            'demissao', 'toxicologico_data', 'toxicologico_validade', 'mopp_validade',
            'curso_carga_indivisivel_validade'] as $campo) {
            $this->{$campo} = $motorista->{$campo}?->format('Y-m-d') ?? '';
        }

        foreach (['valor_diaria', 'percentual_comissao', 'valor_por_km'] as $campo) {
            $valor = $motorista->{$campo};
            $this->{$campo} = $valor === null ? '' : (string) $valor;
        }
    }

    /* ── Reações ───────────────────────────────────────────── */

    public function updatedVinculo(): void
    {
        // CLT opera sob o RNTRC da empresa — não tem RNTRC próprio.
        if (! $this->ehTac) {
            $this->rntrc = '';
            $this->rntrc_validade = '';
        }
    }

    #[Computed]
    public function ehTac(): bool
    {
        return RegrasMotorista::ehTac($this->vinculo);
    }

    #[Computed]
    public function controlaJornada(): bool
    {
        return RegrasMotorista::controlaJornada($this->vinculo);
    }

    #[Computed]
    public function exigeToxicologico(): bool
    {
        return RegrasMotorista::exigeToxicologico($this->cnh_categoria);
    }

    #[Computed]
    public function pessoasDisponiveis()
    {
        // Só pessoas com o papel de motorista. Ao editar, mantém a atual mesmo
        // que o papel tenha sido removido depois. O OR fica DENTRO de um closure
        // para não escapar o filtro de empresa do EmpresaScope (precedência SQL).
        return Pessoa::query()
            ->where(function ($q): void {
                $q->comPapel('motorista');

                if ($this->pessoa_id !== null) {
                    $q->orWhere('id', $this->pessoa_id);
                }
            })
            ->orderBy('razao_social')
            ->get(['id', 'razao_social', 'documento']);
    }

    /* ── Persistência ──────────────────────────────────────── */

    protected function rules(): array
    {
        return [
            'pessoa_id' => [
                'required', 'integer', 'exists:pessoas,id',
                Rule::unique('motoristas', 'pessoa_id')
                    ->ignore($this->motorista?->id)
                    ->where(fn ($q) => $q->whereNull('deleted_at')),
            ],
            'cnh_numero' => ['required', 'string', 'max:11'],
            'cnh_categoria' => ['required', 'string', 'max:5'],
            'cnh_validade' => ['required', 'date'],
            'cnh_primeira_habilitacao' => ['nullable', 'date'],
            'vinculo' => ['required', Rule::in(RegrasMotorista::VINCULOS)],
            'tp_transp' => ['nullable', Rule::in(['1', '2', '3'])],
            'rntrc' => [Rule::requiredIf($this->ehTac), 'nullable', 'string', 'max:10'],
            'rntrc_validade' => ['nullable', 'date'],
            'admissao' => ['nullable', 'date'],
            'demissao' => ['nullable', 'date'],
            'toxicologico_data' => ['nullable', 'date'],
            'toxicologico_validade' => [Rule::requiredIf($this->exigeToxicologico), 'nullable', 'date'],
            'mopp_validade' => ['nullable', 'date'],
            'curso_carga_indivisivel_validade' => ['nullable', 'date'],
            'valor_diaria' => ['nullable', 'numeric', 'min:0'],
            'percentual_comissao' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'valor_por_km' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'string', 'max:20'],
        ];
    }

    protected function messages(): array
    {
        return [
            'pessoa_id.required' => 'Escolha a pessoa. O motorista é um papel sobre o cadastro de pessoas.',
            'pessoa_id.unique' => 'Esta pessoa já tem ficha de motorista.',
            'rntrc.required' => 'Agregado e autônomo são TAC — o RNTRC é próprio e obrigatório.',
            'toxicologico_validade.required' => 'Categorias C, D e E exigem exame toxicológico (Lei 13.103/2015).',
        ];
    }

    public function salvar()
    {
        $this->validate();

        $dados = [
            'pessoa_id' => $this->pessoa_id,
            'cnh_numero' => $this->cnh_numero,
            'cnh_categoria' => strtoupper($this->cnh_categoria),
            'cnh_validade' => $this->cnh_validade,
            'cnh_primeira_habilitacao' => $this->nulo($this->cnh_primeira_habilitacao),
            'cnh_ear' => $this->cnh_ear,
            'vinculo' => $this->vinculo,
            'tp_transp' => $this->nulo($this->tp_transp),
            'rntrc' => $this->ehTac ? $this->nulo($this->rntrc) : null,
            'rntrc_validade' => $this->ehTac ? $this->nulo($this->rntrc_validade) : null,
            'admissao' => $this->nulo($this->admissao),
            'demissao' => $this->nulo($this->demissao),
            'toxicologico_data' => $this->nulo($this->toxicologico_data),
            'toxicologico_validade' => $this->nulo($this->toxicologico_validade),
            'mopp_validade' => $this->nulo($this->mopp_validade),
            'curso_carga_indivisivel_validade' => $this->nulo($this->curso_carga_indivisivel_validade),
            'valor_diaria' => $this->nuloNum($this->valor_diaria),
            'percentual_comissao' => $this->nuloNum($this->percentual_comissao),
            'valor_por_km' => $this->nuloNum($this->valor_por_km),
            'status' => $this->status,
        ];

        DB::transaction(function () use ($dados): void {
            $motorista = $this->motorista?->exists
                ? tap($this->motorista)->update($dados)
                : Motorista::create($dados);

            $this->motorista = $motorista->fresh('pessoa');
        });

        session()->flash('sucesso', 'Motorista salvo.');

        return $this->redirect(route('motoristas.index'), navigate: true);
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
        return view('livewire.motoristas.formulario')
            ->layout('layouts.app', ['title' => $this->motorista?->pessoa?->razao_social ?? 'Novo motorista']);
    }
}
