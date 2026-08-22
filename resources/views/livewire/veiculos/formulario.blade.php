{{--
    Rotina 2010 — ficha do veículo.
    Uma unidade (não a combinação). O bloco de proprietário só aparece para
    veículo de terceiro; o tipo de rodado só para tração. `categCombVeic`
    deriva dos eixos, não é campo.
--}}
<div>
    <x-page-header
        :title="$veiculo?->exists ? $veiculo->placaFormatada() : 'Novo veículo'"
        :subtitle="$veiculo?->exists
            ? 'Frota · rotina 2010'
            : 'Cavalo, reboque, semirreboque ou dolly — cada unidade é um cadastro'">
        <x-slot:actions>
            <x-button variant="ghost" size="sm" :href="route('veiculos.index')" wire:navigate>Cancelar</x-button>
            <x-button variant="primary" size="sm" icon="check" wire:click="salvar" wire:loading.attr="disabled">
                Salvar
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-[1fr_372px]">

        <div class="flex flex-col gap-4">
            <x-card padding="none" class="overflow-hidden">
                {{-- Abas --}}
                <div class="flex gap-1 border-b border-border px-4">
                    @foreach ([
                        'identificacao' => 'Identificação',
                        'tecnica' => 'Ficha técnica',
                        'propriedade' => 'Propriedade',
                        'documentos' => 'Documentos',
                    ] as $chave => $rotulo)
                        <button type="button" wire:click="$set('aba', '{{ $chave }}')"
                                class="-mb-px border-b-2 px-3.5 py-3 text-sm transition-colors
                                       {{ $aba === $chave
                                           ? 'border-primary font-semibold text-primary'
                                           : 'border-transparent font-medium text-text-secondary hover:text-text' }}">
                            {{ $rotulo }}
                            @if ($chave === 'documentos' && count($documentos)) <span class="text-text-muted">({{ count($documentos) }})</span> @endif
                        </button>
                    @endforeach
                </div>

                {{-- Identificação --}}
                <div class="px-5 py-5" @class(['hidden' => $aba !== 'identificacao'])>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <x-input label="Placa" required wire:model.blur="placa"
                                 :error="$errors->first('placa')" placeholder="ABC1D23"
                                 help="Mercosul ou padrão antigo. É texto, nunca número." />
                        <x-select label="Tipo" required wire:model.live="tipo" :error="$errors->first('tipo')">
                            @foreach (config('veiculos.tipos') as $codigo => $rotulo)
                                <option value="{{ $codigo }}">{{ $rotulo }}</option>
                            @endforeach
                        </x-select>
                        <x-input label="RENAVAM" wire:model="renavam" :error="$errors->first('renavam')" />
                        <x-input label="Chassi" wire:model="chassi" :error="$errors->first('chassi')" />
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <x-input label="Marca" wire:model="marca" :error="$errors->first('marca')" />
                        <x-input class="sm:col-span-2" label="Modelo" wire:model="modelo" :error="$errors->first('modelo')" />
                        <x-input label="Cor" wire:model="cor" />
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <x-input label="Ano de fabricação" type="number" wire:model="ano_fabricacao"
                                 :error="$errors->first('ano_fabricacao')" />
                        <x-input label="Ano do modelo" type="number" wire:model="ano_modelo"
                                 :error="$errors->first('ano_modelo')" />
                        <x-input label="UF de licenciamento" required wire:model="uf_licenciamento"
                                 :error="$errors->first('uf_licenciamento')" placeholder="BA" />
                        <x-select label="Município de licenciamento" wire:model="municipio_licenciamento_id">
                            <option value="">Selecione…</option>
                            @foreach ($this->municipios as $municipio)
                                <option value="{{ $municipio->id }}">{{ $municipio->nome }}/{{ $municipio->uf }}</option>
                            @endforeach
                        </x-select>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-select label="Filial" wire:model="filial_id"
                                  help="Estabelecimento emitente ao qual o veículo pertence.">
                            <option value="">Nenhuma</option>
                            @foreach ($this->filiais as $filial)
                                <option value="{{ $filial->id }}">{{ $filial->nome_fantasia ?? $filial->razao_social }}</option>
                            @endforeach
                        </x-select>
                        <x-select label="Situação" required wire:model="status" :error="$errors->first('status')">
                            @foreach (config('veiculos.status') as $codigo => $rotulo)
                                <option value="{{ $codigo }}">{{ $rotulo }}</option>
                            @endforeach
                        </x-select>
                    </div>
                </div>

                {{-- Ficha técnica --}}
                <div class="px-5 py-5" @class(['hidden' => $aba !== 'tecnica'])>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <x-input label="Eixos" type="number" required wire:model.live="eixos"
                                 :error="$errors->first('eixos')"
                                 help="Base do categCombVeic." />
                        @if ($this->exigeRodado)
                            <x-select label="Tipo de rodado (tpRod)" required wire:model="tp_rod"
                                      :error="$errors->first('tp_rod')">
                                <option value="">Selecione…</option>
                                @foreach (config('veiculos.rodados') as $codigo => $rotulo)
                                    <option value="{{ $codigo }}">{{ $codigo }} — {{ $rotulo }}</option>
                                @endforeach
                            </x-select>
                        @endif
                        <x-select class="sm:col-span-2" label="Carroceria" wire:model="carroceria_id"
                                  help="Tabela de negócio; o tpCar fiscal é traduzido na emissão.">
                            <option value="">Selecione…</option>
                            @foreach ($this->carrocerias as $carroceria)
                                <option value="{{ $carroceria->id }}">{{ $carroceria->nome }}</option>
                            @endforeach
                        </x-select>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-select label="Configuração CVC" wire:model="cvc_configuracao_id"
                                  help="Bitrem, rodotrem, vanderleia — com os limites legais.">
                            <option value="">Não é combinação predefinida</option>
                            @foreach ($this->configuracoesCvc as $cvc)
                                <option value="{{ $cvc->id }}">{{ $cvc->nome_popular }} ({{ $cvc->eixos }} eixos)</option>
                            @endforeach
                        </x-select>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2 pb-2.5">
                                <input type="checkbox" wire:model="exige_aet"
                                       class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30">
                                <span class="text-sm text-text">Exige AET (Autorização Especial de Trânsito)</span>
                            </label>
                        </div>
                    </div>

                    <div class="my-5 h-px bg-border"></div>
                    <p class="mb-3 text-[11px] font-bold uppercase tracking-wider text-text-muted">Pesos (kg)</p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <x-input label="Tara" type="number" required wire:model="tara_kg" :error="$errors->first('tara_kg')" />
                        <x-input label="PBT" type="number" wire:model="pbt_kg" :error="$errors->first('pbt_kg')" />
                        <x-input label="PBTC" type="number" wire:model="pbtc_kg" :error="$errors->first('pbtc_kg')" />
                        <x-input label="Capacidade" type="number" wire:model="capacidade_kg" :error="$errors->first('capacidade_kg')" />
                    </div>

                    <div class="mt-3 flex items-center justify-between rounded-md bg-secondary-soft px-3 py-2.5">
                        <span class="text-sm font-semibold text-green-800">Carga útil (PBTC − tara)</span>
                        <span class="font-mono text-sm font-semibold text-green-800">
                            {{ $this->cargaUtil !== null ? number_format($this->cargaUtil, 0, ',', '.') . ' kg' : '—' }}
                        </span>
                    </div>

                    <div class="my-5 h-px bg-border"></div>
                    <p class="mb-3 text-[11px] font-bold uppercase tracking-wider text-text-muted">Dimensões e volume</p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <x-input label="Comprimento (m)" type="number" wire:model="comprimento_m" />
                        <x-input label="Largura (m)" type="number" wire:model="largura_m" />
                        <x-input label="Altura (m)" type="number" wire:model="altura_m" />
                        <x-input label="Capacidade (m³)" type="number" wire:model="capacidade_m3" />
                    </div>

                    <div class="my-5 h-px bg-border"></div>
                    <p class="mb-3 text-[11px] font-bold uppercase tracking-wider text-text-muted">Combustível e custo</p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <x-input label="Combustível" wire:model="combustivel" placeholder="Diesel S10" />
                        <x-input label="Tanque (L)" type="number" wire:model="capacidade_tanque_l" />
                        <x-input label="Média ref. (km/L)" type="number" wire:model="media_referencia_kml" />
                        <x-input label="Custo/km alvo" type="number" wire:model="custo_km_alvo" />
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <x-input label="Odômetro atual" type="number" wire:model="odometro_atual" />
                        <x-input label="Horímetro atual" type="number" wire:model="horimetro_atual" />
                        <x-input class="sm:col-span-2" label="ID do rastreador" wire:model="rastreador_id" />
                    </div>
                </div>

                {{-- Propriedade --}}
                <div class="px-5 py-5" @class(['hidden' => $aba !== 'propriedade'])>
                    <x-select label="Propriedade" required wire:model.live="propriedade" :error="$errors->first('propriedade')">
                        @foreach (config('veiculos.propriedades') as $codigo => $rotulo)
                            <option value="{{ $codigo }}">{{ $rotulo }}</option>
                        @endforeach
                    </x-select>

                    @if ($this->ehTerceiro)
                        <div class="mt-4 rounded-lg border border-warning/40 bg-amber-50/50 p-4 dark:bg-amber-950/20">
                            <div class="mb-3 flex items-center gap-2">
                                <x-icon name="alert-triangle" class="h-4 w-4 text-amber-700" />
                                <p class="text-[11px] font-bold uppercase tracking-wider text-amber-700">Veículo de terceiro</p>
                            </div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <x-select class="sm:col-span-3" label="Proprietário" required
                                          wire:model.live="proprietario_id" :error="$errors->first('proprietario_id')"
                                          help="Só aparecem pessoas com o papel de proprietário.">
                                    <option value="">Selecione…</option>
                                    @foreach ($this->proprietarios as $pessoa)
                                        <option value="{{ $pessoa->id }}">{{ $pessoa->razao_social }}</option>
                                    @endforeach
                                </x-select>
                                <x-input label="RNTRC do proprietário" wire:model="proprietario_rntrc"
                                         :error="$errors->first('proprietario_rntrc')" />
                                <x-select class="sm:col-span-2" label="Tipo de transportador" wire:model="proprietario_tp_transp"
                                          :error="$errors->first('proprietario_tp_transp')">
                                    <option value="">Não informado</option>
                                    <option value="1">1 — ETC (empresa)</option>
                                    <option value="2">2 — TAC (autônomo)</option>
                                    <option value="3">3 — CTC (cooperativa)</option>
                                </x-select>
                            </div>
                            <p class="mt-3 text-xs leading-relaxed text-amber-900 dark:text-amber-300">
                                É o RNTRC e o tipo de transportador <b>do proprietário</b> que vão ao MDF-e — nunca o da
                                transportadora. Errar isso é rejeição e, no caso do TAC, dispara a obrigação de vale-pedágio.
                            </p>
                        </div>
                    @else
                        <div class="mt-3 flex items-start gap-2 rounded-r-md border-l-[3px] border-info bg-blue-50 px-3 py-2.5
                                    text-xs text-blue-800 dark:bg-blue-950/40 dark:text-blue-300">
                            <x-icon name="info" class="mt-px h-3.5 w-3.5 flex-shrink-0" />
                            <span>Veículo próprio opera sob o RNTRC da transportadora. Os campos de proprietário aparecem só para terceiro ou arrendado.</span>
                        </div>
                    @endif
                </div>

                {{-- Documentos --}}
                <div class="px-5 py-5" @class(['hidden' => $aba !== 'documentos'])>
                    @forelse ($documentos as $i => $documento)
                        <div wire:key="doc-{{ $i }}" class="mb-3 rounded-lg border border-border p-4">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6">
                                <x-select class="sm:col-span-2" label="Tipo" wire:model="documentos.{{ $i }}.tipo">
                                    <option value="crlv">CRLV</option>
                                    <option value="civ">CIV</option>
                                    <option value="cipp">CIPP</option>
                                    <option value="tacografo">Tacógrafo</option>
                                    <option value="cronotacografo">Cronotacógrafo</option>
                                    <option value="seguro">Seguro</option>
                                    <option value="aet">AET</option>
                                    <option value="outro">Outro</option>
                                </x-select>
                                <x-input label="Número" wire:model="documentos.{{ $i }}.numero" />
                                <x-input label="Emissão" type="date" wire:model="documentos.{{ $i }}.emissao" />
                                <x-input label="Vencimento" type="date" required
                                         wire:model="documentos.{{ $i }}.vencimento"
                                         :error="$errors->first('documentos.' . $i . '.vencimento')" />
                                <x-input label="Valor" type="number" wire:model="documentos.{{ $i }}.valor" />
                            </div>
                            <div class="mt-3 flex items-center gap-3">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" wire:model="documentos.{{ $i }}.bloqueia_operacao"
                                           class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30">
                                    <span class="text-sm text-text">Vencido bloqueia a operação</span>
                                </label>
                                <span class="flex-1"></span>
                                <button type="button" wire:click="removerDocumento({{ $i }})"
                                        class="rounded p-1 text-danger hover:bg-red-50" title="Remover">
                                    <x-icon name="trash-2" class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    @empty
                        <x-empty-state icon="calendar" title="Nenhum documento"
                                       description="CRLV, CIV, CIPP, seguro e AET com vencimento alimentam o painel 2040." />
                    @endforelse

                    <x-button variant="outline" size="sm" icon="plus" wire:click="adicionarDocumento">
                        Adicionar documento
                    </x-button>
                </div>
            </x-card>
        </div>

        {{-- Painel de conformidade --}}
        <div class="flex flex-col gap-3">
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="shield-check" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Pronto para o MDF-e?</h2>
                </div>
                <div class="px-5 py-4">
                    @if ($this->pendenciasFiscais === [])
                        <div class="flex items-start gap-2.5 rounded-md bg-secondary-soft px-3 py-3">
                            <x-icon name="check" class="mt-px h-4 w-4 flex-shrink-0 text-green-700" />
                            <div>
                                <div class="text-sm font-semibold text-green-800">Sem pendências</div>
                                <div class="mt-0.5 text-xs text-green-700">Este veículo pode compor um MDF-e.</div>
                            </div>
                        </div>
                    @else
                        <p class="mb-2.5 text-xs leading-relaxed text-text-secondary">
                            O cadastro pode ser salvo assim. Estas pendências bloqueiam apenas a <b>emissão</b>:
                        </p>
                        @foreach ($this->pendenciasFiscais as $pendencia)
                            <div class="mb-1.5 flex items-start gap-2 rounded-r-md border-l-[3px] border-warning bg-amber-50 px-3 py-2
                                        text-xs text-amber-900 dark:bg-amber-950/40 dark:text-amber-300">
                                <x-icon name="alert-triangle" class="mt-px h-3.5 w-3.5 flex-shrink-0" />
                                <span>{{ $pendencia }}</span>
                            </div>
                        @endforeach
                    @endif
                </div>
            </x-card>

            <x-card padding="sm">
                <p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-text-muted">Categoria de combinação</p>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-text-secondary">Derivada de {{ (int) $eixos }} eixos</span>
                    <x-badge variant="primary" class="text-[11px]">{{ $this->categoriaCombinacao }}</x-badge>
                </div>
                <p class="mt-2 text-xs leading-relaxed text-text-muted">
                    A sequência do <span class="font-mono">categCombVeic</span> não é contínua — os códigos 03, 05 e 09 não
                    existem. Por isso ela é derivada aqui, nunca digitada.
                </p>
            </x-card>

            <x-card padding="sm">
                <div class="flex items-start gap-2 text-xs leading-relaxed text-text-secondary">
                    <x-icon name="lock" class="mt-px h-3.5 w-3.5 flex-shrink-0 text-text-muted" />
                    <span>A empresa deste veículo vem do usuário autenticado, nunca do formulário — não existe campo de empresa aqui.</span>
                </div>
            </x-card>
        </div>
    </div>
</div>
