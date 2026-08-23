{{-- Rotina 4010 — painel do CT-e: gera da OC, emite e cancela. --}}
<div>
    @if (! $cte)
        {{-- Modo: gerar rascunho a partir de uma ordem de coleta --}}
        <x-page-header title="Novo CT-e" subtitle="Gere o CT-e a partir de uma ordem de coleta">
            <x-slot:actions>
                <x-button variant="ghost" size="sm" :href="route('cte.index')" wire:navigate>Voltar</x-button>
            </x-slot:actions>
        </x-page-header>

        @if (session('erro'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800/50 dark:bg-red-950/50 dark:text-red-300">{{ session('erro') }}</div>
        @endif

        <x-card padding="none" class="overflow-hidden">
            <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                <x-icon name="file-text" class="h-4 w-4 text-text-secondary" />
                <h2 class="text-sm font-semibold text-text">Ordem de coleta</h2>
            </div>
            <div class="px-5 py-5">
                <x-select label="Ordem de coleta (sem CT-e)" wire:model="ordem_coleta_id">
                    <option value="">Selecione…</option>
                    @foreach ($this->ordens as $o)
                        <option value="{{ $o->id }}">OC {{ $o->numero }} · {{ $o->cliente?->razao_social }} · R$ {{ number_format((float) $o->valor_frete_calculado, 2, ',', '.') }}</option>
                    @endforeach
                </x-select>
                <x-button class="mt-4" variant="primary" size="sm" icon="files" wire:click="gerarRascunho" wire:loading.attr="disabled">Gerar rascunho do CT-e</x-button>
                <p class="mt-2 text-xs text-text-muted">O rascunho copia participantes, trecho, carga, componentes do frete e as NF-e da ordem — sem redigitar.</p>
            </div>
        </x-card>
    @else
        @php $editavel = $cte->editavel(); @endphp
        <x-page-header :title="'CT-e nº ' . ($cte->numero ? str_pad((string) $cte->numero, 6, '0', STR_PAD_LEFT) : 'rascunho')"
                       :subtitle="'Origem: ordem ' . ($cte->ordemColeta?->numero ?? '—') . ' · modelo 57 · série ' . $cte->serie">
            <x-slot:actions>
                <x-button variant="ghost" size="sm" :href="route('cte.index')" wire:navigate>Voltar</x-button>
                @if ($editavel)
                    @can('emitir', $cte)
                        <x-button variant="primary" size="sm" icon="lock" wire:click="emitir" wire:loading.attr="disabled">Emitir (2FA)</x-button>
                    @endcan
                @endif
            </x-slot:actions>
        </x-page-header>

        @if (session('sucesso'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800/50 dark:bg-green-950/50 dark:text-green-300">{{ session('sucesso') }}</div>
        @endif
        @if (session('erro'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800/50 dark:bg-red-950/50 dark:text-red-300">{{ session('erro') }}</div>
        @endif

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="flex flex-col gap-4 lg:col-span-2">
                <x-card padding="none" class="overflow-hidden">
                    <div class="flex items-center gap-2 border-b border-border px-5 py-3.5"><x-icon name="users" class="h-4 w-4 text-text-secondary" /><h2 class="text-sm font-semibold text-text">Participantes e trecho</h2></div>
                    <div class="grid grid-cols-1 gap-x-6 gap-y-1 px-5 py-4 sm:grid-cols-2">
                        <x-linha-ficha rotulo="Tomador (RN-04)" :valor="ucfirst($cte->tomadorPapel()) . ' · ' . ($cte->tomador?->razao_social ?? '—')" />
                        <x-linha-ficha rotulo="Natureza" :valor="$cte->natureza_operacao ?? '—'" />
                        <x-linha-ficha rotulo="Remetente" :valor="$cte->remetente?->razao_social ?? '—'" />
                        <x-linha-ficha rotulo="Destinatário" :valor="$cte->destinatario?->razao_social ?? '—'" />
                        <x-linha-ficha rotulo="Início" :valor="$cte->municipioInicio?->nome ?? '—'" />
                        <x-linha-ficha rotulo="Fim" :valor="$cte->municipioFim?->nome ?? '—'" />
                        <x-linha-ficha rotulo="Produto" :valor="$cte->produto_predominante ?? '—'" />
                        <x-linha-ficha rotulo="Peso base" :valor="number_format((float) $cte->peso_bruto, 0, ',', '.') . ' kg'" />
                    </div>
                </x-card>

                <x-card padding="none" class="overflow-hidden">
                    <div class="flex items-center gap-2 border-b border-border px-5 py-3.5"><x-icon name="tag" class="h-4 w-4 text-text-secondary" /><h2 class="text-sm font-semibold text-text">Componentes do frete (vTPrest)</h2></div>
                    <div class="px-5 py-4">
                        @foreach ($cte->componentes as $comp)
                            <div class="flex items-center justify-between border-t border-border py-1.5 text-sm first:border-0"><span class="text-text-secondary">{{ $comp->nome }}</span><span class="font-medium text-text tabular-nums">R$ {{ number_format((float) $comp->valor, 2, ',', '.') }}</span></div>
                        @endforeach
                        <div class="mt-2 flex items-center justify-between border-t-2 border-border pt-2 text-sm"><span class="font-semibold text-text">Total da prestação</span><span class="font-bold text-text tabular-nums">R$ {{ number_format((float) $cte->valor_total_servico, 2, ',', '.') }}</span></div>
                        <div class="mt-3 flex items-start gap-2 rounded-r-md border-l-[3px] border-warning bg-yellow-50 px-3 py-2.5 text-xs text-yellow-800 dark:bg-yellow-950/40 dark:text-yellow-300">
                            <x-icon name="alert-triangle" class="mt-px h-3.5 w-3.5 flex-shrink-0" /><span>O pedágio pode ser componente do frete, mas o grupo valePed não existe no CT-e — é declarado no MDF-e — e não integra a base do ICMS.</span>
                        </div>
                    </div>
                </x-card>

                <x-card padding="none" class="overflow-hidden">
                    <div class="flex items-center gap-2 border-b border-border px-5 py-3.5"><x-icon name="file-text" class="h-4 w-4 text-text-secondary" /><h2 class="text-sm font-semibold text-text">Documentos (NF-e)</h2></div>
                    <div class="px-5 py-4">
                        @forelse ($cte->documentos as $doc)
                            <div class="flex items-center justify-between border-t border-border py-1.5 text-sm first:border-0"><span class="font-mono text-xs text-text-secondary">{{ $doc->chave ?? ('NF-e ' . $doc->numero) }}</span><span class="text-text-muted tabular-nums">R$ {{ number_format((float) $doc->valor, 2, ',', '.') }}</span></div>
                        @empty
                            <p class="text-sm text-text-muted">Sem NF-e vinculada.</p>
                        @endforelse
                    </div>
                </x-card>
            </div>

            <div class="flex flex-col gap-4">
                <x-card padding="none" class="overflow-hidden">
                    <div class="flex items-center gap-2 border-b border-border px-5 py-3.5"><x-icon name="upload" class="h-4 w-4 text-text-secondary" /><h2 class="text-sm font-semibold text-text">Transmissão</h2>@if ($this->ambiente === 2)<x-badge variant="warning" class="ml-auto text-[10px]">Homologação</x-badge>@endif</div>
                    <div class="px-5 py-4">
                        <x-linha-ficha rotulo="Situação" :valor="config('fiscal.cte.status.' . $cte->status, $cte->status)" />
                        <x-linha-ficha rotulo="Chave" :valor="$cte->chave ?? '—'" mono />
                        <x-linha-ficha rotulo="Protocolo" :valor="$cte->protocolo ?? '—'" />
                        <x-linha-ficha rotulo="cStat / xMotivo" :valor="$cte->codigo_status ? $cte->codigo_status . ' · ' . $cte->motivo_status : '—'" />
                        @if ($cte->status === 'rejeitado')
                            <div class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-800 dark:bg-red-950/40 dark:text-red-300">Corrija e emita novamente.</div>
                        @endif
                    </div>
                </x-card>

                @if ($cte->autorizado())
                    <x-card padding="none" class="overflow-hidden">
                        <div class="flex items-center gap-2 border-b border-border px-5 py-3.5"><x-icon name="x" class="h-4 w-4 text-text-secondary" /><h2 class="text-sm font-semibold text-text">Cancelar</h2></div>
                        <div class="px-5 py-4">
                            <x-input-label>Justificativa (mín. 15 caracteres)</x-input-label>
                            <textarea wire:model="justificativa" rows="2" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"></textarea>
                            @can('cancelar', $cte)
                                <x-button class="mt-2 w-full justify-center" variant="danger" size="sm" icon="trash-2" wire:click="cancelar" wire:loading.attr="disabled">Cancelar CT-e (110111)</x-button>
                            @endcan
                        </div>
                    </x-card>
                @endif

                <x-card padding="none" class="overflow-hidden">
                    <div class="flex items-center gap-2 border-b border-border px-5 py-3.5"><x-icon name="clock" class="h-4 w-4 text-text-secondary" /><h2 class="text-sm font-semibold text-text">Eventos</h2></div>
                    <div class="px-5 py-4">
                        @forelse ($cte->eventos as $ev)
                            <div class="flex items-start gap-2 border-t border-border py-2 text-sm first:border-0">
                                <x-icon name="check" class="mt-0.5 h-3.5 w-3.5 flex-shrink-0 text-success" />
                                <div><div class="font-medium text-text">{{ \App\Models\CteEvento::TIPOS[$ev->tipo_evento] ?? $ev->tipo_evento }}</div><div class="text-xs text-text-muted">{{ $ev->data_evento?->format('d/m/Y H:i') }} · {{ $ev->protocolo }}</div></div>
                            </div>
                        @empty
                            <p class="text-sm text-text-muted">Nenhum evento ainda.</p>
                        @endforelse
                    </div>
                </x-card>
            </div>
        </div>
    @endif
</div>
