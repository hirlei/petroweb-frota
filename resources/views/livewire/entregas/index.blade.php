{{--
    Rotina 3050 — Entregas (POD).
    Prova de entrega por viagem/ordem; canhoto, foto ou evento eletrônico.
--}}
<div>
    <x-page-header
        title="Entregas (POD)"
        subtitle="{{ number_format($this->resumo['total'], 0, ',', '.') }} entregas · {{ $this->resumo['a_comprovar'] }} a comprovar">
        <x-slot:actions>
            @can('create', \App\Models\Entrega::class)
                <x-button variant="primary" size="sm" icon="plus" :href="route('entregas.criar')" wire:navigate>
                    Registrar entrega
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
                'a_comprovar' => 'A comprovar · ' . $this->resumo['a_comprovar'],
                'comprovadas' => 'Comprovadas · ' . $this->resumo['comprovadas'],
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
                   placeholder="Recebedor, viagem ou ordem…"
                   class="w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm text-text
                          outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>
    </div>

    <x-card padding="none" class="overflow-hidden">
        <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
            <x-icon name="inbox" class="h-4 w-4 text-text-secondary" />
            <h2 class="text-sm font-semibold text-text">Comprovantes</h2>
            <span class="text-sm text-text-muted">({{ number_format($this->entregas->total(), 0, ',', '.') }})</span>
        </div>

        @if ($this->entregas->isEmpty())
            <x-empty-state icon="inbox" title="Nenhuma entrega registrada"
                           description="Registre o comprovante de entrega (canhoto, foto ou evento) para fechar o ciclo da viagem." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-border bg-surface-elevated">
                        <tr>
                            @foreach (['Ordem / CT-e', 'Destinatário', 'Cidade', 'Recebedor', 'Data/hora', 'Comprovação', ''] as $cabecalho)
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-text-muted first:pl-5">{{ $cabecalho }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->entregas as $e)
                            <tr wire:key="en-{{ $e->id }}" class="border-t border-border hover:bg-surface-elevated">
                                <td class="whitespace-nowrap px-4 py-3 pl-5 text-sm font-medium text-text tabular-nums">
                                    @if ($e->ordemColeta)
                                        OC {{ $e->ordemColeta->numero }}
                                    @elseif ($e->viagem)
                                        Viagem {{ $e->viagem->numero }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-text-secondary">{{ $e->ordemColeta?->destinatario?->razao_social ?? $e->ordemColeta?->cliente?->razao_social ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-text-secondary">{{ $e->ordemColeta?->municipioFim?->nome ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-text-secondary">{{ $e->recebedor_nome ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-text-secondary tabular-nums">{{ $e->data_hora?->format('d/m H:i') ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @if ($e->comprovada())
                                        <x-badge :variant="config('entregas.comprovacao_cores.' . $e->tipo_comprovacao, 'success')" class="text-[10px]">
                                            {{ config('entregas.tipos_comprovacao.' . $e->tipo_comprovacao, $e->tipo_comprovacao) }}
                                        </x-badge>
                                    @else
                                        <x-badge variant="warning" class="text-[10px]">A comprovar</x-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @can('update', $e)
                                        <a href="{{ route('entregas.editar', $e) }}" wire:navigate
                                           class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-primary hover:bg-primary-soft">
                                            <x-icon name="eye" class="h-3.5 w-3.5" /> Abrir
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-border px-5 py-3">
                {{ $this->entregas->links() }}
            </div>
        @endif
    </x-card>
</div>
