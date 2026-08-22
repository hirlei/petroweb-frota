{{--
    Rotina 3060 — Despesas de viagem.
    Gastos de estrada por viagem; a despesa aprovada entra no custo da viagem.
--}}
<div>
    <x-page-header
        title="Despesas de viagem"
        subtitle="Gasto do mês: R$ {{ number_format($this->resumo['gasto_mes'], 2, ',', '.') }} · {{ $this->resumo['pendentes'] }} aguardando aprovação">
        <x-slot:actions>
            @can('create', \App\Models\Despesa::class)
                <x-button variant="primary" size="sm" icon="plus" :href="route('despesas.criar')" wire:navigate>
                    Nova despesa
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
                '' => 'Todas · ' . $this->resumo['total'],
                'pendentes' => 'Pendentes · ' . $this->resumo['pendentes'],
                'aprovadas' => 'Aprovadas',
            ];
        @endphp
        @foreach ($chips as $chave => $rotulo)
            <button type="button" wire:click="$set('situacao', '{{ $chave }}')"
                    class="rounded-full px-3 py-1.5 text-sm font-medium transition-colors
                           {{ $situacao === $chave ? 'bg-primary-soft text-amber-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' }}">
                {{ $rotulo }}
            </button>
        @endforeach

        <div class="flex-1"></div>

        <div class="relative max-w-xs flex-1">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
            <input type="search" wire:model.live.debounce.400ms="busca"
                   placeholder="Viagem, motorista ou tipo…"
                   class="w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm text-text
                          outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>
    </div>

    @if ($this->resumo['adiantado'] > 0)
        <div class="mb-4 flex items-start gap-2 rounded-r-md border-l-[3px] border-info bg-blue-50 px-4 py-2.5 text-sm text-blue-800
                    dark:bg-blue-950/40 dark:text-blue-300">
            <x-icon name="info" class="mt-0.5 h-4 w-4 flex-shrink-0" />
            <span>R$ {{ number_format($this->resumo['adiantado'], 2, ',', '.') }} em adiantamentos ainda pendentes de aprovação/prestação de contas.</span>
        </div>
    @endif

    <x-card padding="none" class="overflow-hidden">
        <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
            <x-icon name="ticket" class="h-4 w-4 text-text-secondary" />
            <h2 class="text-sm font-semibold text-text">Lançamentos</h2>
            <span class="text-sm text-text-muted">({{ number_format($this->despesas->total(), 0, ',', '.') }})</span>
        </div>

        @if ($this->despesas->isEmpty())
            <x-empty-state icon="ticket" title="Nenhuma despesa"
                           description="Lance pedágio, alimentação, chapa e afins por viagem — o custo entra na margem ao aprovar." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-border bg-surface-elevated">
                        <tr>
                            @foreach (['Data', 'Viagem', 'Motorista', 'Tipo', 'Forma', 'Valor', 'Situação', ''] as $cabecalho)
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-text-muted first:pl-5">{{ $cabecalho }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->despesas as $d)
                            <tr wire:key="dp-{{ $d->id }}" class="border-t border-border hover:bg-surface-elevated">
                                <td class="whitespace-nowrap px-4 py-3 pl-5 text-sm text-text-secondary tabular-nums">{{ $d->data?->format('d/m/Y') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-text tabular-nums">Nº {{ $d->viagem?->numero ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-text-secondary">{{ $d->motorista?->pessoa?->razao_social ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-text-secondary">{{ config('despesas.tipos.' . $d->tipo, $d->tipo) }}</td>
                                <td class="px-4 py-3">
                                    <x-badge :variant="config('despesas.forma_cores.' . $d->forma_pagamento, 'gray')" class="text-[10px]">
                                        {{ config('despesas.formas_pagamento.' . $d->forma_pagamento, $d->forma_pagamento) }}
                                    </x-badge>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-text tabular-nums">R$ {{ number_format((float) $d->valor, 2, ',', '.') }}</td>
                                <td class="px-4 py-3">
                                    @if ($d->aprovada)
                                        <x-badge variant="success" class="text-[10px]">Aprovada</x-badge>
                                    @else
                                        <x-badge variant="warning" class="text-[10px]">Pendente</x-badge>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    @can('update', $d)
                                        @unless ($d->aprovada)
                                            <button type="button" wire:click="aprovar({{ $d->id }})"
                                                    class="mr-1 inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-success hover:bg-green-50 dark:hover:bg-green-950/40">
                                                <x-icon name="check" class="h-3.5 w-3.5" /> Aprovar
                                            </button>
                                        @else
                                            <button type="button" wire:click="reabrir({{ $d->id }})"
                                                    class="mr-1 inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-text-secondary hover:bg-surface-elevated">
                                                Reabrir
                                            </button>
                                        @endunless
                                        <a href="{{ route('despesas.editar', $d) }}" wire:navigate
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
                {{ $this->despesas->links() }}
            </div>
        @endif
    </x-card>
</div>
