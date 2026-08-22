{{--
    Rotina 2020 — montagem da combinação.
    Escolhe-se o cavalo e adicionam-se os reboques na ordem em que rodam. Eixos,
    PBTC, capacidade, categoria e AET se recompõem a cada mudança — nada é digitado.
--}}
<div>
    <x-page-header
        :title="$composicao?->exists ? $composicao->descricao : 'Nova composição'"
        :subtitle="$composicao?->exists ? 'Frota · rotina 2020' : 'Cavalo mais reboques, na ordem em que rodam'">
        <x-slot:actions>
            <x-button variant="ghost" size="sm" :href="route('composicoes.index')" wire:navigate>Cancelar</x-button>
            <x-button variant="primary" size="sm" icon="check" wire:click="salvar" wire:loading.attr="disabled">
                Salvar
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-[1fr_372px]">

        <div class="flex flex-col gap-4">
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="link" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Combinação</h2>
                </div>
                <div class="px-5 py-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-input class="sm:col-span-2" label="Descrição" required wire:model="descricao"
                                 :error="$errors->first('descricao')" placeholder="Bitrem graneleiro — frota BA" />
                        <x-select label="Veículo de tração (cavalo)" required wire:model.live="veiculo_tracao_id"
                                  :error="$errors->first('veiculo_tracao_id')">
                            <option value="">Selecione…</option>
                            @foreach ($this->tracoes as $tracao)
                                <option value="{{ $tracao->id }}">{{ $tracao->placaFormatada() }} · {{ $tracao->eixos }} eixos</option>
                            @endforeach
                        </x-select>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2 pb-2.5">
                                <input type="checkbox" wire:model="ativa"
                                       class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30">
                                <span class="text-sm text-text">Composição ativa</span>
                            </label>
                        </div>
                    </div>
                </div>
            </x-card>

            {{-- Unidades na ordem --}}
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="package" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Unidades rebocadas, na ordem</h2>
                    <span class="text-sm text-text-muted">({{ count($reboques) }})</span>
                </div>
                <div class="px-5 py-4">
                    @error('reboques') <p class="mb-2 text-xs text-danger">{{ $message }}</p> @enderror

                    {{-- Tração como primeira unidade (posição fixa) --}}
                    @if ($veiculo_tracao_id)
                        @php $tracao = $this->tracoes->firstWhere('id', $veiculo_tracao_id); @endphp
                        @if ($tracao)
                            <div class="mb-2 flex items-center gap-3 rounded-lg border border-primary bg-primary-soft/40 px-3 py-2.5">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-white">1</span>
                                <x-icon name="truck" class="h-4 w-4 text-amber-700" />
                                <div class="flex-1">
                                    <span class="font-mono text-sm font-semibold text-text">{{ $tracao->placaFormatada() }}</span>
                                    <span class="ml-2 text-xs text-text-muted">Tração · {{ $tracao->eixos }} eixos</span>
                                </div>
                            </div>
                        @endif
                    @endif

                    @foreach ($reboques as $i => $reboqueId)
                        @php $unidade = $this->unidades->firstWhere('id', $reboqueId); @endphp
                        <div wire:key="reboque-{{ $reboqueId }}" class="mb-2 flex items-center gap-3 rounded-lg border border-border px-3 py-2.5">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-surface-elevated text-xs font-bold text-text-secondary">{{ $i + 2 }}</span>
                            <x-icon name="package" class="h-4 w-4 text-text-secondary" />
                            <div class="flex-1">
                                <span class="font-mono text-sm font-semibold text-text">{{ $unidade?->placaFormatada() ?? '—' }}</span>
                                <span class="ml-2 text-xs text-text-muted">
                                    {{ $unidade ? config('veiculos.tipos.' . $unidade->tipo) : '' }} · {{ $unidade?->eixos }} eixos
                                </span>
                            </div>
                            <div class="flex items-center gap-1">
                                <button type="button" wire:click="moverCima({{ $i }})" @disabled($i === 0)
                                        class="rounded p-1 text-text-secondary hover:bg-surface-elevated disabled:opacity-30" title="Subir">
                                    <x-icon name="chevron-down" class="h-4 w-4 rotate-180" />
                                </button>
                                <button type="button" wire:click="moverBaixo({{ $i }})" @disabled($i === count($reboques) - 1)
                                        class="rounded p-1 text-text-secondary hover:bg-surface-elevated disabled:opacity-30" title="Descer">
                                    <x-icon name="chevron-down" class="h-4 w-4" />
                                </button>
                                <button type="button" wire:click="removerReboque({{ $i }})"
                                        class="rounded p-1 text-danger hover:bg-red-50" title="Remover">
                                    <x-icon name="trash-2" class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    @endforeach

                    <div class="mt-3 flex items-end gap-2">
                        <x-select class="flex-1" label="Adicionar unidade" wire:model="reboqueParaAdicionar">
                            <option value="">Selecione um reboque, semirreboque ou dolly…</option>
                            @foreach ($this->reboquesDisponiveis as $disponivel)
                                <option value="{{ $disponivel->id }}">
                                    {{ $disponivel->placaFormatada() }} · {{ config('veiculos.tipos.' . $disponivel->tipo) }} · {{ $disponivel->eixos }} eixos
                                </option>
                            @endforeach
                        </x-select>
                        <x-button variant="outline" size="sm" icon="plus" wire:click="adicionarReboque">Adicionar</x-button>
                    </div>
                </div>
            </x-card>
        </div>

        {{-- Totais calculados --}}
        <div class="flex flex-col gap-3">
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="layout-dashboard" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Totais da combinação</h2>
                </div>
                <div class="px-5 py-4">
                    <x-linha-ficha rotulo="Eixos totais" :valor="(string) $this->eixosTotal" />
                    <x-linha-ficha rotulo="Tara total" :valor="number_format($this->taraTotal, 0, ',', '.') . ' kg'" />
                    <x-linha-ficha rotulo="PBTC (da tração)" :valor="$this->pbtcTotal ? number_format($this->pbtcTotal, 0, ',', '.') . ' kg' : '—'" />
                    <x-linha-ficha rotulo="Capacidade" :valor="$this->capacidadeTotal ? number_format($this->capacidadeTotal, 0, ',', '.') . ' kg' : '—'" />

                    <div class="my-1.5 flex items-center justify-between rounded-md bg-primary-soft px-2.5 py-2">
                        <span class="text-sm font-semibold text-amber-700">Categoria (categCombVeic)</span>
                        <x-badge variant="primary" class="text-[11px]">{{ $this->categoria->value }}</x-badge>
                    </div>
                    <p class="text-xs text-text-muted">{{ $this->categoria->descricao() }}</p>

                    @if ($this->precisaAet)
                        <div class="mt-3 flex items-start gap-2 rounded-r-md border-l-[3px] border-warning bg-amber-50 px-3 py-2.5
                                    text-xs text-amber-900 dark:bg-amber-950/40 dark:text-amber-300">
                            <x-icon name="alert-triangle" class="mt-px h-3.5 w-3.5 flex-shrink-0" />
                            <span>Esta combinação exige AET — acima de 57 t ou com mais de duas unidades acopladas (art. 17 CONTRAN).</span>
                        </div>
                    @endif
                </div>
            </x-card>

            <x-card padding="sm">
                <div class="flex items-start gap-2 text-xs leading-relaxed text-text-secondary">
                    <x-icon name="info" class="mt-px h-3.5 w-3.5 flex-shrink-0 text-text-muted" />
                    <span>A ordem importa: ela define a sequência do <span class="font-mono">veicReboque</span> no MDF-e e a categoria da combinação. Reordene com as setas.</span>
                </div>
            </x-card>
        </div>
    </div>
</div>
