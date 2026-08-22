{{--
    Rotina 1030 — Tabelas de frete.
    Geral ou por cliente; o que responde numa data é a tabela vigente.
--}}
<div>
    <x-page-header
        title="Tabelas de frete"
        subtitle="{{ number_format($this->total, 0, ',', '.') }} tabelas · gerais ou por cliente — o preço é a soma dos componentes">
        <x-slot:actions>
            @can('create', \App\Models\TabelaFrete::class)
                <x-button variant="primary" size="sm" icon="plus" :href="route('tabelas-frete.criar')" wire:navigate>
                    Nova tabela
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
        @foreach (['' => 'Todas', 'geral' => 'Gerais', 'cliente' => 'Por cliente'] as $chave => $rotulo)
            <button type="button" wire:click="$set('escopo', '{{ $chave }}')"
                    class="rounded-full px-3 py-1.5 text-sm font-medium transition-colors
                           {{ $escopo === $chave ? 'bg-primary-soft text-amber-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' }}">
                {{ $rotulo }}
            </button>
        @endforeach

        <div class="flex-1"></div>

        <select wire:model.live="situacao"
                class="rounded-md border border-border bg-surface px-3 py-1.5 text-sm text-text">
            <option value="vigentes">Vigentes hoje</option>
            <option value="inativas">Inativas</option>
            <option value="todas">Todas</option>
        </select>
    </div>

    <div class="mb-4">
        <div class="relative max-w-sm">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
            <input type="search" wire:model.live.debounce.400ms="busca"
                   placeholder="Descrição ou cliente…"
                   class="w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm text-text
                          outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>
    </div>

    <x-card padding="none" class="overflow-hidden">
        <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
            <x-icon name="tag" class="h-4 w-4 text-text-secondary" />
            <h2 class="text-sm font-semibold text-text">Tabelas</h2>
            <span class="text-sm text-text-muted">({{ number_format($this->tabelas->total(), 0, ',', '.') }})</span>
        </div>

        @if ($this->tabelas->isEmpty())
            <x-empty-state icon="tag" title="Nenhuma tabela de frete"
                           description="Crie uma tabela geral ou específica de cliente para precificar a operação." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-border bg-surface-elevated">
                        <tr>
                            @foreach (['Descrição', 'Cliente', 'Trecho', 'Vigência', 'Componentes', ''] as $cabecalho)
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-text-muted first:pl-5">{{ $cabecalho }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->tabelas as $registro)
                            <tr wire:key="tf-{{ $registro->id }}" class="border-t border-border hover:bg-surface-elevated">
                                <td class="px-4 py-3 pl-5">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-text">{{ $registro->descricao }}</span>
                                        @unless ($registro->vigenteEm())
                                            <x-badge variant="gray" class="text-[10px]">Fora de vigência</x-badge>
                                        @endunless
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($registro->cliente)
                                        <span class="text-sm text-text-secondary">{{ $registro->cliente->razao_social }}</span>
                                    @else
                                        <x-badge variant="info" class="text-[10px]">Geral</x-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-text-secondary">
                                    @if ($registro->municipioOrigem || $registro->uf_origem)
                                        {{ $registro->municipioOrigem?->nome ?? $registro->uf_origem }}
                                        →
                                        {{ $registro->municipioDestino?->nome ?? $registro->uf_destino ?? '—' }}
                                    @else
                                        <span class="text-text-muted">Qualquer trecho</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-text-secondary">
                                    {{ $registro->vigencia_inicio?->format('d/m/Y') }}
                                    @if ($registro->vigencia_fim) – {{ $registro->vigencia_fim->format('d/m/Y') }} @else – sem fim @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-text-secondary">{{ $registro->itens_count }}</td>
                                <td class="px-4 py-3 text-right">
                                    @can('update', $registro)
                                        <a href="{{ route('tabelas-frete.editar', $registro) }}" wire:navigate
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
                {{ $this->tabelas->links() }}
            </div>
        @endif
    </x-card>
</div>
