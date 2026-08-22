{{--
    Rotina 1030 — ficha da tabela de frete.
    Cabeçalho (para quem, quando, trecho) + itens (o preço, um componente por linha).
--}}
<div>
    <x-page-header
        :title="$tabela?->exists ? $tabela->descricao : 'Nova tabela de frete'"
        :subtitle="$tabela?->exists ? 'Cadastros · rotina 1030' : 'O preço é a soma dos componentes — peso, valor, GRIS, pedágio…'">
        <x-slot:actions>
            <x-button variant="ghost" size="sm" :href="route('tabelas-frete.index')" wire:navigate>Cancelar</x-button>
            <x-button variant="primary" size="sm" icon="check" wire:click="salvar" wire:loading.attr="disabled">Salvar</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-col gap-4">
        {{-- Cabeçalho --}}
        <x-card padding="none" class="overflow-hidden">
            <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                <x-icon name="tag" class="h-4 w-4 text-text-secondary" />
                <h2 class="text-sm font-semibold text-text">Cabeçalho</h2>
            </div>
            <div class="px-5 py-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <x-input class="sm:col-span-2" label="Descrição" required wire:model="descricao"
                             :error="$errors->first('descricao')" placeholder="Tabela geral 2026 — cargas secas" />
                    <x-select label="Cliente" wire:model="pessoa_id" help="Em branco = tabela geral.">
                        <option value="">Geral (todos os clientes)</option>
                        @foreach ($this->clientes as $cliente)
                            <option value="{{ $cliente->id }}">{{ $cliente->razao_social }}</option>
                        @endforeach
                    </x-select>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <x-input label="Vigência início" type="date" required wire:model="vigencia_inicio"
                             :error="$errors->first('vigencia_inicio')" />
                    <x-input label="Vigência fim" type="date" wire:model="vigencia_fim"
                             :error="$errors->first('vigencia_fim')" help="Em branco = sem prazo." />
                    <x-input label="Tipo de veículo" wire:model="tipo_veiculo" placeholder="Bitrem, carreta…" />
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 pb-2.5">
                            <input type="checkbox" wire:model="ativo"
                                   class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30">
                            <span class="text-sm text-text">Ativa</span>
                        </label>
                    </div>
                </div>

                <div class="my-5 h-px bg-border"></div>
                <p class="mb-3 text-[11px] font-bold uppercase tracking-wider text-text-muted">Trecho (opcional)</p>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <x-input label="UF origem" wire:model="uf_origem" :error="$errors->first('uf_origem')" placeholder="BA" />
                    <x-select label="Município origem" wire:model="municipio_origem_id">
                        <option value="">Qualquer</option>
                        @foreach ($this->municipios as $m)
                            <option value="{{ $m->id }}">{{ $m->nome }}/{{ $m->uf }}</option>
                        @endforeach
                    </x-select>
                    <x-input label="UF destino" wire:model="uf_destino" :error="$errors->first('uf_destino')" placeholder="SP" />
                    <x-select label="Município destino" wire:model="municipio_destino_id">
                        <option value="">Qualquer</option>
                        @foreach ($this->municipios as $m)
                            <option value="{{ $m->id }}">{{ $m->nome }}/{{ $m->uf }}</option>
                        @endforeach
                    </x-select>
                </div>
            </div>
        </x-card>

        {{-- Itens --}}
        <x-card padding="none" class="overflow-hidden">
            <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                <x-icon name="files" class="h-4 w-4 text-text-secondary" />
                <h2 class="text-sm font-semibold text-text">Componentes do preço</h2>
                <span class="text-sm text-text-muted">({{ count($itens) }})</span>
            </div>
            <div class="px-5 py-4">
                @error('itens') <p class="mb-2 text-xs text-danger">{{ $message }}</p> @enderror

                @foreach ($itens as $i => $item)
                    <div wire:key="item-{{ $i }}" class="mb-3 rounded-lg border border-border p-4">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-6">
                            <x-select class="sm:col-span-2" label="Componente" wire:model="itens.{{ $i }}.componente"
                                      :error="$errors->first('itens.' . $i . '.componente')">
                                @foreach (config('tabelas_frete.componentes') as $codigo => $rotulo)
                                    <option value="{{ $codigo }}">{{ $rotulo }}</option>
                                @endforeach
                            </x-select>
                            <x-select class="sm:col-span-2" label="Base de cálculo" wire:model="itens.{{ $i }}.base_calculo"
                                      :error="$errors->first('itens.' . $i . '.base_calculo')">
                                @foreach (config('tabelas_frete.bases') as $codigo => $rotulo)
                                    <option value="{{ $codigo }}">{{ $rotulo }}</option>
                                @endforeach
                            </x-select>
                            <x-input label="Valor" type="number" required wire:model="itens.{{ $i }}.valor"
                                     :error="$errors->first('itens.' . $i . '.valor')" />
                            <div class="flex items-end justify-end">
                                <button type="button" wire:click="removerItem({{ $i }})"
                                        class="rounded p-2 text-danger hover:bg-red-50" title="Remover">
                                    <x-icon name="trash-2" class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-4">
                            <x-input label="Faixa de" type="number" wire:model="itens.{{ $i }}.faixa_de"
                                     help="Início da faixa (peso/valor)." />
                            <x-input label="Faixa até" type="number" wire:model="itens.{{ $i }}.faixa_ate" />
                            <x-input label="Mínimo" type="number" wire:model="itens.{{ $i }}.minimo" />
                            <x-input label="Máximo" type="number" wire:model="itens.{{ $i }}.maximo" />
                        </div>
                    </div>
                @endforeach

                <x-button variant="outline" size="sm" icon="plus" wire:click="adicionarItem">Adicionar componente</x-button>

                <div class="mt-3 flex items-start gap-2 rounded-r-md border-l-[3px] border-info bg-blue-50 px-3 py-2.5
                            text-xs text-blue-800 dark:bg-blue-950/40 dark:text-blue-300">
                    <x-icon name="info" class="mt-px h-3.5 w-3.5 flex-shrink-0" />
                    <span>O pedágio pode entrar como componente informativo, mas não integra o valor do frete nem a base do ICMS no CT-e — a separação é feita na emissão.</span>
                </div>
            </div>
        </x-card>
    </div>
</div>
