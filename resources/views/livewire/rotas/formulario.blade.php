{{--
    Rotina 3030 — ficha da rota.
    Cabeçalho (trecho + estimativas + restrições) e os pontos na ordem em que aparecem.
--}}
<div>
    <x-page-header
        :title="$rota?->exists ? $rota->descricao : 'Nova rota'"
        :subtitle="$rota?->exists ? 'Operação · rotina 3030' : 'Trecho planejado com pontos, distância e pedágio'">
        <x-slot:actions>
            <x-button variant="ghost" size="sm" :href="route('rotas.index')" wire:navigate>Cancelar</x-button>
            <x-button variant="primary" size="sm" icon="check" wire:click="salvar" wire:loading.attr="disabled">Salvar</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-[1fr_320px]">
        <div class="flex flex-col gap-4">
            {{-- Cabeçalho --}}
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="map" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Trecho</h2>
                </div>
                <div class="px-5 py-5">
                    <x-input label="Descrição" required wire:model="descricao" :error="$errors->first('descricao')"
                             placeholder="Feira de Santana → Guarulhos" />
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-select label="Município de origem" required wire:model="municipio_origem_id"
                                  :error="$errors->first('municipio_origem_id')">
                            <option value="">Selecione…</option>
                            @foreach ($this->municipios as $m)
                                <option value="{{ $m->id }}">{{ $m->nome }}/{{ $m->uf }}</option>
                            @endforeach
                        </x-select>
                        <x-select label="Município de destino" required wire:model="municipio_destino_id"
                                  :error="$errors->first('municipio_destino_id')">
                            <option value="">Selecione…</option>
                            @foreach ($this->municipios as $m)
                                <option value="{{ $m->id }}">{{ $m->nome }}/{{ $m->uf }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <x-input label="Distância (km)" type="number" wire:model="distancia_km" :error="$errors->first('distancia_km')" />
                        <x-input label="Tempo estimado (min)" type="number" wire:model="tempo_estimado_min" :error="$errors->first('tempo_estimado_min')" />
                        <x-input class="sm:col-span-2" label="Pedágio estimado (R$)" type="number" wire:model="valor_pedagio_estimado"
                                 :error="$errors->first('valor_pedagio_estimado')" />
                    </div>

                    <label class="mt-4 flex items-center gap-2">
                        <input type="checkbox" wire:model="ativa" class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30">
                        <span class="text-sm text-text">Rota ativa</span>
                    </label>

                    <div class="my-5 h-px bg-border"></div>
                    <p class="mb-3 text-[11px] font-bold uppercase tracking-wider text-text-muted">Restrições do trecho</p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <x-input label="Altura máxima (m)" type="number" wire:model="restr_altura_m" />
                        <x-input label="Peso máximo (t)" type="number" wire:model="restr_peso_t" />
                        <x-input label="Janela de horário" wire:model="restr_janela" placeholder="Ex.: proibido 6h–9h" />
                    </div>
                </div>
            </x-card>

            {{-- Pontos --}}
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="route" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Pontos, na ordem</h2>
                    <span class="text-sm text-text-muted">({{ count($pontos) }})</span>
                </div>
                <div class="px-5 py-4">
                    @forelse ($pontos as $i => $ponto)
                        <div wire:key="ponto-{{ $i }}" class="mb-2 flex items-start gap-3 rounded-lg border border-border p-3">
                            <span class="mt-6 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-surface-elevated text-xs font-bold text-text-secondary">{{ $i + 1 }}</span>
                            <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-6">
                                <x-select class="sm:col-span-2" label="Tipo" wire:model="pontos.{{ $i }}.tipo">
                                    <option value="origem">Origem</option>
                                    <option value="passagem">Passagem</option>
                                    <option value="pedagio">Pedágio</option>
                                    <option value="parada">Parada</option>
                                    <option value="destino">Destino</option>
                                </x-select>
                                <x-select class="sm:col-span-2" label="Município" wire:model="pontos.{{ $i }}.municipio_id">
                                    <option value="">—</option>
                                    @foreach ($this->municipios as $m)
                                        <option value="{{ $m->id }}">{{ $m->nome }}/{{ $m->uf }}</option>
                                    @endforeach
                                </x-select>
                                <x-input label="Km acumulado" type="number" wire:model="pontos.{{ $i }}.distancia_acumulada_km" />
                                <x-input label="Pedágio (R$)" type="number" wire:model="pontos.{{ $i }}.valor_pedagio" />
                                <x-input class="sm:col-span-2" label="Descrição" wire:model="pontos.{{ $i }}.descricao"
                                         placeholder="Praça, posto, referência…" />
                                <x-input class="sm:col-span-2" label="Latitude" type="number" wire:model="pontos.{{ $i }}.latitude"
                                         :error="$errors->first('pontos.' . $i . '.latitude')" placeholder="-12,2664" />
                                <x-input class="sm:col-span-2" label="Longitude" type="number" wire:model="pontos.{{ $i }}.longitude"
                                         :error="$errors->first('pontos.' . $i . '.longitude')" placeholder="-38,9663" />
                            </div>
                            <button type="button" wire:click="removerPonto({{ $i }})"
                                    class="mt-6 rounded p-1 text-danger hover:bg-red-50" title="Remover">
                                <x-icon name="trash-2" class="h-4 w-4" />
                            </button>
                        </div>
                    @empty
                        <x-empty-state icon="route" title="Sem pontos intermediários"
                                       description="Adicione praças de pedágio e paradas para estimar custo e tempo com precisão." />
                    @endforelse

                    <x-button variant="outline" size="sm" icon="plus" wire:click="adicionarPonto">Adicionar ponto</x-button>
                </div>
            </x-card>
        </div>

        <div class="flex flex-col gap-3">
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="ticket" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Pedágio somado</h2>
                </div>
                <div class="px-5 py-4">
                    <x-linha-ficha rotulo="Soma dos pontos" :valor="'R$ ' . number_format($this->pedagioDosPontos, 2, ',', '.')" />
                    <x-linha-ficha rotulo="Estimativa do cabeçalho"
                                   :valor="$valor_pedagio_estimado !== '' ? 'R$ ' . number_format((float) $valor_pedagio_estimado, 2, ',', '.') : '—'" />
                    <p class="mt-2 text-xs text-text-muted">Some os pedágios dos pontos e compare com a estimativa — divergência grande costuma ser praça faltando.</p>
                </div>
            </x-card>
        </div>
    </div>

    {{-- Mapa do percurso --}}
    <x-card padding="none" class="mt-4 overflow-hidden">
        <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
            <x-icon name="map" class="h-4 w-4 text-text-secondary" />
            <h2 class="text-sm font-semibold text-text">Mapa do percurso</h2>
            <span class="text-sm text-text-muted">Pontos com coordenada informada</span>
        </div>
        <div class="p-4">
            <x-mapa :pontos="$this->pontosMapa" :linha="true" altura="380px" wire:key="mapa-rota-{{ $rota?->id ?? 'nova' }}" />
        </div>
    </x-card>
</div>
