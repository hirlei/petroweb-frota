{{-- Rotina 4030 — lançamento de vale-pedágio por veículo. --}}
<div>
    <x-page-header :title="$vale?->exists ? 'Vale-pedágio' : 'Novo vale-pedágio'" subtitle="Fiscal · rotina 4030">
        <x-slot:actions>
            <x-button variant="ghost" size="sm" :href="route('vale-pedagio.index')" wire:navigate>Cancelar</x-button>
            <x-button variant="primary" size="sm" icon="check" wire:click="salvar" wire:loading.attr="disabled">Salvar</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mx-auto max-w-3xl">
        <x-card padding="none" class="overflow-hidden">
            <div class="flex items-center gap-2 border-b border-border px-5 py-3.5"><x-icon name="ticket" class="h-4 w-4 text-text-secondary" /><h2 class="text-sm font-semibold text-text">Dados do vale-pedágio</h2></div>
            <div class="px-5 py-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-select label="Viagem" required wire:model.live="viagem_id" :error="$errors->first('viagem_id')">
                        <option value="">Selecione…</option>
                        @foreach ($this->viagens as $v)
                            <option value="{{ $v->id }}">Viagem {{ $v->numero }} · {{ $v->veiculoTracao?->placaFormatada() }} · {{ $v->municipioOrigem?->nome ?? '—' }} → {{ $v->municipioDestino?->nome ?? '—' }}</option>
                        @endforeach
                    </x-select>
                    <x-select label="Veículo (um vale por veículo)" required wire:model="veiculo_id" :error="$errors->first('veiculo_id')">
                        <option value="">Selecione a viagem primeiro…</option>
                        @foreach ($this->veiculos as $veic)
                            <option value="{{ $veic->id }}">{{ $veic->placaFormatada() }} · {{ ucfirst($veic->tipo) }}</option>
                        @endforeach
                    </x-select>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-select label="Fornecedor (catálogo ANTT)" required wire:model="fornecedor_vpo_id" :error="$errors->first('fornecedor_vpo_id')" help="Só fornecedoras habilitadas — evita a rejeição 733.">
                        <option value="">Selecione…</option>
                        @foreach ($this->fornecedores as $f)
                            <option value="{{ $f->id }}">{{ $f->razao_social }}</option>
                        @endforeach
                    </x-select>
                    <x-select label="Papel" wire:model="papel" help="'Fornecido' quando a transportadora repassa a um subcontratado.">
                        <option value="recebido">Recebido</option>
                        <option value="fornecido">Fornecido</option>
                    </x-select>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <x-input label="IDVPO (nCompra)" required wire:model="idvpo" :error="$errors->first('idvpo')" />
                    <x-select label="Tipo" wire:model="tipo" :error="$errors->first('tipo')">
                        <option value="01">01 · TAG</option>
                        <option value="04">04 · Leitura de placa</option>
                    </x-select>
                    <x-input label="Valor (R$)" type="number" wire:model="valor" :error="$errors->first('valor')" />
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-input label="Documento do pagador" wire:model="pagador_documento" placeholder="CNPJ ou CPF" />
                    <x-select label="Tipo do pagador" wire:model="pagador_tipo">
                        <option value="J">Jurídica (CNPJ)</option>
                        <option value="F">Física (CPF)</option>
                    </x-select>
                </div>

                <div class="mt-4 flex items-start gap-2 rounded-r-md border-l-[3px] border-info bg-blue-50 px-3 py-2.5 text-xs text-blue-800 dark:bg-blue-950/40 dark:text-blue-300">
                    <x-icon name="info" class="mt-px h-3.5 w-3.5 flex-shrink-0" />
                    <span>O IDVPO vai na tag nCompra — é o identificador do vale, não um número livre. O CNPJ da fornecedora é gravado a partir do catálogo e conferido antes da transmissão.</span>
                </div>
            </div>
        </x-card>
    </div>
</div>
