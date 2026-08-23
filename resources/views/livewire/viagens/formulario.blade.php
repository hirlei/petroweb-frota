{{--
    Rotina 3020 — painel da viagem.
    Composição + condução + trecho + custos; resultado e CT-e no painel lateral.
--}}
<div>
    <x-page-header
        :title="$viagem?->exists ? 'Viagem nº ' . $viagem->numero : 'Nova viagem'"
        :subtitle="$viagem?->exists ? 'Operação · rotina 3020' : 'A execução física do transporte'">
        <x-slot:actions>
            <x-button variant="ghost" size="sm" :href="route('viagens.index')" wire:navigate>Cancelar</x-button>
            <x-button variant="primary" size="sm" icon="check" wire:click="salvar" wire:loading.attr="disabled">Salvar</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Coluna principal --}}
        <div class="flex flex-col gap-4 lg:col-span-2">
            {{-- Cabeçalho --}}
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="route" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Dados da viagem</h2>
                </div>
                <div class="grid grid-cols-1 gap-4 px-5 py-5 sm:grid-cols-3">
                    <x-input label="Número" required wire:model="numero" :error="$errors->first('numero')" />
                    <x-select label="Tipo" wire:model="tipo">
                        @foreach (config('viagens.tipos') as $codigo => $rotulo)
                            <option value="{{ $codigo }}">{{ $rotulo }}</option>
                        @endforeach
                    </x-select>
                    <x-select label="Filial" required wire:model="filial_id" :error="$errors->first('filial_id')">
                        @foreach ($this->filiais as $f)
                            <option value="{{ $f->id }}">{{ $f->nome_fantasia ?? $f->razao_social }}</option>
                        @endforeach
                    </x-select>
                </div>
            </x-card>

            {{-- Composição e condução --}}
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="truck" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Composição e condução</h2>
                </div>
                <div class="px-5 py-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-select label="Composição" wire:model.live="composicao_id" help="Congela as placas dos reboques no momento da viagem.">
                            <option value="">Montar manualmente</option>
                            @foreach ($this->composicoes as $c)
                                <option value="{{ $c->id }}">{{ $c->descricao }}</option>
                            @endforeach
                        </x-select>
                        <x-select label="Veículo de tração" required wire:model="veiculo_tracao_id" :error="$errors->first('veiculo_tracao_id')">
                            <option value="">Selecione…</option>
                            @foreach ($this->veiculosTracao as $v)
                                <option value="{{ $v->id }}">{{ $v->placaFormatada() }}</option>
                            @endforeach
                        </x-select>
                    </div>

                    @if ($composicao_snapshot !== [])
                        <div class="mt-3 flex flex-wrap items-center gap-2 rounded-lg bg-surface-elevated px-4 py-3">
                            <span class="text-xs font-bold uppercase tracking-wider text-text-muted">Reboques (snapshot)</span>
                            @foreach ($composicao_snapshot as $placa)
                                <x-badge variant="secondary" class="text-[11px] tabular-nums">{{ $placa }}</x-badge>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-select label="Motorista" required wire:model="motorista_id" :error="$errors->first('motorista_id')">
                            <option value="">Selecione…</option>
                            @foreach ($this->motoristas as $m)
                                <option value="{{ $m->id }}">{{ $m->pessoa?->razao_social }}</option>
                            @endforeach
                        </x-select>
                        <x-select label="2º motorista" wire:model="motorista_2_id" :error="$errors->first('motorista_2_id')" help="Dupla em viagens longas.">
                            <option value="">—</option>
                            @foreach ($this->motoristas as $m)
                                <option value="{{ $m->id }}">{{ $m->pessoa?->razao_social }}</option>
                            @endforeach
                        </x-select>
                    </div>
                </div>
            </x-card>

            {{-- Trecho --}}
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="map" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Trecho e tempos</h2>
                </div>
                <div class="px-5 py-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <x-select label="Rota" wire:model.live="rota_id">
                            <option value="">—</option>
                            @foreach ($this->rotas as $r)
                                <option value="{{ $r->id }}">{{ $r->descricao }}</option>
                            @endforeach
                        </x-select>
                        <x-select label="Origem" wire:model="municipio_origem_id">
                            <option value="">—</option>
                            @foreach ($this->municipios as $m)
                                <option value="{{ $m->id }}">{{ $m->nome }}/{{ $m->uf }}</option>
                            @endforeach
                        </x-select>
                        <x-select label="Destino" wire:model="municipio_destino_id">
                            <option value="">—</option>
                            @foreach ($this->municipios as $m)
                                <option value="{{ $m->id }}">{{ $m->nome }}/{{ $m->uf }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <x-input label="Saída prevista" type="datetime-local" wire:model="saida_prevista" />
                        <x-input label="Saída real" type="datetime-local" wire:model="saida_real" />
                        <x-input label="Chegada prevista" type="datetime-local" wire:model="chegada_prevista" />
                        <x-input label="Chegada real" type="datetime-local" wire:model="chegada_real" />
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <x-input label="Km inicial" type="number" wire:model="km_inicial" />
                        <x-input label="Km final" type="number" wire:model="km_final" :error="$errors->first('km_final')" />
                        <x-input label="Peso total (kg)" type="number" wire:model="peso_total" />
                        <x-input label="Valor da carga (R$)" type="number" wire:model="valor_carga" />
                    </div>
                </div>
            </x-card>

            {{-- Custos --}}
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="fuel" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Custos e receita</h2>
                </div>
                <div class="px-5 py-5">
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                        <x-input label="Combustível (R$)" type="number" wire:model.live.debounce.500ms="custo_combustivel" />
                        <x-input label="Pedágio (R$)" type="number" wire:model.live.debounce.500ms="custo_pedagio" />
                        <x-input label="Motorista (R$)" type="number" wire:model.live.debounce.500ms="custo_motorista" />
                        <x-input label="Manutenção (R$)" type="number" wire:model.live.debounce.500ms="custo_manutencao" />
                        <x-input label="Outros (R$)" type="number" wire:model.live.debounce.500ms="custo_outros" />
                        <x-input label="Receita total (R$)" type="number" wire:model.live.debounce.500ms="receita_total" />
                    </div>
                    <div class="mt-3 flex items-start gap-2 rounded-r-md border-l-[3px] border-info bg-blue-50 px-3 py-2.5
                                text-xs text-blue-800 dark:bg-blue-950/40 dark:text-blue-300">
                        <x-icon name="info" class="mt-px h-3.5 w-3.5 flex-shrink-0" />
                        <span>Os custos são recalculados por evento quando abastecimento, despesa ou OS da viagem mudam. A receita vem da soma dos CT-e vinculados.</span>
                    </div>

                    <div class="mt-5">
                        <x-input-label>Observações</x-input-label>
                        <textarea wire:model="observacoes" rows="2"
                                  class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-text
                                         outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20"></textarea>
                    </div>
                </div>
            </x-card>
        </div>

        {{-- Painel lateral --}}
        <div class="flex flex-col gap-4">
            {{-- Status --}}
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="check" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Andamento</h2>
                </div>
                <div class="px-5 py-5">
                    <x-select label="Status" wire:model="status">
                        @foreach (config('viagens.status') as $codigo => $rotulo)
                            <option value="{{ $codigo }}">{{ $rotulo }}</option>
                        @endforeach
                    </x-select>

                    <ol class="mt-4 flex flex-col gap-0">
                        @php $seq = config('viagens.timeline'); $atual = array_search($status, $seq, true); @endphp
                        @foreach ($seq as $idx => $etapa)
                            @php $feito = $atual !== false && $idx <= $atual; @endphp
                            <li class="flex items-center gap-3 py-1.5">
                                <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full text-[11px] font-bold
                                             {{ $feito ? 'bg-success text-white' : 'bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }}">
                                    {{ $idx + 1 }}
                                </span>
                                <span class="text-sm {{ $feito ? 'font-medium text-text' : 'text-text-muted' }}">
                                    {{ config('viagens.status.' . $etapa) }}
                                </span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </x-card>

            {{-- Resultado --}}
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="tag" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Resultado</h2>
                </div>
                <div class="px-5 py-5">
                    @php $calc = $this->calculo; @endphp
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-lg bg-surface-elevated p-3">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-text-muted">Receita</span>
                            <p class="mt-0.5 text-lg font-bold text-text tabular-nums">R$ {{ number_format((float) ($receita_total ?: 0), 2, ',', '.') }}</p>
                        </div>
                        <div class="rounded-lg bg-surface-elevated p-3">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-text-muted">Custo total</span>
                            <p class="mt-0.5 text-lg font-bold text-text tabular-nums">R$ {{ number_format($calc['total'], 2, ',', '.') }}</p>
                        </div>
                    </div>
                    <div class="mt-3 rounded-lg p-4 {{ $calc['margem'] >= 0 ? 'bg-green-50 dark:bg-green-950/40' : 'bg-red-50 dark:bg-red-950/40' }}">
                        <span class="text-[11px] font-bold uppercase tracking-wider {{ $calc['margem'] >= 0 ? 'text-success' : 'text-danger' }}">Margem</span>
                        <p class="mt-0.5 text-2xl font-bold tabular-nums {{ $calc['margem'] >= 0 ? 'text-success' : 'text-danger' }}">
                            R$ {{ number_format($calc['margem'], 2, ',', '.') }}
                            @if ($calc['percentual'] !== null)
                                <span class="text-sm font-semibold">({{ number_format($calc['percentual'], 1, ',', '.') }}%)</span>
                            @endif
                        </p>
                        @if ($calc['por_km'] !== null)
                            <p class="mt-1 text-xs {{ $calc['margem'] >= 0 ? 'text-success' : 'text-danger' }}">Custo por km: R$ {{ number_format($calc['por_km'], 2, ',', '.') }}</p>
                        @endif
                    </div>
                </div>
            </x-card>

            {{-- CT-e vinculados (N:N) --}}
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="files" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">CT-e vinculados</h2>
                    <span class="ml-auto text-sm text-text-muted">{{ $this->ctesVinculados->count() }}</span>
                </div>
                <div class="px-5 py-5">
                    @if (! $viagem?->exists)
                        <p class="text-sm text-text-muted">Salve a viagem para vincular os CT-e.</p>
                    @else
                        @forelse ($this->ctesVinculados as $c)
                            <div class="flex items-center justify-between border-t border-border py-2 first:border-0">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-medium text-text">Nº {{ $c->numero ? str_pad((string) $c->numero, 6, '0', STR_PAD_LEFT) : 'rascunho' }} · {{ $c->tomador?->razao_social ?? '—' }}</div>
                                    <div class="text-xs text-text-muted tabular-nums">R$ {{ number_format((float) $c->valor_total_servico, 2, ',', '.') }}</div>
                                </div>
                                <button type="button" wire:click="desvincularCte({{ $c->id }})" class="rounded p-1.5 text-danger hover:bg-red-50 dark:hover:bg-red-950/40" title="Desvincular"><x-icon name="x" class="h-4 w-4" /></button>
                            </div>
                        @empty
                            <p class="text-sm text-text-muted">Nenhum CT-e vinculado ainda.</p>
                        @endforelse

                        @if ($this->ctesDisponiveis->isNotEmpty())
                            <div class="mt-3 border-t border-border pt-3">
                                <x-input-label>Vincular CT-e autorizado</x-input-label>
                                <select wire:model="cteParaVincular" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-text">
                                    <option value="">Selecione…</option>
                                    @foreach ($this->ctesDisponiveis as $c)
                                        <option value="{{ $c->id }}">Nº {{ str_pad((string) $c->numero, 6, '0', STR_PAD_LEFT) }} · {{ $c->tomador?->razao_social }} · R$ {{ number_format((float) $c->valor_total_servico, 2, ',', '.') }}</option>
                                    @endforeach
                                </select>
                                <x-button class="mt-2 w-full justify-center" variant="neutral" size="sm" icon="plus"
                                          x-on:click="$wire.cteParaVincular && $wire.vincularCte($wire.cteParaVincular)">Vincular</x-button>
                            </div>
                        @endif
                        <p class="mt-3 text-xs text-text-muted">A receita da viagem é a soma dos CT-e vinculados (N:N — RN-02).</p>
                    @endif
                </div>
            </x-card>
        </div>
    </div>

    {{-- Mapa da viagem --}}
    <x-card padding="none" class="mt-4 overflow-hidden">
        <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
            <x-icon name="map" class="h-4 w-4 text-text-secondary" />
            <h2 class="text-sm font-semibold text-text">Mapa da viagem</h2>
            <span class="text-sm text-text-muted">Percurso da rota ou origem → destino</span>
        </div>
        <div class="p-4">
            <x-mapa :pontos="$this->pontosMapa" :geometria="$this->geometriaViagem" :caminhao="$this->caminhaoViagem"
                    :linha="true" altura="420px"
                    wire:key="mapa-viagem-{{ $viagem?->id ?? 'nova' }}-{{ $rota_id }}-{{ $veiculo_tracao_id }}" />
        </div>
    </x-card>
</div>
