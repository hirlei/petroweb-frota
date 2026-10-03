{{-- Janela "Fornecedoras de vale-pedágio" (4030 e bloco do vale no 4020). --}}
<div>
    @if ($aberto)
        <div class="fixed inset-0 z-[95] flex items-start justify-center overflow-y-auto bg-[rgba(15,26,58,.45)] px-4 py-[8vh]" wire:keydown.escape.window="fechar">
            <div class="w-full max-w-xl rounded-2xl bg-surface p-5 shadow-2xl" role="dialog" aria-modal="true" aria-label="Fornecedoras de vale-pedágio">
                <h3 class="text-base font-bold text-text">Fornecedoras de vale-pedágio</h3>
                <p class="mt-0.5 text-[12.5px] text-text-secondary">Só fornecedora habilitada pela ANTT vale no MDF-e (senão, rejeição 733). Inativa não aparece na emissão.</p>

                <div class="mt-4 flex max-h-[38vh] flex-col gap-1.5 overflow-y-auto">
                    @forelse ($this->lista as $f)
                        <div wire:key="fvpo-{{ $f->id }}" class="flex items-center gap-2.5 rounded-lg border px-3 py-2.5 {{ $editandoId === $f->id ? 'border-[var(--h6-azul)] bg-[var(--h6-hover-bg)]' : 'border-border' }} {{ $f->ativo ? '' : 'opacity-60' }}">
                            <div class="min-w-0 flex-1">
                                <b class="block truncate text-[13px] font-semibold text-text">{{ $f->razao_social ?: 'Sem nome' }}</b>
                                <small class="text-[11.5px] text-text-secondary">CNPJ {{ \App\Domain\Cadastro\Documento::formatar((string) $f->cnpj) }}{{ $f->ato_habilitacao ? ' · ' . $f->ato_habilitacao : '' }}</small>
                            </div>
                            <button type="button" wire:click="alternarAtivo({{ $f->id }})" title="{{ $f->ativo ? 'Desativar' : 'Reativar' }}">
                                <x-badge :variant="$f->ativo ? 'success' : 'gray'" class="text-[11px]">{{ $f->ativo ? 'Ativa' : 'Inativa' }}</x-badge>
                            </button>
                            <button type="button" wire:click="editar({{ $f->id }})" class="text-xs font-semibold text-[var(--h6-azul-tx)] hover:underline">Editar</button>
                        </div>
                    @empty
                        <p class="rounded-lg bg-amber-50 px-3 py-2.5 text-[12.5px] text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">Nenhuma fornecedora ainda. Cadastre abaixo a que você usa.</p>
                    @endforelse
                </div>

                <p class="mb-2 mt-4 text-sm font-semibold text-text">{{ $editandoId ? 'Editar fornecedora' : 'Nova fornecedora' }}</p>
                <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-[1.1fr_1.3fr_1fr]">
                    <x-input label="CNPJ" wire:model="cnpj" placeholder="00.000.000/0000-00" maxlength="18" mono :error="$errors->first('cnpj')" />
                    <x-input label="Nome" wire:model="razao_social" placeholder="Ex.: Sem Parar" maxlength="150" :error="$errors->first('razao_social')" />
                    <x-input label="Habilitação ANTT" wire:model="ato_habilitacao" placeholder="Opcional" maxlength="60" />
                </div>
                <p class="mt-2 text-[11.5px] leading-relaxed text-text-secondary">As de exemplo têm CNPJ fictício: para valer, cadastre a fornecedora real (CNPJ da lista da ANTT ou do contrato) e desative as de exemplo.</p>

                <div class="mt-5 flex justify-end gap-2">
                    @if ($editandoId)
                        <x-button variant="neutral" size="sm" wire:click="cancelarEdicao">Cancelar edição</x-button>
                    @endif
                    <x-button variant="neutral" size="sm" wire:click="fechar">Fechar</x-button>
                    <x-button variant="primary" size="sm" :icon="$editandoId ? 'check' : 'plus'" wire:click="salvar" wire:loading.attr="disabled">{{ $editandoId ? 'Salvar' : 'Adicionar' }}</x-button>
                </div>
            </div>
        </div>
    @endif
</div>
