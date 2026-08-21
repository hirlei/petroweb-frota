<?php

declare(strict_types=1);

namespace App\Livewire\Pessoas;

use App\Domain\Cadastro\Documento;
use App\Domain\Cadastro\RegrasPessoa;
use App\Models\Contato;
use App\Models\Endereco;
use App\Models\Municipio;
use App\Models\Pessoa;
use App\Models\PessoaPapel;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Rotina 1010 — ficha da pessoa.
 *
 * O formulário é um só para os sete papéis. Os blocos condicionais aparecem
 * pelo papel marcado, não por uma tela diferente para cada tipo — foi a
 * decisão aprovada no mockup, e é o que evita CNPJ cadastrado três vezes.
 *
 * `empresa_id` não existe neste formulário. Quem carimba é a trait
 * PertenceAEmpresa, a partir do TenantContext.
 */
class Formulario extends Component
{
    use AuthorizesRequests;

    public ?Pessoa $pessoa = null;

    public string $aba = 'identificacao';

    // Identificação
    public string $tipo = RegrasPessoa::TIPO_JURIDICA;
    public string $documento = '';
    public string $documento_estrangeiro = '';
    public string $razao_social = '';
    public string $nome_fantasia = '';
    public string $ie = '';
    public string $ie_indicador = RegrasPessoa::IE_ISENTO;
    public string $im = '';
    public string $suframa = '';
    public string $cnae = '';
    public string $email = '';
    public string $telefone = '';
    public string $observacoes = '';
    public bool $ativo = true;

    // Transportador
    public string $rntrc = '';
    public string $rntrc_validade = '';
    public string $tp_transp = '';

    /** @var list<string> */
    public array $papeis = [];

    /** @var list<array<string,mixed>> */
    public array $enderecos = [];

    /** @var list<array<string,mixed>> */
    public array $contatos = [];

    public function mount(?Pessoa $pessoa = null): void
    {
        if ($pessoa?->exists) {
            $this->authorize('update', $pessoa);
            $this->pessoa = $pessoa->load(['papeis', 'enderecos', 'contatos']);
            $this->preencherDe($pessoa);

            return;
        }

        $this->authorize('create', Pessoa::class);
        $this->enderecos = [$this->enderecoVazio(true)];
    }

    private function preencherDe(Pessoa $pessoa): void
    {
        foreach (['tipo', 'razao_social', 'ie_indicador'] as $campo) {
            $this->{$campo} = (string) $pessoa->{$campo};
        }

        $this->documento = Documento::formatar((string) $pessoa->documento);

        foreach (['documento_estrangeiro', 'nome_fantasia', 'ie', 'im', 'suframa',
            'cnae', 'email', 'telefone', 'observacoes', 'rntrc', 'tp_transp'] as $campo) {
            $this->{$campo} = (string) ($pessoa->{$campo} ?? '');
        }

        $this->rntrc_validade = $pessoa->rntrc_validade?->format('Y-m-d') ?? '';
        $this->ativo = (bool) $pessoa->ativo;
        $this->papeis = $pessoa->papeis->pluck('papel')->all();

        $this->enderecos = $pessoa->enderecos->map(fn (Endereco $e): array => [
            'id' => $e->id,
            'tipo' => $e->tipo,
            'logradouro' => $e->logradouro,
            'numero' => (string) $e->numero,
            'complemento' => (string) $e->complemento,
            'bairro' => (string) $e->bairro,
            'municipio_id' => $e->municipio_id,
            'cep' => (string) $e->cep,
            'principal' => (bool) $e->principal,
        ])->all();

        $this->contatos = $pessoa->contatos->map(fn (Contato $c): array => [
            'id' => $c->id,
            'nome' => $c->nome,
            'cargo' => (string) $c->cargo,
            'email' => (string) $c->email,
            'telefone' => (string) $c->telefone,
            'setor' => (string) $c->setor,
            'recebe_dfe' => (bool) $c->recebe_dfe,
        ])->all();
    }

