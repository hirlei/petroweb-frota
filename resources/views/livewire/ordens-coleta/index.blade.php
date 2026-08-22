{{--
    Rotina 3010 — Ordens de coleta.
    Documento de entrada da operação; ao ser faturada vira CT-e.
--}}
<div>
    <x-page-header
        title="Ordens de coleta"
        subtitle="{{ number_format($this->contagem['total'], 0, ',', '.') }} ordens · o pedido de transporte que origina o CT-e">
        <x-slot:actions>
            @can('create', \App\Models\OrdemColeta::class)
                <x-button variant="primary" size="sm" icon="plus" :href="route('ordens-coleta.criar')" wire:navigate>
                    Nova ordem
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
        @php
            $chips = [
                '' => 'Todas · ' . $this->contagem['total'],
                'aberta' => 'Abertas · ' . $this->contagem['aberta'],
                'coletada' => 'Coletadas · ' . $this->contagem['coletada'],
                'faturada' => 'Faturadas · ' . $this->contagem['faturada'],
            ];
        @endphp
        @foreach ($chips as $chave => $rotulo)
            <button type="button" wire:click="$set('status', '{{ $chave }}')"
                    class="rounded-full px-3 py-1.5 text-sm font-medium transition-colors
                           {{ $status === $chave ? 'bg-primary-soft text-amber-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' }}">
                {{ $rotulo }}
            </button>
        @endforeach

        <div class="flex-1"></div>

        <div class="relative max-w-xs flex-1">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
            <input type="search" wire:model.live.debounce.400ms="busca"
                   placeholder="Número ou cliente…"
                   class="w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm text-text
                          outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>
    </div>

    <x-card padding="none" class="overflow-hidden">
        <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
            <x-icon name="file-text" class="h-4 w-4 text-text-secondary" />
            <h2 class="text-sm font-semibold text-text">Ordens</h2>
            <span class="text-sm text-text-muted">({{ number_format($this->ordens->total(), 0, ',', '.') }})</span>
        </div>

        @if ($this->ordens->isEmpty())
            <x-empty-state icon="file-text" title="Nenhuma ordem de coleta"
                           description="Registre o pedido de transporte do cliente para calcular o frete e, depois, gerar o CT-e." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-border bg-surface-elevated">
                        <tr>
                            @foreach (['Ordem', 'Cliente', 'Trecho', 'Peso', 'Frete', 'Status', ''] as $cabecalho)
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-text-muted first:pl-5">{{ $cabecalho }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->ordens as $registro)
                            <tr wire:key="oc-{{ $registro->id }}" class="border-t border-border hover:bg-surface-elevated">
                                <td class="px-4 py-3 pl-5">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-semibold text-text tabular-nums">Nº {{ $registro->numero }}</span>
                                        <span class="text-xs text-text-muted">{{ $registro->data?->format('d/m/Y') }} · {{ $registro->itens_count }} {{ $registro->itens_count == 1 ? 'item' : 'itens' }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-text-secondary">{{ $registro->cliente?->razao_social ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-text-secondary">
                                    @if ($registro->municipioInicio || $registro->municipioFim)
                                        {{ $registro->municipioInicio?->nome ?? '—' }}
                                        <span class="text-text-muted">→</span>
                                        {{ $registro->municipioFim?->nome ?? '—' }}
                                    @else
                                        <span class="text-text-muted">—</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-text-secondary tabular-nums">
                                    {{ $registro->peso_bruto ? number_format((float) $registro->peso_bruto, 0, ',', '.') . ' kg' : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-text tabular-nums">
                                    {{ $registro->valor_frete_calculado !== null ? 'R$ ' . number_format((float) $registro->valor_frete_calculado, 2, ',', '.') : '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <x-badge :variant="config('ordens_coleta.status_cores.' . $registro->status, 'gray')" class="text-[10px]">
                                        {{ config('ordens_coleta.status.' . $registro->status, $registro->status) }}
                                    </x-badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @can('update', $registro)
                                        <a href="{{ route('ordens-coleta.editar', $registro) }}" wire:navigate
                                           class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-primary hover:bg-primary-soft">
                                            <x-icon name="pencil" class="h-3.5 w-3.5" /> Abrir
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-border px-5 py-3">
                {{ $this->ordens->links() }}
            </div>
        @endif
    </x-card>
</div>
