{{--
    Rotina 2020 — Composições.
    A combinação montada: cavalo + reboques na ordem em que rodam. A categoria
    e a AET saem da soma dos eixos, calculadas — nunca digitadas.
--}}
<div>
    <x-page-header
        title="Composições"
        subtitle="{{ number_format($this->total, 0, ',', '.') }} combinações · cavalo mais reboques na ordem em que rodam">
        <x-slot:actions>
            @can('create', \App\Models\Composicao::class)
                <x-button variant="primary" size="sm" icon="plus" :href="route('composicoes.criar')" wire:navigate>
                    Nova composição
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

    <div class="mb-4 flex items-center gap-3">
        <div class="relative max-w-sm flex-1">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
            <input type="search" wire:model.live.debounce.400ms="busca"
                   placeholder="Descrição ou placa da tração…"
                   class="w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm text-text
                          outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>
        <select wire:model.live="situacao"
                class="rounded-md border border-border bg-surface px-3 py-1.5 text-sm text-text">
            <option value="ativas">Somente ativas</option>
            <option value="inativas">Inativas</option>
            <option value="todas">Todas</option>
        </select>
    </div>

    <x-card padding="none" class="overflow-hidden">
        <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
            <x-icon name="link" class="h-4 w-4 text-text-secondary" />
            <h2 class="text-sm font-semibold text-text">Combinações</h2>
            <span class="text-sm text-text-muted">({{ number_format($this->composicoes->total(), 0, ',', '.') }})</span>
        </div>

        @if ($this->composicoes->isEmpty())
            <x-empty-state icon="link" title="Nenhuma composição"
                           description="Monte a combinação escolhendo o cavalo e os reboques na ordem em que rodam." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-border bg-surface-elevated">
                        <tr>
                            @foreach (['Descrição', 'Tração', 'Unidades', 'Eixos', 'Categoria', 'AET', 'Situação'] as $cabecalho)
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-text-muted first:pl-5">
                                    {{ $cabecalho }}
                                </th>
                            @endforeach
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->composicoes as $registro)
                            <tr wire:key="composicao-{{ $registro->id }}" class="border-t border-border hover:bg-surface-elevated">
                                <td class="px-4 py-3 pl-5">
                                    <div class="text-sm font-medium text-text">{{ $registro->descricao }}</div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="font-mono text-xs text-text">{{ $registro->tracao?->placaFormatada() ?? '—' }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-text-secondary">{{ $registro->veiculos->count() }}</td>
                                <td class="px-4 py-3 text-sm text-text-secondary">{{ $registro->eixos_total ?? $registro->veiculos->sum('eixos') }}</td>
                                <td class="px-4 py-3">
                                    <x-badge variant="primary" class="text-[10px]">{{ $registro->categoriaCombinacaoVeicular()->value }}</x-badge>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($registro->precisaAet())
                                        <x-badge variant="warning" class="text-[10px]">Exige AET</x-badge>
                                    @else
                                        <span class="text-xs text-text-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium {{ $registro->ativa ? 'text-green-700' : 'text-text-muted' }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $registro->ativa ? 'bg-success' : 'bg-text-muted' }}"></span>
                                        {{ $registro->ativa ? 'Ativa' : 'Inativa' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @can('update', $registro)
                                        <a href="{{ route('composicoes.editar', $registro) }}" wire:navigate
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
                {{ $this->composicoes->links() }}
            </div>
        @endif
    </x-card>
</div>
