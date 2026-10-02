{{--
    Rotina 3010 — ficha da ordem de coleta.
    Participantes + trecho + carga + itens (com NF-e) + frete calculado.
--}}
<div>
    <x-page-header
        :title="$ordem?->exists ? 'Ordem de coleta nº ' . $ordem->numero : 'Nova ordem de coleta'"
        :subtitle="$ordem?->exists ? 'Operação · rotina 3010' : 'O pedido de transporte que origina o CT-e'">
        <x-slot:actions>
            <x-button variant="ghost" size="sm" :href="route('ordens-coleta.index')" wire:navigate>Cancelar</x-button>
            <x-button variant="primary" size="sm" icon="check" wire:click="salvar" wire:loading.attr="disabled">Salvar</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Coluna principal --}}
        <div class="flex flex-col gap-4 lg:col-span-2">
            {{-- Cabeçalho --}}
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="file-text" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Dados da ordem</h2>
                </div>
                <div class="grid grid-cols-1 gap-4 px-5 py-5 sm:grid-cols-3">
                    <x-input label="Número" required wire:model="numero" :error="$errors->first('numero')" />
                    <x-input label="Data" type="date" required wire:model="data" :error="$errors->first('data')" />
                    <x-select label="Filial" required wire:model="filial_id" :error="$errors->first('filial_id')">
                        @foreach ($this->filiais as $f)
                            <option value="{{ $f->id }}">{{ $f->nome_fantasia ?? $f->razao_social }}</option>
                        @endforeach
                    </x-select>
                    <x-select class="sm:col-span-2" label="Cliente contratante" required wire:model.live="cliente_id" :error="$errors->first('cliente_id')">
                        <option value="">Selecione…</option>
                        @foreach ($this->clientes as $c)
                            <option value="{{ $c->id }}">{{ $c->razao_social }}</option>
                        @endforeach
                    </x-select>
                    <x-select label="Tomador do frete" wire:model="tomador_tipo">
                        @foreach (config('ordens_coleta.tomador_tipos') as $codigo => $rotulo)
                            <option value="{{ $codigo }}">{{ $rotulo }}</option>
                        @endforeach
                    </x-select>
                </div>
            </x-card>

            {{-- Participantes --}}
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="users" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Participantes</h2>
                    <span class="text-sm text-text-muted">Remetente e destinatário, e os demais quando houver</span>
                </div>
                <div class="grid grid-cols-1 gap-4 px-5 py-5 sm:grid-cols-2">
                    <x-select label="Remetente" wire:model="remetente_id" help="Quem envia a carga.">
                        <option value="">—</option>
                        @foreach ($this->pessoas as $p)
                            <option value="{{ $p->id }}">{{ $p->razao_social }}</option>
                        @endforeach
                    </x-select>
                    <x-select label="Destinatário" wire:model="destinatario_id" help="Quem recebe a carga.">
                        <option value="">—</option>
                        @foreach ($this->pessoas as $p)
                            <option value="{{ $p->id }}">{{ $p->razao_social }}</option>
                        @endforeach
                    </x-select>
                    <x-select label="Expedidor" wire:model="expedidor_id" help="Quem entrega a carga ao transportador (opcional).">
                        <option value="">—</option>
                        @foreach ($this->pessoas as $p)
                            <option value="{{ $p->id }}">{{ $p->razao_social }}</option>
                        @endforeach
                    </x-select>
                    <x-select label="Recebedor" wire:model="recebedor_id" help="Quem recebe do transportador (opcional).">
                        <option value="">—</option>
                        @foreach ($this->pessoas as $p)
                            <option value="{{ $p->id }}">{{ $p->razao_social }}</option>
                        @endforeach
                    </x-select>
                </div>
            </x-card>

            {{-- Trecho e datas --}}
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="map" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Trecho e datas</h2>
                </div>
                <div class="grid grid-cols-1 gap-4 px-5 py-5 sm:grid-cols-2">
                    <x-select label="Município de início" wire:model="municipio_inicio_id">
                        <option value="">—</option>
                        @foreach ($this->municipios as $m)
                            <option value="{{ $m->id }}">{{ $m->nome }}/{{ $m->uf }}</option>
                        @endforeach
                    </x-select>
                    <x-select label="Município de fim" wire:model="municipio_fim_id">
                        <option value="">—</option>
                        @foreach ($this->municipios as $m)
                            <option value="{{ $m->id }}">{{ $m->nome }}/{{ $m->uf }}</option>
                        @endforeach
                    </x-select>
                    <x-input label="Previsão de coleta" type="datetime-local" wire:model="previsao_coleta" />
                    <x-input label="Previsão de entrega" type="datetime-local" wire:model="previsao_entrega" />
                </div>
            </x-card>

            {{-- Itens / carga --}}
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="package" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Carga e notas fiscais</h2>
                    <span class="text-sm text-text-muted">({{ count($itens) }})</span>
                </div>
                <div class="px-5 py-4">
                    @error('itens') <p class="mb-2 text-xs text-danger">{{ $message }}</p> @enderror

                    @foreach ($itens as $i => $item)
                        <div wire:key="oci-{{ $i }}" class="mb-3 rounded-lg border border-border p-4">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6">
                                <x-select class="sm:col-span-2" label="Mercadoria" wire:model="itens.{{ $i }}.mercadoria_id">
                                    <option value="">Avulsa</option>
                                    @foreach ($this->mercadorias as $mc)
                                        <option value="{{ $mc->id }}">{{ $mc->descricao }}</option>
                                    @endforeach
                                </x-select>
                                <x-input class="sm:col-span-3" label="Descrição" required wire:model="itens.{{ $i }}.descricao"
                                         :error="$errors->first('itens.' . $i . '.descricao')" placeholder="O que está sendo transportado" />
                                <div class="flex items-end justify-end">
                                    <button type="button" wire:click="removerItem({{ $i }})"
                                            class="rounded p-2 text-danger hover:bg-red-50 dark:hover:bg-red-950/40" title="Remover">
                                        <x-icon name="trash-2" class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-5">
                                <x-input label="Qtd." type="number" wire:model="itens.{{ $i }}.quantidade" />
                                <x-select label="Unidade" wire:model="itens.{{ $i }}.unidade">
                                    @foreach (config('ordens_coleta.unidades') as $u)
                                        <option value="{{ $u }}">{{ $u }}</option>
                                    @endforeach
                                </x-select>
                                <x-input label="Peso (kg)" type="number" wire:model="itens.{{ $i }}.peso" />
                                <x-input label="Volume (m³)" type="number" wire:model="itens.{{ $i }}.volume" />
                                <x-input label="Valor (R$)" type="number" wire:model="itens.{{ $i }}.valor" />
                            </div>
                            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-6">
                                <x-input class="sm:col-span-4" label="Chave da NF-e" wire:model="itens.{{ $i }}.nfe_chave"
                                         :error="$errors->first('itens.' . $i . '.nfe_chave')" placeholder="44 dígitos" />
                                <x-input label="Nº NF-e" wire:model="itens.{{ $i }}.nfe_numero" />
                                <x-input label="Série" wire:model="itens.{{ $i }}.nfe_serie" />
                            </div>
                        </div>
                    @endforeach

                    <div class="flex flex-wrap gap-2">
                        <x-button variant="outline" size="sm" icon="plus" wire:click="adicionarItem">Adicionar item</x-button>
                        <x-button variant="neutral" size="sm" icon="package" wire:click="somarCarga">Somar itens na carga</x-button>
                    </div>

                    <div class="mt-3 flex items-start gap-2 rounded-r-md border-l-[3px] border-info bg-blue-50 px-3 py-2.5
                                text-xs text-blue-800 dark:bg-blue-950/40 dark:text-blue-300">
                        <x-icon name="info" class="mt-px h-3.5 w-3.5 flex-shrink-0" />
                        <span>As chaves de NF-e informadas aqui são reaproveitadas no CT-e e no MDF-e — não precisam ser redigitadas na emissão.</span>
                    </div>
                </div>
            </x-card>

            <x-card padding="none" class="overflow-hidden">
                <div class="px-5 py-5">
                    <x-input-label>Observações</x-input-label>
                    <textarea wire:model="observacoes" rows="3"
                              class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-text
                                     outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20"
                              placeholder="Instruções de coleta, agendamento, restrições…"></textarea>
                </div>
            </x-card>
        </div>

        {{-- Painel lateral: carga + frete + status --}}
        <div class="flex flex-col gap-4">
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="package" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Carga consolidada</h2>
                </div>
                <div class="grid grid-cols-2 gap-4 px-5 py-5">
                    <x-input label="Peso bruto (kg)" type="number" wire:model="peso_bruto" :error="$errors->first('peso_bruto')" />
                    <x-input label="Peso cubado (kg)" type="number" wire:model="peso_cubado" />
                    <x-input label="Volumes" type="number" wire:model="volumes" :error="$errors->first('volumes')" />
                    <x-input label="Valor da carga (R$)" type="number" wire:model="valor_mercadoria" :error="$errors->first('valor_mercadoria')" />
                </div>
            </x-card>

            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="tag" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Frete</h2>
                </div>
                <div class="px-5 py-5">
                    <x-select label="Tabela de frete" wire:model="tabela_frete_id" help="Em branco = a tabela vigente do cliente (ou geral).">
                        <option value="">Automática (vigente)</option>
                        @foreach ($this->tabelas as $t)
                            <option value="{{ $t->id }}">{{ $t->descricao }}{{ $t->pessoa_id ? '' : ' · geral' }}</option>
                        @endforeach
                    </x-select>

                    @if (session('aviso_frete'))
                        <div class="mt-3 flex items-start gap-2 rounded-r-md border-l-[3px] border-warning bg-yellow-50 px-3 py-2.5
                                    text-xs text-yellow-800 dark:bg-yellow-950/40 dark:text-yellow-300">
                            <x-icon name="alert-triangle" class="mt-px h-3.5 w-3.5 flex-shrink-0" />
                            <span>{{ session('aviso_frete') }}</span>
                        </div>
                    @endif

                    <div class="mt-4 rounded-lg bg-surface-elevated p-4">
                        <div class="flex items-baseline justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-text-muted">Frete calculado</span>
                        </div>
                        <p class="mt-1 text-2xl font-bold text-text tabular-nums">
                            {{ $valor_frete_calculado !== null ? 'R$ ' . number_format((float) $valor_frete_calculado, 2, ',', '.') : '—' }}
                        </p>

                        @if ($freteComponentes !== [])
                            <div class="mt-3 flex flex-col gap-1 border-t border-border pt-3">
                                @foreach ($freteComponentes as $comp)
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-text-secondary">{{ config('tabelas_frete.componentes.' . $comp['componente'], $comp['componente']) }}</span>
                                        <span class="font-medium text-text tabular-nums">R$ {{ number_format($comp['valor'], 2, ',', '.') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <x-button class="mt-3 w-full justify-center" variant="neutral" size="sm" icon="loader-2" wire:click="recalcularFrete">
                        Recalcular frete
                    </x-button>
                </div>
            </x-card>

            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="check" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Status</h2>
                </div>
                <div class="px-5 py-5">
                    <x-select label="Situação da ordem" wire:model="status">
                        @foreach (config('ordens_coleta.status') as $codigo => $rotulo)
                            <option value="{{ $codigo }}">{{ $rotulo }}</option>
                        @endforeach
                    </x-select>
                    <p class="mt-2 text-xs text-text-muted">Ao faturar, a ordem gera o CT-e (módulo fiscal, em breve).</p>
                </div>
            </x-card>
        </div>
    </div>
</div>
