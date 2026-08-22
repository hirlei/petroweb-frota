{{--
    Rotina 9020 — Usuários.
    Usuário é convidado pelo gestor. Lista com papéis (spatie), filial e situação.
--}}
@php
    $papelCor = [
        'Administrador' => 'purple', 'Fiscal' => 'info', 'Operação' => 'secondary',
        'Financeiro' => 'warning', 'Consulta' => 'gray',
    ];
@endphp
<div>
    <x-page-header
        title="Usuários"
        subtitle="{{ number_format($this->total, 0, ',', '.') }} contas · convidadas pelo gestor — não há auto-cadastro">
        <x-slot:actions>
            @can('create', \App\Models\User::class)
                <x-button variant="primary" size="sm" icon="plus" :href="route('usuarios.criar')" wire:navigate>
                    Novo usuário
                </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if (session('sucesso'))
        <div class="mb-4 flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800
                    dark:border-green-800/50 dark:bg-green-950/50 dark:text-green-300">
            <x-icon name="check" class="w-4 h-4 flex-shrink-0" />
            {{ session('sucesso') }}
        </div>
    @endif

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="relative max-w-sm flex-1">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
            <input type="search" wire:model.live.debounce.400ms="busca"
                   placeholder="Nome ou e-mail…"
                   class="w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm text-text
                          outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>
        <select wire:model.live="papel"
                class="rounded-md border border-border bg-surface px-3 py-1.5 text-sm text-text">
            <option value="">Todos os papéis</option>
            @foreach (['Administrador', 'Fiscal', 'Operação', 'Financeiro', 'Consulta'] as $p)
                <option value="{{ $p }}">{{ $p }}</option>
            @endforeach
        </select>
        <select wire:model.live="situacao"
                class="rounded-md border border-border bg-surface px-3 py-1.5 text-sm text-text">
            <option value="ativos">Somente ativos</option>
            <option value="inativos">Inativos</option>
            <option value="todos">Todos</option>
        </select>
    </div>

    <x-card padding="none" class="overflow-hidden">
        <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
            <x-icon name="users" class="h-4 w-4 text-text-secondary" />
            <h2 class="text-sm font-semibold text-text">Contas</h2>
            <span class="text-sm text-text-muted">({{ number_format($this->usuarios->total(), 0, ',', '.') }})</span>
        </div>

        @if ($this->usuarios->isEmpty())
            <x-empty-state icon="users" title="Nenhum usuário encontrado"
                           description="Convide o primeiro usuário para dar acesso à operação." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-border bg-surface-elevated">
                        <tr>
                            @foreach (['Nome', 'E-mail', 'Papéis', 'Situação', ''] as $cabecalho)
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-text-muted first:pl-5">{{ $cabecalho }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->usuarios as $registro)
                            <tr wire:key="usuario-{{ $registro->id }}" class="border-t border-border hover:bg-surface-elevated">
                                <td class="px-4 py-3 pl-5">
                                    <div class="text-sm font-medium text-text">{{ $registro->name }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-sm text-text-secondary">{{ $registro->email }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($registro->roles as $role)
                                            <x-badge :variant="$papelCor[$role->name] ?? 'gray'" class="text-[10px]">{{ $role->name }}</x-badge>
                                        @empty
                                            <span class="text-xs text-text-muted">Sem papel</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium {{ $registro->ativo ? 'text-green-700' : 'text-text-muted' }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $registro->ativo ? 'bg-success' : 'bg-text-muted' }}"></span>
                                        {{ $registro->ativo ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @can('update', $registro)
                                        <a href="{{ route('usuarios.editar', $registro) }}" wire:navigate
                                           class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-primary hover:bg-primary-soft">
                                            <x-icon name="pencil" class="h-3.5 w-3.5" /> Editar
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-border px-5 py-3">
                {{ $this->usuarios->links() }}
            </div>
        @endif
    </x-card>
</div>
