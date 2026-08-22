{{--
    Rotina 2060 — Abastecimentos.
    Consumo por veículo, com média entre tanques cheios e alerta de desvio.
--}}
<div>
    <x-page-header
        title="Abastecimentos"
        subtitle="Consumo por veículo · média entre tanques cheios e alerta quando o desvio passa do limite">
        <x-slot:actions>
            @can('create', \App\Models\Abastecimento::class)
                <x-button variant="primary" size="sm" icon="plus" :href="route('abastecimentos.criar')" wire:navigate>
                    Lançar abastecimento
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
        <x-kpi label="Litros no mês" :valor="number_format($this->resumo['litros_mes'], 0, ',', '.') . ' L'" cor="azul" />
        <x-kpi label="Gasto no mês" :valor="'R$ ' . number_format($this->resumo['gasto_mes'], 2, ',', '.')" cor="teal" />
        <x-kpi label="Alertas de desvio" :valor="(string) $this->resumo['alertas']" :alerta="$this->resumo['alertas'] > 0"
               sub="Consumo fora do esperado" />
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="relative max-w-sm flex-1">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
            <input type="search" wire:model.live.debounce.400ms="busca" placeholder="Placa do veículo…"
                   class="w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm text-text
                          outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>
        <select wire:model.live="filtro" class="rounded-md border border-border bg-surface px-3 py-1.5 text-sm text-text">
            <option value="todos">Todos</option>
            <option value="alertas">Só com alerta</option>
        </select>
    </div>

    <x-card padding="none" class="overflow-hidden">
        <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
            <x-icon name="fuel" class="h-4 w-4 text-text-secondary" />
            <h2 class="text-sm font-semibold text-text">Lançamentos</h2>
            <span class="text-sm text-text-muted">({{ number_format($this->abastecimentos->total(), 0, ',', '.') }})</span>
        </div>

        @if ($this->abastecimentos->isEmpty())
            <x-empty-state icon="fuel" title="Nenhum abastecimento"
                           description="Lance os abastecimentos para acompanhar consumo e custo por km." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-border bg-surface-elevated">
                        <tr>
                            @foreach (['Data', 'Veículo', 'Litros', 'R$/L', 'Total', 'Média', 'Desvio', ''] as $cabecalho)
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-text-muted first:pl-5">{{ $cabecalho }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->abastecimentos as $registro)
                            <tr wire:key="ab-{{ $registro->id }}" class="border-t border-border hover:bg-surface-elevated">
                                <td class="whitespace-nowrap px-4 py-3 pl-5 text-sm text-text-secondary">{{ $registro->data_hora?->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3"><span class="font-mono text-sm text-text">{{ $registro->veiculo?->placaFormatada() ?? '—' }}</span></td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-text-secondary">{{ number_format((float) $registro->litros, 1, ',', '.') }} L</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-text-secondary">R$ {{ number_format((float) $registro->valor_litro, 2, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-text">R$ {{ number_format((float) $registro->valor_total, 2, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-text-secondary">{{ $registro->media_calculada ? number_format((float) $registro->media_calculada, 2, ',', '.') . ' km/L' : '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    @if ($registro->desvio_percentual !== null)
                                        <span class="inline-flex items-center gap-1.5 text-xs font-medium {{ $registro->alerta ? 'text-danger' : 'text-text-secondary' }}">
                                            @if ($registro->alerta) <x-icon name="alert-triangle" class="h-3.5 w-3.5" /> @endif
                                            {{ $registro->desvio_percentual > 0 ? '+' : '' }}{{ number_format((float) $registro->desvio_percentual, 1, ',', '.') }}%
                                        </span>
                                    @else
                                        <span class="text-xs text-text-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @can('update', $registro)
                                        <a href="{{ route('abastecimentos.editar', $registro) }}" wire:navigate
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
                {{ $this->abastecimentos->links() }}
            </div>
        @endif
    </x-card>
</div>
