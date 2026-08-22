{{--
    Rotina 2010 — Veículos.
    Uma UNIDADE por linha (cavalo, reboque, semirreboque, dolly). Chips por tipo
    no topo, tabela à esquerda e a ficha técnica resumida à direita. A combinação
    em si é a rotina 2020.
--}}
<div>
    <x-page-header
        title="Veículos"
        subtitle="{{ number_format($this->total, 0, ',', '.') }} unidades · cavalo, reboque, semirreboque e dolly — a combinação é a rotina 2020">
        <x-slot:actions>
            @can('create', \App\Models\Veiculo::class)
                <x-button variant="primary" size="sm" icon="plus" :href="route('veiculos.criar')" wire:navigate>
                    Novo veículo
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

    {{-- Filtros por tipo --}}
    <div class="mb-4 flex flex-wrap items-center gap-1.5">
        <button type="button" wire:click="filtrarPor('')"
                class="rounded-full px-3 py-1.5 text-sm font-medium transition-colors
                       {{ $tipo === '' ? 'bg-primary-soft text-amber-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' }}">
            Todos os tipos <span class="font-bold">{{ number_format($this->total, 0, ',', '.') }}</span>
        </button>

        @foreach ($this->totaisPorTipo as $codigo => $quantidade)
            <button type="button" wire:click="filtrarPor('{{ $codigo }}')"
                    class="rounded-full px-3 py-1.5 text-sm font-medium transition-colors
                           {{ $tipo === $codigo ? 'bg-primary-soft text-amber-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' }}">
                {{ config('veiculos.tipos.' . $codigo) }} <span class="font-bold">{{ $quantidade }}</span>
            </button>
        @endforeach

        <div class="flex-1"></div>

        <select wire:model.live="situacao"
                class="rounded-md border border-border bg-surface px-3 py-1.5 text-sm text-text">
            <option value="ativos">Somente ativos</option>
            <option value="inativos">Inativos / manutenção</option>
            <option value="todos">Todos</option>
        </select>
    </div>

    <div class="mb-4">
        <div class="relative max-w-sm">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
            <input type="search" wire:model.live.debounce.400ms="busca"
                   placeholder="Placa, modelo, marca ou RENAVAM…"
                   class="w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm text-text
                          outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_400px] items-start">

        {{-- Tabela --}}
        <x-card padding="none" class="overflow-hidden">
            <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                <x-icon name="truck" class="h-4 w-4 text-text-secondary" />
                <h2 class="text-sm font-semibold text-text">Frota</h2>
                <span class="text-sm text-text-muted">({{ number_format($this->veiculos->total(), 0, ',', '.') }})</span>
            </div>

            @if ($this->veiculos->isEmpty())
                <x-empty-state icon="truck" title="Nenhum veículo encontrado"
                               description="Ajuste a busca ou o filtro de tipo." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-b border-border bg-surface-elevated">
                            <tr>
                                @foreach (['Placa', 'Veículo', 'Tipo', 'Propriedade', 'Situação'] as $cabecalho)
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-text-muted first:pl-5">
                                        {{ $cabecalho }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->veiculos as $registro)
                                <tr wire:key="veiculo-{{ $registro->id }}"
                                    wire:click="selecionar({{ $registro->id }})"
                                    class="cursor-pointer border-t border-border transition-colors
                                           {{ $selecionado === $registro->id ? 'bg-primary-soft' : 'hover:bg-surface-elevated' }}">
                                    <td class="px-4 py-3 pl-5">
                                        <div class="font-mono text-sm font-semibold text-text">{{ $registro->placaFormatada() }}</div>
                                        @if ($registro->renavam)
                                            <div class="mt-0.5 font-mono text-[11px] text-text-muted">RENAVAM {{ $registro->renavam }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="text-sm text-text">{{ $registro->marca }} {{ $registro->modelo ?: '—' }}</div>
                                        <div class="mt-0.5 text-xs text-text-muted">
                                            {{ $registro->ano_fabricacao ? $registro->ano_fabricacao . '/' . $registro->ano_modelo : '—' }}
                                            · {{ $registro->eixos }} eixos
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-badge :variant="config('veiculos.tipos_cores.' . $registro->tipo, 'gray')" class="text-[10px]">
                                            {{ config('veiculos.tipos.' . $registro->tipo) }}
                                        </x-badge>
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-badge :variant="config('veiculos.propriedades_cores.' . $registro->propriedade, 'gray')" class="text-[10px]">
                                            {{ config('veiculos.propriedades.' . $registro->propriedade) }}
                                        </x-badge>
                                        @if ($registro->propriedade !== 'propria' && $registro->proprietario)
                                            <div class="mt-0.5 max-w-[140px] truncate text-[11px] text-text-muted">{{ $registro->proprietario->razao_social }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @php
                                            $dot = match ($registro->status) {
                                                'ativo' => 'bg-success', 'manutencao' => 'bg-warning', default => 'bg-text-muted',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center gap-1.5 text-xs font-medium
                                                     {{ $registro->status === 'ativo' ? 'text-green-700' : 'text-text-muted' }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $dot }}"></span>
                                            {{ config('veiculos.status.' . $registro->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-border px-5 py-3">
                    {{ $this->veiculos->links() }}
                </div>
            @endif
        </x-card>

        {{-- Ficha resumida --}}
        <div class="flex flex-col gap-3">
            @if ($this->veiculo)
                @include('livewire.veiculos.partials.ficha', ['veiculo' => $this->veiculo])
            @else
                <x-card padding="md">
                    <x-empty-state icon="truck" title="Selecione um veículo"
                                   description="A ficha técnica resumida aparece aqui, com eixos, capacidade e propriedade." />
                </x-card>
            @endif
        </div>
    </div>
</div>
