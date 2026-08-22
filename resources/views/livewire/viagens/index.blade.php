{{--
    Rotina 3020 — Viagens.
    A execução física do transporte; carrega os CT-e (N:N).
--}}
<div>
    <x-page-header
        title="Viagens"
        subtitle="{{ number_format($this->resumo['total'], 0, ',', '.') }} viagens · a execução física do transporte">
        <x-slot:actions>
            @can('create', \App\Models\Viagem::class)
                <x-button variant="primary" size="sm" icon="plus" :href="route('viagens.criar')" wire:navigate>
                    Nova viagem
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
                'planejada' => 'Planejadas · ' . $this->resumo['planejada'],
                'em_transito' => 'Em trânsito · ' . $this->resumo['em_transito'],
                'entregue' => 'Entregues · ' . $this->resumo['entregue'],
                'encerrada' => 'Encerradas · ' . $this->resumo['encerrada'],
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
                   placeholder="Número, placa ou motorista…"
                   class="w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm text-text
                          outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>
    </div>

    <x-card padding="none" class="overflow-hidden">
        <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
            <x-icon name="route" class="h-4 w-4 text-text-secondary" />
            <h2 class="text-sm font-semibold text-text">Viagens</h2>
            <span class="text-sm text-text-muted">({{ number_format($this->viagens->total(), 0, ',', '.') }})</span>
            <div class="flex-1"></div>
            <span class="text-xs text-text-muted">Margem em aberto:</span>
            <span class="text-sm font-semibold tabular-nums {{ $this->resumo['margem_aberta'] >= 0 ? 'text-success' : 'text-danger' }}">
                R$ {{ number_format($this->resumo['margem_aberta'], 2, ',', '.') }}
            </span>
        </div>

        @if ($this->viagens->isEmpty())
            <x-empty-state icon="route" title="Nenhuma viagem"
                           description="Planeje uma viagem com a composição, o motorista e a rota para acompanhar custo e margem." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-border bg-surface-elevated">
                        <tr>
                            @foreach (['Viagem', 'Composição', 'Motorista', 'Rota', 'Receita', 'Custo', 'Margem', 'Status', ''] as $cabecalho)
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-text-muted first:pl-5">{{ $cabecalho }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->viagens as $v)
                            @php $margem = (float) $v->margem; @endphp
                            <tr wire:key="vg-{{ $v->id }}" class="border-t border-border hover:bg-surface-elevated">
                                <td class="px-4 py-3 pl-5">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-semibold text-text tabular-nums">Nº {{ $v->numero }}</span>
                                        <span class="text-xs text-text-muted">{{ $v->saida_prevista?->format('d/m/Y') ?? '—' }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-text-secondary">
                                    <span class="font-medium text-text">{{ $v->veiculoTracao?->placaFormatada() ?? '—' }}</span>
                                    @php $reb = is_array($v->composicao_snapshot) ? count($v->composicao_snapshot) : 0; @endphp
                                    @if ($reb) <span class="text-xs text-text-muted">+{{ $reb }} reb.</span> @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-text-secondary">{{ $v->motorista?->pessoa?->razao_social ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-text-secondary">{{ $v->rota?->descricao ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-text-secondary tabular-nums">R$ {{ number_format((float) $v->receita_total, 2, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-text-secondary tabular-nums">R$ {{ number_format((float) $v->custo_total, 2, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm font-medium tabular-nums {{ $margem >= 0 ? 'text-success' : 'text-danger' }}">
                                    R$ {{ number_format($margem, 2, ',', '.') }}
                                </td>
                                <td class="px-4 py-3">
                                    <x-badge :variant="config('viagens.status_cores.' . $v->status, 'gray')" class="text-[10px]">
                                        {{ config('viagens.status.' . $v->status, $v->status) }}
                                    </x-badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @can('update', $v)
                                        <a href="{{ route('viagens.editar', $v) }}" wire:navigate
                                           class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-primary hover:bg-primary-soft">
                                            <x-icon name="eye" class="h-3.5 w-3.5" /> Painel
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-border px-5 py-3">
                {{ $this->viagens->links() }}
            </div>
        @endif
    </x-card>
</div>
