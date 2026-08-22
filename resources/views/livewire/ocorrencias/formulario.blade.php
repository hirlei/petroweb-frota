{{--
    Rotina 3040 — ficha da ocorrência.
--}}
<div>
    <x-page-header
        :title="$ocorrencia?->exists ? 'Ocorrência' : 'Nova ocorrência'"
        :subtitle="$ocorrencia?->exists ? 'Operação · rotina 3040' : 'Registre o fato, o responsável apurado e o prejuízo'">
        <x-slot:actions>
            <x-button variant="ghost" size="sm" :href="route('ocorrencias.index')" wire:navigate>Cancelar</x-button>
            <x-button variant="primary" size="sm" icon="check" wire:click="salvar" wire:loading.attr="disabled">Salvar</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-[1fr_320px]">
        <x-card padding="none" class="overflow-hidden">
            <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                <x-icon name="alert-triangle" class="h-4 w-4 text-text-secondary" />
                <h2 class="text-sm font-semibold text-text">Registro</h2>
            </div>
            <div class="px-5 py-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <x-select label="Tipo" required wire:model="tipo" :error="$errors->first('tipo')">
                        @foreach (config('ocorrencias.tipos') as $codigo => $rotulo)
                            <option value="{{ $codigo }}">{{ $rotulo }}</option>
                        @endforeach
                    </x-select>
                    <x-input label="Data e hora" type="datetime-local" required wire:model="data_hora" :error="$errors->first('data_hora')" />
                    <x-select label="Município" wire:model="municipio_id">
                        <option value="">—</option>
                        @foreach ($this->municipios as $m)
                            <option value="{{ $m->id }}">{{ $m->nome }}/{{ $m->uf }}</option>
                        @endforeach
                    </x-select>
                </div>

                <div class="mt-4">
                    <x-input-label>Descrição <span class="text-danger">*</span></x-input-label>
                    <textarea wire:model="descricao" rows="4"
                              class="mt-1.5 w-full rounded-md border border-border bg-white px-3 py-2 text-sm text-text outline-none
                                     transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20 dark:bg-surface-elevated"
                              placeholder="O que aconteceu, com que carga/veículo, e o impacto."></textarea>
                    @error('descricao') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <x-select label="Responsável apurado" required wire:model="responsavel" :error="$errors->first('responsavel')">
                        @foreach (config('ocorrencias.responsaveis') as $codigo => $rotulo)
                            <option value="{{ $codigo }}">{{ $rotulo }}</option>
                        @endforeach
                    </x-select>
                    <x-input label="Prejuízo estimado (R$)" type="number" wire:model="valor_prejuizo" :error="$errors->first('valor_prejuizo')" />
                    <x-select label="Status" required wire:model="status" :error="$errors->first('status')">
                        @foreach (config('ocorrencias.status') as $codigo => $rotulo)
                            <option value="{{ $codigo }}">{{ $rotulo }}</option>
                        @endforeach
                    </x-select>
                </div>

                <div class="mt-4">
                    <x-input-label>Tratamento / providências</x-input-label>
                    <textarea wire:model="tratamento" rows="3"
                              class="mt-1.5 w-full rounded-md border border-border bg-white px-3 py-2 text-sm text-text outline-none
                                     transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20 dark:bg-surface-elevated"
                              placeholder="O que foi feito para resolver — acionamento de seguro, negociação, recuperação da carga…"></textarea>
                    @error('tratamento') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>
            </div>
        </x-card>

        <div class="flex flex-col gap-3">
            <x-card padding="sm">
                <p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-text-muted">Fluxo</p>
                <p class="text-xs leading-relaxed text-text-secondary">
                    Aberta → Em análise → Resolvida. Enquanto não resolvida, o prejuízo entra no total do painel e a ocorrência aparece nos alertas da operação.
                </p>
            </x-card>
            <x-card padding="sm">
                <div class="flex items-start gap-2 text-xs leading-relaxed text-text-secondary">
                    <x-icon name="info" class="mt-px h-3.5 w-3.5 flex-shrink-0 text-text-muted" />
                    <span>O vínculo com viagem e CT-e entra quando esses módulos existirem — o registro já fica pronto para recebê-lo.</span>
                </div>
            </x-card>
        </div>
    </div>
</div>
