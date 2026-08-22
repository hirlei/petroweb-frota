{{--
    Rotina 1020 — Produtos (mercadorias).
    Catálogo de cargas. Chips para perigosas e refrigeradas, badge de ONU.
--}}
<div>
    <x-page-header
        title="Produtos"
        subtitle="{{ number_format($this->total, 0, ',', '.') }} mercadorias · {{ $this->totalPerigosas }} perigosas — o catálogo de cargas da operação">
        <x-slot:actions>
            @can('create', \App\Models\Mercadoria::class)
                <x-button variant="primary" size="sm" icon="plus" :href="route('produtos.criar')" wire:navigate>
                    Novo produto
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

    <div class="mb-4 flex flex-wrap items-center gap-1.5">
        @foreach (['' => 'Todos', 'perigosas' => 'Perigosas', 'refrigeradas' => 'Refrigeradas'] as $chave => $rotulo)
            <button type="button" wire:click="$set('filtro', '{{ $chave }}')"
                    class="rounded-full px-3 py-1.5 text-sm font-medium transition-colors
                           {{ $filtro === $chave ? 'bg-primary-soft text-amber-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' }}">
                {{ $rotulo }}
            </button>
        @endforeach
        <div class="flex-1"></div>
        <select wire:model.live="situacao" class="rounded-md border border-border bg-surface px-3 py-1.5 text-sm text-text">
            <option value="ativos">Somente ativos</option>
            <option value="inativos">Inativos</option>
            <option value="todos">Todos</option>
        </select>
    </div>

    <div class="mb-4">
        <div class="relative max-w-sm">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
            <input type="search" wire:model.live.debounce.400ms="busca" placeholder="Descrição, código, NCM ou ONU…"
                   class="w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm text-text
                          outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>
    </div>

    <x-card padding="none" class="overflow-hidden">
        <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
            <x-icon name="package" class="h-4 w-4 text-text-secondary" />
            <h2 class="text-sm font-semibold text-text">Catálogo</h2>
            <span class="text-sm text-text-muted">({{ number_format($this->mercadorias->total(), 0, ',', '.') }})</span>
        </div>

        @if ($this->mercadorias->isEmpty())
            <x-empty-state icon="package" title="Nenhuma mercadoria"
                           description="Cadastre as cargas que a transportadora movimenta para agilizar ordens e CT-e." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-border bg-surface-elevated">
                        <tr>
                            @foreach (['Descrição', 'Código', 'NCM', 'Natureza', 'Marcadores', 'Situação', ''] as $cabecalho)
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-text-muted first:pl-5">{{ $cabecalho }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->mercadorias as $registro)
                            <tr wire:key="merc-{{ $registro->id }}" class="border-t border-border hover:bg-surface-elevated">
                                <td class="px-4 py-3 pl-5">
                                    <div class="text-sm font-medium text-text">{{ $registro->descricao }}</div>
                                    @if ($registro->marca)
                                        <div class="mt-0.5 text-xs text-text-muted">{{ $registro->marca }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3"><span class="font-mono text-xs text-text-secondary">{{ $registro->codigo_interno }}</span></td>
                                <td class="px-4 py-3"><span class="font-mono text-xs text-text-secondary">{{ $registro->ncm ?? '—' }}</span></td>
                                <td class="px-4 py-3 text-sm text-text-secondary">{{ $registro->naturezaCarga?->nome ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @if ($registro->eh_perigoso)
                                            <x-badge variant="danger" class="text-[10px]">ONU {{ $registro->num_onu ?? '—' }}</x-badge>
                                        @endif
                                        @if ($registro->exige_temp_controlada)
                                            <x-badge variant="info" class="text-[10px]">Refrigerada</x-badge>
                                        @endif
                                        @if ($registro->fragil)
                                            <x-badge variant="warning" class="text-[10px]">Frágil</x-badge>
                                        @endif
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
                                        <a href="{{ route('produtos.editar', $registro) }}" wire:navigate
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
                {{ $this->mercadorias->links() }}
            </div>
        @endif
    </x-card>
</div>
