{{--
    Rotina 2060 — lançamento de abastecimento.
    Ao salvar, o sistema calcula km rodado, média e desvio contra a referência do veículo.
--}}
<div>
    <x-page-header
        :title="$abastecimento?->exists ? 'Abastecimento' : 'Novo abastecimento'"
        subtitle="Frota · rotina 2060">
        <x-slot:actions>
            <x-button variant="ghost" size="sm" :href="route('abastecimentos.index')" wire:navigate>Cancelar</x-button>
            <x-button variant="primary" size="sm" icon="check" wire:click="salvar" wire:loading.attr="disabled">Salvar</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-[1fr_320px]">
        <x-card padding="none" class="overflow-hidden">
            <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                <x-icon name="fuel" class="h-4 w-4 text-text-secondary" />
                <h2 class="text-sm font-semibold text-text">Dados do abastecimento</h2>
            </div>
            <div class="px-5 py-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <x-select label="Veículo" required wire:model="veiculo_id" :error="$errors->first('veiculo_id')">
                        <option value="">Selecione…</option>
                        @foreach ($this->veiculos as $v)
                            <option value="{{ $v->id }}">{{ $v->placaFormatada() }}</option>
                        @endforeach
                    </x-select>
                    <x-input label="Data e hora" type="datetime-local" required wire:model="data_hora" :error="$errors->first('data_hora')" />
                    <x-input label="Combustível" required wire:model="combustivel" :error="$errors->first('combustivel')" />
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <x-input label="Litros" type="number" required wire:model.live="litros" :error="$errors->first('litros')" />
                    <x-input label="R$ / litro" type="number" required wire:model.live="valor_litro" :error="$errors->first('valor_litro')" />
                    <x-input label="Odômetro" type="number" required wire:model="odometro" :error="$errors->first('odometro')" />
                    <x-input label="Nota fiscal" wire:model="nota_fiscal" />
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-select label="Motorista" wire:model="motorista_id">
                        <option value="">—</option>
                        @foreach ($this->motoristas as $m)
                            <option value="{{ $m->id }}">{{ $m->pessoa?->razao_social }}</option>
                        @endforeach
                    </x-select>
                    <x-select label="Posto" wire:model="posto_id">
                        <option value="">—</option>
                        @foreach ($this->postos as $p)
                            <option value="{{ $p->id }}">{{ $p->razao_social }}</option>
                        @endforeach
                    </x-select>
                </div>

                <label class="mt-4 flex items-center gap-2">
                    <input type="checkbox" wire:model="tanque_cheio" class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30">
                    <span class="text-sm text-text">Tanque cheio</span>
                </label>
                <p class="mt-1 text-xs text-text-muted">A média de consumo só é calculada entre dois abastecimentos de tanque cheio.</p>
            </div>
        </x-card>

        <div class="flex flex-col gap-3">
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="layout-dashboard" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Total</h2>
                </div>
                <div class="px-5 py-4">
                    <div class="flex items-center justify-between rounded-md bg-secondary-soft px-3 py-2.5">
                        <span class="text-sm font-semibold text-green-800">Valor total</span>
                        <span class="font-mono text-sm font-semibold text-green-800">R$ {{ number_format($this->valorTotal, 2, ',', '.') }}</span>
                    </div>
                    <p class="mt-2 text-xs text-text-muted">Litros × R$/litro. A média e o desvio aparecem na lista após salvar — dependem do abastecimento anterior de tanque cheio.</p>
                </div>
            </x-card>
        </div>
    </div>
</div>
