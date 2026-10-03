{{-- Despesas da viagem no acerto (3070). $editavel: mostra Aceita/Glosa. --}}
@php
    $fmt = fn ($v, $d = 2) => number_format((float) $v, $d, ',', '.');
    $th = 'px-3 py-2 text-[11px] font-semibold uppercase tracking-wider text-text-muted';
    $td = 'px-3 py-2 text-[13px] align-top';
    $pagoCom = ['adiantamento' => 'Adiantamento', 'reembolso' => 'Do bolso', 'cartao' => 'Cartão da empresa', 'empresa' => 'Empresa'];
    $seg = fn ($on, $glosa = false) => 'px-2.5 py-1 text-xs font-medium ' . ($on ? ($glosa ? 'bg-red-100 text-red-800 dark:bg-red-500/25 dark:text-red-300' : 'bg-green-100 text-green-800 dark:bg-green-500/25 dark:text-green-300') : 'bg-surface text-text-secondary hover:bg-surface-elevated');
@endphp
@if ($despesas->isEmpty())
    <p class="rounded-lg border border-dashed border-border px-3 py-4 text-center text-[12.5px] text-text-muted">Nenhuma despesa lançada nesta viagem.</p>
@else
    <div class="overflow-x-auto rounded-[10px] border border-border">
        <table class="w-full" style="font-variant-numeric:tabular-nums">
            <thead class="bg-surface-elevated">
                <tr>
                    <th class="{{ $th }} text-left">Data</th>
                    <th class="{{ $th }} text-left">Despesa</th>
                    <th class="{{ $th }} text-left">Pago com</th>
                    <th class="{{ $th }} text-right">Valor</th>
                    <th class="{{ $th }} text-left">Conferência</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($despesas as $d)
                    @php
                        $entra = in_array($d->forma_pagamento, ['adiantamento', 'reembolso'], true);
                        $conf = $d->conferencia();
                    @endphp
                    <tr class="border-t border-border {{ $entra ? '' : 'opacity-60' }}" wire:key="dp-{{ $d->id }}">
                        <td class="{{ $td }} whitespace-nowrap text-text-secondary">{{ $d->data?->format('d/m') }}</td>
                        <td class="{{ $td }}">
                            <span class="text-text">{{ config('despesas.tipos.' . $d->tipo, $d->tipo) }}</span>
                            <small class="block text-[11px] text-text-secondary">{{ collect([$d->descricao, $d->comprovante_path ? 'com comprovante' : 'sem comprovante'])->filter()->implode(' · ') }}</small>
                        </td>
                        <td class="{{ $td }} whitespace-nowrap">{{ $pagoCom[$d->forma_pagamento] ?? $d->forma_pagamento }}</td>
                        <td class="{{ $td }} whitespace-nowrap text-right font-mono">R$ {{ $fmt($d->valor) }}</td>
                        <td class="{{ $td }} min-w-[170px]">
                            @if (! $entra)
                                <span class="text-[11.5px] text-text-muted">Não entra no acerto</span>
                            @elseif ($editavel && $glosaId === $d->id)
                                <div class="flex flex-col gap-1.5">
                                    <input type="text" wire:model="glosaMotivo" wire:keydown.enter="conferir({{ $d->id }}, 'glosa')" placeholder="Motivo da glosa (opcional)" maxlength="255" autofocus
                                           class="w-full rounded-md border border-border bg-white px-2 py-1 text-xs text-text outline-none focus:border-primary dark:bg-surface-elevated">
                                    <div class="flex gap-1.5">
                                        <x-button variant="danger" size="xs" wire:click="conferir({{ $d->id }}, 'glosa')">Glosar</x-button>
                                        <x-button variant="neutral" size="xs" wire:click="cancelarGlosa">Cancelar</x-button>
                                    </div>
                                </div>
                            @elseif ($editavel)
                                <span class="inline-flex overflow-hidden rounded-md border border-border">
                                    <button type="button" wire:click="conferir({{ $d->id }}, '{{ $conf === 'aceita' ? 'pendente' : 'aceita' }}')" class="{{ $seg($conf === 'aceita') }}">Aceita</button>
                                    <button type="button" wire:click="conferir({{ $d->id }}, '{{ $conf === 'glosa' ? 'pendente' : 'glosa' }}')" class="{{ $seg($conf === 'glosa', true) }} border-l border-border">Glosa</button>
                                </span>
                                @if ($conf === 'pendente')
                                    <small class="block text-[11px] font-semibold text-amber-600 dark:text-amber-400">Falta conferir</small>
                                @elseif ($conf === 'glosa')
                                    <small class="block text-[11px] text-text-secondary">{{ $d->motivo_glosa ?: ($d->forma_pagamento === 'adiantamento' ? 'Desconta do motorista' : 'Não reembolsa') }}</small>
                                @endif
                            @else
                                @if ($conf === 'aceita')
                                    <x-badge variant="success" class="text-[11px]">Aceita</x-badge>
                                @elseif ($conf === 'glosa')
                                    <x-badge variant="danger" class="text-[11px]">Glosa</x-badge>
                                    @if ($d->motivo_glosa)<small class="block text-[11px] text-text-secondary">{{ $d->motivo_glosa }}</small>@endif
                                @else
                                    <x-badge variant="warning" class="text-[11px]">Falta conferir</x-badge>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
