{{--
    Rotina 9030 — Papéis e permissões (leitura).
    O que cada papel pode fazer, por módulo. Os papéis canônicos vêm do seeder
    para ficarem iguais em todos os tenants; a edição fica para o sprint de segurança.
--}}
@php
    $papelCor = [
        'Administrador' => 'purple', 'Fiscal' => 'info', 'Operação' => 'secondary',
        'Financeiro' => 'warning', 'Consulta' => 'gray',
    ];
    $acaoCor = ['Consultar' => 'gray', 'Gerenciar' => 'info', 'Emitir' => 'secondary',
        'Cancelar' => 'danger', 'Encerrar' => 'warning', 'Baixar' => 'warning', 'Aprovar' => 'secondary'];
@endphp
<div>
    <x-page-header
        title="Papéis e permissões"
        subtitle="O que cada papel pode fazer, por módulo · leitura — os papéis canônicos vêm do sistema" />

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[320px_1fr] items-start">

        {{-- Lista de papéis --}}
        <x-card padding="none" class="overflow-hidden">
            <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                <x-icon name="key" class="h-4 w-4 text-text-secondary" />
                <h2 class="text-sm font-semibold text-text">Papéis</h2>
                <span class="text-sm text-text-muted">({{ $this->papeis->count() }})</span>
            </div>
            <div class="flex flex-col">
                @foreach ($this->papeis as $papel)
                    <button type="button" wire:click="selecionar({{ $papel->id }})"
                            class="flex items-center gap-3 border-b border-border px-5 py-3 text-left transition-colors
                                   {{ $papelSelecionado === $papel->id ? 'bg-primary-soft' : 'hover:bg-surface-elevated' }}">
                        <x-badge :variant="$papelCor[$papel->name] ?? 'gray'" class="text-[10px]">{{ $papel->name }}</x-badge>
                        <div class="flex-1"></div>
                        <div class="text-right">
                            <div class="text-xs font-medium text-text">{{ $papel->permissions_count }} permissões</div>
                            <div class="text-[11px] text-text-muted">{{ $papel->users_count }} {{ $papel->users_count === 1 ? 'usuário' : 'usuários' }}</div>
                        </div>
                    </button>
                @endforeach
            </div>
        </x-card>

        {{-- Detalhe do papel --}}
        <div class="flex flex-col gap-3">
            @if ($this->papel)
                <x-card padding="none" class="overflow-hidden">
                    <div class="flex items-center gap-2 border-b border-border bg-primary-soft px-5 py-3.5">
                        <x-icon name="shield-check" class="h-4 w-4 text-amber-700" />
                        <h2 class="text-sm font-semibold text-amber-700">{{ $this->papel->name }}</h2>
                        <span class="text-sm text-amber-700/70">· {{ $this->papel->permissions_count ?? $this->papel->permissions->count() }} permissões</span>
                    </div>
                    <div class="px-5 py-4">
                        @if ($this->permissoesPorModulo === [])
                            <x-empty-state icon="key" title="Sem permissões" description="Este papel não tem permissões atribuídas." />
                        @else
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                @foreach ($this->permissoesPorModulo as $modulo => $acoes)
                                    <div class="rounded-lg border border-border p-3">
                                        <div class="mb-2 text-xs font-semibold text-text">{{ $modulo }}</div>
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($acoes as $acao)
                                                <x-badge :variant="$acaoCor[$acao] ?? 'gray'" class="text-[10px]">{{ $acao }}</x-badge>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </x-card>
            @else
                <x-card padding="md">
                    <x-empty-state icon="key" title="Selecione um papel"
                                   description="As permissões do papel, agrupadas por módulo, aparecem aqui." />
                </x-card>
            @endif

            <x-card padding="sm">
                <div class="flex items-start gap-2 text-xs leading-relaxed text-text-secondary">
                    <x-icon name="info" class="mt-px h-3.5 w-3.5 flex-shrink-0 text-text-muted" />
                    <span>Os papéis são definidos no sistema para ficarem idênticos em todos os clientes — assim uma permissão nova entra no lugar certo de uma vez. A atribuição de papéis a usuários é feita na rotina 9020.</span>
                </div>
            </x-card>
        </div>
    </div>
</div>
