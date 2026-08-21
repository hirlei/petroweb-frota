{{--
    Rotina 1010 — Pessoas.
    Fiel ao mockup aprovado: chips de papel no topo, tabela à esquerda e a
    ficha resumida à direita. Os papéis aparecem como chips na linha porque
    acumular papéis é a regra deste cadastro, não a exceção.
--}}
<div>
    <x-page-header
        title="Pessoas"
        subtitle="{{ number_format($this->total, 0, ',', '.') }} cadastradas · cliente, fornecedor, motorista, proprietário, oficina, seguradora e posto na mesma tabela">
        <x-slot:actions>
            @can('create', \App\Models\Pessoa::class)
                <x-button variant="primary" size="sm" icon="plus" :href="route('pessoas.criar')" wire:navigate>
                    Nova pessoa
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

    {{-- Filtros por papel --}}
    <div class="mb-4 flex flex-wrap items-center gap-1.5">
        <button type="button" wire:click="filtrarPor('')"
                class="rounded-full px-3 py-1.5 text-sm font-medium transition-colors
                       {{ $papel === '' ? 'bg-primary-soft text-amber-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' }}">
            Todos os papéis <span class="font-bold">{{ number_format($this->total, 0, ',', '.') }}</span>
        </button>

        @foreach ($this->totaisPorPapel as $codigo => $quantidade)
            <button type="button" wire:click="filtrarPor('{{ $codigo }}')"
                    class="rounded-full px-3 py-1.5 text-sm font-medium transition-colors
                           {{ $papel === $codigo ? 'bg-primary-soft text-amber-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' }}">
                {{ config('papeis.rotulos.' . $codigo) }} <span class="font-bold">{{ $quantidade }}</span>
            </button>
        @endforeach

        <div class="flex-1"></div>

        <select wire:model.live="situacao"
                class="rounded-md border border-border bg-surface px-3 py-1.5 text-sm text-text">
            <option value="ativos">Somente ativos</option>
            <option value="inativos">Somente inativos</option>
            <option value="todos">Todos</option>
        </select>
    </div>

    <div class="mb-4">
        <div class="relative max-w-sm">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
            <input type="search" wire:model.live.debounce.400ms="busca"
                   placeholder="Nome, CNPJ ou CPF…"
                   class="w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm text-text
                          outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_400px] items-start">

        {{-- Tabela --}}
        <x-card padding="none" class="overflow-hidden">
            <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                <x-icon name="users" class="h-4 w-4 text-text-secondary" />
                <h2 class="text-sm font-semibold text-text">Cadastro</h2>
                <span class="text-sm text-text-muted">({{ number_format($this->pessoas->total(), 0, ',', '.') }})</span>
            </div>

            @if ($this->pessoas->isEmpty())
                <x-empty-state icon="users" title="Nenhuma pessoa encontrada"
                               description="Ajuste a busca ou o filtro de papel." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-b border-border bg-surface-elevated">
                            <tr>
                                @foreach (['Nome', 'Documento', 'Papéis', 'Município', 'Situação'] as $cabecalho)
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-text-muted first:pl-5">
                                        {{ $cabecalho }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->pessoas as $registro)
                                <tr wire:key="pessoa-{{ $registro->id }}"
                                    wire:click="selecionar({{ $registro->id }})"
                                    class="cursor-pointer border-t border-border transition-colors
                                           {{ $selecionada === $registro->id ? 'bg-primary-soft' : 'hover:bg-surface-elevated' }}">
                                    <td class="px-4 py-3 pl-5">
                                        <div class="text-sm font-medium text-text">{{ $registro->razao_social }}</div>
                                        @if ($registro->nome_fantasia)
                                            <div class="mt-0.5 text-xs text-text-muted">{{ $registro->nome_fantasia }}</div>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <div class="font-mono text-xs text-text">
                                            {{ $registro->ehPessoaFisica()
                                                ? \App\Domain\Cadastro\Documento::mascarar($registro->documento)
                                                : \App\Domain\Cadastro\Documento::formatar($registro->documento) }}
                                        </div>
                                        <div class="mt-0.5 text-xs text-text-muted">
                                            {{ $registro->ehPessoaFisica() ? 'Física' : ($registro->tipo === 'E' ? 'Estrangeiro' : 'Jurídica') }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($registro->papeis->where('ativo', true) as $vinculo)
                                                <x-badge :variant="config('papeis.cores.' . $vinculo->papel, 'gray')" class="text-[10px]">
                                                    {{ config('papeis.rotulos.' . $vinculo->papel) }}
                                                </x-badge>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-text-secondary">
                                        {{ $registro->enderecoPrincipal?->municipio?->nomeComUf() ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @if (! $registro->ativo)
                                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-text-muted">
                                                <span class="h-1.5 w-1.5 rounded-full bg-text-muted"></span>Inativo
                                            </span>
                                        @elseif ($registro->ie_indicador === '1' && ! $registro->ie)
                                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-amber-700">
                                                <span class="h-1.5 w-1.5 rounded-full bg-warning"></span>Sem IE
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-green-700">
                                                <span class="h-1.5 w-1.5 rounded-full bg-success"></span>Ativo
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-border px-5 py-3">
                    {{ $this->pessoas->links() }}
                </div>
            @endif
        </x-card>

        {{-- Ficha resumida --}}
        <div class="flex flex-col gap-3">
            @if ($this->pessoa)
                @include('livewire.pessoas.partials.ficha', ['pessoa' => $this->pessoa])
            @else
                <x-card padding="md">
                    <x-empty-state icon="users" title="Selecione uma pessoa"
                                   description="A ficha resumida aparece aqui, com papéis, dados fiscais e endereço principal." />
                </x-card>
            @endif
        </div>
    </div>
</div>
