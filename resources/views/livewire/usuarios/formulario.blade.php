{{--
    Rotina 9020 — ficha do usuário.
    Convite pelo gestor: cria a conta e atribui papéis. Sem auto-cadastro.
--}}
@php
    $papelCor = [
        'Administrador' => 'purple', 'Fiscal' => 'info', 'Operação' => 'secondary',
        'Financeiro' => 'warning', 'Consulta' => 'gray',
    ];
    $papelDesc = [
        'Administrador' => 'Acesso total, inclusive gestão de usuários e permissões.',
        'Fiscal' => 'Emite e gere CT-e, MDF-e e certificados. Exige 2FA.',
        'Operação' => 'Cadastros, frota, ordens de coleta e viagens.',
        'Financeiro' => 'Faturas, títulos e acertos. Exige 2FA.',
        'Consulta' => 'Somente leitura — enxerga, não altera.',
    ];
@endphp
<div>
    <x-page-header
        :title="$usuario?->exists ? $usuario->name : 'Novo usuário'"
        :subtitle="$usuario?->exists ? 'Configurações · rotina 9020' : 'Convide um usuário e atribua os papéis de acesso'">
        <x-slot:actions>
            <x-button variant="ghost" size="sm" :href="route('usuarios.index')" wire:navigate>Cancelar</x-button>
            <x-button variant="primary" size="sm" icon="check" wire:click="salvar" wire:loading.attr="disabled">Salvar</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-[1fr_372px]">

        <div class="flex flex-col gap-4">
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="users" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Dados da conta</h2>
                </div>
                <div class="px-5 py-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-input label="Nome" required wire:model="name" :error="$errors->first('name')" />
                        <x-input label="E-mail" type="email" required wire:model="email" :error="$errors->first('email')"
                                 :help="$usuario?->exists ? null : 'É por aqui que o convite e as notificações chegam.'" />
                        <x-input :label="$usuario?->exists ? 'Redefinir senha' : 'Senha inicial'"
                                 type="password" :required="! $usuario?->exists" wire:model="senha"
                                 :error="$errors->first('senha')"
                                 :help="$usuario?->exists ? 'Deixe em branco para manter a senha atual.' : 'Mínimo 8 caracteres — o usuário troca no primeiro acesso.'" />
                        <x-select label="Filial padrão" wire:model="filial_id">
                            <option value="">Nenhuma</option>
                            @foreach ($this->filiais as $filial)
                                <option value="{{ $filial->id }}">{{ $filial->nome_fantasia ?? $filial->razao_social }}</option>
                            @endforeach
                        </x-select>
                    </div>

                    <label class="mt-4 flex items-center gap-2">
                        <input type="checkbox" wire:model="ativo"
                               class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30">
                        <span class="text-sm text-text">Conta ativa (pode entrar no sistema)</span>
                    </label>
                </div>
            </x-card>

            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="key" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Papéis</h2>
                    <span class="text-sm text-text-muted">({{ count($papeis) }})</span>
                </div>
                <div class="px-5 py-4">
                    @error('papeis.*') <p class="mb-2 text-xs text-danger">{{ $message }}</p> @enderror
                    <div class="flex flex-col gap-2">
                        @foreach ($this->papeisDisponiveis as $nome)
                            @php $marcado = in_array($nome, $papeis, true); @endphp
                            <button type="button" wire:click="alternarPapel('{{ $nome }}')"
                                    class="flex items-start gap-3 rounded-lg border p-3 text-left transition-colors
                                           {{ $marcado ? 'border-primary bg-primary-soft/40' : 'border-border hover:bg-surface-elevated' }}">
                                <span class="mt-0.5 flex h-5 w-5 items-center justify-center rounded border
                                             {{ $marcado ? 'border-primary bg-primary text-white' : 'border-border' }}">
                                    @if ($marcado) <x-icon name="check" class="h-3.5 w-3.5" /> @endif
                                </span>
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold text-text">{{ $nome }}</span>
                                        <x-badge :variant="$papelCor[$nome] ?? 'gray'" class="text-[10px]">{{ $nome }}</x-badge>
                                    </div>
                                    <div class="mt-0.5 text-xs text-text-muted">{{ $papelDesc[$nome] ?? 'Papel personalizado.' }}</div>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>
            </x-card>
        </div>

        <div class="flex flex-col gap-3">
            <x-card padding="sm">
                <div class="flex items-start gap-2 text-xs leading-relaxed text-text-secondary">
                    <x-icon name="shield-check" class="mt-px h-3.5 w-3.5 flex-shrink-0 text-text-muted" />
                    <span>Papéis com poder fiscal ou financeiro (Fiscal, Financeiro, Administrador) exigem autenticação em duas etapas no primeiro acesso.</span>
                </div>
            </x-card>
            <x-card padding="sm">
                <div class="flex items-start gap-2 text-xs leading-relaxed text-text-secondary">
                    <x-icon name="lock" class="mt-px h-3.5 w-3.5 flex-shrink-0 text-text-muted" />
                    <span>A empresa do usuário vem do contexto do gestor logado — não há campo de empresa aqui.</span>
                </div>
            </x-card>
        </div>
    </div>
</div>
