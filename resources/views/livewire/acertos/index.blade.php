{{-- Rotina 3070 — Acerto de viagem (mockup aprovado em 03/10/2026). --}}
@php
    $r = $this->resumo;
    $fmt = fn ($v, $d = 2) => number_format((float) $v, $d, ',', '.');
    $kpiT = 'text-text-muted uppercase tracking-wide text-[10px] font-semibold';
    $kpiV = 'text-[21px] font-medium leading-tight mt-0.5';
@endphp
<div>
    <x-page-header title="Acerto de viagem" subtitle="Fechamento com o motorista da empresa: adiantamentos, despesas, diárias e o saldo." />

    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4" style="font-variant-numeric:tabular-nums">
        <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
            <p class="{{ $kpiT }}">Viagens a acertar</p>
            <p class="{{ $kpiV }} {{ $r['a_acertar'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-text' }}">{{ $r['a_acertar'] }}</p>
            <p class="truncate text-[11px] text-text-secondary">Entregues, com motorista CLT</p>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
            <p class="{{ $kpiT }}">Adiantado em aberto</p>
            <p class="{{ $kpiV }} text-text">R$ {{ $fmt($r['adiantado']) }}</p>
            <p class="truncate text-[11px] text-text-secondary">Dinheiro com os motoristas</p>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
            <p class="{{ $kpiT }}">Empresa paga</p>
            <p class="{{ $kpiV }}" style="color:#2a78d6">R$ {{ $fmt($r['paga']) }}</p>
            <p class="truncate text-[11px] text-text-secondary">{{ $r['paga_qtd'] }} {{ $r['paga_qtd'] === 1 ? 'acerto' : 'acertos' }} com saldo para o motorista</p>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
            <p class="{{ $kpiT }}">Motorista devolve</p>
            <p class="{{ $kpiV }} text-green-600 dark:text-green-400">R$ {{ $fmt($r['devolve']) }}</p>
            <p class="truncate text-[11px] text-text-secondary">{{ $r['devolve_qtd'] }} {{ $r['devolve_qtd'] === 1 ? 'acerto' : 'acertos' }} com sobra</p>
        </div>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <div class="relative min-w-[220px] flex-1">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
            <input type="search" wire:model.live.debounce.400ms="busca" placeholder="Buscar por viagem, motorista ou placa"
                   class="w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>
        @foreach (['a_acertar' => 'A acertar · ' . $r['a_acertar'], 'fechados' => 'Fechados · ' . $r['fechados'], 'todos' => 'Todos'] as $chave => $rotulo)
            <button type="button" wire:click="$set('situacao', '{{ $chave }}')"
                    class="rounded-full px-3 py-1.5 text-sm font-medium {{ $situacao === $chave ? 'bg-primary-soft text-amber-700 dark:text-amber-300' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' }}">{{ $rotulo }}</button>
        @endforeach
    </div>

    <x-card padding="none" class="overflow-hidden">
        @if ($this->viagens->isEmpty())
            <x-empty-state icon="check" title="Nada para acertar"
                           description="Viagens entregues com motorista CLT aparecem aqui. Agregado e autônomo são pagos pelo CIOT (4050)." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full" style="font-variant-numeric:tabular-nums">
                    <thead class="border-b border-border bg-surface-elevated">
                        <tr>
                            @foreach (['Viagem', 'Motorista', 'Período', 'Adiantado', 'Despesas', 'Saldo', 'Situação', ''] as $i => $cab)
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-text-muted first:pl-5 {{ in_array($i, [3, 4, 5], true) ? 'text-right' : 'text-left' }}">{{ $cab }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->viagens as $v)
                            @php
                                $a = $v->acerto;
                                $l = $a ? null : $this->linhas[$v->id];
                                $saldo = $a ? (float) $a->saldo : $l['saldo'];
                                $adiantado = $a ? (float) $a->total_adiantado : $l['adiantado'];
                                $despesas = $a ? (float) $a->gasto_adiantamento + (float) $a->bolso_aceito + (float) $a->glosado : $l['gasto_adiantamento'] + $l['bolso_aceito'] + $l['glosado'];
                                $dias = $a ? $a->dias : $l['dias'];
                                $ini = $v->saida_real ?? $v->saida_prevista;
                                $fim = $v->chegada_real ?? $v->chegada_prevista;
                            @endphp
                            <tr wire:key="ac-{{ $v->id }}" class="border-t border-border hover:bg-surface-elevated">
                                <td class="px-4 py-3 pl-5 text-sm">
                                    <span class="font-mono text-xs font-semibold text-[var(--h6-azul-tx)]">{{ $v->numero }}</span>
                                    <span class="block text-[11px] text-text-secondary">{{ $v->municipioOrigem?->nome ?? '—' }} → {{ $v->municipioDestino?->nome ?? '—' }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="font-medium text-text">{{ $v->motorista?->pessoa?->razao_social ?? '—' }}</span>
                                    <span class="block text-[11px] text-text-secondary">{{ $v->veiculoTracao?->placaFormatada() }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-text-secondary">{{ $ini?->format('d/m') ?? '—' }} a {{ $fim?->format('d/m') ?? '—' }}<span class="block text-[11px]">{{ $dias }} {{ $dias === 1 ? 'dia' : 'dias' }}</span></td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-text">R$ {{ $fmt($adiantado) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-text">R$ {{ $fmt($despesas) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-sm">
                                    @if ($saldo > 0)<span style="color:#2a78d6">R$ {{ $fmt($saldo) }}</span><span class="block font-sans text-[11px] text-text-secondary">Empresa paga</span>
                                    @elseif ($saldo < 0)<span class="text-green-600 dark:text-green-400">R$ {{ $fmt(-$saldo) }}</span><span class="block font-sans text-[11px] text-text-secondary">Motorista devolve</span>
                                    @else<span class="text-text-secondary">R$ 0,00</span><span class="block font-sans text-[11px] text-text-secondary">Zerado</span>@endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($a)
                                        <x-badge variant="success" class="text-[11px]">Fechado</x-badge>
                                        <span class="block text-[11px] text-text-secondary">{{ $a->conta_pagar_id ? 'Conta a pagar no 5030' : ($a->motoristaDevolve() ? ($a->devolvido_em ? 'Devolvido em ' . $a->devolvido_em->format('d/m') : 'Devolução pendente') : $a->fechado_em?->format('d/m/Y')) }}</span>
                                    @elseif ($l['pendentes'] > 0)
                                        <x-badge variant="warning" class="text-[11px]">Despesa pendente</x-badge>
                                        <span class="block text-[11px] text-text-secondary">{{ $l['pendentes'] }} sem conferência</span>
                                    @else
                                        <x-badge variant="gray" class="text-[11px]">Pronto para fechar</x-badge>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <x-button :variant="$a ? 'neutral' : 'primary'" size="xs" :href="route('acertos.ver', $v)" wire:navigate>{{ $a ? 'Abrir' : 'Acertar' }}</x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-border px-5 py-3 text-[12px] text-text-secondary">Viagem de agregado ou autônomo não tem acerto aqui — o frete dele é pago pelo CIOT (4050).</div>
            <div class="border-t border-border px-5 py-3">{{ $this->viagens->links() }}</div>
        @endif
    </x-card>
</div>
