{{-- Rotina 3080 — Custo e margem (mockup aprovado em 03/10/2026). --}}
@php
    $r = $this->resumo;
    $fmt = fn ($v, $d = 2) => number_format((float) $v, $d, ',', '.');
    $kpiT = 'text-text-muted uppercase tracking-wide text-[10px] font-semibold';
    $kpiV = 'text-[21px] font-medium leading-tight mt-0.5';
    $rotulo = 'mb-1.5 block text-[10.5px] font-semibold uppercase tracking-wide text-text-muted';
    $th = 'px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-text-muted';
    $mg = fn ($v) => (float) $v < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-700 dark:text-green-400';
    $barra = function (?float $pct) {
        $w = $pct === null ? 0 : min(100, abs($pct) * 2);
        $cor = ($pct ?? 0) < 0 ? 'bg-red-600 dark:bg-red-400' : 'bg-green-700 dark:bg-green-400';

        return '<span class="ml-1.5 inline-block h-1.5 w-14 overflow-hidden rounded-full bg-surface-elevated align-middle"><i class="block h-full rounded-full ' . $cor . '" style="width:' . $w . '%"></i></span>';
    };
    $p = $r['pendencias'];
@endphp
<div class="viz-custo">
    @include('livewire.custo-margem.partials.cores')
    <x-page-header title="Custo e margem" subtitle="Quanto cada viagem rendeu: receita dos CT-e menos tudo o que ela custou." />

    <div class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <span class="{{ $rotulo }}">Mês</span>
            <select wire:model.live="mes" class="min-w-[170px] rounded-md border border-border bg-white px-3 py-2 text-sm text-text dark:bg-surface-elevated">
                @foreach ($this->meses as $valor => $nome)<option value="{{ $valor }}">{{ $nome }}</option>@endforeach
            </select>
        </div>
        <div>
            <span class="{{ $rotulo }}">Filial</span>
            <select wire:model.live="filial" class="min-w-[170px] rounded-md border border-border bg-white px-3 py-2 text-sm text-text dark:bg-surface-elevated">
                <option value="">Todas</option>
                @foreach ($this->filiais as $f)<option value="{{ $f->id }}">{{ $f->nome_fantasia ?: $f->razao_social }}</option>@endforeach
            </select>
        </div>
        <div>
            <span class="{{ $rotulo }}">Ver por</span>
            <div class="flex gap-0.5 rounded-[9px] border border-border bg-surface-elevated p-[3px]">
                @foreach (\App\Livewire\CustoMargem\Index::VISOES as $chave => $nome)
                    <button type="button" wire:click="$set('por', '{{ $chave }}')"
                            class="rounded-md px-2.5 py-1 text-xs font-medium {{ $por === $chave ? 'bg-surface text-text shadow-sm' : 'text-text-secondary hover:text-text' }}">{{ $nome }}</button>
                @endforeach
            </div>
        </div>
    </div>

    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4" style="font-variant-numeric:tabular-nums">
        <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
            <p class="{{ $kpiT }}">Receita</p>
            <p class="{{ $kpiV }} text-text">R$ {{ $fmt($r['receita'], 0) }}</p>
            <p class="truncate text-[11px] text-text-secondary">{{ $r['qtd'] }} {{ $r['qtd'] === 1 ? 'viagem' : 'viagens' }} · CT-e autorizados</p>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
            <p class="{{ $kpiT }}">Custo</p>
            <p class="{{ $kpiV }} text-text">R$ {{ $fmt($r['custo'], 0) }}</p>
            <p class="truncate text-[11px] text-text-secondary">Lançamentos ligados às viagens</p>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
            <p class="{{ $kpiT }}">Margem</p>
            <p class="{{ $kpiV }} {{ $mg($r['margem']) }}">R$ {{ $fmt($r['margem'], 0) }}</p>
            <p class="truncate text-[11px] text-text-secondary">{{ $r['pct'] !== null ? $fmt($r['pct'], 1) . '% da receita' : 'Sem receita no mês' }}</p>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
            <p class="{{ $kpiT }}">Custo por km</p>
            <p class="{{ $kpiV }} text-text">{{ $r['custo_km'] !== null ? 'R$ ' . $fmt($r['custo_km']) : '—' }}</p>
            <p class="truncate text-[11px] text-text-secondary">{{ $r['receita_km'] !== null ? 'Receita R$ ' . $fmt($r['receita_km']) . '/km · ' . $fmt($r['km'], 0) . ' km' : 'Sem km lançado' }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
        <x-card padding="sm">
            <p class="mb-3 flex items-center justify-between gap-2 text-[14px] font-semibold text-text">
                Para onde foi o custo<span class="text-[11.5px] font-normal text-text-secondary">R$ {{ $fmt($r['custo']) }} no mês</span>
            </p>
            @if ($r['custo'] > 0)
                <div class="mb-3 flex h-[22px] gap-[2px] overflow-hidden rounded-md" role="img"
                     aria-label="Composição do custo: {{ collect($r['componentes'])->map(fn ($c) => $c['rotulo'] . ' ' . $fmt($c['pct'], 0) . '%')->implode(', ') }}">
                    @foreach ($r['componentes'] as $k => $c)
                        @if ($c['valor'] > 0)
                            <i class="block h-full" style="width: {{ $c['pct'] }}%; background: var(--cm-{{ $k }})" title="{{ $c['rotulo'] }}: R$ {{ $fmt($c['valor']) }} · {{ $fmt($c['pct'], 1) }}%"></i>
                        @endif
                    @endforeach
                </div>
            @else
                <p class="mb-3 rounded-lg border border-dashed border-border px-3 py-4 text-center text-[12.5px] text-text-muted">Nenhum custo lançado nas viagens do mês.</p>
            @endif
            <div class="grid grid-cols-1 gap-x-5 gap-y-1.5 sm:grid-cols-3">
                @foreach ($r['componentes'] as $k => $c)
                    <div class="flex items-center gap-2 text-xs text-text-secondary">
                        <span class="h-2.5 w-2.5 flex-shrink-0 rounded-[3px]" style="background: var(--cm-{{ $k }})"></span>{{ $c['rotulo'] }}
                        <b class="ml-auto font-mono font-medium text-text">{{ $fmt($c['valor'], 0) }}</b>
                        <small class="w-9 text-right text-[11px] text-text-muted">{{ $fmt($c['pct'], 0) }}%</small>
                    </div>
                @endforeach
            </div>
        </x-card>

        <aside>
            <x-card padding="sm">
                <p class="mb-2 text-[14px] font-semibold text-text">Custo ainda incompleto</p>
                @php
                    $itens = [
                        ['n' => $p['sem_abastecimento'], 't' => 'sem abastecimento lançado', 'd' => 'O combustível vem do 2060. Sem lançamento, a margem aparece maior do que é.'],
                        ['n' => $p['acerto_aberto'], 't' => 'com acerto ainda aberto', 'd' => 'Diárias e comissão entram estimadas pelo cadastro do motorista até o acerto fechar (3070).'],
                        ['n' => $p['sem_km'], 't' => 'sem km final', 'd' => 'Sem km, o custo por km fica em branco.'],
                        ['n' => $p['sem_cte'], 't' => 'sem CT-e nem receita', 'd' => 'Confira se falta vincular o CT-e na viagem (3020).'],
                    ];
                    $algum = collect($itens)->sum('n') > 0;
                @endphp
                @foreach ($itens as $it)
                    @if ($it['n'] > 0)
                        <div class="flex gap-2.5 border-t border-border py-2.5 text-[12.5px] first-of-type:border-0">
                            <span class="flex h-5 min-w-[22px] flex-shrink-0 items-center justify-center rounded-md bg-amber-50 px-1 font-mono text-[11px] font-bold text-amber-700 dark:bg-amber-950/50 dark:text-amber-300">{{ $it['n'] }}</span>
                            <div class="text-text">{{ $it['n'] === 1 ? 'Viagem' : 'Viagens' }} {{ $it['t'] }}<small class="block text-[11.5px] text-text-secondary">{{ $it['d'] }}</small></div>
                        </div>
                    @endif
                @endforeach
                @unless ($algum)
                    <p class="text-[12.5px] text-green-700 dark:text-green-400">Tudo lançado nas viagens do mês.</p>
                @endunless
                <p class="mt-2.5 text-[11.5px] leading-relaxed text-text-secondary">Os números se refazem a cada lançamento (abastecimento, despesa, OS, CIOT, acerto) e de novo toda noite.</p>
            </x-card>
        </aside>
    </div>

    <x-card padding="none" class="mt-4 overflow-hidden">
        @if ($por === 'viagem')
            @if ($this->viagens->isEmpty())
                <x-empty-state icon="route" title="Nenhuma viagem no mês" description="Entram aqui as viagens que saíram no mês escolhido, menos as canceladas." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full" style="font-variant-numeric:tabular-nums">
                        <thead class="border-b border-border bg-surface-elevated">
                            <tr>
                                @foreach (['Viagem', 'Veículo · motorista', 'Cliente', 'Km', 'Receita', 'Custo', 'Margem', 'Custo/km'] as $i => $cab)
                                    <th class="{{ $th }} {{ $i >= 3 ? 'text-right' : 'text-left' }} first:pl-5">{{ $cab }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->viagens as $v)
                                @php
                                    $pct = $v->percentualMargem();
                                    $fechada = in_array($v->status, ['entregue', 'encerrada'], true);
                                    $clientes = $v->ctes->whereIn('status', ['autorizado', 'contingencia'])->pluck('tomador.razao_social')->filter()->unique();
                                    $flags = array_filter([
                                        $fechada && $v->veiculoTracao?->propriedade === \App\Models\Veiculo::PROPRIEDADE_PROPRIA && ! $v->abastecimentos_exists ? 'Sem abastecimento' : null,
                                        $fechada && $v->motorista?->controlaJornada() && $v->acerto === null ? 'Acerto aberto' : null,
                                        $fechada && $v->km_final === null ? 'Sem km final' : null,
                                        $v->ctes->isEmpty() && (float) $v->receita_total <= 0 ? 'Sem CT-e' : null,
                                    ]);
                                @endphp
                                <tr wire:key="cm-{{ $v->id }}" class="border-t border-border text-sm hover:bg-surface-elevated">
                                    <td class="px-4 py-2.5 pl-5">
                                        <a href="{{ route('custos.ver', $v) }}" wire:navigate class="font-mono text-xs font-semibold text-[var(--h6-azul-tx)] hover:underline">{{ $v->numero }}</a>
                                        <span class="block text-[11px] text-text-secondary">{{ $v->municipioOrigem?->nome ?? '—' }} → {{ $v->municipioDestino?->nome ?? '—' }}</span>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <span class="font-mono text-xs font-semibold">{{ $v->veiculoTracao?->placaFormatada() }}</span>
                                        <span class="block text-[11px] text-text-secondary">{{ $v->motorista?->pessoa?->razao_social ?? '—' }}</span>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        {{ $clientes->isNotEmpty() ? $clientes->implode(', ') : '—' }}
                                        @if ($flags)
                                            <span class="block">@foreach ($flags as $f)<span class="mr-1 mt-0.5 inline-block rounded-full bg-amber-50 px-2 py-px text-[10.5px] font-semibold text-amber-700 dark:bg-amber-950/50 dark:text-amber-300">{{ $f }}</span>@endforeach</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-mono">{{ $v->km_percorrido !== null ? $fmt($v->km_percorrido, 0) : '—' }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono">{{ $fmt($v->receita_total) }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono">{{ $fmt($v->custo_total) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right font-mono">
                                        <span class="{{ $mg($v->margem) }}">{{ $fmt($v->margem) }}</span>
                                        <small class="block text-[11px] {{ $mg($v->margem) }}">{{ $pct !== null ? $fmt($pct, 1) . '%' : 'Sem receita' }}{!! $barra($pct) !!}</small>
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-mono">{{ $v->custo_por_km !== null ? $fmt($v->custo_por_km) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-border px-5 py-3 text-[12px] text-text-secondary">
                    <span>Piores margens primeiro</span>
                    <div>{{ $this->viagens->links() }}</div>
                </div>
            @endif
        @else
            @php $g = $this->grupos; $temKm = $por !== 'cliente'; @endphp
            @if ($g === [])
                <x-empty-state icon="route" title="Nenhuma viagem no mês" description="Entram aqui as viagens que saíram no mês escolhido, menos as canceladas." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full" style="font-variant-numeric:tabular-nums">
                        <thead class="border-b border-border bg-surface-elevated">
                            <tr>
                                <th class="{{ $th }} pl-5 text-left">{{ \App\Livewire\CustoMargem\Index::VISOES[$por] }}</th>
                                <th class="{{ $th }} text-right">Viagens</th>
                                @if ($temKm)<th class="{{ $th }} text-right">Km</th>@endif
                                <th class="{{ $th }} text-right">Receita</th><th class="{{ $th }} text-right">Custo</th><th class="{{ $th }} text-right">Margem</th>
                                @if ($temKm)<th class="{{ $th }} text-right">Custo/km</th><th class="{{ $th }} text-right">Receita/km</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($g as $l)
                                <tr wire:key="g-{{ $por }}-{{ $l['chave'] }}" class="border-t border-border text-sm">
                                    <td class="px-4 py-2.5 pl-5">
                                        <span class="{{ $por === 'veiculo' ? 'font-mono text-xs font-semibold' : 'font-medium text-text' }}">{{ $l['nome'] }}</span>
                                        @if ($l['sub'] !== '' || $l['fora'] > 0)
                                            <span class="block text-[11px] text-text-secondary">{{ $l['sub'] }}{{ $l['fora'] > 0 ? ($l['sub'] !== '' ? ' · ' : '') . 'inclui R$ ' . $fmt($l['fora']) . ' de manutenção fora de viagem' : '' }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-mono">{{ $l['viagens'] }}</td>
                                    @if ($temKm)<td class="px-4 py-2.5 text-right font-mono">{{ $l['km'] > 0 ? $fmt($l['km'], 0) : '—' }}</td>@endif
                                    <td class="px-4 py-2.5 text-right font-mono">{{ $fmt($l['receita']) }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono">{{ $fmt($l['custo']) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right font-mono">
                                        <span class="{{ $mg($l['margem']) }}">{{ $fmt($l['margem']) }}</span>
                                        <small class="block text-[11px] {{ $mg($l['margem']) }}">{{ $l['pct'] !== null ? $fmt($l['pct'], 1) . '%' : 'Sem receita' }}{!! $barra($l['pct']) !!}</small>
                                    </td>
                                    @if ($temKm)
                                        <td class="px-4 py-2.5 text-right font-mono">{{ $l['custo_km'] !== null ? $fmt($l['custo_km']) : '—' }}</td>
                                        <td class="px-4 py-2.5 text-right font-mono">{{ $l['receita_km'] !== null ? $fmt($l['receita_km']) : '—' }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="border-t border-border px-5 py-3 text-[11.5px] leading-relaxed text-text-secondary">
                    Piores margens primeiro.
                    @if ($por === 'cliente') A viagem com CT-e de mais de um tomador tem o custo dividido na proporção da receita de cada um. @endif
                    @if ($por === 'veiculo') Manutenção que não foi lançada numa viagem (preventiva na oficina) entra no custo do veículo, em "fora de viagem". @endif
                </p>
            @endif
        @endif
    </x-card>
</div>
