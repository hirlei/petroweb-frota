{{--
    Rotina 9010 — Empresa e filiais.
    Cada filial é um emitente fiscal independente: CNPJ, IE, certificado e
    ambiente SEFAZ próprios. A matriz aparece primeiro.
--}}
<div>
    <x-page-header
        title="Empresa e filiais"
        subtitle="{{ number_format($this->total, 0, ',', '.') }} estabelecimentos · cada filial emite por conta própria, com ambiente SEFAZ próprio">
        <x-slot:actions>
            @can('create', \App\Models\Filial::class)
                <x-button variant="primary" size="sm" icon="plus" :href="route('filiais.criar')" wire:navigate>
                    Nova filial
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

    <div class="mb-4 flex items-center gap-3">
        <div class="relative max-w-sm flex-1">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
            <input type="search" wire:model.live.debounce.400ms="busca"
                   placeholder="Razão social, fantasia ou CNPJ…"
                   class="w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm text-text
                          outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>
        <select wire:model.live="situacao"
                class="rounded-md border border-border bg-surface px-3 py-1.5 text-sm text-text">
            <option value="ativas">Somente ativas</option>
            <option value="inativas">Inativas</option>
            <option value="todas">Todas</option>
        </select>
    </div>

    <x-card padding="none" class="overflow-hidden">
        <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
            <x-icon name="building" class="h-4 w-4 text-text-secondary" />
            <h2 class="text-sm font-semibold text-text">Estabelecimentos</h2>
            <span class="text-sm text-text-muted">({{ number_format($this->filiais->total(), 0, ',', '.') }})</span>
        </div>

        @if ($this->filiais->isEmpty())
            <x-empty-state icon="building" title="Nenhuma filial"
                           description="Cadastre ao menos a matriz para começar a emitir documentos fiscais." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-border bg-surface-elevated">
                        <tr>
                            @foreach (['Filial', 'CNPJ', 'Município', 'Ambiente', 'Certificado', 'Situação', ''] as $cabecalho)
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-text-muted first:pl-5">{{ $cabecalho }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->filiais as $registro)
                            <tr wire:key="filial-{{ $registro->id }}" class="border-t border-border hover:bg-surface-elevated">
                                <td class="px-4 py-3 pl-5">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-text">{{ $registro->nome_fantasia ?? $registro->razao_social }}</span>
                                        @if ($registro->matriz)
                                            <x-badge variant="primary" class="text-[10px]">Matriz</x-badge>
                                        @endif
                                    </div>
                                    <div class="mt-0.5 text-xs text-text-muted">{{ $registro->codigo }}</div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="font-mono text-xs text-text">{{ \App\Domain\Cadastro\Documento::formatar($registro->cnpj) }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-text-secondary">
                                    {{ $registro->municipio ? $registro->municipio->nome . '/' . $registro->municipio->uf : '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    @if ($registro->ambiente_sefaz === 1)
                                        <x-badge variant="success" class="text-[10px]">Produção</x-badge>
                                    @else
                                        <x-badge variant="warning" class="text-[10px]">Homologação</x-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($registro->certificado && $registro->certificado->vigente())
                                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-green-700">
                                            <x-icon name="shield-check" class="h-3.5 w-3.5" /> Vigente
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-text-muted">
                                            <x-icon name="alert-triangle" class="h-3.5 w-3.5" /> Ausente
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium {{ $registro->ativa ? 'text-green-700' : 'text-text-muted' }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $registro->ativa ? 'bg-success' : 'bg-text-muted' }}"></span>
                                        {{ $registro->ativa ? 'Ativa' : 'Inativa' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @can('update', $registro)
                                        <a href="{{ route('filiais.editar', $registro) }}" wire:navigate
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
                {{ $this->filiais->links() }}
            </div>
        @endif
    </x-card>
</div>
