{{-- Rotina 4010 — CT-e (modelo 57). --}}
<div>
    <x-page-header title="CT-e" subtitle="Conhecimento de Transporte eletrônico · modelo 57">
        <x-slot:actions>
            @if ($this->ambiente === 2)
                <x-badge variant="warning">Homologação</x-badge>
            @endif
            @can('create', \App\Models\Cte::class)
                <x-button variant="primary" size="sm" icon="plus" :href="route('cte.criar')" wire:navigate>Novo CT-e</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if (session('sucesso'))
        <div class="mb-4 flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800/50 dark:bg-green-950/50 dark:text-green-300">
            <x-icon name="check" class="w-4 h-4 flex-shrink-0" /> {{ session('sucesso') }}
        </div>
    @endif

    <div class="mb-4 flex flex-wrap items-center gap-1.5">
        @php $chips = ['' => 'Todos · ' . $this->contagem['total'], 'rascunho' => 'Rascunho · ' . $this->contagem['rascunho'], 'autorizado' => 'Autorizados · ' . $this->contagem['autorizado'], 'rejeitado' => 'Rejeitados · ' . $this->contagem['rejeitado'], 'cancelado' => 'Cancelados · ' . $this->contagem['cancelado']]; @endphp
        @foreach ($chips as $chave => $rotulo)
            <button type="button" wire:click="$set('status', '{{ $chave }}')"
                    class="rounded-full px-3 py-1.5 text-sm font-medium transition-colors {{ $status === $chave ? 'bg-primary-soft text-amber-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' }}">
                {{ $rotulo }}
            </button>
        @endforeach
        <div class="flex-1"></div>
        <div class="relative max-w-xs flex-1">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
            <input type="search" wire:model.live.debounce.400ms="busca" placeholder="Número, chave, tomador…"
                   class="w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>
    </div>

    <x-card padding="none" class="overflow-hidden">
        <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
            <x-icon name="files" class="h-4 w-4 text-text-secondary" />
            <h2 class="text-sm font-semibold text-text">Documentos</h2>
            <span class="text-sm text-text-muted">({{ number_format($this->ctes->total(), 0, ',', '.') }})</span>
        </div>

        @if ($this->ctes->isEmpty())
            <x-empty-state icon="files" title="Nenhum CT-e" description="Gere um CT-e a partir de uma ordem de coleta faturável." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-border bg-surface-elevated">
                        <tr>@foreach (['Nº / Série', 'Tomador', 'Trecho', 'Frete', 'Situação', ''] as $c)<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-text-muted first:pl-5">{{ $c }}</th>@endforeach</tr>
                    </thead>
                    <tbody>
                        @foreach ($this->ctes as $cte)
                            <tr wire:key="cte-{{ $cte->id }}" class="border-t border-border hover:bg-surface-elevated">
                                <td class="px-4 py-3 pl-5">
                                    <div class="text-sm font-semibold text-text tabular-nums">{{ $cte->numero ? str_pad((string) $cte->numero, 6, '0', STR_PAD_LEFT) . ' / ' . $cte->serie : 'rascunho' }}</div>
                                    <div class="font-mono text-[11px] text-text-muted">{{ $cte->chave ? substr($cte->chave, 0, 8) . '…' . substr($cte->chave, -4) : 'sem chave' }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-text-secondary">{{ $cte->tomador?->razao_social ?? '—' }}<div class="text-xs text-text-muted">{{ ucfirst($cte->tomadorPapel()) }}</div></td>
                                <td class="px-4 py-3 text-sm text-text-secondary">{{ $cte->municipioInicio?->nome ?? '—' }} <span class="text-text-muted">→</span> {{ $cte->municipioFim?->nome ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-text tabular-nums">R$ {{ number_format((float) $cte->valor_total_servico, 2, ',', '.') }}</td>
                                <td class="px-4 py-3"><x-badge :variant="config('fiscal.cte.status_cores.' . $cte->status, 'gray')" class="text-[10px]">{{ config('fiscal.cte.status.' . $cte->status, $cte->status) }}</x-badge></td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    @if (in_array($cte->status, ['autorizado', 'cancelado', 'contingencia'], true))
                                        <a href="{{ route('cte.dacte', $cte) }}" target="_blank" rel="noopener" title="DACTE em PDF" aria-label="DACTE em PDF" class="inline-flex items-center rounded px-1.5 py-1 text-text-secondary hover:bg-surface-elevated hover:text-text"><x-icon name="file-text" class="h-4 w-4" /></a>
                                    @endif
                                    <a href="{{ route('cte.editar', $cte) }}" wire:navigate class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-primary hover:bg-primary-soft">
                                        <x-icon name="eye" class="h-3.5 w-3.5" /> Abrir
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-border px-5 py-3">{{ $this->ctes->links() }}</div>
        @endif
    </x-card>

    <div class="mt-4 flex items-start gap-2 rounded-r-md border-l-[3px] border-info bg-blue-50 px-4 py-2.5 text-xs text-blue-800 dark:bg-blue-950/40 dark:text-blue-300">
        <x-icon name="info" class="mt-0.5 h-4 w-4 flex-shrink-0" />
        <span>Documento autorizado é imutável — correção por Carta de Correção (110110) ou cancelamento (110111) e reemissão. A transmissão é assíncrona e não trava a tela.</span>
    </div>
</div>
