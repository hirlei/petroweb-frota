{{--
    Rotina 3030 — Rotas planejadas.
    Trecho origem→destino com distância, tempo e pedágio estimados.
--}}
<div>
    <x-page-header
        title="Rotas"
        subtitle="{{ number_format($this->total, 0, ',', '.') }} rotas · trecho planejado com distância, tempo e pedágio estimados">
        <x-slot:actions>
            @can('create', \App\Models\Rota::class)
                <x-button variant="primary" size="sm" icon="plus" :href="route('rotas.criar')" wire:navigate>
                    Nova rota
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
                   placeholder="Descrição, origem ou destino…"
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
            <x-icon name="map" class="h-4 w-4 text-text-secondary" />
            <h2 class="text-sm font-semibold text-text">Rotas</h2>
            <span class="text-sm text-text-muted">({{ number_format($this->rotas->total(), 0, ',', '.') }})</span>
        </div>

        @if ($this->rotas->isEmpty())
            <x-empty-state icon="map" title="Nenhuma rota"
                           description="Cadastre trechos recorrentes para agilizar o planejamento das viagens." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-border bg-surface-elevated">
                        <tr>
                            @foreach (['Rota', 'Trecho', 'Distância', 'Tempo', 'Pedágio', 'Pontos', ''] as $cabecalho)
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-text-muted first:pl-5">{{ $cabecalho }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->rotas as $registro)
                            <tr wire:key="rota-{{ $registro->id }}" class="border-t border-border hover:bg-surface-elevated">
                                <td class="px-4 py-3 pl-5">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-text">{{ $registro->descricao }}</span>
                                        @unless ($registro->ativa) <x-badge variant="gray" class="text-[10px]">Inativa</x-badge> @endunless
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-text-secondary">
                                    {{ $registro->municipioOrigem?->nome }}/{{ $registro->municipioOrigem?->uf }}
                                    → {{ $registro->municipioDestino?->nome }}/{{ $registro->municipioDestino?->uf }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-text-secondary">
                                    {{ $registro->distancia_km ? number_format((float) $registro->distancia_km, 0, ',', '.') . ' km' : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-text-secondary">
                                    {{ $registro->tempoEstimadoHoras() !== null ? $registro->tempoEstimadoHoras() . ' h' : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-text-secondary">
                                    {{ $registro->valor_pedagio_estimado ? 'R$ ' . number_format((float) $registro->valor_pedagio_estimado, 2, ',', '.') : '—' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-text-secondary">{{ $registro->pontos_count }}</td>
                                <td class="px-4 py-3 text-right">
                                    @can('update', $registro)
                                        <a href="{{ route('rotas.editar', $registro) }}" wire:navigate
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
                {{ $this->rotas->links() }}
            </div>
        @endif
    </x-card>
</div>
