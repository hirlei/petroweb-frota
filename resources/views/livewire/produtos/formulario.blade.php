{{--
    Rotina 1020 — ficha da mercadoria.
    O bloco de produto perigoso só aparece quando marcado, e alimenta o grupo
    `peri` do MDF-e (não do CT-e rodoviário).
--}}
<div>
    <x-page-header
        :title="$mercadoria?->exists ? $mercadoria->descricao : 'Nova mercadoria'"
        :subtitle="$mercadoria?->exists ? 'Cadastros · rotina 1020' : 'Catálogo de cargas — físico, natureza, temperatura e produto perigoso'">
        <x-slot:actions>
            <x-button variant="ghost" size="sm" :href="route('produtos.index')" wire:navigate>Cancelar</x-button>
            <x-button variant="primary" size="sm" icon="check" wire:click="salvar" wire:loading.attr="disabled">Salvar</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-[1fr_320px]">
        <x-card padding="none" class="overflow-hidden">
            <div class="flex flex-wrap gap-1 border-b border-border px-4">
                @php
                    $abas = ['identificacao' => 'Identificação', 'fisico' => 'Físico e logístico', 'temperatura' => 'Temperatura'];
                    if ($eh_perigoso) $abas['perigoso'] = 'Produto perigoso';
                    $abas['controles'] = 'Controles';
                @endphp
                @foreach ($abas as $chave => $rotulo)
                    <button type="button" wire:click="$set('aba', '{{ $chave }}')"
                            class="-mb-px border-b-2 px-3.5 py-3 text-sm transition-colors
                                   {{ $aba === $chave ? 'border-primary font-semibold text-primary' : 'border-transparent font-medium text-text-secondary hover:text-text' }}">
                        {{ $rotulo }}
                    </button>
                @endforeach
            </div>

            {{-- Identificação --}}
            <div class="px-5 py-5" @class(['hidden' => $aba !== 'identificacao'])>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <x-input label="Código interno" required wire:model="codigo_interno" :error="$errors->first('codigo_interno')" />
                    <x-input class="sm:col-span-3" label="Descrição" required wire:model="descricao" :error="$errors->first('descricao')" />
                </div>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-input label="Descrição complementar" wire:model="descricao_complementar" />
                    <x-input label="Marca" wire:model="marca" />
                </div>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <x-input label="NCM" wire:model="ncm" :error="$errors->first('ncm')" placeholder="8 dígitos" />
                    <x-input label="CEST" wire:model="cest" :error="$errors->first('cest')" />
                    <x-input label="GTIN" wire:model="gtin" :error="$errors->first('gtin')" />
                    <x-input label="Unidade comercial" wire:model="unidade_comercial" placeholder="KG, UN, L…" />
                </div>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-select label="Natureza da carga" wire:model="natureza_carga_id"
                              help="Tabela operacional; traduzida para o tpCarga na emissão.">
                        <option value="">—</option>
                        @foreach ($this->naturezas as $n)
                            <option value="{{ $n->id }}">{{ $n->nome }}</option>
                        @endforeach
                    </x-select>
                    <x-select label="Carroceria recomendada" wire:model="carroceria_recomendada_id">
                        <option value="">—</option>
                        @foreach ($this->carrocerias as $c)
                            <option value="{{ $c->id }}">{{ $c->nome }}</option>
                        @endforeach
                    </x-select>
                </div>

                <div class="mt-5 flex flex-wrap gap-5">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model="ativo" class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30">
                        <span class="text-sm text-text">Ativo</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model.live="eh_perigoso" class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30">
                        <span class="text-sm text-text">É produto perigoso</span>
                    </label>
                </div>
            </div>

            {{-- Físico --}}
            <div class="px-5 py-5" @class(['hidden' => $aba !== 'fisico'])>
                <p class="mb-3 text-[11px] font-bold uppercase tracking-wider text-text-muted">Peso e dimensões</p>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <x-input label="Peso bruto (kg)" type="number" wire:model="peso_bruto_kg" />
                    <x-input label="Peso líquido (kg)" type="number" wire:model="peso_liquido_kg" />
                    <x-input label="Densidade (kg/m³)" type="number" wire:model="densidade_kg_m3" />
                    <x-input label="Fator cubagem (kg/m³)" type="number" wire:model="fator_cubagem_kg_m3"
                             help="Sem norma — parâmetro em cascata." />
                </div>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <x-input label="Comprimento (m)" type="number" wire:model.live="comprimento_m" />
                    <x-input label="Largura (m)" type="number" wire:model.live="largura_m" />
                    <x-input label="Altura (m)" type="number" wire:model.live="altura_m" />
                    <x-input label="Volume (m³)" type="number" wire:model="volume_m3"
                             :help="$this->volumePrevisto !== null ? 'Calculado: ' . $this->volumePrevisto . ' m³' : 'Em branco = calculado das dimensões.'" />
                </div>

                <div class="my-5 h-px bg-border"></div>
                <p class="mb-3 text-[11px] font-bold uppercase tracking-wider text-text-muted">Empilhamento e manuseio</p>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <x-input label="Máx. camadas" type="number" wire:model="empilhamento_max_camadas" />
                    <div class="flex items-end"><label class="flex items-center gap-2 pb-2.5"><input type="checkbox" wire:model="permite_empilhar" class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30"><span class="text-sm text-text">Permite empilhar</span></label></div>
                    <div class="flex items-end gap-5">
                        <label class="flex items-center gap-2 pb-2.5"><input type="checkbox" wire:model="fragil" class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30"><span class="text-sm text-text">Frágil</span></label>
                        <label class="flex items-center gap-2 pb-2.5"><input type="checkbox" wire:model="sentido_obrigatorio" class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30"><span class="text-sm text-text">Sentido obrigatório</span></label>
                    </div>
                </div>
            </div>

            {{-- Temperatura --}}
            <div class="px-5 py-5" @class(['hidden' => $aba !== 'temperatura'])>
                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model.live="exige_temp_controlada" class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30">
                    <span class="text-sm text-text">Exige temperatura controlada</span>
                </label>
                @if ($exige_temp_controlada)
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <x-input label="Temp. mínima (°C)" type="number" wire:model="temp_min_c" :error="$errors->first('temp_min_c')" />
                        <x-input label="Temp. máxima (°C)" type="number" wire:model="temp_max_c" :error="$errors->first('temp_max_c')" />
                        <div class="flex items-end"><label class="flex items-center gap-2 pb-2.5"><input type="checkbox" wire:model="exige_registro_continuo" class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30"><span class="text-sm text-text">Registro contínuo</span></label></div>
                    </div>
                @else
                    <p class="mt-3 text-sm text-text-muted">Carga seca — sem exigência de temperatura.</p>
                @endif
            </div>

            {{-- Perigoso --}}
            @if ($eh_perigoso)
                <div class="px-5 py-5" @class(['hidden' => $aba !== 'perigoso'])>
                    <div class="mb-4 flex items-start gap-2 rounded-r-md border-l-[3px] border-danger bg-red-50 px-3 py-2.5
                                text-xs text-red-800 dark:bg-red-950/40 dark:text-red-300">
                        <x-icon name="alert-triangle" class="mt-px h-3.5 w-3.5 flex-shrink-0" />
                        <span>Estes campos alimentam o grupo <span class="font-mono">peri</span> do MDF-e — o CT-e rodoviário não tem grupo de produto perigoso.</span>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <x-input label="Número ONU" required wire:model="num_onu" :error="$errors->first('num_onu')" placeholder="4 dígitos" />
                        <x-input class="sm:col-span-3" label="Nome apropriado para embarque" wire:model="nome_embarque" />
                        <x-input label="Classe de risco" required wire:model="classe_risco" :error="$errors->first('classe_risco')" placeholder="Ex.: 3" />
                        <x-input label="Risco subsidiário" wire:model="risco_subsidiario" />
                        <x-input label="Número de risco" wire:model="num_risco" />
                        <x-input label="Grupo de embalagem" wire:model="grupo_embalagem" placeholder="I, II ou III" />
                        <x-input label="Ponto de fulgor (°C)" type="number" wire:model="ponto_fulgor_c"
                                 help="Da FISPQ — não vai a DF-e; deriva a embalagem da Classe 3." />
                    </div>
                    <div class="mt-4 flex flex-wrap gap-5">
                        <label class="flex items-center gap-2"><input type="checkbox" wire:model="risco_ambiental" class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30"><span class="text-sm text-text">Poluente / risco ambiental</span></label>
                        <label class="flex items-center gap-2"><input type="checkbox" wire:model="exige_mopp" class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30"><span class="text-sm text-text">Exige motorista MOPP</span></label>
                        <label class="flex items-center gap-2"><input type="checkbox" wire:model="exige_kit_9735" class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30"><span class="text-sm text-text">Exige kit ABNT 9735</span></label>
                    </div>
                </div>
            @endif

            {{-- Controles --}}
            <div class="px-5 py-5" @class(['hidden' => $aba !== 'controles'])>
                <p class="mb-3 text-[11px] font-bold uppercase tracking-wider text-text-muted">Controles setoriais</p>
                <div class="flex flex-col gap-3">
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="pce_exercito" class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30"><span class="text-sm text-text">Produto controlado pelo Exército (PCE)</span></label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="controlado_pf" class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30"><span class="text-sm text-text">Controlado pela Polícia Federal</span></label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="exige_mapa_siproquim" class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30"><span class="text-sm text-text">Exige mapa SIPROQUIM</span></label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="origem_animal" class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30"><span class="text-sm text-text">Produto de origem animal (SIF/DIPOA)</span></label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model.live="eh_agrotoxico" class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30"><span class="text-sm text-text">É agrotóxico</span></label>
                    @if ($eh_agrotoxico)
                        <x-input class="max-w-xs" label="Registro MAPA" wire:model="registro_mapa" />
                    @endif
                </div>
            </div>
        </x-card>

        <div class="flex flex-col gap-3">
            <x-card padding="sm">
                <p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-text-muted">Resumo</p>
                <x-linha-ficha rotulo="Volume previsto" :valor="$this->volumePrevisto !== null ? $this->volumePrevisto . ' m³' : '—'" />
                <x-linha-ficha rotulo="Perigoso" :valor="$eh_perigoso ? 'Sim' : 'Não'" :mono="false" />
                <x-linha-ficha rotulo="Refrigerada" :valor="$exige_temp_controlada ? 'Sim' : 'Não'" :mono="false" />
            </x-card>
            <x-card padding="sm">
                <div class="flex items-start gap-2 text-xs leading-relaxed text-text-secondary">
                    <x-icon name="info" class="mt-px h-3.5 w-3.5 flex-shrink-0 text-text-muted" />
                    <span>O fator de cubagem não tem norma: é parâmetro em cascata (tabela de frete → cliente → produto → empresa). Aqui fica o nível do produto.</span>
                </div>
            </x-card>
        </div>
    </div>
</div>
