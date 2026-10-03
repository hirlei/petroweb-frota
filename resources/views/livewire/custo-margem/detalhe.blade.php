{{-- Rotina 3080 — custo de uma viagem: de onde vem cada valor (mockup aprovado em 03/10/2026). --}}
@php
    $d = $this->detalhe;
    $v = $viagem;
    $res = $d['resultado'];
    $fmt = fn ($x, $c = 2) => number_format((float) $x, $c, ',', '.');
    $mg = fn ($x) => (float) $x < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-700 dark:text-green-400';
    $ini = $v->saida_real ?? $v->saida_prevista;
    $fim = $v->chegada_real ?? $v->chegada_prevista;
    $lin = 'flex justify-between gap-3 py-1.5 text-[13px]';
    $semOs = $d['componentes']['manutencao']['valor'];
@endphp
<div class="viz-custo">
    @include('livewire.custo-margem.partials.cores')
    <a href="{{ route('custos.index') }}" wire:navigate class="mb-1.5 inline-flex items-center gap-1 text-[12.5px] text-text-secondary hover:text-text">‹ Custo e margem</a>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-text">Custo · viagem {{ $v->numero }}</h1>
            <p class="mt-1 text-sm text-text-secondary">
                {{ $v->motorista?->pessoa?->razao_social ?? '—' }}{{ $v->motorista?->controlaJornada() ? ' (CLT)' : '' }}
                · <span class="font-mono">{{ $v->veiculoTracao?->placaFormatada() }}</span>
                · {{ $v->municipioOrigem?->nome ?? '—' }} → {{ $v->municipioDestino?->nome ?? '—' }}
                @if ($ini) · {{ $ini->format('d/m') }}{{ $fim ? ' a ' . $fim->format('d/m') : '' }} @endif
            </p>
        </div>
        <div class="flex flex-shrink-0 items-center gap-2">
            <x-button variant="neutral" size="sm" wire:click="recalcular" wire:loading.attr="disabled">Recalcular agora</x-button>
            @can('viagem.consultar')
                <x-button variant="neutral" size="sm" :href="route('viagens.editar', $v)" wire:navigate>Abrir viagem (3020)</x-button>
            @endcan
        </div>
    </div>

    @if (session('sucesso'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800/50 dark:bg-green-950/50 dark:text-green-300">{{ session('sucesso') }}</div>
    @endif

    <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
        <x-card padding="sm">
            <p class="mb-2 text-[14px] font-semibold text-text">De onde vem cada valor</p>
            <div style="font-variant-numeric:tabular-nums">
                @foreach ($d['componentes'] as $k => $c)
                    <div class="flex justify-between gap-3 border-t border-border pt-2.5 pb-1 text-[13px] first:border-0">
                        <span class="flex items-center gap-2 font-semibold text-text"><span class="h-2.5 w-2.5 rounded-[3px]" style="background: var(--cm-{{ $k }})"></span>{{ $c['rotulo'] }}</span>
                        <b class="font-mono font-medium text-text">{{ $fmt($c['valor']) }}</b>
                    </div>
                    <div class="pb-2 pl-[18px]">
                        @forelse ($c['linhas'] as $l)
                            <div class="flex justify-between gap-3 py-0.5 text-xs text-text-secondary">
                                <span class="min-w-0">
                                    <span class="mr-1.5 rounded bg-[var(--h6-cod-bg)] px-1.5 py-px font-mono text-[10.5px] font-semibold text-[var(--h6-azul-tx)]">{{ $l['rotina'] }}</span>
                                    @if (! empty($l['url']))
                                        <a href="{{ $l['url'] }}" wire:navigate class="font-medium text-[var(--h6-azul-tx)] hover:underline">{{ $l['texto'] }}</a>
                                    @else
                                        <span class="text-text">{{ $l['texto'] }}</span>
                                    @endif
                                    @if (! empty($l['detalhe']))· {{ $l['detalhe'] }}@endif
                                </span>
                                <span class="flex-shrink-0 font-mono">{{ $l['valor'] !== null ? $fmt($l['valor']) : '—' }}</span>
                            </div>
                        @empty
                            <div class="py-0.5 text-xs italic text-text-muted">
                                {{ $k === 'terceiro' ? 'Sem frete a pagar a terceiro' : 'Nenhum lançamento' }}
                            </div>
                        @endforelse
                    </div>
                @endforeach
                <div class="mt-1 flex justify-between border-t-2 border-border pt-2.5 text-[14px] font-semibold text-text">
                    <span>Custo da viagem</span><b class="font-mono text-[15px]">R$ {{ $fmt($d['custo']) }}</b>
                </div>
            </div>
            @if ($d['glosado'] > 0)
                <p class="mt-2 text-[11.5px] text-text-secondary">R$ {{ $fmt($d['glosado']) }} em despesas glosadas no acerto: o motorista devolve, então não entram como custo.</p>
            @endif
        </x-card>

        <aside class="lg:sticky lg:top-0">
            <x-card padding="sm">
                <p class="mb-2 text-[14px] font-semibold text-text">Resultado</p>
                <div style="font-variant-numeric:tabular-nums">
                    @if ($d['receita_linhas'] !== [])
                        @foreach ($d['receita_linhas'] as $rl)
                            <div class="{{ $lin }}"><span class="text-text-secondary">Receita · <a href="{{ $rl['url'] }}" wire:navigate class="font-medium text-[var(--h6-azul-tx)] hover:underline">{{ $rl['texto'] }}</a></span><b class="font-mono font-medium">R$ {{ $fmt($rl['valor']) }}</b></div>
                        @endforeach
                    @else
                        <div class="{{ $lin }}"><span class="text-text-secondary">Receita{{ $d['receita_digitada'] ? ' · digitada na viagem' : ' · CT-e não autorizado' }}</span><b class="font-mono font-medium">R$ {{ $fmt($d['receita']) }}</b></div>
                    @endif
                    <div class="{{ $lin }}"><span class="text-text-secondary">(−) Custo</span><b class="font-mono font-medium">R$ {{ $fmt($d['custo']) }}</b></div>
                    <div class="mt-1 flex items-baseline justify-between gap-3 border-t-2 border-border pt-2.5">
                        <span class="text-[14px] font-semibold text-text">Margem{{ $res['percentual'] !== null ? ' · ' . $fmt($res['percentual'], 1) . '%' : '' }}</span>
                        <b class="font-mono text-lg {{ $mg($res['margem']) }}">R$ {{ $fmt($res['margem']) }}</b>
                    </div>
                    <div class="{{ $lin }} mt-2"><span class="text-text-secondary">Km rodado{{ $v->km_inicial !== null && $v->km_final !== null ? ' · ' . $fmt($v->km_inicial, 0) . ' → ' . $fmt($v->km_final, 0) : '' }}</span><b class="font-mono font-medium">{{ $d['km'] !== null ? $fmt($d['km'], 0) : '—' }}</b></div>
                    <div class="{{ $lin }}"><span class="text-text-secondary">Custo por km</span><b class="font-mono font-medium">{{ $res['custo_km'] !== null ? 'R$ ' . $fmt($res['custo_km']) : '—' }}</b></div>
                    <div class="{{ $lin }}"><span class="text-text-secondary">Receita por km</span><b class="font-mono font-medium">{{ $res['receita_km'] !== null ? 'R$ ' . $fmt($res['receita_km']) : '—' }}</b></div>
                </div>
                @if ($semOs > 0 && $d['km'])
                    <p class="mt-2 text-[11.5px] text-text-secondary">Sem a manutenção, o custo por km desta viagem seria R$ {{ $fmt(($d['custo'] - $semOs) / $d['km']) }}.</p>
                @endif
                @if ($d['estimado'])
                    <p class="mt-2 text-[11.5px] text-amber-700 dark:text-amber-400">Diárias e comissão estimadas pelo cadastro do motorista — o valor final sai quando o acerto (3070) fechar.</p>
                @endif
                <p class="mt-2 text-[11.5px] leading-relaxed text-text-secondary">Combustível e manutenção não se digitam na viagem: vêm do abastecimento (2060) e da OS (2050) lançados nela. Viagem antiga sem lançamento mantém o valor que foi digitado, marcado como "Digitado na viagem".</p>
                @if ($v->custos_recalculados_em)
                    <p class="mt-2 text-[11px] text-text-muted">Gravado em {{ $v->custos_recalculados_em->format('d/m/Y H:i') }}</p>
                @endif
            </x-card>
        </aside>
    </div>
</div>
