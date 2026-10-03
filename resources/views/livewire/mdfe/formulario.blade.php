{{-- Rotina 4020 — painel do MDF-e: gera da viagem, emite e encerra. --}}
<div>
    @if (! $mdfe)
        <x-page-header title="Novo MDF-e" subtitle="Gere o MDF-e a partir de uma viagem">
            <x-slot:actions><x-button variant="ghost" size="sm" :href="route('mdfe.index')" wire:navigate>Voltar</x-button></x-slot:actions>
        </x-page-header>
        @if (session('erro'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800/50 dark:bg-red-950/50 dark:text-red-300">{{ session('erro') }}</div>
        @endif
        <x-card padding="none" class="overflow-hidden">
            <div class="flex items-center gap-2 border-b border-border px-5 py-3.5"><x-icon name="route" class="h-4 w-4 text-text-secondary" /><h2 class="text-sm font-semibold text-text">Viagem</h2></div>
            <div class="px-5 py-5">
                <x-select label="Viagem (sem MDF-e aberto)" wire:model="viagem_id">
                    <option value="">Selecione…</option>
                    @foreach ($this->viagens as $v)
                        <option value="{{ $v->id }}">Viagem {{ $v->numero }} · {{ $v->veiculoTracao?->placaFormatada() }} · {{ $v->municipioOrigem?->nome ?? '—' }} → {{ $v->municipioDestino?->nome ?? '—' }}</option>
                    @endforeach
                </x-select>
                <x-button class="mt-4" variant="primary" size="sm" icon="files" wire:click="gerarRascunho" wire:loading.attr="disabled">Gerar rascunho do MDF-e</x-button>
                <p class="mt-2 text-xs text-text-muted">Traz veículo, reboques, condutores e percurso da viagem. A categoria por eixos é derivada da composição.</p>
            </div>
        </x-card>
    @else
        <x-page-header :title="'MDF-e nº ' . ($mdfe->numero ? str_pad((string) $mdfe->numero, 6, '0', STR_PAD_LEFT) : 'rascunho')"
                       :subtitle="'Origem: viagem ' . ($mdfe->viagem?->numero ?? '—') . ' · modelo 58 · série ' . $mdfe->serie">
            <x-slot:actions>
                <x-button variant="ghost" size="sm" :href="route('mdfe.index')" wire:navigate>Voltar</x-button>
            </x-slot:actions>
        </x-page-header>

        @if (session('sucesso'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800/50 dark:bg-green-950/50 dark:text-green-300">{{ session('sucesso') }}</div>
        @endif
        @if (session('erro'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800/50 dark:bg-red-950/50 dark:text-red-300">{{ session('erro') }}</div>
        @endif
        @if ($mdfe->status === 'rejeitado')
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800/50 dark:bg-red-950/50 dark:text-red-300">
                <b>Rejeitado pela SEFAZ:</b> {{ $mdfe->codigo_status }} · {{ $mdfe->motivo_status }}. Corrija e emita de novo — o CIOT e o vale-pedágio da viagem são reaproveitados.
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="flex flex-col gap-4 lg:col-span-2">
                <x-card padding="none" class="overflow-hidden">
                    <div class="flex items-center gap-2 border-b border-border px-5 py-3.5"><x-icon name="truck" class="h-4 w-4 text-text-secondary" /><h2 class="text-sm font-semibold text-text">Veículo e condutores</h2></div>
                    <div class="grid grid-cols-1 gap-x-6 gap-y-1 px-5 py-4 sm:grid-cols-2">
                        <x-linha-ficha rotulo="Tração" :valor="$mdfe->veiculoTracao?->placaFormatada() ?? '—'" mono />
                        <x-linha-ficha rotulo="Reboques" :valor="collect($mdfe->reboques ?? [])->pluck('placa')->implode(' · ') ?: '—'" mono />
                        <x-linha-ficha rotulo="categCombVeic" :valor="$mdfe->categoria_comb_veicular ? str_pad((string) $mdfe->categoria_comb_veicular, 2, '0', STR_PAD_LEFT) : '—'" />
                        <x-linha-ficha rotulo="Condutores" :valor="collect($mdfe->condutores ?? [])->pluck('nome')->implode(', ') ?: '—'" />
                        <x-linha-ficha rotulo="Peso bruto" :valor="number_format((float) $mdfe->peso_bruto_total, 0, ',', '.') . ' kg'" />
                        <x-linha-ficha rotulo="Valor da carga" :valor="'R$ ' . number_format((float) $mdfe->valor_carga_total, 2, ',', '.')" />
                    </div>
                    <div class="px-5 pb-4"><div class="flex items-start gap-2 rounded-r-md border-l-[3px] border-info bg-blue-50 px-3 py-2.5 text-xs text-blue-800 dark:bg-blue-950/40 dark:text-blue-300"><x-icon name="info" class="mt-px h-3.5 w-3.5 flex-shrink-0" /><span>A categoria por eixos (categCombVeic) é derivada da composição — trocou reboque, muda sozinha. Os códigos não são sequenciais (9 eixos = 12).</span></div></div>
                </x-card>

                <x-card padding="none" class="overflow-hidden">
                    <div class="flex items-center gap-2 border-b border-border px-5 py-3.5"><x-icon name="map" class="h-4 w-4 text-text-secondary" /><h2 class="text-sm font-semibold text-text">Percurso</h2></div>
                    <div class="grid grid-cols-1 gap-x-6 gap-y-1 px-5 py-4 sm:grid-cols-2">
                        <x-linha-ficha rotulo="UF início" :valor="$mdfe->uf_inicio ?? '—'" />
                        <x-linha-ficha rotulo="UF fim" :valor="$mdfe->uf_fim ?? '—'" />
                        <x-linha-ficha rotulo="UFs de percurso" :valor="collect($mdfe->percurso_ufs ?? [])->implode(' · ') ?: '—'" />
                        <x-linha-ficha rotulo="Carregamento" :valor="collect($mdfe->municipio_carregamento ?? [])->pluck('nome')->implode(', ') ?: '—'" />
                    </div>
                </x-card>

                <x-card padding="none" class="overflow-hidden">
                    <div class="flex items-center gap-2 border-b border-border px-5 py-3.5"><x-icon name="files" class="h-4 w-4 text-text-secondary" /><h2 class="text-sm font-semibold text-text">Documentos vinculados</h2><span class="ml-auto text-sm text-text-muted">{{ $mdfe->documentos->count() }} CT-e</span>@if ($mdfe->status === 'rascunho')<button type="button" wire:click="sincronizarDocumentos" class="rounded px-2 py-1 text-xs font-medium text-primary hover:bg-primary-soft">Sincronizar</button>@endif</div>
                    <div class="px-5 py-4">
                        @forelse ($mdfe->documentos as $doc)
                            <div class="flex items-center justify-between border-t border-border py-1.5 text-sm first:border-0"><span class="font-mono text-xs text-text-secondary">{{ $doc->chave ?? '—' }}</span><span class="text-text-muted tabular-nums">R$ {{ number_format((float) $doc->valor, 2, ',', '.') }}</span></div>
                        @empty
                            <p class="text-sm text-text-muted">Nenhum documento vinculado ainda — vincule os CT-e da viagem.</p>
                        @endforelse
                    </div>
                </x-card>

                @include('livewire.mdfe.partials.ciot')
                @include('livewire.mdfe.partials.vale')
            </div>

            <div class="flex flex-col gap-4">
                @if ($this->emitivel())
                    @include('livewire.mdfe.partials.emitir')
                @endif

                <x-card padding="none" class="overflow-hidden">
                    <div class="flex items-center gap-2 border-b border-border px-5 py-3.5"><x-icon name="upload" class="h-4 w-4 text-text-secondary" /><h2 class="text-sm font-semibold text-text">Transmissão</h2>@if ($this->ambiente === 2)<x-badge variant="warning" class="ml-auto text-[10px]">Homologação</x-badge>@endif</div>
                    <div class="px-5 py-4">
                        <x-linha-ficha rotulo="Situação" :valor="config('fiscal.mdfe.status.' . $mdfe->status, $mdfe->status)" />
                        <x-linha-ficha rotulo="Chave" :valor="$mdfe->chave ?? '—'" mono />
                        <x-linha-ficha rotulo="Protocolo" :valor="$mdfe->protocolo ?? '—'" />
                    </div>
                </x-card>


                @if ($mdfe->encerravel())
                    <x-card padding="none" class="overflow-hidden">
                        <div class="flex items-center gap-2 border-b border-border px-5 py-3.5"><x-icon name="check" class="h-4 w-4 text-text-secondary" /><h2 class="text-sm font-semibold text-text">Encerrar</h2></div>
                        <div class="px-5 py-4">
                            <x-select label="Município de encerramento" wire:model="municipio_encerramento_id">
                                <option value="">Selecione…</option>
                                @foreach ($this->municipios as $mun)<option value="{{ $mun->id }}">{{ $mun->nome }}/{{ $mun->uf }}</option>@endforeach
                            </x-select>
                            @can('encerrar', $mdfe)<x-button class="mt-3 w-full justify-center" variant="primary" size="sm" icon="check" wire:click="encerrar" wire:loading.attr="disabled">Encerrar (110112)</x-button>@endcan
                        </div>
                    </x-card>
                @endif

                <x-card padding="none" class="overflow-hidden">
                    <div class="flex items-center gap-2 border-b border-border px-5 py-3.5"><x-icon name="clock" class="h-4 w-4 text-text-secondary" /><h2 class="text-sm font-semibold text-text">Eventos</h2></div>
                    <div class="px-5 py-4">
                        @forelse ($mdfe->eventos as $ev)
                            <div class="flex items-start gap-2 border-t border-border py-2 text-sm first:border-0"><x-icon name="check" class="mt-0.5 h-3.5 w-3.5 flex-shrink-0 text-success" /><div><div class="font-medium text-text">{{ \App\Models\MdfeEvento::TIPOS[$ev->tipo_evento] ?? $ev->tipo_evento }}</div><div class="text-xs text-text-muted">{{ $ev->data_evento?->format('d/m/Y H:i') }} · {{ $ev->protocolo }}</div></div></div>
                        @empty
                            <p class="text-sm text-text-muted">Nenhum evento ainda.</p>
                        @endforelse
                    </div>
                </x-card>
            </div>
        </div>

        @include('livewire.mdfe.partials.resultado')

        @if ($this->emitivel())
            @can('emitir', $mdfe)
                <livewire:vales-pedagio.fornecedoras />
            @endcan
        @endif
    @endif
</div>
