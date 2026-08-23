{{-- Rotina 4020 — MDF-e (modelo 58). --}}
<div>
    <x-page-header title="MDF-e" subtitle="Manifesto Eletrônico de Documentos Fiscais · modelo 58">
        <x-slot:actions>
            @if ($this->ambiente === 2)<x-badge variant="warning">Homologação</x-badge>@endif
            @can('create', \App\Models\Mdfe::class)
                <x-button variant="primary" size="sm" icon="plus" :href="route('mdfe.criar')" wire:navigate>Novo MDF-e</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if (session('sucesso'))
        <div class="mb-4 flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800/50 dark:bg-green-950/50 dark:text-green-300"><x-icon name="check" class="w-4 h-4 flex-shrink-0" /> {{ session('sucesso') }}</div>
    @endif

    <div class="mb-4 flex flex-wrap items-center gap-1.5">
        @php $chips = ['' => 'Todos · ' . $this->contagem['total'], 'rascunho' => 'Rascunho · ' . $this->contagem['rascunho'], 'autorizado' => 'Autorizados · ' . $this->contagem['autorizado'], 'encerrado' => 'Encerrados · ' . $this->contagem['encerrado'], 'cancelado' => 'Cancelados · ' . $this->contagem['cancelado']]; @endphp
        @foreach ($chips as $chave => $rotulo)
            <button type="button" wire:click="$set('status', '{{ $chave }}')" class="rounded-full px-3 py-1.5 text-sm font-medium transition-colors {{ $status === $chave ? 'bg-primary-soft text-amber-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' }}">{{ $rotulo }}</button>
        @endforeach
        <div class="flex-1"></div>
        <div class="relative max-w-xs flex-1">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
            <input type="search" wire:model.live.debounce.400ms="busca" placeholder="Número, placa, viagem…" class="w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>
    </div>

    <x-card padding="none" class="overflow-hidden">
        <div class="flex items-center gap-2 border-b border-border px-5 py-3.5"><x-icon name="files" class="h-4 w-4 text-text-secondary" /><h2 class="text-sm font-semibold text-text">Manifestos</h2><span class="text-sm text-text-muted">({{ number_format($this->mdfes->total(), 0, ',', '.') }})</span></div>

        @if ($this->mdfes->isEmpty())
            <x-empty-state icon="files" title="Nenhum MDF-e" description="Gere um MDF-e a partir de uma viagem para manifestar os CT-e ao veículo." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-border bg-surface-elevated"><tr>@foreach (['Nº / Série', 'Viagem', 'Veículo', 'Percurso', 'CT-e', 'Situação', ''] as $c)<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-text-muted first:pl-5">{{ $c }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($this->mdfes as $m)
                            <tr wire:key="mdfe-{{ $m->id }}" class="border-t border-border hover:bg-surface-elevated">
                                <td class="px-4 py-3 pl-5"><div class="text-sm font-semibold text-text tabular-nums">{{ $m->numero ? str_pad((string) $m->numero, 6, '0', STR_PAD_LEFT) . ' / ' . $m->serie : 'rascunho' }}</div><div class="font-mono text-[11px] text-text-muted">{{ $m->chave ? substr($m->chave, 0, 8) . '…' . substr($m->chave, -4) : 'sem chave' }}</div></td>
                                <td class="px-4 py-3 text-sm text-text-secondary tabular-nums">{{ $m->viagem?->numero ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-text">{{ $m->veiculoTracao?->placaFormatada() ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-text-secondary">{{ $m->uf_inicio ?? '—' }} → {{ $m->uf_fim ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-text-secondary tabular-nums">{{ $m->documentos_count }}</td>
                                <td class="px-4 py-3"><x-badge :variant="config('fiscal.mdfe.status_cores.' . $m->status, 'gray')" class="text-[10px]">{{ config('fiscal.mdfe.status.' . $m->status, $m->status) }}</x-badge></td>
                                <td class="px-4 py-3 text-right"><a href="{{ route('mdfe.editar', $m) }}" wire:navigate class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-primary hover:bg-primary-soft"><x-icon name="eye" class="h-3.5 w-3.5" /> Abrir</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-border px-5 py-3">{{ $this->mdfes->links() }}</div>
        @endif
    </x-card>

    <div class="mt-4 flex items-start gap-2 rounded-r-md border-l-[3px] border-warning bg-yellow-50 px-4 py-2.5 text-xs text-yellow-800 dark:bg-yellow-950/40 dark:text-yellow-300">
        <x-icon name="alert-triangle" class="mt-0.5 h-4 w-4 flex-shrink-0" />
        <span>RN-03: um veículo com MDF-e aberto não inicia outro. O encerramento é o evento 110112.</span>
    </div>
</div>
