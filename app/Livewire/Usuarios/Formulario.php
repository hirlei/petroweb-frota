<?php

declare(strict_types=1);

namespace App\Livewire\Usuarios;

use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Spatie\Permission\Models\Role;

/**
 * Rotina 9020 — ficha do usuário.
 *
 * Usuário é CONVIDADO pelo gestor: o formulário cria a conta e atribui papéis.
 * Não existe auto-cadastro. Perfis com poder fiscal/financeiro exigem 2FA na
 * primeira entrada — a checagem é da permissão (ver User::exige2fa()).
 *
 * empresa_id vem do TenantContext, nunca do formulário.
 */
class Formulario extends Component
{
    use AuthorizesRequests;

    public ?User $usuario = null;

    public string $name = '';
    public string $email = '';
    public string $senha = '';
    public ?int $filial_id = null;
    public bool $ativo = true;

    /** @var list<string> */
    public array $papeis = [];

    public function mount(?User $usuario = null): void
    {
        if ($usuario?->exists) {
            $this->authorize('update', $usuario);
            $this->usuario = $usuario->load('roles');
            $this->name = (string) $usuario->name;
            $this->email = (string) $usuario->email;
            $this->filial_id = $usuario->filial_id;
            $this->ativo = (bool) $usuario->ativo;
            $this->papeis = $usuario->roles->pluck('name')->all();

            return;
        }

        $this->authorize('create', User::class);
        $this->filial_id = TenantContext::filialId();
    }

    public function alternarPapel(string $papel): void
    {
        $this->papeis = in_array($papel, $this->papeis, true)
            ? array_values(array_diff($this->papeis, [$papel]))
            : [...$this->papeis, $papel];
    }

    #[Computed]
    public function papeisDisponiveis()
    {
        return Role::query()->orderBy('name')->pluck('name')->all();
    }

    #[Computed]
    public function filiais()
    {
        return \App\Models\Filial::orderBy('nome_fantasia')->get(['id', 'nome_fantasia', 'razao_social']);
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required', 'email', 'max:150',
                Rule::unique('users', 'email')->ignore($this->usuario?->id),
            ],
            'senha' => [Rule::requiredIf($this->usuario === null), 'nullable', 'string', 'min:8'],
            'filial_id' => ['nullable', 'integer', 'exists:filiais,id'],
            'papeis' => ['array'],
            'papeis.*' => [Rule::in($this->papeisDisponiveis)],
            'ativo' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'email.unique' => 'Já existe um usuário com este e-mail.',
            'senha.required' => 'Defina uma senha inicial — o usuário a troca no primeiro acesso.',
            'senha.min' => 'A senha precisa de ao menos 8 caracteres.',
        ];
    }

    public function salvar()
    {
        $this->validate();

        $dados = [
            'name' => trim($this->name),
            'email' => mb_strtolower(trim($this->email)),
            'filial_id' => $this->filial_id,
            'ativo' => $this->ativo,
        ];

        if ($this->senha !== '') {
            // O cast 'hashed' do model criptografa ao salvar.
            $dados['password'] = $this->senha;
        }

        DB::transaction(function () use ($dados): void {
            if ($this->usuario?->exists) {
                $this->usuario->update($dados);
                $usuario = $this->usuario;
            } else {
                $dados['empresa_id'] = TenantContext::empresaId();
                $usuario = User::create($dados);
            }

            $usuario->syncRoles($this->papeis);

            $this->usuario = $usuario->fresh('roles');
        });

        session()->flash('sucesso', 'Usuário salvo.');

        return $this->redirect(route('usuarios.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.usuarios.formulario')
            ->layout('layouts.app', ['title' => $this->usuario?->name ?? 'Novo usuário']);
    }
}
