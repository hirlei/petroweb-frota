{{-- Janelas "Registrar recebimento" e "Estornar recebimento" (rotinas 5010 e 5020).
     Componente que inclui precisa usar o trait App\Livewire\Concerns\RegistraRecebimento. --}}
@php $fmt = fn ($v) => number_format((float) $v, 2, ',', '.'); @endphp

@if ($recTituloId)
    <div class="fixed inset-0 z-[90] flex items-start justify-center bg-[rgba(15,26,58,.45)] px-4 pt-[10vh]" wire:keydown.escape.window="fecharRecebimento">
        <div class="w-full max-w-md rounded-2xl bg-surface p-5 shadow-modal" role="dialog" aria-modal="true" aria-label="Registrar recebimento">
            <h3 class="text-base font-bold text-text">Registrar recebimento</h3>
            <p class="mt-0.5 text-[12.5px] text-text-secondary">
                Título {{ $recInfo['numero'] ?? '' }} · {{ $recInfo['cliente'] ?? '' }} · vence em {{ $recInfo['vencimento'] ?? '' }}
            </p>
            <p class="mt-1 text-[12.5px] text-text-secondary">Saldo do título: <b class="font-mono text-text">R$ {{ $fmt($recInfo['saldo'] ?? 0) }}</b></p>

            <p class="mb-1.5 mt-4 text-[11px] font-semibold uppercase tracking-wide text-text-muted">Forma</p>
            <div class="grid grid-cols-4 gap-1.5">
                @foreach (config('financeiro.formas') as $codigo => $rotulo)
                    <button type="button" wire:click="$set('recForma', '{{ $codigo }}')"
                            class="rounded-lg border py-2 text-xs font-medium {{ $recForma === $codigo ? 'border-[var(--h6-azul)] bg-[var(--h6-hover-bg)] font-semibold text-[var(--h6-azul-tx)]' : 'border-border text-text-secondary' }}">
                        {{ $rotulo }}
                    </button>
                @endforeach
            </div>

            <div class="mt-3 grid grid-cols-2 gap-3">
                <x-input label="Data do recebimento" type="date" wire:model.live="recData" :error="$errors->first('recData')" />
                <x-input label="Conta" wire:model="recConta" placeholder="Ex.: Sicoob c/c 1234-5" />
            </div>
            <div class="mt-3 grid grid-cols-3 gap-3">
                <x-input label="Valor recebido" type="number" step="0.01" wire:model.live.debounce.400ms="recPrincipal" />
                <x-input label="Juros e multa" type="number" step="0.01" wire:model.live.debounce.400ms="recJuros" />
                <x-input label="Desconto" type="number" step="0.01" wire:model.live.debounce.400ms="recDesconto" />
            </div>
            @error('recPrincipal') <p class="mt-2 text-xs text-danger">{{ $message }}</p> @enderror

            <div class="mt-3 flex items-center justify-between border-t border-border pt-3">
                <span class="text-sm text-text-secondary">Total que entra no caixa</span>
                <b class="font-mono text-lg font-bold text-text">R$ {{ $fmt($this->totalRecebido()) }}</b>
            </div>
            <p class="mt-2 text-[11.5px] leading-relaxed text-text-secondary">
                @if (($recInfo['dias_atraso'] ?? 0) > 0)
                    {{ $recInfo['dias_atraso'] }} {{ $recInfo['dias_atraso'] === 1 ? 'dia' : 'dias' }} de atraso.
                    @if ($recInfo['multa'] !== null || $recInfo['juros'] !== null)
                        Sugestão pelo cadastro do cliente: multa de {{ $fmt($recInfo['multa'] ?? 0) }}% (R$ {{ $fmt($recInfo['multa_valor'] ?? 0) }})
                        e juros de {{ $fmt($recInfo['juros'] ?? 0) }}% ao mês (R$ {{ $fmt($recInfo['juros_valor'] ?? 0) }}).
                    @else
                        O cliente não tem multa nem juros no cadastro; informe se for cobrar.
                    @endif
                @else
                    Sem atraso.
                @endif
                Valor menor que o saldo deixa o restante em aberto.
            </p>
            <x-input class="mt-3" label="Observação" wire:model="recObs" placeholder="Opcional" />

            <div class="mt-5 flex justify-end gap-2">
                <x-button variant="neutral" size="sm" wire:click="fecharRecebimento">Cancelar</x-button>
                <x-button variant="success" size="sm" icon="check" wire:click="confirmarRecebimento" wire:loading.attr="disabled">Confirmar recebimento</x-button>
            </div>
        </div>
    </div>
@endif

@if ($estornoId)
    <div class="fixed inset-0 z-[90] flex items-start justify-center bg-[rgba(15,26,58,.45)] px-4 pt-[14vh]" wire:keydown.escape.window="fecharEstorno">
        <div class="w-full max-w-sm rounded-2xl bg-surface p-5 shadow-modal" role="dialog" aria-modal="true" aria-label="Estornar recebimento">
            <h3 class="text-base font-bold text-text">Estornar recebimento</h3>
            <p class="mt-0.5 text-[12.5px] text-text-secondary">O recebimento fica no histórico, marcado como estornado, e o valor volta para o saldo do título.</p>
            <x-input class="mt-4" label="Motivo do estorno" required wire:model="estornoMotivo" placeholder="Ex.: Pix devolvido pelo banco" :error="$errors->first('estornoMotivo')" />
            <div class="mt-5 flex justify-end gap-2">
                <x-button variant="neutral" size="sm" wire:click="fecharEstorno">Voltar</x-button>
                <x-button variant="danger" size="sm" wire:click="confirmarEstorno" wire:loading.attr="disabled">Estornar</x-button>
            </div>
        </div>
    </div>
@endif
