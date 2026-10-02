{{-- Rotina 4030 — Vale-pedágio (grupo valePed do MDF-e). --}}
<div>
    <x-page-header title="Vale-pedágio" subtitle="Comprado ou informado na emissão do MDF-e (4020). Aqui: consulta, cancelamento e lançamento manual.">
        <x-slot:actions>
            @can('mdfe.emitir')
                <x-button variant="primary" size="sm" icon="plus" :href="route('vale-pedagio.criar')" wire:navigate>Lançar manualmente</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if (session('sucesso'))
        <div class="mb-4 flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800/50 dark:bg-green-950/50 dark:text-green-300"><x-icon name="check" class="w-4 h-4 flex-shrink-0" /> {{ session('sucesso') }}</div>
    @endif

    <div class="mb-4 flex items-center gap-3">
        <div class="relative max-w-xs flex-1">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
            <input type="search" wire:model.live.debounce.400ms="busca" placeholder="IDVPO, CNPJ ou placa…" class="w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>
        <div class="flex-1"></div>
        <span class="text-xs text-text-muted">Total lançado:</span>
        <span class="text-sm font-semibold text-text tabular-nums">R$ {{ number_format($this->resumo['valor'], 2, ',', '.') }}</span>
    </div>

    <x-card padding="none" class="overflow-hidden">
        <div class="flex items-center gap-2 border-b border-border px-5 py-3.5"><x-icon name="ticket" class="h-4 w-4 text-text-secondary" /><h2 class="text-sm font-semibold text-text">Lançamentos</h2><span class="text-sm text-text-muted">({{ number_format($this->vales->total(), 0, ',', '.') }})</span></div>

        @if ($this->vales->isEmpty())
            <x-empty-state icon="ticket" title="Nenhum vale-pedágio" description="Lance um vale por veículo da composição — a fornecedora é validada contra a ANTT." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-border bg-surface-elevated"><tr>@foreach (['Viagem', 'Veículo', 'Fornecedora', 'Compra', 'Tipo', 'Quem paga', 'Valor', 'Situação', ''] as $c)<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-text-muted first:pl-5">{{ $c }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($this->vales as $v)
                            <tr wire:key="vp-{{ $v->id }}" class="border-t border-border hover:bg-surface-elevated">
                                <td class="px-4 py-3 pl-5 text-sm text-text-secondary tabular-nums">{{ $v->viagem?->numero ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-text">{{ $v->veiculo?->placaFormatada() ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-text-secondary">{{ $v->fornecedorVpo?->razao_social ?? $v->cnpj_forn }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-text-secondary">{{ $v->idvpo ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-text-secondary">{{ $v->dispensado ? '—' : ($v->tipo === '04' ? 'Leitura de placa' : 'TAG') }}</td>
                                <td class="px-4 py-3"><x-badge :variant="$v->papel === 'fornecido' ? 'warning' : 'gray'" class="text-[10px]">{{ $v->papel === 'fornecido' ? 'Transportadora' : 'Embarcador' }}</x-badge></td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-text tabular-nums">{{ $v->valor !== null ? 'R$ ' . number_format((float) $v->valor, 2, ',', '.') : '—' }}</td>
                                <td class="px-4 py-3">
                                    @if ($v->situacao === 'cancelado')
                                        <x-badge variant="gray" class="text-[10px] line-through">Cancelado</x-badge>
                                    @elseif ($v->dispensado)
                                        <x-badge variant="gray" class="text-[10px]">Dispensado</x-badge>
                                    @else
                                        <x-badge :variant="$v->origem === 'compra' ? 'success' : 'info'" class="text-[10px]">{{ $v->origem === 'compra' ? 'Comprado' : 'Informado' }}</x-badge>
                                    @endif
                                    @if ($v->mdfe)<span class="block text-[11px] text-text-muted">MDF-e {{ $v->mdfe->numero ?? 'rascunho' }}</span>@endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    @can('mdfe.emitir')
                                        @if ($v->cancelavel())
                                            <button type="button" wire:click="abrirCancelamento({{ $v->id }})" class="rounded px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">Cancelar</button>
                                        @endif
                                        @if ($v->editavel())
                                            <a href="{{ route('vale-pedagio.editar', $v) }}" wire:navigate class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-primary hover:bg-primary-soft"><x-icon name="pencil" class="h-3.5 w-3.5" /> Editar</a>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-border px-5 py-3">{{ $this->vales->links() }}</div>
        @endif
    </x-card>

    <div class="mt-4 flex items-start gap-2 rounded-r-md border-l-[3px] border-warning bg-yellow-50 px-4 py-2.5 text-xs text-yellow-800 dark:bg-yellow-950/40 dark:text-yellow-300">
        <x-icon name="alert-triangle" class="mt-0.5 h-4 w-4 flex-shrink-0" />
        <span>Quem subcontrata TAC vira embarcador equiparado e é quem paga o vale. Multa por falta: R$ 3.000/veículo.</span>
    </div>

    @if ($cancelandoId)
        <div class="fixed inset-0 z-[90] flex items-start justify-center bg-[rgba(15,26,58,.45)] px-4 pt-[10vh]" wire:keydown.escape.window="$set('cancelandoId', null)">
            <div class="w-full max-w-sm rounded-2xl bg-surface p-5 shadow-2xl" role="dialog" aria-modal="true" aria-label="Cancelar vale-pedágio">
                <h3 class="text-base font-bold text-text">Cancelar vale-pedágio</h3>
                <p class="mt-0.5 text-[12.5px] text-text-secondary">Compra feita pelo sistema é cancelada também na fornecedora.</p>
                <x-input class="mt-4" label="Motivo" wire:model="motivoCancelamento" placeholder="Ex.: Viagem cancelada" :error="$errors->first('motivoCancelamento')" />
                <div class="mt-5 flex justify-end gap-2">
                    <x-button variant="neutral" size="sm" wire:click="$set('cancelandoId', null)">Voltar</x-button>
                    <x-button variant="danger" size="sm" wire:click="cancelar" wire:loading.attr="disabled">Cancelar vale</x-button>
                </div>
            </div>
        </div>
    @endif
</div>
