<?php

declare(strict_types=1);

namespace App\Livewire\Filiais;

use App\Domain\Cadastro\Documento;
use App\Models\Filial;
use App\Models\Municipio;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Rotina 9010 — ficha da filial (emitente fiscal).
 *
 * Cada filial emite por conta própria: CNPJ, IE, CRT e AMBIENTE SEFAZ são dela.
 * O ambiente é atributo da filial, não da aplicação — uma pode homologar
 * enquanto outra produz. `empresa_id` vem do TenantContext.
 */
class Formulario extends Component
{
    use AuthorizesRequests;

    public ?Filial $filial = null;

    public string $aba = 'identificacao';

    // Identificação fiscal
    public string $codigo = '';
    public string $razao_social = '';
    public string $nome_fantasia = '';
    public string $cnpj = '';
    public string $ie = '';
    public string $im = '';
    public string $crt = Filial::CRT_SIMPLES;
    public string $rntrc = '';
    public string $rntrc_validade = '';
    public string $tp_transp = '1';
    public bool $matriz = false;

    // Endereço
    public string $logradouro = '';
    public string $numero = '';
    public string $complemento = '';
    public string $bairro = '';
    public ?int $municipio_id = null;
    public string $cep = '';
    public string $telefone = '';
    public string $email = '';

    // SEFAZ
    public int $ambiente_sefaz = Filial::AMBIENTE_HOMOLOGACAO;
    public string $uf_autorizadora = '';
    public bool $contingencia_automatica = false;
    public bool $ativa = true;

    public function mount(?Filial $filial = null): void
    {
        if ($filial?->exists) {
            $this->authorize('update', $filial);
            $this->filial = $filial;
            $this->preencherDe($filial);

            return;
        }

        $this->authorize('create', Filial::class);
    }

    private function preencherDe(Filial $filial): void
    {
        foreach (['codigo', 'razao_social', 'crt', 'uf_autorizadora'] as $campo) {
            $this->{$campo} = (string) $filial->{$campo};
        }

        foreach (['nome_fantasia', 'ie', 'im', 'rntrc', 'tp_transp', 'logradouro',
            'numero', 'complemento', 'bairro', 'cep', 'telefone', 'email'] as $campo) {
            $this->{$campo} = (string) ($filial->{$campo} ?? '');
        }

        $this->cnpj = Documento::formatar((string) $filial->cnpj);
        $this->rntrc_validade = $filial->rntrc_validade?->format('Y-m-d') ?? '';
        $this->municipio_id = $filial->municipio_id;
        $this->matriz = (bool) $filial->matriz;
        $this->ambiente_sefaz = (int) ($filial->ambiente_sefaz ?? Filial::AMBIENTE_HOMOLOGACAO);
        $this->contingencia_automatica = (bool) $filial->contingencia_automatica;
        $this->ativa = (bool) $filial->ativa;
    }

    public function updatedCnpj(): void
    {
        $this->cnpj = Documento::formatar(Documento::limpar($this->cnpj));
    }

    #[Computed]
    public function municipios()
    {
        return Municipio::orderBy('uf')->orderBy('nome')->get(['id', 'nome', 'uf']);
    }

    protected function rules(): array
    {
        return [
            'codigo' => [
                'required', 'string', 'max:20',
                Rule::unique('filiais', 'codigo')
                    ->ignore($this->filial?->id)
                    ->where(fn ($q) => $q->whereNull('deleted_at')),
            ],
            'razao_social' => ['required', 'string', 'max:150'],
            'nome_fantasia' => ['nullable', 'string', 'max:150'],
            'cnpj' => [
                'required',
                function (string $atributo, mixed $valor, callable $falhar): void {
                    if (! Documento::valido(Documento::limpar((string) $valor))) {
                        $falhar('Informe um CNPJ válido.');
                    }
                },
            ],
            'ie' => ['nullable', 'string', 'max:20'],
            'im' => ['nullable', 'string', 'max:20'],
            'crt' => ['required', Rule::in([Filial::CRT_SIMPLES, Filial::CRT_SIMPLES_EXCESSO, Filial::CRT_REGIME_NORMAL])],
            'rntrc' => ['nullable', 'string', 'max:10'],
            'rntrc_validade' => ['nullable', 'date'],
            'tp_transp' => ['nullable', Rule::in(['1', '2', '3'])],
            'logradouro' => ['nullable', 'string', 'max:150'],
            'municipio_id' => ['required', 'integer', 'exists:municipios,id'],
            'cep' => ['nullable', 'string', 'size:8'],
            'email' => ['nullable', 'email', 'max:150'],
            'ambiente_sefaz' => ['required', Rule::in([Filial::AMBIENTE_PRODUCAO, Filial::AMBIENTE_HOMOLOGACAO])],
            'uf_autorizadora' => ['required', 'string', 'size:2'],
        ];
    }

    protected function messages(): array
    {
        return [
            'codigo.unique' => 'Já existe uma filial com este código.',
            'municipio_id.required' => 'O município é código IBGE — escolha na lista.',
        ];
    }

    public function salvar()
    {
        $this->validate();

        $dados = [
            'codigo' => trim($this->codigo),
            'razao_social' => trim($this->razao_social),
            'nome_fantasia' => $this->nulo($this->nome_fantasia),
            'cnpj' => Documento::limpar($this->cnpj),
            'ie' => $this->nulo($this->ie),
            'im' => $this->nulo($this->im),
            'crt' => $this->crt,
            'rntrc' => $this->nulo($this->rntrc),
            'rntrc_validade' => $this->nulo($this->rntrc_validade),
            'tp_transp' => $this->nulo($this->tp_transp),
            'matriz' => $this->matriz,
            'logradouro' => $this->nulo($this->logradouro),
            'numero' => $this->nulo($this->numero),
            'complemento' => $this->nulo($this->complemento),
            'bairro' => $this->nulo($this->bairro),
            'municipio_id' => $this->municipio_id,
            'cep' => $this->nulo($this->cep),
            'telefone' => $this->nulo($this->telefone),
            'email' => $this->nulo($this->email),
            'ambiente_sefaz' => $this->ambiente_sefaz,
            'uf_autorizadora' => strtoupper($this->uf_autorizadora),
            'contingencia_automatica' => $this->contingencia_automatica,
            'ativa' => $this->ativa,
        ];

        DB::transaction(function () use ($dados): void {
            // Só uma matriz por empresa: o banco garante com índice parcial;
            // aqui a tela evita que o usuário descubra pela tela de erro.
            if ($this->matriz) {
                Filial::query()
                    ->where('matriz', true)
                    ->when($this->filial?->id, fn ($q) => $q->where('id', '!=', $this->filial->id))
                    ->update(['matriz' => false]);
            }

            $filial = $this->filial?->exists
                ? tap($this->filial)->update($dados)
                : Filial::create($dados);

            $this->filial = $filial->fresh();
        });

        session()->flash('sucesso', 'Filial salva.');

        return $this->redirect(route('filiais.index'), navigate: true);
    }

    private function nulo(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    public function render(): View
    {
        return view('livewire.filiais.formulario')
            ->layout('layouts.app', ['title' => $this->filial?->nome_fantasia ?? 'Nova filial']);
    }
}
