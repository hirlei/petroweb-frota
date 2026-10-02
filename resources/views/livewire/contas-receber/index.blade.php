{{--
    Rotina 5020 — Contas a receber (mockup aprovado em 02/10/2026).
    Parcelas das faturas por vencimento; receber aqui ou dentro da fatura.
--}}
@php
    $fmt = fn ($v, $d = 2) => number_format((float) $v, $d, ',', '.');
    $fx = $this->faixas;
    $cartoes = [
        'abertas' => ['Todas em aberto', ''],
        'a_vencer' => ['A vencer', ''],
        'hoje' => ['Vence hoje', 'text-amber-600 dark:text-amber-400'],
        'vencidas_30' => ['Vencidas até 30 dias', 'text-red-600 dark:text-red-400'],
        'vencidas_mais' => ['Vencidas há mais de 30', 'text-red-600 dark:text-red-400'],
    ];
@endphp
<div>
    <x-page-header title="Contas a receber" subtitle="Parcelas das faturas, por vencimento. Receba aqui ou dentro da fatura." />

    @include('livewire.financeiro.alertas')

    <div class="mb-3 grid grid-cols-2 gap-2 lg:grid-cols-5" style="font-variant-numeric:tabular-nums">
        @foreach ($cartoes as $chave => [$rotulo, $cor])
            <button type="button" wire:click="$set('faixa', '{{ $chave }}')"
                    class="rounded-[10px] border bg-surface px-3 py-2.5 text-left transition-colors {{ $faixa === $chave || ($chave === 'vencidas_30' && $faixa === 'vencidas') ? 'border-[var(--h6-azul)] shadow-[inset_0_0_0_1px_var(--h6-azul)]' : 'border-border hover:border-border-strong' }}">
                <small class="block text-[10px] font-semibold uppercase tracking-wide text-text-muted">{{ $rotulo }}</small>
                <b class="mt-0.5 block font-mono text-[15px] font-medium {{ $fx[$chave]['qtd'] > 0 ? $cor : '' }}">R$ {{ $fmt($fx[$chave]['valor'], 0) }}</b>
                <span class="text-[11px] text-text-secondary">{{ $fx[$chave]['qtd'] === 0 ? 'Nenhuma' : $fx[$chave]['qtd'] . ' ' . ($fx[$chave]['qtd'] === 1 ? 'parcela' : 'parcelas') }}</span>
            </button>
        @endforeach
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <div class="relative min-w-[220px] flex-1">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
            <input type="search" wire:model.live.debounce.400ms="busca" placeholder="Buscar por título, fatura ou cliente"
                   class="w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>
        <input type="month" wire:model.live="mes" title="Vencimento no mês" class="rounded-md border border-border bg-surface px-2.5 py-2 text-sm text-text">
        <button type="button" wire:click="$set('faixa', 'recebidas')"
                class="rounded-full px-3 py-1.5 text-sm font-medium {{ $faixa === 'recebidas' ? 'bg-primary-soft text-amber-700 dark:text-amber-300' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' }}">Recebidas</button>
    </div>

    <x-card padding="none" class="overflow-hidden">
        @if ($this->titulos->isEmpty())
            <x-empty-state icon="inbox" title="Nenhuma parcela aqui"
                           description="As parcelas aparecem quando uma fatura é gerada na rotina 5010." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full" style="font-variant-numeric:tabular-nums">
                    <thead class="border-b border-border bg-surface-elevated">
                        <tr>
                            @foreach (['Título', 'Cliente', 'Vencimento', 'Valor', 'Saldo', 'Situação', ''] as $i => $cab)
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-text-muted first:pl-5 {{ in_array($i, [3, 4], true) ? 'text-right' : 'text-left' }}">{{ $cab }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->titulos as $t)
                            <tr wire:key="tr-{{ $t->id }}" class="border-t border-border hover:bg-surface-elevated">
                                <td class="whitespace-nowrap px-4 py-3 pl-5">
                                    <a href="{{ route('faturas.ver', $t->fatura_id) }}" wire:navigate class="font-mono text-sm text-[var(--h6-azul-tx)] hover:underline">{{ $t->numero }}</a>
                                    <span class="block text-[11px] text-text-secondary">Fatura {{ $t->fatura?->numero }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm font-medium text-text">{{ $t->tomador?->razao_social }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-text-secondary">{{ $t->vencimento?->format('d/m/Y') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-text">R$ {{ $fmt($t->valor) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-sm {{ $t->vencido() ? 'text-red-600 dark:text-red-400' : 'text-text-secondary' }}">{{ $t->emAberto() ? 'R$ ' . $fmt($t->saldo()) : '—' }}</td>
                                <td class="px-4 py-3">
                                    @if ($t->vencido())
                                        <x-badge variant="danger" class="text-[11px]">Vencida há {{ $t->diasAtraso() }} {{ $t->diasAtraso() === 1 ? 'dia' : 'dias' }}</x-badge>
                                    @elseif ($t->emAberto() && $t->vencimento?->isToday())
                                        <x-badge variant="warning" class="text-[11px]">Vence hoje</x-badge>
                                    @elseif ($t->status === 'parcial')
                                        <x-badge variant="info" class="text-[11px]">Parcial</x-badge>
                                    @elseif ($t->status === 'recebido')
                                        <x-badge variant="success" class="text-[11px]">Recebido</x-badge>
                                    @else
                                        <x-badge variant="gray" class="text-[11px]">A vencer</x-badge>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    @if ($t->emAberto())
                                        @can('receber', $t)
                                            <x-button variant="success" size="xs" wire:click="abrirRecebimento({{ $t->id }})">Receber</x-button>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-border px-5 py-3">{{ $this->titulos->links() }}</div>
        @endif
    </x-card>

    @include('livewire.financeiro.modal-recebimento')
</div>
