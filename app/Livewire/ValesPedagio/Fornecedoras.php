<?php

declare(strict_types=1);

namespace App\Livewire\ValesPedagio;

use App\Domain\Cadastro\Documento;
use App\Models\FornecedorVpo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Janela "Fornecedoras de vale-pedágio" (mockup aprovado em 03/10/2026).
 * Abre no 4030 e dentro do bloco do vale no MDF-e (4020) — evento
 * `abrir-fornecedoras`. Ao salvar, avisa a tela com `fornecedoras-atualizadas`.
 *
 * Catálogo GLOBAL do tenant (sem empresa_id): só fornecedora habilitada pela
 * ANTT vale no MDF-e (rejeição 733). Inativa não aparece na emissão.
 */
class Fornecedoras extends Component
{
    public bool $aberto = false;

    public ?int $editandoId = null;

    public string $cnpj = '';

    public string $razao_social = '';

    public string $ato_habilitacao = '';

    #[On('abrir-fornecedoras')]
    public function abrir(): void
    {
        $this->autorizar();
        $this->limpar();
        $this->aberto = true;
    }

    public function fechar(): void
    {
        $this->aberto = false;
        $this->limpar();
    }

    #[Computed]
    public function lista()
    {
        return FornecedorVpo::query()->orderByDesc('ativo')->orderBy('razao_social')->get();
    }

    public function editar(int $id): void
    {
        $this->autorizar();
        $f = FornecedorVpo::query()->findOrFail($id);
        $this->editandoId = $f->id;
        $this->cnpj = Documento::formatar((string) $f->cnpj);
        $this->razao_social = (string) $f->razao_social;
        $this->ato_habilitacao = (string) ($f->ato_habilitacao ?? '');
        $this->resetErrorBag();
    }

    public function cancelarEdicao(): void
    {
        $this->limpar();
    }

    public function salvar(): void
    {
        $this->autorizar();
        $this->cnpj = Documento::limpar($this->cnpj);

        $this->validate([
            'cnpj' => ['required', 'size:14', Rule::unique('fornecedores_vpo', 'cnpj')->ignore($this->editandoId)],
            'razao_social' => ['required', 'string', 'max:150'],
            'ato_habilitacao' => ['nullable', 'string', 'max:60'],
        ], [
            'cnpj.required' => 'Informe o CNPJ.',
            'cnpj.size' => 'O CNPJ tem 14 caracteres.',
            'cnpj.unique' => 'Já existe uma fornecedora com este CNPJ.',
            'razao_social.required' => 'Informe o nome.',
        ]);

        if (! Documento::cnpjValido($this->cnpj)) {
            $this->addError('cnpj', 'CNPJ inválido — confira os dígitos.');

            return;
        }

        $dados = [
            'cnpj' => $this->cnpj,
            'razao_social' => trim($this->razao_social),
            'ato_habilitacao' => trim($this->ato_habilitacao) !== '' ? trim($this->ato_habilitacao) : null,
        ];

        $f = $this->editandoId !== null
            ? tap(FornecedorVpo::query()->findOrFail($this->editandoId))->update($dados)
            : FornecedorVpo::create($dados + ['ativo' => true]);

        $this->limpar();
        unset($this->lista);
        $this->dispatch('fornecedoras-atualizadas', id: $f->id);
    }

    public function alternarAtivo(int $id): void
    {
        $this->autorizar();
        $f = FornecedorVpo::query()->findOrFail($id);
        $f->update(['ativo' => ! $f->ativo]);
        unset($this->lista);
        $this->dispatch('fornecedoras-atualizadas', id: $f->ativo ? $f->id : null);
    }

    private function limpar(): void
    {
        $this->editandoId = null;
        $this->cnpj = '';
        $this->razao_social = '';
        $this->ato_habilitacao = '';
        $this->resetErrorBag();
    }

    private function autorizar(): void
    {
        abort_unless(Auth::user()?->can('mdfe.emitir') ?? false, 403);
    }

    public function render(): View
    {
        return view('livewire.vales-pedagio.fornecedoras');
    }
}
