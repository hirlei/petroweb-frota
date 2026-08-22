{{--
    Rotina 2030 — Motoristas.
    O motorista é um papel sobre pessoas. Chips por vínculo, tabela ordenada
    pela validade da CNH (o que vence primeiro sobe), e a ficha à direita.
    RN-12: a coluna de jornada torna visível que só CLT a tem.
--}}
<div>
    <x-page-header
        title="Motoristas"
        subtitle="{{ number_format($this->total, 0, ',', '.') }} cadastrados · papel sobre o cadastro de pessoas — CLT, agregado, autônomo e terceiro">
        <x-slot:actions>
            @can('create', \App\Models\Motorista::class)
                <x-button variant="primary" size="sm" icon="plus" :href="route('motoristas.criar')" wire:navigate>
                    Novo motorista
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
        <button type="button" wire:click="filtrarPor('')"
                class="rounded-full px-3 py-1.5 text-sm font-medium transition-colors
                       {{ $vinculo === '' ? 'bg-primary-soft text-amber-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' }}">
            Todos os vínculos <span class="font-bold">{{ number_format($this->total, 0, ',', '.') }}</span>
        </button>

        @foreach ($this->totaisPorVinculo as $codigo => $quantidade)
            <button type="button" wire:click="filtrarPor('{{ $codigo }}')"
                    class="rounded-full px-3 py-1.5 text-sm font-medium transition-colors
                           {{ $vinculo === $codigo ? 'bg-primary-soft text-amber-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' }}">
                {{ config('motoristas.vinculos.' . $codigo) }} <span class="font-bold">{{ $quantidade }}</span>
            </button>
        @endforeach

        <div class="flex-1"></div>

        <select wire:model.live="situacao"
                class="rounded-md border border-border bg-surface px-3 py-1.5 text-sm text-text">
            <option value="ativos">Somente ativos</option>
            <option value="inativos">Inativos / afastados</option>
            <option value="todos">Todos</option>
        </select>
    </div>

    <div class="mb-4">
        <div class="relative max-w-sm">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
            <input type="search" wire:model.live.debounce.400ms="busca"
                   placeholder="Nome ou número da CNH…"
                   class="w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm text-text
                          outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_400px] items-start">
        <x-card padding="none" class="overflow-hidden">
            <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                <x-icon name="id-card" class="h-4 w-4 text-text-secondary" />
                <h2 class="text-sm font-semibold text-text">Motoristas</h2>
                <span class="text-sm text-text-muted">({{ number_format($this->motoristas->total(), 0, ',', '.') }})</span>
            </div>

            @if ($this->motoristas->isEmpty())
                <x-empty-state icon="id-card" title="Nenhum motorista encontrado"
                               description="Cadastre a pessoa com o papel de motorista antes de criar a ficha aqui." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-b border-border bg-surface-elevated">
                            <tr>
                                @foreach (['Nome', 'CNH', 'Vínculo', 'Jornada', 'Situação'] as $cabecalho)
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-text-muted first:pl-5">
                                        {{ $cabecalho }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->motoristas as $registro)
                                @php $cnhVencida = $registro->cnh_validade && $registro->cnh_validade->isPast(); @endphp
                                <tr wire:key="motorista-{{ $registro->id }}"
                                    wire:click="selecionar({{ $registro->id }})"
                                    class="cursor-pointer border-t border-border transition-colors
                                           {{ $selecionado === $registro->id ? 'bg-primary-soft' : 'hover:bg-surface-elevated' }}">
                                    <td class="px-4 py-3 pl-5">
                                        <div class="text-sm font-medium text-text">{{ $registro->pessoa?->razao_social ?? '—' }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <div class="font-mono text-xs text-text">{{ $registro->cnh_categoria }} · {{ $registro->cnh_numero }}</div>
                                        <div class="mt-0.5 text-xs {{ $cnhVencida ? 'font-semibold text-danger' : 'text-text-muted' }}">
                                            {{ $cnhVencida ? 'Vencida em ' : 'Vence ' }}{{ $registro->cnh_validade?->format('d/m/Y') }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-badge :variant="config('motoristas.vinculos_cores.' . $registro->vinculo, 'gray')" class="text-[10px]">
                                            {{ config('motoristas.vinculos.' . $registro->vinculo) }}
                                        </x-badge>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($registro->controlaJornada())
                                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-info">
                                                <x-icon name="check" class="h-3.5 w-3.5" /> Controlada
                                            </span>
                                        @else
                                            <span class="text-xs text-text-muted">Não se aplica (TAC)</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @php
                                            $dot = match ($registro->status) {
                                                'ativo' => 'bg-success', 'afastado' => 'bg-warning', 'ferias' => 'bg-info', default => 'bg-text-muted',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center gap-1.5 text-xs font-medium
                                                     {{ $registro->status === 'ativo' ? 'text-green-700' : 'text-text-muted' }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $dot }}"></span>
                                            {{ config('motoristas.status.' . $registro->status, $registro->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-border px-5 py-3">
                    {{ $this->motoristas->links() }}
                </div>
            @endif
        </x-card>

        <div class="flex flex-col gap-3">
            @if ($this->motorista)
                @include('livewire.motoristas.partials.ficha', ['motorista' => $this->motorista])
            @else
                <x-card padding="md">
                    <x-empty-state icon="id-card" title="Selecione um motorista"
                                   description="A ficha resumida aparece aqui, com CNH, vínculo e pendências que bloqueiam a viagem." />
                </x-card>
            @endif
        </div>
    </div>
</div>
