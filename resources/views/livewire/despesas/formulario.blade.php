{{--
    Rotina 3060 — ficha da despesa de viagem.
    Amarrada a viagem + motorista; aprovada, entra no custo da viagem.
--}}
<div>
    <x-page-header
        :title="$despesa?->exists ? 'Despesa de viagem' : 'Nova despesa'"
        subtitle="Operação · rotina 3060">
        <x-slot:actions>
            <x-button variant="ghost" size="sm" :href="route('despesas.index')" wire:navigate>Cancelar</x-button>
            <x-button variant="primary" size="sm" icon="check" wire:click="salvar" wire:loading.attr="disabled">Salvar</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Coluna principal --}}
        <div class="flex flex-col gap-4 lg:col-span-2">
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="ticket" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Dados da despesa</h2>
                </div>
                <div class="px-5 py-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-select label="Viagem" required wire:model.live="viagem_id" :error="$errors->first('viagem_id')">
                            <option value="">Selecione…</option>
                            @foreach ($this->viagens as $v)
                                <option value="{{ $v->id }}">Nº {{ $v->numero }} · {{ $v->municipioOrigem?->nome ?? '—' }} → {{ $v->municipioDestino?->nome ?? '—' }}</option>
                            @endforeach
                        </x-select>
                        <x-select label="Motorista" wire:model="motorista_id">
                            <option value="">—</option>
                            @foreach ($this->motoristas as $m)
                                <option value="{{ $m->id }}">{{ $m->pessoa?->razao_social }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <x-select label="Tipo" wire:model.live="tipo">
                            @foreach (config('despesas.tipos') as $codigo => $rotulo)
                                <option value="{{ $codigo }}">{{ $rotulo }}</option>
                            @endforeach
                        </x-select>
                        <x-input label="Data" type="date" required wire:model="data" :error="$errors->first('data')" />
                        <x-input label="Valor (R$)" type="number" required wire:model.live.debounce.500ms="valor" :error="$errors->first('valor')" />
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-select label="Forma de pagamento" wire:model="forma_pagamento">
                            @foreach (config('despesas.formas_pagamento') as $codigo => $rotulo)
                                <option value="{{ $codigo }}">{{ $rotulo }}</option>
                            @endforeach
                        </x-select>
                        <x-select label="Origem" wire:model="origem">
                            @foreach (config('despesas.origens') as $codigo => $rotulo)
                                <option value="{{ $codigo }}">{{ $rotulo }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <div class="mt-4">
                        <x-input-label>Descrição</x-input-label>
                        <textarea wire:model="descricao" rows="2"
                                  class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-text
                                         outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20"
                                  placeholder="Detalhe do gasto — praça de pedágio, cidade, nota…"></textarea>
                        @error('descricao') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div class="mt-4 flex items-start gap-2 rounded-r-md border-l-[3px] border-border bg-surface-elevated px-3 py-2.5 text-xs text-text-secondary">
                        <x-icon name="upload" class="mt-px h-3.5 w-3.5 flex-shrink-0" />
                        <span>Anexo do comprovante (foto/PDF) entra numa próxima etapa — o campo já existe no cadastro para receber o arquivo.</span>
                    </div>
                </div>
            </x-card>
        </div>

        {{-- Painel lateral --}}
        <div class="flex flex-col gap-4">
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="check" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Aprovação</h2>
                </div>
                <div class="px-5 py-5">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model.live="aprovada"
                               class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30">
                        <span class="text-sm text-text">Despesa aprovada</span>
                    </label>
                    <div class="mt-3 flex items-start gap-2 rounded-r-md border-l-[3px] border-warning bg-yellow-50 px-3 py-2.5
                                text-xs text-yellow-800 dark:bg-yellow-950/40 dark:text-yellow-300">
                        <x-icon name="alert-triangle" class="mt-px h-3.5 w-3.5 flex-shrink-0" />
                        <span>Enquanto pendente, não entra no custo da viagem. Aprovada, soma automaticamente e a margem recalcula.</span>
                    </div>
                </div>
            </x-card>

            @if ($this->impacto)
                <x-card padding="none" class="overflow-hidden">
                    <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                        <x-icon name="route" class="h-4 w-4 text-text-secondary" />
                        <h2 class="text-sm font-semibold text-text">Impacto na viagem</h2>
                    </div>
                    <div class="px-5 py-5">
                        <div class="flex items-center justify-between py-1.5 text-sm">
                            <span class="text-text-secondary">Componente</span>
                            <span class="font-medium text-text">{{ [
                                'custo_pedagio' => 'Custo de pedágio',
                                'custo_motorista' => 'Custo do motorista',
                                'custo_outros' => 'Outros custos',
                            ][$this->impacto['componente']] ?? 'Custo' }}</span>
                        </div>
                        <div class="flex items-center justify-between border-t border-border py-1.5 text-sm">
                            <span class="text-text-secondary">Este lançamento</span>
                            <span class="font-medium tabular-nums {{ $aprovada ? 'text-warning' : 'text-text-muted' }}">
                                {{ $aprovada ? '+ ' : '' }}R$ {{ number_format($this->impacto['valor'], 2, ',', '.') }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between border-t border-border py-1.5 text-sm">
                            <span class="text-text-secondary">Margem atual da viagem</span>
                            <span class="font-medium text-text tabular-nums">R$ {{ number_format($this->impacto['margem_atual'], 2, ',', '.') }}</span>
                        </div>
                        <p class="mt-2 text-xs text-text-muted">A margem final é recalculada ao salvar.</p>
                    </div>
                </x-card>
            @endif
        </div>
    </div>
</div>
