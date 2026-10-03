{{--
    Rotina 1010 — ficha da pessoa.
    Um formulário para os sete papéis. Os blocos condicionais aparecem pelo
    papel marcado; não existe "tela de cliente" e "tela de motorista".
--}}
<div>
    <x-page-header
        :title="$pessoa?->exists ? $pessoa->razao_social : 'Nova pessoa'"
        :subtitle="$pessoa?->exists
            ? 'Cadastro unificado · rotina 1010'
            : 'O mesmo cadastro serve para cliente, fornecedor, motorista, proprietário, oficina, seguradora e posto'">
        <x-slot:actions>
            <x-button variant="ghost" size="sm" :href="route('pessoas.index')" wire:navigate>Cancelar</x-button>
            <x-button variant="primary" size="sm" icon="check" wire:click="salvar" wire:loading.attr="disabled">
                Salvar
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-[1fr_372px]">

        <div class="flex flex-col gap-4">
            {{-- ── Papéis ───────────────────────────────────── --}}
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="users" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Papéis</h2>
                    <span class="text-sm text-text-muted">({{ count($papeis) }})</span>
                </div>
                <div class="px-5 py-4">
                    <div class="flex flex-wrap gap-2">
                        @foreach (config('papeis.rotulos') as $codigo => $rotulo)
                            @php $marcado = in_array($codigo, $papeis, true); @endphp
                            <button type="button" wire:click="alternarPapel('{{ $codigo }}')"
                                    title="{{ config('papeis.descricoes.' . $codigo) }}"
                                    class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium transition-colors
                                           {{ $marcado
                                               ? 'border-primary bg-primary-soft text-amber-700'
                                               : 'border-border bg-surface text-text-secondary hover:bg-surface-elevated' }}">
                                <x-icon :name="$marcado ? 'check' : 'plus'" class="h-3.5 w-3.5" />
                                {{ $rotulo }}
                            </button>
                        @endforeach
                    </div>
                    @error('papeis.*')
                        <p class="mt-2 text-xs text-danger">{{ $message }}</p>
                    @enderror
                </div>
            </x-card>

            {{-- ── Abas ─────────────────────────────────────── --}}
            <x-card padding="none" class="overflow-hidden">
                <div class="flex gap-1 border-b border-border px-4">
                    @foreach (['identificacao' => 'Identificação', 'enderecos' => 'Endereços', 'contatos' => 'Contatos'] as $chave => $rotulo)
                        <button type="button" wire:click="$set('aba', '{{ $chave }}')"
                                class="-mb-px border-b-2 px-3.5 py-3 text-sm transition-colors
                                       {{ $aba === $chave
                                           ? 'border-primary font-semibold text-primary'
                                           : 'border-transparent font-medium text-text-secondary hover:text-text' }}">
                            {{ $rotulo }}
                            @if ($chave === 'enderecos' && count($enderecos)) <span class="text-text-muted">({{ count($enderecos) }})</span> @endif
                            @if ($chave === 'contatos' && count($contatos)) <span class="text-text-muted">({{ count($contatos) }})</span> @endif
                        </button>
                    @endforeach
                </div>

                {{-- Identificação --}}
                <div class="px-5 py-5" @class(['hidden' => $aba !== 'identificacao'])>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <x-select label="Tipo" required wire:model.live="tipo" :error="$errors->first('tipo')">
                            <option value="J">Jurídica</option>
                            <option value="F">Física</option>
                            <option value="E">Estrangeiro</option>
                        </x-select>

                        <x-input class="sm:col-span-2" :label="$tipo === 'F' ? 'CPF' : 'CNPJ'"
                                 :required="$tipo !== 'E'" wire:model.blur="documento"
                                 :error="$errors->first('documento')"
                                 :help="$tipo !== 'F' ? 'CNPJ pode conter letras a partir de julho de 2026 — é texto, nunca número.' : null"
                                 placeholder="{{ $tipo === 'F' ? '000.000.000-00' : '00.000.000/0000-00' }}" />

                        <x-input :label="$tipo === 'E' ? 'Identificador no exterior' : 'Inscrição municipal'"
                                 wire:model="{{ $tipo === 'E' ? 'documento_estrangeiro' : 'im' }}" />
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-input :label="$tipo === 'F' ? 'Nome completo' : 'Razão social'" required
                                 wire:model="razao_social" :error="$errors->first('razao_social')" />
                        <x-input label="Nome fantasia" wire:model="nome_fantasia"
                                 :error="$errors->first('nome_fantasia')" />
                    </div>

                    <div class="my-5 h-px bg-border"></div>

                    <p class="mb-3 text-[11px] font-bold uppercase tracking-wider text-text-muted">Situação fiscal</p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <x-select label="Indicador de IE" required wire:model.live="ie_indicador"
                                  :error="$errors->first('ie_indicador')"
                                  help="Vira o indIEDest do CT-e. É a rejeição de cadastro mais comum.">
                            <option value="1">1 — Contribuinte</option>
                            <option value="2">2 — Isento</option>
                            <option value="9">9 — Não contribuinte</option>
                        </x-select>

                        <x-input label="Inscrição estadual" :required="$ie_indicador === '1'"
                                 wire:model.blur="ie" :error="$errors->first('ie')" />

                        <x-input label="SUFRAMA" wire:model="suframa" :error="$errors->first('suframa')"
                                 help="Exigida pela NT 2026.002 em área de livre comércio." />
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <x-input label="CNAE principal" wire:model="cnae" :error="$errors->first('cnae')" />
                        <x-input label="E-mail" type="email" wire:model="email" :error="$errors->first('email')" />
                        <x-input label="Telefone" wire:model="telefone" :error="$errors->first('telefone')" />
                    </div>

                    {{-- Bloco que só existe para quem transporta --}}
                    @if ($this->exigeRntrc)
                        <div class="my-5 h-px bg-border"></div>
                        <div class="mb-3 flex items-center gap-2">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-text-muted">Como transportador</p>
                            <x-badge variant="primary" class="text-[10px]">Exigido pelo papel marcado</x-badge>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <x-input label="RNTRC" required wire:model="rntrc" :error="$errors->first('rntrc')"
                                     help="Vai ao MDF-e quando o veículo é de terceiro." />
                            <x-input label="Validade do RNTRC" type="date" wire:model="rntrc_validade"
                                     :error="$errors->first('rntrc_validade')" />
                            <x-select label="Tipo de transportador" wire:model="tp_transp"
                                      :error="$errors->first('tp_transp')">
                                <option value="">Não se aplica</option>
                                <option value="1">1 — ETC (empresa)</option>
                                <option value="2">2 — TAC (autônomo)</option>
                                <option value="3">3 — CTC (cooperativa)</option>
                            </x-select>
                        </div>
                    @endif

                    @if (in_array('cliente', $papeis, true))
                        <div class="my-5 h-px bg-border"></div>
                        <div class="mb-3 flex items-center gap-2">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-text-muted">Faturamento</p>
                            <x-badge variant="info" class="text-[10px]">Usado na Fatura (5010), no recebimento (5020) e no Contas a pagar (5030)</x-badge>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <x-input label="Prazo de faturamento (dias)" wire:model="prazo_faturamento" placeholder="Ex.: 28 ou 28/56" mono
                                     :error="$errors->first('prazo_faturamento')" help="0 é à vista. Separe as parcelas com barra." />
                            <x-input label="Multa por atraso (%)" type="number" step="0.01" min="0" wire:model="multa_percentual"
                                     :error="$errors->first('multa_percentual')" help="Uma vez, sobre o valor em aberto." />
                            <x-input label="Juros ao mês (%)" type="number" step="0.01" min="0" wire:model="juros_mes_percentual"
                                     :error="$errors->first('juros_mes_percentual')" help="Simples, proporcional aos dias." />
                        </div>
                    @endif

                    @if (! in_array('cliente', $papeis, true) && array_intersect(['fornecedor', 'oficina', 'posto', 'seguradora', 'proprietario'], $papeis))
                        <div class="my-5 h-px bg-border"></div>
                        <div class="mb-3 flex items-center gap-2">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-text-muted">Pagamento</p>
                            <x-badge variant="info" class="text-[10px]">Usado no Contas a pagar (5030)</x-badge>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <x-input label="Prazo de pagamento (dias)" wire:model="prazo_faturamento" placeholder="Ex.: 15 ou 30/60" mono
                                     :error="$errors->first('prazo_faturamento')" help="Vencimento das contas lançadas. Sem prazo, 30 dias." />
                        </div>
                    @endif

                    <div class="my-5 h-px bg-border"></div>
                    <x-input label="Observações" wire:model="observacoes" />

                    <label class="mt-4 flex items-center gap-2">
                        <input type="checkbox" wire:model="ativo"
                               class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30">
                        <span class="text-sm text-text">Cadastro ativo</span>
                    </label>
                </div>

                {{-- Endereços --}}
                <div class="px-5 py-5" @class(['hidden' => $aba !== 'enderecos'])>
                    @foreach ($enderecos as $i => $endereco)
                        <div wire:key="endereco-{{ $i }}"
                             class="mb-3 rounded-lg border p-4 {{ $endereco['principal'] ? 'border-primary bg-primary-soft/30' : 'border-border' }}">
                            <div class="mb-3 flex items-center gap-2">
                                <x-select wire:model="enderecos.{{ $i }}.tipo" class="w-40">
                                    <option value="principal">Principal</option>
                                    <option value="coleta">Coleta</option>
                                    <option value="entrega">Entrega</option>
                                    <option value="cobranca">Cobrança</option>
                                </x-select>
                                <span class="flex-1"></span>
                                <button type="button" wire:click="marcarPrincipal({{ $i }})"
                                        class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium
                                               {{ $endereco['principal'] ? 'bg-primary text-white' : 'text-text-secondary hover:bg-surface-elevated' }}">
                                    <x-icon name="star" class="h-3.5 w-3.5" />
                                    {{ $endereco['principal'] ? 'Principal' : 'Tornar principal' }}
                                </button>
                                <button type="button" wire:click="removerEndereco({{ $i }})"
                                        class="rounded p-1 text-danger hover:bg-red-50" title="Remover">
                                    <x-icon name="trash-2" class="h-4 w-4" />
                                </button>
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6">
                                <x-input class="sm:col-span-4" label="Logradouro" required
                                         wire:model="enderecos.{{ $i }}.logradouro"
                                         :error="$errors->first('enderecos.' . $i . '.logradouro')" />
                                <x-input label="Número" wire:model="enderecos.{{ $i }}.numero" />
                                <x-input label="CEP" wire:model="enderecos.{{ $i }}.cep"
                                         :error="$errors->first('enderecos.' . $i . '.cep')" />
                                <x-input class="sm:col-span-2" label="Complemento" wire:model="enderecos.{{ $i }}.complemento" />
                                <x-input class="sm:col-span-2" label="Bairro" wire:model="enderecos.{{ $i }}.bairro" />
                                <x-select class="sm:col-span-2" label="Município" required
                                          wire:model="enderecos.{{ $i }}.municipio_id"
                                          :error="$errors->first('enderecos.' . $i . '.municipio_id')"
                                          help="Código IBGE — exigido pelo CT-e.">
                                    <option value="">Selecione…</option>
                                    @foreach ($this->municipios as $municipio)
                                        <option value="{{ $municipio->id }}">{{ $municipio->nome }}/{{ $municipio->uf }}</option>
                                    @endforeach
                                </x-select>
                            </div>
                        </div>
                    @endforeach

                    <x-button variant="outline" size="sm" icon="plus" wire:click="adicionarEndereco">
                        Adicionar endereço
                    </x-button>

                    <div class="mt-3 flex items-start gap-2 rounded-r-md border-l-[3px] border-info bg-blue-50 px-3 py-2.5
                                text-xs text-blue-800 dark:bg-blue-950/40 dark:text-blue-300">
                        <x-icon name="info" class="mt-px h-3.5 w-3.5 flex-shrink-0" />
                        <span>Um endereço principal por pessoa. A regra é do banco — marcar outro desmarca o anterior automaticamente.</span>
                    </div>
                </div>

                {{-- Contatos --}}
                <div class="px-5 py-5" @class(['hidden' => $aba !== 'contatos'])>
                    @forelse ($contatos as $i => $contato)
                        <div wire:key="contato-{{ $i }}" class="mb-3 rounded-lg border border-border p-4">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-5">
                                <x-input class="sm:col-span-2" label="Nome" required
                                         wire:model="contatos.{{ $i }}.nome"
                                         :error="$errors->first('contatos.' . $i . '.nome')" />
                                <x-input label="Cargo" wire:model="contatos.{{ $i }}.cargo" />
                                <x-input class="sm:col-span-2" label="E-mail" type="email"
                                         wire:model="contatos.{{ $i }}.email"
                                         :error="$errors->first('contatos.' . $i . '.email')" />
                                <x-input label="Telefone" wire:model="contatos.{{ $i }}.telefone" />
                                <x-input label="Setor" wire:model="contatos.{{ $i }}.setor" />
                            </div>
                            <div class="mt-3 flex items-center gap-3">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" wire:model="contatos.{{ $i }}.recebe_dfe"
                                           class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30">
                                    <span class="text-sm text-text">Recebe XML e DACTE por e-mail</span>
                                </label>
                                <span class="flex-1"></span>
                                <button type="button" wire:click="removerContato({{ $i }})"
                                        class="rounded p-1 text-danger hover:bg-red-50" title="Remover">
                                    <x-icon name="trash-2" class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    @empty
                        <x-empty-state icon="mail" title="Nenhum contato"
                                       description="Quem recebe o XML e o DACTE na autorização é marcado aqui." />
                    @endforelse

                    <x-button variant="outline" size="sm" icon="plus" wire:click="adicionarContato">
                        Adicionar contato
                    </x-button>
                </div>
            </x-card>
        </div>

        {{-- ── Painel de conformidade ───────────────────────── --}}
        <div class="flex flex-col gap-3">
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="shield-check" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Pronta para emitir?</h2>
                </div>
                <div class="px-5 py-4">
                    @if ($this->pendenciasFiscais === [])
                        <div class="flex items-start gap-2.5 rounded-md bg-secondary-soft px-3 py-3">
                            <x-icon name="check" class="mt-px h-4 w-4 flex-shrink-0 text-green-700" />
                            <div>
                                <div class="text-sm font-semibold text-green-800">Sem pendências fiscais</div>
                                <div class="mt-0.5 text-xs text-green-700">
                                    Esta pessoa pode ser tomador, remetente ou destinatário de um CT-e.
                                </div>
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

            @if ($papeis !== [])
                <x-card padding="sm">
                    <p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-text-muted">O que cada papel implica</p>
                    @foreach ($papeis as $codigo)
                        <div class="flex items-start gap-2 border-b border-border py-2 last:border-0">
                            <x-icon name="check" class="mt-0.5 h-3.5 w-3.5 flex-shrink-0 text-secondary" />
                            <div>
                                <div class="text-xs font-semibold text-text">{{ config('papeis.rotulos.' . $codigo) }}</div>
                                <div class="mt-0.5 text-xs leading-relaxed text-text-muted">
                                    {{ config('papeis.descricoes.' . $codigo) }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </x-card>
            @endif

            <x-card padding="sm">
                <div class="flex items-start gap-2 text-xs leading-relaxed text-text-secondary">
                    <x-icon name="lock" class="mt-px h-3.5 w-3.5 flex-shrink-0 text-text-muted" />
                    <span>A empresa deste cadastro vem do usuário autenticado, nunca do formulário. Não existe campo de empresa aqui — e não deve existir.</span>
                </div>
            </x-card>
        </div>
    </div>
</div>