    /* ── Papéis ────────────────────────────────────────────── */

    public function alternarPapel(string $papel): void
    {
        if (! RegrasPessoa::papelValido($papel)) {
            return;
        }

        $this->papeis = in_array($papel, $this->papeis, true)
            ? array_values(array_diff($this->papeis, [$papel]))
            : [...$this->papeis, $papel];
    }

    #[Computed]
    public function exigeRntrc(): bool
    {
        return RegrasPessoa::exigeRntrc($this->papeis);
    }

    /* ── Endereços e contatos ──────────────────────────────── */

    private function enderecoVazio(bool $principal = false): array
    {
        return ['id' => null, 'tipo' => 'principal', 'logradouro' => '', 'numero' => '',
            'complemento' => '', 'bairro' => '', 'municipio_id' => null, 'cep' => '',
            'principal' => $principal];
    }

    public function adicionarEndereco(): void
    {
        $this->enderecos[] = $this->enderecoVazio(count($this->enderecos) === 0);
    }

    public function removerEndereco(int $i): void
    {
        unset($this->enderecos[$i]);
        $this->enderecos = array_values($this->enderecos);
    }

    /**
     * Só um principal. O banco tem índice parcial que garante isso; aqui a
     * tela evita que o usuário descubra a regra pelo erro 500.
     */
    public function marcarPrincipal(int $i): void
    {
        foreach ($this->enderecos as $k => $_) {
            $this->enderecos[$k]['principal'] = $k === $i;
        }
    }

    public function adicionarContato(): void
    {
        $this->contatos[] = ['id' => null, 'nome' => '', 'cargo' => '', 'email' => '',
            'telefone' => '', 'setor' => '', 'recebe_dfe' => false];
    }

    public function removerContato(int $i): void
    {
        unset($this->contatos[$i]);
        $this->contatos = array_values($this->contatos);
    }

    /* ── Reações do formulário ─────────────────────────────── */

    public function updatedIe(): void
    {
        // Sugere, nunca decide: só o cliente sabe se está isento.
        if ($this->ie_indicador === RegrasPessoa::IE_ISENTO || $this->ie_indicador === '') {
            $this->ie_indicador = RegrasPessoa::sugerirIndicadorIe($this->tipo, $this->ie);
        }
    }

    public function updatedDocumento(): void
    {
        $limpo = Documento::limpar($this->documento);

        if (strlen($limpo) === 11 && $this->tipo === RegrasPessoa::TIPO_JURIDICA) {
            $this->tipo = RegrasPessoa::TIPO_FISICA;
        }

        if (strlen($limpo) === 14 && $this->tipo === RegrasPessoa::TIPO_FISICA) {
            $this->tipo = RegrasPessoa::TIPO_JURIDICA;
        }

        $this->documento = Documento::formatar($limpo);
    }

    #[Computed]
    public function municipios()
    {
        return Municipio::orderBy('uf')->orderBy('nome')->get(['id', 'nome', 'uf']);
    }

    /** Pendências que não bloqueiam o cadastro, mas bloqueiam a emissão. */
    #[Computed]
    public function pendenciasFiscais(): array
    {
        return RegrasPessoa::pendenciasParaEmissao([
            'tipo' => $this->tipo,
            'documento' => Documento::limpar($this->documento),
            'ie' => $this->ie,
            'ie_indicador' => $this->ie_indicador,
            'papeis' => $this->papeis,
            'rntrc' => $this->rntrc !== '' ? $this->rntrc : null,
        ]);
    }

    /* ── Persistência ──────────────────────────────────────── */

