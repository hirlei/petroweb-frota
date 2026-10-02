{{--
    Rotina 2030 — ficha do motorista.
    Primeiro a pessoa (que já tem o papel de motorista), depois CNH, vínculo e
    documentos. NÃO há campo de jornada: para TAC seria prova de vínculo (RN-12).
--}}
<div>
    <x-page-header
        :title="$motorista?->exists ? ($motorista->pessoa?->razao_social ?? 'Motorista') : 'Novo motorista'"
        :subtitle="$motorista?->exists
            ? 'Frota · rotina 2030'
            : 'O motorista é um papel sobre o cadastro de pessoas'">
        <x-slot:actions>
            <x-button variant="ghost" size="sm" :href="route('motoristas.index')" wire:navigate>Cancelar</x-button>
            <x-button variant="primary" size="sm" icon="check" wire:click="salvar" wire:loading.attr="disabled">
                Salvar
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-[1fr_372px]">

        <div class="flex flex-col gap-4">
            {{-- Pessoa --}}
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="users" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Pessoa</h2>
                </div>
                <div class="px-5 py-4">
                    <x-select label="Pessoa (papel motorista)" required wire:model="pessoa_id"
                              :error="$errors->first('pessoa_id')"
                              help="Só aparecem pessoas com o papel de motorista. Não achou? Marque o papel no cadastro 1010.">
                        <option value="">Selecione…</option>
                        @foreach ($this->pessoasDisponiveis as $pessoa)
                            <option value="{{ $pessoa->id }}">{{ $pessoa->razao_social }}</option>
                        @endforeach
                    </x-select>
                </div>
            </x-card>

            <x-card padding="none" class="overflow-hidden">
                {{-- Abas --}}
                <div class="flex gap-1 border-b border-border px-4">
                    @foreach ([
                        'habilitacao' => 'Habilitação',
                        'vinculo' => 'Vínculo',
                        'documentos' => 'Documentos',
                        'remuneracao' => 'Remuneração',
                    ] as $chave => $rotulo)
                        <button type="button" wire:click="$set('aba', '{{ $chave }}')"
                                class="-mb-px border-b-2 px-3.5 py-3 text-sm transition-colors
                                       {{ $aba === $chave
                                           ? 'border-primary font-semibold text-primary'
                                           : 'border-transparent font-medium text-text-secondary hover:text-text' }}">
                            {{ $rotulo }}
                        </button>
                    @endforeach
                </div>

                {{-- Habilitação --}}
                <div class="px-5 py-5" @class(['hidden' => $aba !== 'habilitacao'])>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <x-input label="Número da CNH" required wire:model="cnh_numero" :error="$errors->first('cnh_numero')" />
                        <x-input label="Categoria" required wire:model.live="cnh_categoria"
                                 :error="$errors->first('cnh_categoria')" placeholder="E" />
                        <x-input label="Validade" type="date" required wire:model="cnh_validade" :error="$errors->first('cnh_validade')" />
                        <x-input label="1ª habilitação" type="date" wire:model="cnh_primeira_habilitacao" />
                    </div>

                    <label class="mt-4 flex items-center gap-2">
                        <input type="checkbox" wire:model="cnh_ear"
                               class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30">
                        <span class="text-sm text-text">CNH com observação EAR (exerce atividade remunerada)</span>
                    </label>

                    @if ($this->exigeToxicologico)
                        <div class="mt-4 flex items-start gap-2 rounded-r-md border-l-[3px] border-warning bg-amber-50 px-3 py-2.5
                                    text-xs text-amber-900 dark:bg-amber-950/40 dark:text-amber-300">
                            <x-icon name="alert-triangle" class="mt-px h-3.5 w-3.5 flex-shrink-0" />
                            <span>Categoria {{ $cnh_categoria }} exige exame toxicológico em dia (Lei 13.103/2015). Informe a validade na aba Documentos.</span>
                        </div>
                    @endif
                </div>

                {{-- Vínculo --}}
                <div class="px-5 py-5" @class(['hidden' => $aba !== 'vinculo'])>
                    <x-select label="Vínculo" required wire:model.live="vinculo" :error="$errors->first('vinculo')"
                              help="Decide se há jornada (só CLT) e como sai o CIOT (TAC: pela instituição de pagamento).">
                        @foreach (config('motoristas.vinculos') as $codigo => $rotulo)
                            <option value="{{ $codigo }}">{{ $rotulo }}</option>
                        @endforeach
                    </x-select>

                    <div class="mt-2 flex items-start gap-2 rounded-r-md border-l-[3px] border-info bg-blue-50 px-3 py-2.5
                                text-xs text-blue-800 dark:bg-blue-950/40 dark:text-blue-300">
                        <x-icon name="info" class="mt-px h-3.5 w-3.5 flex-shrink-0" />
                        <span>{{ config('motoristas.vinculos_descricoes.' . $vinculo) }}</span>
                    </div>

                    @if ($this->ehTac)
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <x-input label="RNTRC" required wire:model="rntrc" :error="$errors->first('rntrc')" />
                            <x-input label="Validade do RNTRC" type="date" wire:model="rntrc_validade" />
                            <x-select label="Tipo de transportador" wire:model="tp_transp">
                                <option value="">Não informado</option>
                                <option value="2">2 — TAC</option>
                                <option value="3">3 — CTC</option>
                            </x-select>
                        </div>
                    @endif

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-input label="Admissão" type="date" wire:model="admissao" />
                        <x-input label="Demissão" type="date" wire:model="demissao" />
                    </div>

                    <div class="mt-4">
                        <x-select label="Situação" required wire:model="status" :error="$errors->first('status')">
                            @foreach (config('motoristas.status') as $codigo => $rotulo)
                                <option value="{{ $codigo }}">{{ $rotulo }}</option>
                            @endforeach
                        </x-select>
                    </div>
                </div>

                {{-- Documentos --}}
                <div class="px-5 py-5" @class(['hidden' => $aba !== 'documentos'])>
                    <p class="mb-3 text-[11px] font-bold uppercase tracking-wider text-text-muted">Validades que bloqueiam a viagem</p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-input label="Exame toxicológico — data" type="date" wire:model="toxicologico_data" />
                        <x-input label="Exame toxicológico — validade" type="date"
                                 :required="$this->exigeToxicologico" wire:model="toxicologico_validade"
                                 :error="$errors->first('toxicologico_validade')" />
                        <x-input label="MOPP — validade" type="date" wire:model="mopp_validade"
                                 help="Movimentação e Operação de Produtos Perigosos." />
                        <x-input label="Curso de carga indivisível — validade" type="date"
                                 wire:model="curso_carga_indivisivel_validade" />
                    </div>
                </div>

                {{-- Remuneração --}}
                <div class="px-5 py-5" @class(['hidden' => $aba !== 'remuneracao'])>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <x-input label="Valor da diária" type="number" wire:model="valor_diaria" :error="$errors->first('valor_diaria')" />
                        <x-input label="Comissão (%)" type="number" wire:model="percentual_comissao" :error="$errors->first('percentual_comissao')" />
                        <x-input label="Valor por km" type="number" wire:model="valor_por_km" :error="$errors->first('valor_por_km')" />
                    </div>
                    <p class="mt-3 text-xs text-text-muted">
                        Os três modelos coexistem: diária, comissão sobre o frete e valor por km rodado. Deixe em branco o que não se aplica.
                    </p>
                </div>
            </x-card>
        </div>

        {{-- Painel lateral --}}
        <div class="flex flex-col gap-3">
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="info" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">O que o vínculo implica</h2>
                </div>
                <div class="px-5 py-4">
                    @if ($this->controlaJornada)
                        <div class="flex items-start gap-2.5 rounded-md bg-blue-50 px-3 py-3 dark:bg-blue-950/40">
                            <x-icon name="check" class="mt-px h-4 w-4 flex-shrink-0 text-info" />
                            <div class="text-xs leading-relaxed text-blue-800 dark:text-blue-300">
                                <b>CLT.</b> Jornada, escala e ponto se aplicam. Opera sob o RNTRC da empresa; o CIOT da viagem é registrado direto na ANTT.
                            </div>
                        </div>
                    @else
                        <div class="flex items-start gap-2.5 rounded-md bg-amber-50 px-3 py-3 dark:bg-amber-950/30">
                            <x-icon name="alert-triangle" class="mt-px h-4 w-4 flex-shrink-0 text-amber-700" />
                            <div class="text-xs leading-relaxed text-amber-900 dark:text-amber-300">
                                <b>{{ config('motoristas.vinculos.' . $vinculo) }}.</b> Sem jornada — registrar ponto de TAC
                                fabrica prova de vínculo empregatício (RN-12). @if ($this->ehTac) RNTRC próprio e CIOT pela instituição de pagamento. @endif
                            </div>
                        </div>
                    @endif
                </div>
            </x-card>

            <x-card padding="sm">
                <div class="flex items-start gap-2 text-xs leading-relaxed text-text-secondary">
                    <x-icon name="lock" class="mt-px h-3.5 w-3.5 flex-shrink-0 text-text-muted" />
                    <span>A empresa vem do usuário autenticado. Uma pessoa tem uma única ficha de motorista por empresa.</span>
                </div>
            </x-card>
        </div>
    </div>
</div>
