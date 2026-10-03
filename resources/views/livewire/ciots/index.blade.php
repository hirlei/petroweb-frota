{{--
    Rotina 4050 — CIOT (mockup aprovado em 02/10/2026).
    O registro acontece na emissão do MDF-e (4020); aqui é consulta, saldo,
    cancelamento e reenvio.
--}}
@php
    $r = $this->resumo;
    $fmt = fn ($v) => number_format((float) $v, 2, ',', '.');
    $kpiT = 'text-text-muted uppercase tracking-wide text-[10px] font-semibold';
    $kpiV = 'text-[21px] font-medium leading-tight mt-0.5';
    $modCores = config('ciot.modalidade_cores');
    $sitCores = config('ciot.situacao_cores');
@endphp
<div>
    <x-page-header title="CIOT" subtitle="Consulta, pagamento do saldo, cancelamento e reenvio. O registro acontece na emissão do MDF-e (4020)." />

    @if (config('ciot.driver') === 'fake')
        <div class="mb-4 flex items-start gap-2.5 rounded-xl bg-amber-50 px-4 py-2.5 text-[12.5px] leading-relaxed text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
            <x-icon name="info" class="mt-0.5 h-4 w-4 flex-shrink-0" />
            <div><b>Emissor de teste.</b> Os números gerados aqui são fictícios e não valem na fiscalização. Para valer: frete com TAC precisa de uma instituição de pagamento homologada pela ANTT (Repom, Pamcard, e-Frete…); frota própria registra direto na ANTT. A troca é só de configuração — as telas ficam iguais.</div>
        </div>
    @endif

    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4" style="font-variant-numeric:tabular-nums">
        <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
            <p class="{{ $kpiT }}">Viagens sem CIOT</p>
            <p class="{{ $kpiV }} {{ $r['aguardando'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-text' }}">{{ $r['aguardando'] }}</p>
            <p class="truncate text-[11px] text-text-secondary">Saem junto com o MDF-e de cada uma</p>
            <a href="{{ route('mdfe.index') }}" wire:navigate class="text-[10px] font-bold text-primary hover:underline">MDF-e →</a>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
            <p class="{{ $kpiT }}">Registrados no mês</p>
            <p class="{{ $kpiV }} text-text">{{ $r['registrados_mes'] }}</p>
            <p class="truncate text-[11px] text-text-secondary">{{ $r['mes_antt'] }} frota própria · {{ $r['mes_ipef'] }} com TAC · {{ $r['mes_informado'] }} terceiras</p>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
            <p class="{{ $kpiT }}">Saldo a pagar a TAC</p>
            <p class="{{ $kpiV }}" style="color:#2a78d6">R$ {{ $fmt($r['saldo']) }}</p>
            <p class="truncate text-[11px] text-text-secondary">{{ $r['saldo_qtd'] }} {{ $r['saldo_qtd'] === 1 ? 'contrato' : 'contratos' }} em aberto</p>
            <button type="button" wire:click="$set('situacao', 'a_quitar')" class="text-[10px] font-bold text-primary hover:underline">Ver a quitar →</button>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
            <p class="{{ $kpiT }}">Quitação vencendo</p>
            <p class="{{ $kpiV }} {{ $r['vencidos'] > 0 ? 'text-red-600 dark:text-red-400' : ($r['vencendo'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-text') }}">{{ $r['vencendo'] }}</p>
            <p class="truncate text-[11px] text-text-secondary">{{ $r['vencidos'] > 0 ? $r['vencidos'] . ' já vencido(s) · ' : '' }}Prazo legal: até 30 dias úteis</p>
        </div>
    </div>

    @if ($this->aguardando->isNotEmpty() && $situacao === '')
        <x-card padding="none" class="mb-4 overflow-hidden">
            <div class="flex items-center gap-2 border-b border-border px-5 py-3">
                <x-icon name="alert-triangle" class="h-4 w-4 text-red-600 dark:text-red-400" />
                <h2 class="text-sm font-semibold text-text">Aguardando emissão do MDF-e</h2>
                <span class="ml-auto text-xs text-text-muted">O CIOT é registrado no clique de emitir</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full" style="font-variant-numeric:tabular-nums">
                    <tbody>
                        @foreach ($this->aguardando as $m)
                            @php $v = $m->viagem; $mod = $v->modalidadeCiot(); $rec = $v->ciot?->status === 'recusado'; @endphp
                            <tr wire:key="ag-{{ $m->id }}" class="border-t border-border first:border-0">
                                <td class="whitespace-nowrap px-4 py-2.5 pl-5" style="box-shadow:inset 3px 0 0 #b91c1c">
                                    <x-button variant="primary" size="xs" :href="route('mdfe.editar', $m)" wire:navigate>{{ $m->status === 'rejeitado' || $rec ? 'Reenviar' : 'Emitir MDF-e' }}</x-button>
                                </td>
                                <td class="px-4 py-2.5 text-sm">
                                    <span class="font-mono text-xs font-semibold text-[var(--h6-azul-tx)]">{{ $v->numero }}</span>
                                    <span class="block text-[11px] text-text-secondary">{{ $v->municipioOrigem?->nome ?? '—' }} → {{ $v->municipioDestino?->nome ?? '—' }}{{ $v->saida_prevista ? ' · sai ' . $v->saida_prevista->format('d/m') : '' }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-sm">
                                    <span class="font-medium text-text">{{ $mod === 'antt' ? 'Frota própria' : ($v->motorista?->ehTac() ? $v->motorista?->pessoa?->razao_social : ($v->veiculoTracao?->proprietario?->razao_social ?? '—')) }}</span>
                                    <span class="block text-[11px] text-text-secondary">{{ $v->veiculoTracao?->placaFormatada() }}</span>
                                </td>
                                <td class="px-4 py-2.5"><x-badge :variant="$modCores[$mod] ?? 'gray'" class="text-[11px]">{{ config('ciot.modalidades.' . $mod) }}</x-badge></td>
                                <td class="px-4 py-2.5">
                                    @if ($rec)
                                        <x-badge variant="danger" class="text-[11px]">Recusado</x-badge>
                                        <span class="block max-w-[260px] truncate text-[11px] text-text-secondary" title="{{ $v->ciot->motivo }}">{{ $v->ciot->motivo }}</span>
                                    @else
                                        <x-badge variant="gray" class="text-[11px]">Aguardando MDF-e</x-badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    <div class="mb-4 flex flex-wrap items-center gap-1.5">
        @php $chips = ['' => 'Todos', 'recusados' => 'Recusados', 'a_quitar' => 'A quitar', 'quitados' => 'Quitados', 'cancelados' => 'Cancelados']; @endphp
        @foreach ($chips as $chave => $rotulo)
            <button type="button" wire:click="$set('situacao', '{{ $chave }}')"
                    class="rounded-full px-3 py-1.5 text-sm font-medium transition-colors
                           {{ $situacao === $chave ? 'bg-primary-soft text-amber-700 dark:text-amber-300' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' }}">
                {{ $rotulo }} · {{ $r['contagem'][$chave] }}
            </button>
        @endforeach
        <div class="flex-1"></div>
        <div class="relative w-full max-w-xs sm:w-auto sm:flex-1">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
            <input type="search" wire:model.live.debounce.400ms="busca" placeholder="CIOT, viagem, placa ou contratado…"
                   class="w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm text-text outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>
    </div>

    <x-card padding="none" class="overflow-hidden">
        @if ($this->ciots->isEmpty())
            <x-empty-state icon="key" title="Nenhum CIOT"
                           description="O CIOT é registrado na emissão do MDF-e de cada viagem e aparece aqui." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full" style="font-variant-numeric:tabular-nums">
                    <thead class="border-b border-border bg-surface-elevated">
                        <tr>
                            @foreach (['CIOT', 'Viagem', 'Contratado', 'Registro', 'Frete', 'Situação'] as $i => $cab)
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-text-muted first:pl-5 {{ $i === 4 ? 'text-right' : 'text-left' }}">{{ $cab }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->ciots as $c)
                            @php $sit = $c->situacao(); $v = $c->viagem; @endphp
                            <tr wire:key="ci-{{ $c->id }}" class="border-t border-border hover:bg-surface-elevated">
                                <td class="whitespace-nowrap px-4 py-3 pl-5" @if ($sit === 'recusado') style="box-shadow:inset 3px 0 0 #b91c1c" @endif>
                                    <a href="{{ route('ciot.ver', $c) }}" wire:navigate class="font-mono text-sm font-semibold text-[var(--h6-azul-tx)] hover:underline">{{ $c->numero ? $c->numeroFormatado() : 'Sem número' }}</a>
                                    <span class="block text-[11px] text-text-secondary">{{ $c->registrado_em ? 'Registrado ' . $c->registrado_em->format('d/m H:i') : ($c->status === 'recusado' ? $c->tentativas . ' tentativa(s)' : '—') }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="font-mono text-xs font-semibold text-[var(--h6-azul-tx)]">{{ $v?->numero }}</span>
                                    <span class="block text-[11px] text-text-secondary">{{ $v?->municipioOrigem?->nome ?? '—' }} → {{ $v?->municipioDestino?->nome ?? '—' }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="font-medium text-text">{{ $c->contratado_nome ?? 'Frota própria' }}</span>
                                    <span class="block text-[11px] text-text-secondary">{{ $v?->veiculoTracao?->placaFormatada() }}</span>
                                </td>
                                <td class="px-4 py-3"><x-badge :variant="$modCores[$c->modalidade] ?? 'gray'" class="whitespace-nowrap text-[11px]">{{ config('ciot.modalidades.' . $c->modalidade) }}</x-badge></td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-text">{{ (float) $c->valor_frete > 0 ? 'R$ ' . $fmt($c->valor_frete) : '—' }}</td>
                                <td class="px-4 py-3">
                                    <x-badge :variant="$sitCores[$sit] ?? 'gray'" class="text-[11px] {{ $sit === 'cancelado' ? 'line-through' : '' }}">{{ config('ciot.situacoes.' . $sit, $sit) }}</x-badge>
                                    <span class="block max-w-[240px] truncate text-[11px] text-text-secondary" @if ($sit === 'recusado') title="{{ $c->motivo }}" @endif>
                                        @switch($sit)
                                            @case('recusado') {{ $c->motivo }} @break
                                            @case('adiantado') Pago R$ {{ $fmt($c->valor_pago) }} · saldo até {{ $c->prazo_quitacao?->format('d/m') }} @break
                                            @case('a_quitar_vencido') Venceu em {{ $c->prazo_quitacao?->format('d/m') }} @break
                                            @case('registrado') {{ $c->temPagamento() ? 'Saldo até ' . $c->prazo_quitacao?->format('d/m') : 'Em viagem' }} @break
                                            @case('quitado') Quitado em {{ $c->quitado_em?->format('d/m') }} @break
                                            @case('cancelado') {{ $c->cancelado_em?->format('d/m') }} @break
                                        @endswitch
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-border px-5 py-3">{{ $this->ciots->links() }}</div>
        @endif
    </x-card>
</div>
