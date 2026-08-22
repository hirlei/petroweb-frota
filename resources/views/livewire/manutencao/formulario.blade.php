{{--
    Rotina 2050 — ficha da ordem de serviço.
    Cabeçalho + itens (peças e serviços). O total é a soma dos itens, recalculado ao salvar.
--}}
<div>
    <x-page-header
        :title="$os?->exists ? 'OS ' . $os->numero : 'Nova ordem de serviço'"
        subtitle="Frota · rotina 2050">
        <x-slot:actions>
            <x-button variant="ghost" size="sm" :href="route('manutencao.index')" wire:navigate>Cancelar</x-button>
            <x-button variant="primary" size="sm" icon="check" wire:click="salvar" wire:loading.attr="disabled">Salvar</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-[1fr_320px]">
        <div class="flex flex-col gap-4">
            {{-- Cabeçalho --}}
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="wrench" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Ordem de serviço</h2>
                </div>
                <div class="px-5 py-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <x-input label="Número" required wire:model="numero" :error="$errors->first('numero')" />
                        <x-select label="Veículo" required wire:model="veiculo_id" :error="$errors->first('veiculo_id')">
                            <option value="">Selecione…</option>
                            @foreach ($this->veiculos as $v)
                                <option value="{{ $v->id }}">{{ $v->placaFormatada() }}</option>
                            @endforeach
                        </x-select>
                        <x-select label="Tipo" required wire:model="tipo" :error="$errors->first('tipo')">
                            @foreach (config('manutencao.tipos') as $codigo => $rotulo)
                                <option value="{{ $codigo }}">{{ $rotulo }}</option>
                            @endforeach
                        </x-select>
                        <x-select label="Status" required wire:model="status" :error="$errors->first('status')">
                            @foreach (config('manutencao.status') as $codigo => $rotulo)
                                <option value="{{ $codigo }}">{{ $rotulo }}</option>
                            @endforeach
                        </x-select>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <x-select class="sm:col-span-2" label="Oficina" wire:model="oficina_id">
                            <option value="">—</option>
                            @foreach ($this->oficinas as $o)
                                <option value="{{ $o->id }}">{{ $o->razao_social }}</option>
                            @endforeach
                        </x-select>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2 pb-2.5">
                                <input type="checkbox" wire:model="interna" class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30">
                                <span class="text-sm text-text">Oficina interna</span>
                            </label>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <x-input label="Abertura" type="date" required wire:model="abertura" :error="$errors->first('abertura')" />
                        <x-input label="Previsão" type="date" wire:model="previsao" />
                        <x-input label="Encerramento" type="date" wire:model="encerramento" />
                        <x-input label="Odômetro" type="number" wire:model="odometro" />
                    </div>

                    <div class="mt-4">
                        <x-input-label>Observações</x-input-label>
                        <textarea wire:model="observacoes" rows="2"
                                  class="mt-1.5 w-full rounded-md border border-border bg-white px-3 py-2 text-sm text-text outline-none
                                         transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20 dark:bg-surface-elevated"></textarea>
                    </div>
                </div>
            </x-card>

            {{-- Itens --}}
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="files" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Peças e serviços</h2>
                    <span class="text-sm text-text-muted">({{ count($itens) }})</span>
                </div>
                <div class="px-5 py-4">
                    @foreach ($itens as $i => $item)
                        <div wire:key="osi-{{ $i }}" class="mb-3 rounded-lg border border-border p-4">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6">
                                <x-select label="Tipo" wire:model.live="itens.{{ $i }}.tipo">
                                    <option value="peca">Peça</option>
                                    <option value="servico">Serviço</option>
                                </x-select>
                                <x-input class="sm:col-span-3" label="Descrição" required wire:model="itens.{{ $i }}.descricao"
                                         :error="$errors->first('itens.' . $i . '.descricao')" />
                                <x-input label="Código" wire:model="itens.{{ $i }}.codigo" />
                                <div class="flex items-end justify-end">
                                    <button type="button" wire:click="removerItem({{ $i }})"
                                            class="rounded p-2 text-danger hover:bg-red-50" title="Remover">
                                        <x-icon name="trash-2" class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-4">
                                <x-input label="Quantidade" type="number" wire:model.live="itens.{{ $i }}.quantidade" />
                                <x-input label="Valor unitário" type="number" wire:model.live="itens.{{ $i }}.valor_unitario" />
                                <x-input label="Garantia até" type="date" wire:model="itens.{{ $i }}.garantia_ate" />
                                <div class="flex items-end">
                                    <span class="pb-2.5 text-sm text-text-secondary">
                                        Subtotal:
                                        <b class="text-text">R$ {{ number_format(((float) ($item['quantidade'] ?: 0)) * ((float) ($item['valor_unitario'] ?: 0)), 2, ',', '.') }}</b>
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <x-button variant="outline" size="sm" icon="plus" wire:click="adicionarItem">Adicionar item</x-button>
                </div>
            </x-card>
        </div>

        {{-- Totais --}}
        <div class="flex flex-col gap-3">
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="layout-dashboard" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Totais</h2>
                </div>
                <div class="px-5 py-4">
                    <x-linha-ficha rotulo="Peças" :valor="'R$ ' . number_format($this->totais['pecas'], 2, ',', '.')" />
                    <x-linha-ficha rotulo="Mão de obra" :valor="'R$ ' . number_format($this->totais['mao_obra'], 2, ',', '.')" />
                    <div class="my-1.5 flex items-center justify-between rounded-md bg-secondary-soft px-2.5 py-2">
                        <span class="text-sm font-semibold text-green-800">Total</span>
                        <span class="font-mono text-sm font-semibold text-green-800">R$ {{ number_format($this->totais['total'], 2, ',', '.') }}</span>
                    </div>
                </div>
            </x-card>
        </div>
    </div>
</div>
