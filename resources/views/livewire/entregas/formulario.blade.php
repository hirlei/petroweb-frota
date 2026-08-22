{{--
    Rotina 3050 — ficha da entrega (POD).
    Recebedor, comprovação e vínculo com viagem/ordem. CT-e reservado (4010).
--}}
<div>
    <x-page-header
        :title="$entrega?->exists ? 'Entrega' : 'Registrar entrega'"
        subtitle="Operação · rotina 3050">
        <x-slot:actions>
            <x-button variant="ghost" size="sm" :href="route('entregas.index')" wire:navigate>Cancelar</x-button>
            <x-button variant="primary" size="sm" icon="check" wire:click="salvar" wire:loading.attr="disabled">Salvar</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Coluna principal --}}
        <div class="flex flex-col gap-4 lg:col-span-2">
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="inbox" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Comprovação de entrega</h2>
                </div>
                <div class="px-5 py-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-select label="Viagem" wire:model="viagem_id" :error="$errors->first('viagem_id')">
                            <option value="">—</option>
                            @foreach ($this->viagens as $v)
                                <option value="{{ $v->id }}">Nº {{ $v->numero }} · {{ $v->municipioOrigem?->nome ?? '—' }} → {{ $v->municipioDestino?->nome ?? '—' }}</option>
                            @endforeach
                        </x-select>
                        <x-select label="Ordem de coleta" wire:model="ordem_coleta_id" help="O CT-e será vinculado quando o módulo 4010 existir.">
                            <option value="">—</option>
                            @foreach ($this->ordens as $o)
                                <option value="{{ $o->id }}">OC {{ $o->numero }} · {{ $o->cliente?->razao_social }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-input label="Recebedor" wire:model="recebedor_nome" placeholder="Nome de quem recebeu" />
                        <x-input label="Documento do recebedor" wire:model="recebedor_documento" placeholder="CPF / RG" />
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-input label="Data e hora" type="datetime-local" required wire:model="data_hora" :error="$errors->first('data_hora')" />
                        <x-select label="Tipo de comprovação" wire:model="tipo_comprovacao">
                            @foreach (config('entregas.tipos_comprovacao') as $codigo => $rotulo)
                                <option value="{{ $codigo }}">{{ $rotulo }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-input label="Latitude" type="number" wire:model="latitude" :error="$errors->first('latitude')" placeholder="-12,2664" />
                        <x-input label="Longitude" type="number" wire:model="longitude" :error="$errors->first('longitude')" placeholder="-38,9663" />
                    </div>
                    <div class="mt-4">
                        <x-input-label>Observações</x-input-label>
                        <textarea wire:model="observacoes" rows="2"
                                  class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-text
                                         outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20"
                                  placeholder="Ex.: carga conferida; 2 volumes avariados registrados em ocorrência."></textarea>
                    </div>

                    <div class="mt-4 flex items-start gap-2 rounded-r-md border-l-[3px] border-border bg-surface-elevated px-3 py-2.5 text-xs text-text-secondary">
                        <x-icon name="upload" class="mt-px h-3.5 w-3.5 flex-shrink-0" />
                        <span>Anexo do canhoto/foto entra numa próxima etapa — os campos já existem no cadastro para receber o arquivo.</span>
                    </div>
                </div>
            </x-card>
        </div>

        {{-- Painel lateral --}}
        <div class="flex flex-col gap-4">
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    <x-icon name="files" class="h-4 w-4 text-text-secondary" />
                    <h2 class="text-sm font-semibold text-text">Comprovação eletrônica</h2>
                    <x-badge variant="warning" class="text-[10px]">Em breve</x-badge>
                </div>
                <div class="px-5 py-5">
                    <div class="flex items-start gap-2 rounded-r-md border-l-[3px] border-warning bg-yellow-50 px-3 py-2.5
                                text-xs text-yellow-800 dark:bg-yellow-950/40 dark:text-yellow-300">
                        <x-icon name="info" class="mt-px h-3.5 w-3.5 flex-shrink-0" />
                        <span>A comprovação eletrônica é o evento <strong>110180</strong> do CT-e (cancelamento pelo 110181). É liberada quando o módulo fiscal (4010) entrar; até lá, use canhoto digitalizado ou foto.</span>
                    </div>
                </div>
            </x-card>
        </div>
    </div>
</div>
