{{--
    Rotina 3040 — Ocorrências.
    Avaria, extravio, atraso, sinistro, multa. Chips por tipo, KPIs de abertas
    e prejuízo, tabela ordenada pela data.
--}}
<div>
    <x-page-header
        title="Ocorrências"
        subtitle="{{ number_format($this->total, 0, ',', '.') }} registros · o que saiu do previsto na operação, com responsável e prejuízo">
        <x-slot:actions>
            @can('create', \App\Models\Ocorrencia::class)
                <x-button variant="primary" size="sm" icon="plus" :href="route('ocorrencias.criar')" wire:navigate>
                    Nova ocorrência
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

    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
        <x-kpi label="Em aberto" :valor="(string) $this->resumo['abertas']" :alerta="$this->resumo['abertas'] > 0"
               sub="Aguardando tratamento" />
        <x-kpi label="Prejuízo em aberto" :valor="'R$ ' . number_format($this->resumo['prejuizo'], 2, ',', '.')" cor="vermelho"
               sub="Soma das não resolvidas" />
        <x-kpi label="Total" :valor="(string) $this->total" sub="Todas as ocorrências" />
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-1.5">
        <button type="button" wire:click="filtrarPor('')"
                class="rounded-full px-3 py-1.5 text-sm font-medium transition-colors
                       {{ $tipo === '' ? 'bg-primary-soft text-amber-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' }}">
            Todos os tipos
        </button>
        @foreach (config('ocorrencias.tipos') as $codigo => $rotulo)
            <button type="button" wire:click="filtrarPor('{{ $codigo }}')"
                    class="rounded-full px-3 py-1.5 text-sm font-medium transition-colors
                           {{ $tipo === $codigo ? 'bg-primary-soft text-amber-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' }}">
                {{ $rotulo }}
            </button>
        @endforeach
        <div class="flex-1"></div>
        <select wire:model.live="situacao" class="rounded-md border border-border bg-surface px-3 py-1.5 text-sm text-text">
            <option value="abertas">Em aberto</option>
            <option value="resolvidas">Resolvidas</option>
            <option value="todas">Todas</option>
        </select>
    </div>

    <div class="mb-4">
        <div class="relative max-w-sm">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
            <input type="search" wire:model.live.debounce.400ms="busca" placeholder="Buscar na descrição…"
                   class="w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm text-text
                          outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>
    </div>

    <x-card padding="none" class="overflow-hidden">
        <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
            <x-icon name="alert-triangle" class="h-4 w-4 text-text-secondary" />
            <h2 class="text-sm font-semibold text-text">Ocorrências</h2>
            <span class="text-sm text-text-muted">({{ number_format($this->ocorrencias->total(), 0, ',', '.') }})</span>
        </div>

        @if ($this->ocorrencias->isEmpty())
            <x-empty-state icon="check" title="Nenhuma ocorrência no filtro"
                           description="Operação limpa por aqui — ou ajuste o filtro para ver o histórico." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-border bg-surface-elevated">
                        <tr>
                            @foreach (['Tipo', 'Data', 'Local', 'Descrição', 'Responsável', 'Prejuízo', 'Status', ''] as $cabecalho)
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-text-muted first:pl-5">{{ $cabecalho }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->ocorrencias as $registro)
                            <tr wire:key="oc-{{ $registro->id }}" class="border-t border-border hover:bg-surface-elevated">
                                <td class="px-4 py-3 pl-5">
                                    <x-badge :variant="config('ocorrencias.tipos_cores.' . $registro->tipo, 'gray')" class="text-[10px]">
                                        {{ config('ocorrencias.tipos.' . $registro->tipo) }}
                                    </x-badge>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-text-secondary">{{ $registro->data_hora?->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3 text-sm text-text-secondary">{{ $registro->municipio ? $registro->municipio->nome . '/' . $registro->municipio->uf : '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="line-clamp-1 max-w-[280px] text-sm text-text">{{ $registro->descricao }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-text-secondary">{{ config('ocorrencias.responsaveis.' . $registro->responsavel, '—') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm {{ $registro->valor_prejuizo ? 'font-medium text-danger' : 'text-text-muted' }}">
                                    {{ $registro->valor_prejuizo ? 'R$ ' . number_format((float) $registro->valor_prejuizo, 2, ',', '.') : '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <x-badge :variant="config('ocorrencias.status_cores.' . $registro->status, 'gray')" class="text-[10px]">
                                        {{ config('ocorrencias.status.' . $registro->status) }}
                                    </x-badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @can('update', $registro)
                                        <a href="{{ route('ocorrencias.editar', $registro) }}" wire:navigate
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
                {{ $this->ocorrencias->links() }}
            </div>
        @endif
    </x-card>
</div>
