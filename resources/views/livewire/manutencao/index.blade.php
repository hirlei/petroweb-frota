{{--
    Rotina 2050 — Manutenção (ordens de serviço).
    Preventiva, corretiva, sinistro, pneu, revisão. KPIs de abertas e custo do mês.
--}}
<div>
    <x-page-header
        title="Manutenção"
        subtitle="Ordens de serviço da frota · preventiva, corretiva, sinistro, pneu e revisão">
        <x-slot:actions>
            @can('create', \App\Models\OrdemServico::class)
                <x-button variant="primary" size="sm" icon="plus" :href="route('manutencao.criar')" wire:navigate>
                    Nova OS
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

    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
        <x-kpi label="OS em aberto" :valor="(string) $this->resumo['abertas']" :alerta="$this->resumo['abertas'] > 0"
               sub="Aguardando execução" />
        <x-kpi label="Custo encerrado no mês" :valor="'R$ ' . number_format($this->resumo['custo_mes'], 2, ',', '.')" cor="teal"
               sub="OS encerradas neste mês" />
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-1.5">
        <button type="button" wire:click="filtrarPor('')"
                class="rounded-full px-3 py-1.5 text-sm font-medium transition-colors
                       {{ $tipo === '' ? 'bg-primary-soft text-amber-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' }}">
            Todos os tipos
        </button>
        @foreach (config('manutencao.tipos') as $codigo => $rotulo)
            <button type="button" wire:click="filtrarPor('{{ $codigo }}')"
                    class="rounded-full px-3 py-1.5 text-sm font-medium transition-colors
                           {{ $tipo === $codigo ? 'bg-primary-soft text-amber-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' }}">
                {{ $rotulo }}
            </button>
        @endforeach
        <div class="flex-1"></div>
        <select wire:model.live="situacao" class="rounded-md border border-border bg-surface px-3 py-1.5 text-sm text-text">
            <option value="abertas">Em aberto</option>
            <option value="encerradas">Encerradas</option>
            <option value="todas">Todas</option>
        </select>
    </div>

    <div class="mb-4">
        <div class="relative max-w-sm">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
            <input type="search" wire:model.live.debounce.400ms="busca" placeholder="Número da OS ou placa…"
                   class="w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm text-text
                          outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>
    </div>

    <x-card padding="none" class="overflow-hidden">
        <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
            <x-icon name="wrench" class="h-4 w-4 text-text-secondary" />
            <h2 class="text-sm font-semibold text-text">Ordens de serviço</h2>
            <span class="text-sm text-text-muted">({{ number_format($this->ordens->total(), 0, ',', '.') }})</span>
        </div>

        @if ($this->ordens->isEmpty())
            <x-empty-state icon="wrench" title="Nenhuma ordem de serviço"
                           description="Abra uma OS para registrar manutenção, peças e mão de obra por veículo." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-border bg-surface-elevated">
                        <tr>
                            @foreach (['OS', 'Veículo', 'Tipo', 'Oficina', 'Abertura', 'Total', 'Status', ''] as $cabecalho)
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-text-muted first:pl-5">{{ $cabecalho }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->ordens as $registro)
                            <tr wire:key="os-{{ $registro->id }}" class="border-t border-border hover:bg-surface-elevated">
                                <td class="px-4 py-3 pl-5"><span class="font-mono text-sm font-medium text-text">{{ $registro->numero }}</span></td>
                                <td class="px-4 py-3"><span class="font-mono text-sm text-text-secondary">{{ $registro->veiculo?->placaFormatada() ?? '—' }}</span></td>
                                <td class="px-4 py-3">
                                    <x-badge :variant="config('manutencao.tipos_cores.' . $registro->tipo, 'gray')" class="text-[10px]">
                                        {{ config('manutencao.tipos.' . $registro->tipo) }}
                                    </x-badge>
                                </td>
                                <td class="px-4 py-3 text-sm text-text-secondary">
                                    {{ $registro->interna ? 'Interna' : ($registro->oficina?->razao_social ?? '—') }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-text-secondary">{{ $registro->abertura?->format('d/m/Y') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-text">R$ {{ number_format((float) $registro->valor_total, 2, ',', '.') }}</td>
                                <td class="px-4 py-3">
                                    <x-badge :variant="config('manutencao.status_cores.' . $registro->status, 'gray')" class="text-[10px]">
                                        {{ config('manutencao.status.' . $registro->status) }}
                                    </x-badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @can('update', $registro)
                                        <a href="{{ route('manutencao.editar', $registro) }}" wire:navigate
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
