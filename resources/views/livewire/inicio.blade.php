{{--
    Dashboard — a mesma tela inicial do ERP (02/10/2026, mockup "Frota igual ao ERP").
    Três andares, uma pergunta por andar:
      1. COMO ESTOU AGORA?  cartões curtos (título 10px, valor 21px, uma linha de contexto, link/barra);
      2. VIAGENS EM ANDAMENTO  (no lugar do "Nível dos tanques" do ERP);
      3. ANÁLISES + ATENÇÃO  barras horizontais (azul #2a78d6 / verde #1baf7a, como no ERP).
--}}
@php
    $c = $this->cartoes;
    $j = $this->janela;
    $user = auth()->user();
    $verde = 'text-green-700 dark:text-green-400';
    $vermelho = 'text-red-700 dark:text-red-400';
    $fmt = fn ($v, $d = 2) => number_format((float) $v, $d, ',', '.');
    $andamento = $this->viagensAndamento;
    $alertas = $this->alertas();
    $corSit = ['ok' => $verde, 'dn' => $vermelho, 'wa' => 'text-amber-700 dark:text-amber-400'];
    $barraSit = ['ok' => '#2a78d6', 'dn' => '#dc2626', 'wa' => '#d97706'];
@endphp
<div class="space-y-4">

    {{-- ═══════════ Topo: contexto, saudação, período ═══════════ --}}
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-3">
        <div>
            <p class="text-[11px] text-text-muted uppercase tracking-wider mb-1">
                Dashboard · {{ \App\Support\TenantContext::filial()?->nome_fantasia ?? 'Todas as filiais' }}
            </p>
            <h1 class="text-xl font-semibold text-text">Olá, {{ $user?->name }}</h1>
            <p class="text-sm text-text-secondary">
                {{ \Illuminate\Support\Str::ucfirst(now()->locale('pt_BR')->isoFormat('dddd, DD [de] MMMM [de] YYYY')) }}
                @if ($this->totalAndamento > 0 && Route::has('viagens.index'))
                    · <a href="{{ route('viagens.index') }}" class="inline-flex items-center gap-1.5 text-primary font-medium hover:underline">
                        <span class="relative flex h-1.5 w-1.5" aria-hidden="true">
                            <span class="motion-safe:animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-primary"></span>
                        </span>
                        {{ $this->totalAndamento }} {{ $this->totalAndamento === 1 ? 'viagem em andamento' : 'viagens em andamento' }}
                    </a>
                @endif
            </p>
        </div>

        <div class="flex gap-1 text-xs" role="group" aria-label="Período">
            @foreach ($this->periodos() as $valor => $rotulo)
                <button type="button" wire:click="usarPeriodo('{{ $valor }}')" @if ($periodo === $valor) aria-current="true" @endif
                        class="flex-1 lg:flex-none text-center px-3 py-1.5 rounded-md transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary {{ $periodo === $valor ? 'bg-primary text-white font-semibold' : 'bg-surface-elevated text-text-secondary hover:bg-surface border border-border' }}">
                    {{ $rotulo }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- ═══════════ ANDAR 1 — Como estou agora? ═══════════ --}}
    <div>
        <h2 class="text-[11px] font-semibold text-text-muted uppercase tracking-wide mb-1.5">{{ $j['rotulo'] }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3" style="font-variant-numeric:tabular-nums">

            {{-- Faturamento (CT-e autorizados) --}}
            <div class="bg-surface border border-border rounded-xl" style="padding:12px 14px">
                <p class="text-text-muted uppercase tracking-wide" style="font-size:10px;font-weight:600">Faturamento</p>
                <p class="text-text" style="font-size:21px;font-weight:500;line-height:1.25;margin-top:2px">R$ {{ $fmt($c['faturamento']['valor']) }}</p>
                <p class="text-text-secondary truncate" style="font-size:11px">
                    {{ $c['faturamento']['qtd'] }} CT-e
                    @if ($c['faturamento']['comp'] !== null)
                        · <span class="{{ $c['faturamento']['comp'] >= 0 ? $verde : $vermelho }}" style="font-weight:600">{{ $c['faturamento']['comp'] >= 0 ? '+' : '' }}{{ $fmt($c['faturamento']['comp'], 1) }}% {{ $j['vs'] }}</span>
                    @endif
                </p>
                @if (Route::has('cte.index'))
                    <a href="{{ route('cte.index') }}" class="text-primary hover:underline" style="font-size:10px;font-weight:700">CT-e →</a>
                @endif
            </div>

            {{-- Margem das viagens --}}
            <div class="bg-surface border border-border rounded-xl" style="padding:12px 14px" title="Receita dos CT-e menos os custos das viagens que saíram no período">
                <p class="text-text-muted uppercase tracking-wide" style="font-size:10px;font-weight:600">Margem das viagens</p>
                @if ($c['margem']['pct'] === null)
                    <p class="text-text-muted" style="font-size:21px;font-weight:500;line-height:1.25;margin-top:2px">—</p>
                    <p class="text-text-muted truncate" style="font-size:11px">Nenhuma viagem com receita no período</p>
                @else
                    @php $mp = $c['margem']['pct']; @endphp
                    <p class="{{ $mp < 0 ? 'text-red-600' : ($mp < 15 ? 'text-amber-600' : 'text-green-600') }}" style="font-size:21px;font-weight:500;line-height:1.25;margin-top:2px">{{ $fmt($mp, 1) }}%</p>
                    <p class="text-text-secondary truncate" style="font-size:11px">R$ {{ $fmt($c['margem']['valor']) }} · {{ $c['margem']['qtd'] }} {{ $c['margem']['qtd'] === 1 ? 'viagem' : 'viagens' }}</p>
                @endif
                @if (Route::has('viagens.index'))
                    <a href="{{ route('viagens.index') }}" class="text-primary hover:underline" style="font-size:10px;font-weight:700">Viagens →</a>
                @endif
            </div>

            {{-- Custo por km --}}
            <div class="bg-surface border border-border rounded-xl" style="padding:12px 14px">
                <p class="text-text-muted uppercase tracking-wide" style="font-size:10px;font-weight:600">Custo por km</p>
                @if ($c['custo_km']['valor'] === null)
                    <p class="text-text-muted" style="font-size:21px;font-weight:500;line-height:1.25;margin-top:2px">—</p>
                    <p class="text-text-muted truncate" style="font-size:11px">Sem km rodado no período</p>
                @else
                    <p class="text-text" style="font-size:21px;font-weight:500;line-height:1.25;margin-top:2px">R$ {{ $fmt($c['custo_km']['valor']) }}</p>
                    <p class="text-text-secondary truncate" style="font-size:11px">{{ $c['custo_km']['partes'] ?: '—' }}</p>
                @endif
                @if (Route::has('despesas.index'))
                    <a href="{{ route('despesas.index') }}" class="text-primary hover:underline" style="font-size:10px;font-weight:700">Despesas de viagem →</a>
                @endif
            </div>

            {{-- Frota em uso — valor em AZUL, como o "Disponível hoje" do ERP --}}
            <div class="bg-surface border border-border rounded-xl" style="padding:12px 14px" title="Cavalos em viagem (carregando ou em trânsito) sobre os cavalos ativos">
                <p class="text-text-muted uppercase tracking-wide" style="font-size:10px;font-weight:600">Frota em uso</p>
                <p style="font-size:21px;font-weight:500;line-height:1.25;margin-top:2px;color:#2a78d6">{{ $c['frota']['em_uso'] }} de {{ $c['frota']['total'] }}</p>
                <p class="text-text-secondary truncate" style="font-size:11px">
                    {{ max($c['frota']['total'] - $c['frota']['em_uso'], 0) }} parados @if ($c['frota']['manutencao'] > 0) · {{ $c['frota']['manutencao'] }} em manutenção @endif
                </p>
                @if (Route::has('veiculos.index'))
                    <a href="{{ route('veiculos.index') }}" class="text-primary hover:underline" style="font-size:10px;font-weight:700">Veículos →</a>
                @endif
            </div>

            {{-- Entregas --}}
            <div class="bg-surface border border-border rounded-xl" style="padding:12px 14px">
                <p class="text-text-muted uppercase tracking-wide" style="font-size:10px;font-weight:600">Entregas</p>
                <p class="text-text" style="font-size:21px;font-weight:500;line-height:1.25;margin-top:2px">{{ $c['entregas']['ok'] }} de {{ $c['entregas']['total'] }}</p>
                @php $semComp = $c['entregas']['total'] - $c['entregas']['ok']; @endphp
                <p class="text-text-secondary truncate" style="font-size:11px">
                    @if ($c['entregas']['total'] === 0)
                        Nenhuma entrega no período
                    @elseif ($semComp > 0)
                        <span class="text-amber-700 dark:text-amber-400" style="font-weight:600">{{ $semComp }} sem comprovante</span>
                    @else
                        Todas com comprovante
                    @endif
                </p>
                @if ($c['entregas']['total'] > 0)
                    @php $pe = round($c['entregas']['ok'] / $c['entregas']['total'] * 100); @endphp
                    <div class="flex items-center gap-1.5" style="margin-top:5px" title="{{ $pe }}% com comprovante">
                        <div class="h-1 bg-border rounded-full overflow-hidden flex-1">
                            <div class="h-full {{ $pe >= 100 ? 'bg-green-500' : ($pe >= 70 ? 'bg-primary' : 'bg-amber-500') }}" style="width: {{ $pe }}%"></div>
                        </div>
                        <span class="text-text-muted" style="font-size:9px;font-weight:700">{{ $pe }}%</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ═══════════ ANDAR 2 — Viagens em andamento ═══════════ --}}
    <x-card padding="md">
        <div class="flex items-center justify-between gap-2 mb-4 flex-wrap">
            <h2 class="text-base font-semibold text-text">Viagens em andamento</h2>
            <span class="text-xs text-text-muted">
                {{ $this->totalAndamento }} {{ $this->totalAndamento === 1 ? 'viagem' : 'viagens' }} · progresso pela previsão de chegada
                @if (Route::has('viagens.index')) · <a href="{{ route('viagens.index') }}" class="text-primary font-medium hover:underline">Ver todas →</a>@endif
            </span>
        </div>

        @if ($andamento->isEmpty())
            <p class="text-sm text-text-muted">Nenhuma viagem carregando ou em trânsito agora.</p>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-2" style="font-variant-numeric:tabular-nums">
                @foreach ($andamento as $v)
                    <a href="{{ $v['url'] }}" class="block rounded-[10px] border border-border bg-surface-elevated p-2.5 min-w-0 hover:border-border-strong">
                        <div class="flex items-center justify-between gap-1.5">
                            <span class="font-mono text-[11px] font-semibold border border-border-strong rounded px-1.5 bg-surface">{{ $v['placa'] }}</span>
                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold {{ $corSit[$v['cor']] }}"><i class="w-1.5 h-1.5 rounded-full bg-current"></i>{{ $v['situacao'] }}</span>
                        </div>
                        <p class="text-xs font-semibold text-text mt-2 truncate">{{ $v['motorista'] }}</p>
                        <p class="text-[11px] text-text-secondary truncate" title="{{ $v['rota'] }}">{{ $v['rota'] }}</p>
                        <div class="relative h-1.5 rounded-full bg-border mt-3 mb-1.5">
                            <span class="absolute inset-y-0 left-0 rounded-full" style="width: {{ $v['pct'] }}%; background: {{ $barraSit[$v['cor']] }}"></span>
                            @if ($v['pct'] > 0)
                                <span class="absolute top-1/2 w-3 h-3 -mt-1.5 -ml-1.5 rounded-full bg-surface" style="left: {{ $v['pct'] }}%; border: 2.5px solid {{ $barraSit[$v['cor']] }}"></span>
                            @endif
                        </div>
                        <div class="flex justify-between gap-1.5 text-[10.5px] text-text-muted whitespace-nowrap">
                            <span>{{ $v['pct'] > 0 ? $v['pct'] . '% · ' : '' }}{{ $v['numero'] }}</span>
                            <span>@if ($v['chegada'])Chega <b class="{{ $v['cor'] === 'dn' ? $vermelho : 'text-text' }} font-semibold">{{ $v['chegada'] }}</b>@else Sem previsão @endif</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </x-card>

    {{-- ═══════════ ANDAR 3 — Análises + atenção ═══════════ --}}
    <div>
        <h2 class="text-[11px] font-semibold text-text-muted uppercase tracking-wide mb-2">Análises</h2>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Custo por componente --}}
            <x-card padding="md" class="h-full">
                <h3 class="text-sm font-semibold text-text mb-0.5">Custo por componente</h3>
                <p class="text-xs text-text-secondary mb-4">Mês · viagens que saíram no mês</p>
                @php $cc = $this->custoComponentes; $totCc = array_sum($cc); $maxCc = $cc ? max($cc) : 0; @endphp
                @if ($totCc <= 0)
                    <p class="text-sm text-text-muted">Sem custos lançados no mês.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($cc as $nome => $valor)
                            <div class="flex items-center gap-2 text-xs">
                                <span class="w-[5.5rem] flex-shrink-0 text-text-secondary truncate">{{ $nome }}</span>
                                <span class="h-3 flex-1 min-w-0"><span class="block h-full rounded-r" style="width: {{ max(1.5, $valor / $maxCc * 100) }}%; background: #2a78d6;"></span></span>
                                <span class="font-mono text-text font-medium flex-shrink-0 tabular-nums">R$ {{ $fmt($valor, 0) }}</span>
                                <span class="font-mono text-text-muted flex-shrink-0 w-11 text-right tabular-nums">{{ $fmt($valor / $totCc * 100, 1) }}%</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>

            {{-- Receita por cliente --}}
            <x-card padding="md" class="h-full">
                <h3 class="text-sm font-semibold text-text mb-0.5">Receita por cliente</h3>
                <p class="text-xs text-text-secondary mb-4">{{ $j['rotulo'] }} · CT-e autorizados</p>
                @php $rc = $this->receitaPorCliente; $maxRc = $rc ? max(array_column($rc, 'valor')) : 0; @endphp
                @if ($maxRc <= 0)
                    <p class="text-sm text-text-muted">Nenhum CT-e autorizado no período.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($rc as $l)
                            <div class="flex items-center gap-2 text-xs">
                                <span class="w-28 flex-shrink-0 text-text-secondary truncate" title="{{ $l['nome'] }}">{{ $l['nome'] }}</span>
                                <span class="h-3 flex-1 min-w-0"><span class="block h-full rounded-r" style="width: {{ max(1.5, $l['valor'] / $maxRc * 100) }}%; background: #1baf7a;"></span></span>
                                <span class="font-mono text-text font-medium flex-shrink-0 tabular-nums">R$ {{ $fmt($l['valor'], 0) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>

            {{-- Precisam de atenção — as mesmas pendências do sino e das bolinhas do menu --}}
            <div class="bg-surface border border-border rounded-xl h-full">
                <div class="flex items-center justify-between px-4 pt-4 pb-2">
                    <h3 class="text-sm font-semibold text-text">Precisam de atenção</h3>
                    @if (count($alertas) > 0)
                        <span class="text-[11px] font-bold text-white bg-red-600 rounded-full px-2">{{ count($alertas) }}</span>
                    @endif
                </div>
                @forelse ($alertas as $a)
                    <a href="{{ $a['url'] }}" class="flex items-start gap-2.5 px-4 py-2 text-[12.5px] hover:bg-surface-elevated">
                        <span class="mt-0.5 w-6 h-6 rounded-md flex items-center justify-center flex-shrink-0 {{ $a['cor'] === 'urgente' ? 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300' }}">
                            <x-icon name="alert-triangle" class="w-3.5 h-3.5" />
                        </span>
                        <span class="flex-1 min-w-0"><b class="block font-semibold text-text">{{ $a['titulo'] }}</b><small class="block text-[11px] text-text-secondary">{{ $a['rotina'] }}</small></span>
                        <span class="font-mono text-[10px] font-semibold rounded px-1.5 flex-shrink-0" style="color: var(--h6-cod-tx); background: var(--h6-cod-bg); border: 1px solid var(--h6-cod-borda)">{{ $a['codigo'] }}</span>
                    </a>
                @empty
                    <p class="px-4 pb-4 text-sm text-text-muted">Nada urgente agora. ✓</p>
                @endforelse
            </div>
        </div>

        {{-- Segunda linha: top motoristas e vencimentos --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
            <x-card padding="md">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-text">Top motoristas no mês</h3>
                    <span class="text-[11px] text-text-muted">Top 3 · por receita</span>
                </div>
                @php $tm = $this->topMotoristas; $maxTm = $tm ? max(array_column($tm, 'receita')) : 0; @endphp
                @forelse ($tm as $i => $m)
                    <div class="flex items-center gap-3 py-2 border-b border-border last:border-b-0">
                        <span class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold flex-shrink-0 {{ $i === 0 ? 'bg-primary-soft text-primary' : 'bg-surface-elevated border border-border text-text-secondary' }}">{{ $i + 1 }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="text-[13px] font-semibold text-text truncate">{{ $m['nome'] }}</p>
                            <p class="text-[11px] text-text-secondary">{{ $m['viagens'] }} {{ $m['viagens'] === 1 ? 'viagem' : 'viagens' }} · {{ $fmt($m['km'], 0) }} km</p>
                            <div class="h-1 bg-border rounded-full overflow-hidden mt-1"><div class="h-full bg-primary" style="width: {{ $maxTm > 0 ? $m['receita'] / $maxTm * 100 : 0 }}%"></div></div>
                        </div>
                        <span class="font-mono text-[12.5px] font-semibold text-text flex-shrink-0">R$ {{ $fmt($m['receita'], 0) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-text-muted">Nenhuma viagem no mês ainda.</p>
                @endforelse
            </x-card>

            <x-card padding="md">
                <div class="flex items-start justify-between gap-3 mb-1">
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-text">Vencimentos próximos</h3>
                        <p class="text-[11px] text-text-muted">Próximos 30 dias · documentos de veículos e motoristas</p>
                    </div>
                    @if (Route::has('vencimentos.index'))
                        <a href="{{ route('vencimentos.index') }}" class="text-[11px] font-medium text-primary hover:underline whitespace-nowrap">Ver vencimentos →</a>
                    @endif
                </div>
                @forelse ($this->vencimentos as $v)
                    @php
                        [$cls, $txt] = match (true) {
                            $v['dias'] < 0 => ['bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300', 'Vencido há ' . abs($v['dias']) . ' d'],
                            $v['dias'] <= 7 => ['bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300', $v['dias'] === 0 ? 'Vence hoje' : $v['dias'] . ' dias'],
                            $v['dias'] <= 20 => ['bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300', $v['dias'] . ' dias'],
                            default => ['bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300', $v['dias'] . ' dias'],
                        };
                    @endphp
                    <div class="flex items-center gap-3 py-2 border-b border-border last:border-b-0 text-[13px]">
                        <div class="flex-1 min-w-0"><p class="font-semibold text-text truncate">{{ $v['titulo'] }}</p><p class="text-[11px] text-text-secondary">{{ $v['sub'] }}</p></div>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap {{ $cls }}">{{ $txt }}</span>
                    </div>
                @empty
                    <p class="text-sm text-text-muted mt-2">Nada vencendo nos próximos 30 dias.</p>
                @endforelse
            </x-card>
        </div>

        {{-- Terceira linha: faturamento de 7 dias + atividade recente --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-4">
            <x-card padding="md" class="lg:col-span-2">
                @php $f7 = $this->faturamento7Dias; @endphp
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-text">Faturamento — últimos 7 dias</h3>
                    @if ($f7['total'] > 0)
                        <span class="text-xs text-text-muted font-mono">Total: R$ {{ $fmt($f7['total']) }}</span>
                    @endif
                </div>
                @if ($f7['total'] <= 0)
                    <p class="text-sm text-text-muted">Sem CT-e autorizados nos últimos 7 dias.</p>
                @else
                    @php
                        // Gráfico em SVG gerado aqui (sem Chart.js de CDN): linha âmbar com área, como no ERP.
                        $W = 700; $H = 180; $x0 = 64; $x1 = 688; $y0 = 16; $y1 = 150;
                        $mx = max($f7['valores']) ?: 1;
                        $escala = $mx >= 1000 ? 1000 : 1;
                        $n = count($f7['valores']) - 1;
                        $pts = [];
                        foreach ($f7['valores'] as $i => $val) {
                            $pts[] = [round($x0 + $i / $n * ($x1 - $x0), 1), round($y1 - $val / $mx * ($y1 - $y0), 1)];
                        }
                        $linha = collect($pts)->map(fn ($p, $i) => ($i ? 'L' : 'M') . $p[0] . ' ' . $p[1])->implode(' ');
                        $area = $linha . " L{$x1} {$y1} L{$x0} {$y1} Z";
                        $eixo = fn ($v) => $escala === 1000 ? 'R$ ' . number_format($v / 1000, 1, ',', '.') . 'k' : 'R$ ' . number_format($v, 0, ',', '.');
                    @endphp
                    <svg viewBox="0 0 {{ $W }} {{ $H }}" class="w-full h-[180px]" role="img" aria-label="Faturamento dos últimos 7 dias">
                        <defs><linearGradient id="fat7" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="rgb(217,119,6)" stop-opacity=".25"/><stop offset="1" stop-color="rgb(217,119,6)" stop-opacity="0"/></linearGradient></defs>
                        @foreach ([0, .5, 1] as $fr)
                            @php $yy = $y1 - $fr * ($y1 - $y0); @endphp
                            <line x1="{{ $x0 }}" x2="{{ $x1 }}" y1="{{ $yy }}" y2="{{ $yy }}" style="stroke: rgb(var(--color-border))" />
                            <text x="0" y="{{ $yy + 4 }}" style="fill: rgb(var(--color-text-secondary)); font: 11px 'JetBrains Mono', monospace">{{ $eixo($mx * $fr) }}</text>
                        @endforeach
                        <path d="{{ $area }}" fill="url(#fat7)" />
                        <path d="{{ $linha }}" fill="none" stroke="rgb(217,119,6)" stroke-width="2" />
                        @foreach ($pts as $i => $p)
                            <circle cx="{{ $p[0] }}" cy="{{ $p[1] }}" r="3" fill="rgb(217,119,6)"><title>{{ $f7['labels'][$i] }} · R$ {{ $fmt($f7['valores'][$i]) }}</title></circle>
                            <text x="{{ $p[0] }}" y="{{ $H - 4 }}" text-anchor="middle" style="fill: rgb(var(--color-text-secondary)); font: 11px 'JetBrains Mono', monospace">{{ $f7['labels'][$i] }}</text>
                        @endforeach
                    </svg>
                @endif
            </x-card>

            <x-card padding="md" class="h-full">
                <div class="flex items-center justify-between mb-1.5">
                    <h3 class="text-sm font-semibold text-text">Atividade recente</h3>
                    <span class="text-[11px] text-text-muted">Últimos lançamentos</span>
                </div>
                @forelse ($this->atividade as $e)
                    <div class="flex gap-2.5 py-2 border-b border-border last:border-b-0 text-[12.5px]">
                        <i class="w-2 h-2 rounded-full mt-1.5 flex-shrink-0" style="background: {{ $e['cor'] }}"></i>
                        <div class="flex-1 min-w-0"><p class="text-text truncate">{{ $e['titulo'] }}</p><p class="text-[11px] text-text-muted truncate">{{ $e['sub'] }}</p></div>
                        <em class="not-italic text-[11px] text-text-muted whitespace-nowrap">{{ $e['quando']->locale('pt_BR')->shortRelativeToNowDiffForHumans() }}</em>
                    </div>
                @empty
                    <p class="text-sm text-text-muted">Nenhum lançamento ainda.</p>
                @endforelse
            </x-card>
        </div>
    </div>

    {{-- ═══════════ Rodapé técnico ═══════════ --}}
    @php $cont = array_filter($this->contadores); @endphp
    @if ($cont !== [])
        <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-border opacity-80">
            <span class="text-[10px] text-text-muted uppercase tracking-wider font-semibold mr-1">Operação:</span>
            @foreach ($cont as $rotulo => $n)
                <span class="inline-flex items-center gap-1.5 text-[11px] px-2.5 py-0.5 rounded-full border border-border bg-surface text-text-secondary"><b class="font-mono text-text">{{ $n }}</b> {{ $rotulo }}</span>
            @endforeach
        </div>
    @endif
</div>
