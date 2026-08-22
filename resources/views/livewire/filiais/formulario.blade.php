{{--
    Rotina 9010 — ficha da filial (emitente fiscal).
    CNPJ, IE, CRT e ambiente SEFAZ são da filial. O ambiente é dela, não da
    aplicação — uma pode homologar enquanto outra já produz.
--}}
<div>
    <x-page-header
        :title="$filial?->exists ? ($filial->nome_fantasia ?? $filial->razao_social) : 'Nova filial'"
        :subtitle="$filial?->exists ? 'Configurações · rotina 9010' : 'Cada filial é um emitente fiscal independente'">
        <x-slot:actions>
            <x-button variant="ghost" size="sm" :href="route('filiais.index')" wire:navigate>Cancelar</x-button>
            <x-button variant="primary" size="sm" icon="check" wire:click="salvar" wire:loading.attr="disabled">Salvar</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-[1fr_372px]">

        <div class="flex flex-col gap-4">
            <x-card padding="none" class="overflow-hidden">
                <div class="flex gap-1 border-b border-border px-4">
                    @foreach (['identificacao' => 'Identificação fiscal', 'endereco' => 'Endereço', 'sefaz' => 'SEFAZ'] as $chave => $rotulo)
                        <button type="button" wire:click="$set('aba', '{{ $chave }}')"
                                class="-mb-px border-b-2 px-3.5 py-3 text-sm transition-colors
                                       {{ $aba === $chave ? 'border-primary font-semibold text-primary' : 'border-transparent font-medium text-text-secondary hover:text-text' }}">
                            {{ $rotulo }}
                        </button>
                    @endforeach
                </div>

                {{-- Identificação fiscal --}}
                <div class="px-5 py-5" @class(['hidden' => $aba !== 'identificacao'])>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <x-input label="Código" required wire:model="codigo" :error="$errors->first('codigo')"
                                 placeholder="FIL-01" />
                        <x-input class="sm:col-span-3" label="Razão social" required wire:model="razao_social"
                                 :error="$errors->first('razao_social')" />
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <x-input class="sm:col-span-2" label="Nome fantasia" wire:model="nome_fantasia" />
                        <x-input label="CNPJ" required wire:model.blur="cnpj" :error="$errors->first('cnpj')"
                                 placeholder="00.000.000/0000-00" />
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <x-input label="Inscrição estadual" wire:model="ie" :error="$errors->first('ie')" />
                        <x-input label="Inscrição municipal" wire:model="im" />
                        <x-select label="Regime tributário (CRT)" required wire:model="crt" :error="$errors->first('crt')"
                                  help="Regime normal exige os grupos de IBS/CBS no CT-e.">
                            <option value="1">1 — Simples Nacional</option>
                            <option value="2">2 — Simples, excesso de sublimite</option>
                            <option value="3">3 — Regime normal</option>
                        </x-select>
                    </div>

                    <div class="my-5 h-px bg-border"></div>
                    <p class="mb-3 text-[11px] font-bold uppercase tracking-wider text-text-muted">Como transportador</p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <x-input label="RNTRC" wire:model="rntrc" :error="$errors->first('rntrc')" />
                        <x-input label="Validade do RNTRC" type="date" wire:model="rntrc_validade" />
                        <x-select label="Tipo de transportador" wire:model="tp_transp">
                            <option value="1">1 — ETC (empresa)</option>
                            <option value="2">2 — TAC (autônomo)</option>
                            <option value="3">3 — CTC (cooperativa)</option>
                        </x-select>
                    </div>

                    <label class="mt-4 flex items-center gap-2">
                        <input type="checkbox" wire:model="matriz"
                               class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30">
                        <span class="text-sm text-text">É a matriz da empresa</span>
                    </label>
                    <p class="mt-1 text-xs text-text-muted">Só uma matriz por empresa — marcar esta desmarca a anterior automaticamente.</p>
                </div>

                {{-- Endereço --}}
                <div class="px-5 py-5" @class(['hidden' => $aba !== 'endereco'])>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-6">
                        <x-input class="sm:col-span-4" label="Logradouro" wire:model="logradouro" />
                        <x-input label="Número" wire:model="numero" />
                        <x-input label="CEP" wire:model="cep" :error="$errors->first('cep')" />
                        <x-input class="sm:col-span-2" label="Complemento" wire:model="complemento" />
                        <x-input class="sm:col-span-2" label="Bairro" wire:model="bairro" />
                        <x-select class="sm:col-span-2" label="Município" required wire:model="municipio_id"
                                  :error="$errors->first('municipio_id')" help="Código IBGE — exigido no CT-e.">
                            <option value="">Selecione…</option>
                            @foreach ($this->municipios as $municipio)
                                <option value="{{ $municipio->id }}">{{ $municipio->nome }}/{{ $municipio->uf }}</option>
                            @endforeach
                        </x-select>
                        <x-input class="sm:col-span-3" label="Telefone" wire:model="telefone" />
                        <x-input class="sm:col-span-3" label="E-mail" type="email" wire:model="email"
                                 :error="$errors->first('email')" />
                    </div>
                </div>

                {{-- SEFAZ --}}
                <div class="px-5 py-5" @class(['hidden' => $aba !== 'sefaz'])>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-select label="Ambiente SEFAZ" required wire:model="ambiente_sefaz" :error="$errors->first('ambiente_sefaz')"
                                  help="Homologação não tem valor fiscal; produção emite pra valer.">
                            <option value="2">Homologação</option>
                            <option value="1">Produção</option>
                        </x-select>
                        <x-input label="UF autorizadora" required wire:model="uf_autorizadora"
                                 :error="$errors->first('uf_autorizadora')" placeholder="BA" />
                    </div>

                    <label class="mt-4 flex items-center gap-2">
                        <input type="checkbox" wire:model="contingencia_automatica"
                               class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30">
                        <span class="text-sm text-text">Entrar em contingência automaticamente quando a SEFAZ cair</span>
                    </label>

                    <label class="mt-3 flex items-center gap-2">
                        <input type="checkbox" wire:model="ativa"
                               class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30">
                        <span class="text-sm text-text">Filial ativa</span>
                    </label>

                    <div class="mt-4 flex items-start gap-2 rounded-r-md border-l-[3px] border-warning bg-amber-50 px-3 py-2.5
                                text-xs text-amber-900 dark:bg-amber-950/40 dark:text-amber-300">
                        <x-icon name="alert-triangle" class="mt-px h-3.5 w-3.5 flex-shrink-0" />
                        <span>A filial só emite com certificado A1 vigente vinculado. O certificado é gerido na rotina 4040.</span>
                    </div>
                </div>
            </x-card>
        </div>

        <div class="flex flex-col gap-3">
            <x-card padding="sm">
                <p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-text-muted">Por que filial é emitente</p>
                <p class="text-xs leading-relaxed text-text-secondary">
                    Cada estabelecimento tem CNPJ, IE, certificado e séries próprias. O ambiente SEFAZ é atributo da filial:
                    dá para homologar uma nova enquanto as outras seguem emitindo com valor fiscal.
                </p>
            </x-card>
            <x-card padding="sm">
                <div class="flex items-start gap-2 text-xs leading-relaxed text-text-secondary">
                    <x-icon name="lock" class="mt-px h-3.5 w-3.5 flex-shrink-0 text-text-muted" />
                    <span>A empresa desta filial vem do contexto do usuário logado — não há campo de empresa aqui.</span>
                </div>
            </x-card>
        </div>
    </div>
</div>