    protected function rules(): array
    {
        $documentoLimpo = Documento::limpar($this->documento);

        return [
            'tipo' => ['required', Rule::in([RegrasPessoa::TIPO_FISICA, RegrasPessoa::TIPO_JURIDICA, RegrasPessoa::TIPO_ESTRANGEIRO])],
            'documento' => [
                Rule::requiredIf($this->tipo !== RegrasPessoa::TIPO_ESTRANGEIRO),
                function (string $atributo, mixed $valor, callable $falhar) use ($documentoLimpo): void {
                    if ($this->tipo === RegrasPessoa::TIPO_ESTRANGEIRO) {
                        return;
                    }

                    if (! Documento::valido($documentoLimpo)) {
                        $falhar('Informe um CPF ou CNPJ válido.');
                    }
                },
                // Unicidade é composta com empresa_id — mas o valor não vem do
                // formulário: é o mesmo escopo que o EmpresaScope já aplica.
                Rule::unique('pessoas', 'documento')
                    ->ignore($this->pessoa?->id)
                    ->where(fn ($q) => $q->whereNull('deleted_at')),
            ],
            'razao_social' => ['required', 'string', 'max:150'],
            'nome_fantasia' => ['nullable', 'string', 'max:150'],
            'ie' => ['nullable', 'string', 'max:20', Rule::requiredIf(
                fn (): bool => RegrasPessoa::exigeInscricaoEstadual($this->ie_indicador)
            )],
            'ie_indicador' => ['required', Rule::in(RegrasPessoa::INDICADORES_IE)],
            'im' => ['nullable', 'string', 'max:20'],
            'suframa' => ['nullable', 'string', 'size:9'],
            'cnae' => ['nullable', 'string', 'size:7'],
            'email' => ['nullable', 'email', 'max:150'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'rntrc' => [Rule::requiredIf(fn (): bool => $this->exigeRntrc), 'nullable', 'string', 'max:10'],
            'rntrc_validade' => ['nullable', 'date'],
            'tp_transp' => ['nullable', Rule::in(['1', '2', '3'])],
            'papeis' => ['array'],
            'papeis.*' => [Rule::in(RegrasPessoa::PAPEIS)],
            'enderecos' => ['array'],
            'enderecos.*.logradouro' => ['required', 'string', 'max:150'],
            'enderecos.*.municipio_id' => ['required', 'integer', 'exists:municipios,id'],
            'enderecos.*.cep' => ['nullable', 'string', 'size:8'],
            'contatos.*.nome' => ['required', 'string', 'max:120'],
            'contatos.*.email' => ['nullable', 'email', 'max:150'],
        ];
    }

    protected function messages(): array
    {
        return [
            'documento.unique' => 'Já existe uma pessoa com este documento nesta empresa.',
            'ie.required' => 'Contribuinte de ICMS precisa de inscrição estadual — é o que vai no CT-e.',
            'rntrc.required' => 'Motorista e proprietário transportam carga: o RNTRC é obrigatório.',
            'enderecos.*.municipio_id.required' => 'O município é código IBGE, não texto — escolha na lista.',
        ];
    }

    public function salvar()
    {
        $this->normalizarPrincipal();
        $this->validate();

        $dados = [
            'tipo' => $this->tipo,
            'documento' => Documento::limpar($this->documento),
            'documento_estrangeiro' => $this->vazioParaNulo($this->documento_estrangeiro),
            'razao_social' => trim($this->razao_social),
            'nome_fantasia' => $this->vazioParaNulo($this->nome_fantasia),
            'ie' => $this->vazioParaNulo($this->ie),
            'ie_indicador' => $this->ie_indicador,
            'im' => $this->vazioParaNulo($this->im),
            'suframa' => $this->vazioParaNulo($this->suframa),
            'cnae' => $this->vazioParaNulo($this->cnae),
            'rntrc' => $this->vazioParaNulo($this->rntrc),
            'rntrc_validade' => $this->vazioParaNulo($this->rntrc_validade),
            'tp_transp' => $this->vazioParaNulo($this->tp_transp),
            'email' => $this->vazioParaNulo($this->email),
            'telefone' => $this->vazioParaNulo($this->telefone),
            'observacoes' => $this->vazioParaNulo($this->observacoes),
            'ativo' => $this->ativo,
        ];

        DB::transaction(function () use ($dados): void {
            $pessoa = $this->pessoa?->exists
                ? tap($this->pessoa)->update($dados)
                : Pessoa::create($dados);

            $this->sincronizarPapeis($pessoa);
            $this->sincronizarEnderecos($pessoa);
            $this->sincronizarContatos($pessoa);

            $this->pessoa = $pessoa->fresh(['papeis', 'enderecos', 'contatos']);
        });

        session()->flash('sucesso', 'Pessoa salva.');

        return $this->redirect(route('pessoas.index'), navigate: true);
    }

    /** Sem principal marcado, o primeiro endereço vira o principal. */
    private function normalizarPrincipal(): void
    {
        if ($this->enderecos === []) {
            return;
        }

        $temPrincipal = false;

        foreach ($this->enderecos as $e) {
            if ($e['principal'] ?? false) {
                $temPrincipal = true;
                break;
            }
        }

        if (! $temPrincipal) {
            $this->enderecos[0]['principal'] = true;
        }
    }

    private function sincronizarPapeis(Pessoa $pessoa): void
    {
        PessoaPapel::where('pessoa_id', $pessoa->id)
            ->whereNotIn('papel', $this->papeis ?: ['__nenhum__'])
            ->delete();

        foreach ($this->papeis as $papel) {
            PessoaPapel::updateOrCreate(
                ['pessoa_id' => $pessoa->id, 'papel' => $papel],
                ['ativo' => true],
            );
        }
    }

    private function sincronizarEnderecos(Pessoa $pessoa): void
    {
        $mantidos = [];

        // O principal vai por último: o índice parcial não tolera dois ao
        // mesmo tempo, nem por um instante dentro da transação.
        $ordenados = collect($this->enderecos)->sortBy(fn (array $e): int => ($e['principal'] ?? false) ? 1 : 0);

        foreach ($ordenados as $e) {
            $atributos = [
                'tipo' => $e['tipo'] ?: 'principal',
                'logradouro' => $e['logradouro'],
                'numero' => $this->vazioParaNulo((string) $e['numero']),
                'complemento' => $this->vazioParaNulo((string) $e['complemento']),
                'bairro' => $this->vazioParaNulo((string) $e['bairro']),
                'municipio_id' => $e['municipio_id'],
                'cep' => $this->vazioParaNulo((string) $e['cep']),
                'principal' => (bool) ($e['principal'] ?? false),
            ];

            $modelo = $e['id'] !== null
                ? tap(Endereco::findOrFail($e['id']))->update($atributos)
                : $pessoa->enderecos()->create($atributos);

            $mantidos[] = $modelo->id;
        }

        $pessoa->enderecos()->whereNotIn('id', $mantidos ?: [0])->delete();
    }

    private function sincronizarContatos(Pessoa $pessoa): void
    {
        $mantidos = [];

        foreach ($this->contatos as $c) {
            $atributos = [
                'nome' => $c['nome'],
                'cargo' => $this->vazioParaNulo((string) $c['cargo']),
                'email' => $this->vazioParaNulo((string) $c['email']),
                'telefone' => $this->vazioParaNulo((string) $c['telefone']),
                'setor' => $this->vazioParaNulo((string) $c['setor']),
                'recebe_dfe' => (bool) ($c['recebe_dfe'] ?? false),
            ];

            $modelo = $c['id'] !== null
                ? tap(Contato::findOrFail($c['id']))->update($atributos)
                : $pessoa->contatos()->create($atributos);

            $mantidos[] = $modelo->id;
        }

        $pessoa->contatos()->whereNotIn('id', $mantidos ?: [0])->delete();
    }

    private function vazioParaNulo(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    public function render(): View
    {
        return view('livewire.pessoas.formulario')
            ->layout('layouts.app', ['title' => $this->pessoa?->razao_social ?? 'Nova pessoa']);
    }
}
