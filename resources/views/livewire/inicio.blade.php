{{--
    Tela inicial — dashboard operacional (conforme mockup aprovado).
    KPIs com micro-visuais, faixa de gráficos (status da frota, consumo, custo),
    conformidade em semáforo, ocorrências, ações rápidas, próximas manutenções e
    atalhos por módulo. Tudo isolado por empresa pelo EmpresaScope.
--}}
@php
    $catIcones = ['veiculo' => 'truck', 'motorista' => 'id-card'];
    $venc = $this->vencimentosResumo;
    $custo = $this->custoMes;
    $cons = $this->consumo;
@endphp
<div>
    <div class="mb-4 flex items-start gap-4 flex-wrap">
        <div class="min-w-[240px] flex-1">
            <h1 class="text-2xl font-extrabold tracking-tight text-text">PetroWeb Frota</h1>
            <p class="mt-0.5 text-text-secondary">Gestão de transporte rodoviário de cargas — {{ \App\Support\TenantContext::empresa()?->razao_social ?? 'sem empresa no contexto' }}</p>
        </div>
    </div>

    {{-- ── KPIs com micro-visuais ── --}}
    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        {{-- Frota ativa --}}
        @php $fa = $this->frota; $pctFrota = $fa['total'] ? round($fa['ativos'] / $fa['total'] * 100) : 0; @endphp
        <x-card padding="none" class="relative overflow-hidden">
            <div class="px-4 py-3.5">
                <div class="text-[10.5px] font-bold uppercase tracking-wider text-text-muted">Frota ativa</div>
                <div class="mt-1 text-3xl font-bold tabular-nums text-text">{{ $fa['ativos'] }}</div>
                <div class="mt-0.5 text-[11.5px] text-text-secondary">{{ $fa['total'] }} no total · {{ $fa['tracao'] }} tração @if($fa['manutencao'])· <span class="text-amber-700">{{ $fa['manutencao'] }} em manutenção</span>@endif</div>
            </div>
            <svg class="absolute right-3.5 top-3.5" width="44" height="44" viewBox="0 0 42 42">
                <circle cx="21" cy="21" r="15.9" fill="none" stroke="rgb(var(--color-border))" stroke-width="6"/>
                <circle cx="21" cy="21" r="15.9" fill="none" stroke="rgb(var(--color-success))" stroke-width="6" stroke-linecap="round"
                        stroke-dasharray="{{ $pctFrota }} {{ 100 - $pctFrota }}" stroke-dashoffset="25"/>
            </svg>
        </x-card>

        {{-- Motoristas aptos --}}
        @php $mo = $this->motoristas; $pctMot = $mo['total'] ? round($mo['aptos'] / $mo['total'] * 100) : 0; $motOk = $mo['aptos'] >= $mo['total']; @endphp
        <x-card padding="none" class="relative overflow-hidden">
            <div class="px-4 py-3.5">
                <div class="text-[10.5px] font-bold uppercase tracking-wider text-text-muted">Motoristas aptos</div>
                <div class="mt-1 text-3xl font-bold tabular-nums {{ $motOk ? 'text-green-700' : 'text-amber-700' }}">{{ $mo['aptos'] }}/{{ $mo['total'] }}</div>
                <div class="mt-0.5 text-[11.5px] text-text-secondary">{{ $motOk ? 'Todos aptos a viajar' : ($mo['total'] - $mo['aptos']) . ' com pendência' }}</div>
            </div>
            <svg class="absolute right-3.5 top-3.5" width="44" height="44" viewBox="0 0 42 42">
                <circle cx="21" cy="21" r="15.9" fill="none" stroke="rgb(var(--color-border))" stroke-width="6"/>
                <circle cx="21" cy="21" r="15.9" fill="none" stroke="rgb(var(--color-{{ $motOk ? 'success' : 'warning' }}))" stroke-width="6" stroke-linecap="round"
                        stroke-dasharray="{{ $pctMot }} {{ 100 - $pctMot }}" stroke-dashoffset="25"/>
            </svg>
        </x-card>

        {{-- Vencimentos --}}
        @php $vencTotal = $venc['vencidos'] + $venc['ate30']; $vmax = max($venc['vencidos'], $venc['ate30'], $venc['ate60'], 1); @endphp
        <x-card padding="none" class="relative overflow-hidden {{ $venc['vencidos'] > 0 ? 'border-danger/40' : '' }}">
            <div class="px-4 py-3.5">
                <div class="text-[10.5px] font-bold uppercase tracking-wider text-text-muted">Vencimentos</div>
                <div class="mt-1 text-3xl font-bold tabular-nums {{ $venc['vencidos'] > 0 ? 'text-danger' : 'text-text' }}">{{ $vencTotal }}</div>
                <div class="mt-0.5 text-[11.5px] text-text-secondary">@if($venc['vencidos'])<span class="text-danger">{{ $venc['vencidos'] }} vencidos</span> · @endif{{ $venc['ate30'] }} em 30 dias</div>
            </div>
            <div class="absolute right-3.5 top-4 flex items-end gap-1" style="height:30px">
                <div class="w-2 rounded-sm bg-danger" style="height:{{ max(round($venc['vencidos']/$vmax*30),4) }}px"></div>
                <div class="w-2 rounded-sm bg-warning" style="height:{{ max(round($venc['ate30']/$vmax*30),4) }}px"></div>
                <div class="w-2 rounded-sm bg-info" style="height:{{ max(round($venc['ate60']/$vmax*30),4) }}px"></div>
            </div>
        </x-card>

        {{-- Ocorrências abertas --}}
        @php $oc = $this->ocorrencias; @endphp
        <x-card padding="none" class="relative overflow-hidden">
            <div class="px-4 py-3.5">
                <div class="text-[10.5px] font-bold uppercase tracking-wider text-text-muted">Ocorrências abertas</div>
                <div class="mt-1 text-3xl font-bold tabular-nums {{ $oc['abertas'] > 0 ? 'text-danger' : 'text-text' }}">{{ $oc['abertas'] }}</div>
                <div class="mt-0.5 text-[11.5px] text-text-secondary">R$ {{ number_format($oc['prejuizo'], 2, ',', '.') }} em prejuízo</div>
            </div>
            <div class="absolute right-3.5 top-3.5">
                <x-icon name="alert-triangle" class="h-6 w-6 {{ $oc['abertas'] > 0 ? 'text-danger' : 'text-text-muted' }}" />
            </div>
        </x-card>
    </div>

    {{-- ── Faixa de gráficos ── --}}
    <div class="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Status da frota --}}
        <x-card padding="none" class="overflow-hidden">
            <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                <x-icon name="truck" class="h-4 w-4 text-text-secondary" />
                <h2 class="text-sm font-semibold text-text">Status da frota</h2>
            </div>
            <div class="flex items-center gap-5 px-5 py-4">
                <svg width="104" height="104" viewBox="0 0 42 42" class="flex-shrink-0">
                    <circle cx="21" cy="21" r="15.9" fill="none" stroke="rgb(var(--color-border))" stroke-width="7"/>
                    @php $acc = 0; @endphp
                    @foreach ($this->frotaSegmentos as $seg)
                        @if ($seg['pct'] > 0)
                            <circle cx="21" cy="21" r="15.9" fill="none" stroke="{{ $seg['cor'] }}" stroke-width="7"
                                    stroke-dasharray="{{ $seg['pct'] }} {{ 100 - $seg['pct'] }}" stroke-dashoffset="{{ 25 - $acc }}"/>
                            @php $acc += $seg['pct']; @endphp
                        @endif
                    @endforeach
                    <text x="21" y="20.5" text-anchor="middle" font-size="9" font-weight="800" fill="rgb(var(--color-text))">{{ $fa['total'] }}</text>
                    <text x="21" y="26.5" text-anchor="middle" font-size="3.4" fill="rgb(var(--color-text-muted))">veículos</text>
                </svg>
                <div class="flex flex-col gap-2">
                    @foreach ($this->frotaSegmentos as $seg)
                        <div class="flex items-center gap-2 text-xs text-text-secondary">
                            <span class="h-2.5 w-2.5 rounded" style="background:{{ $seg['cor'] }}"></span>
                            {{ $seg['qtd'] }} {{ $seg['label'] }}
                        </div>
                    @endforeach
                    @if ($fa['terceiro'])
                        <div class="mt-1 text-[11px] text-text-muted">{{ $fa['terceiro'] }} de terceiro</div>
                    @endif
                </div>
            </div>
        </x-card>

        {{-- Consumo km/L --}}
        <x-card padding="none" class="overflow-hidden">
            <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                <x-icon name="fuel" class="h-4 w-4 text-text-secondary" />
                <h2 class="text-sm font-semibold text-text">Consumo km/L @if($cons['veiculo'])<span class="text-text-muted">· {{ $cons['veiculo'] }}</span>@endif</h2>
            </div>
            <div class="px-5 py-4">
                @if (empty($cons['serie']))
                    <x-empty-state icon="fuel" title="Sem leituras" description="Lance abastecimentos de tanque cheio para calcular o consumo." />
                @else
                    @php
                        $medias = array_map(fn ($b) => $b['media'], $cons['serie']);
                        $escala = max(max($medias), $cons['meta'] ?? 0) * 1.15 ?: 1;
                        $plot = 92;
                    @endphp
                    <div class="relative flex items-end justify-around gap-3" style="height:{{ $plot }}px">
                        @if ($cons['meta'])
                            @php $metaY = round(($cons['meta'] / $escala) * $plot); @endphp
                            <div class="pointer-events-none absolute inset-x-0 border-t border-dashed border-text-muted" style="bottom:{{ $metaY }}px">
                                <span class="absolute -top-4 right-0 text-[10px] text-text-muted">meta {{ number_format($cons['meta'], 1, ',', '.') }}</span>
                            </div>
                        @endif
                        @foreach ($cons['serie'] as $b)
                            @php $h = max(round(($b['media'] / $escala) * $plot), 6); @endphp
                            <div class="flex flex-1 flex-col items-center justify-end gap-1" style="height:{{ $plot }}px">
                                <span class="text-[11px] font-semibold tabular-nums {{ $b['alerta'] ? 'text-danger' : 'text-text' }}">{{ number_format($b['media'], 2, ',', '.') }}</span>
                                <div class="w-full rounded-t" style="height:{{ $h }}px;background:rgb(var(--color-{{ $b['alerta'] ? 'danger' : 'secondary' }}))"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-1.5 flex justify-around gap-3">
                        @foreach ($cons['serie'] as $b)
                            <span class="flex-1 text-center text-[10px] text-text-muted">{{ $b['label'] }}</span>
                        @endforeach
                    </div>
                    @php $ultimo = end($cons['serie']); @endphp
                    @if ($ultimo && $ultimo['alerta'])
                        <div class="mt-2 flex items-center gap-1.5 text-xs text-danger">
                            <x-icon name="alert-triangle" class="h-3.5 w-3.5" /> Último abastecimento abaixo da meta — alerta de desvio
                        </div>
                    @endif
                @endif
            </div>
        </x-card>

        {{-- Custo do mês --}}
        <x-card padding="none" class="overflow-hidden">
            <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                <x-icon name="layout-dashboard" class="h-4 w-4 text-text-secondary" />
                <h2 class="text-sm font-semibold text-text">Custo do mês</h2>
            </div>
            <div class="px-5 py-4">
                <div class="text-2xl font-bold tabular-nums tracking-tight text-text">R$ {{ number_format($custo['total'], 2, ',', '.') }}</div>
                @php $tot = $custo['total'] ?: 1; $pComb = round($custo['combustivel'] / $tot * 100); @endphp
                <div class="my-3 flex h-3 gap-0.5 overflow-hidden rounded-md">
                    <div style="width:{{ $pComb }}%;background:rgb(var(--color-secondary))"></div>
                    <div style="width:{{ 100 - $pComb }}%;background:rgb(var(--color-primary))"></div>
                </div>
                <div class="flex flex-wrap gap-x-4 gap-y-1">
                    <span class="flex items-center gap-1.5 text-xs text-text-secondary"><span class="h-2.5 w-2.5 rounded" style="background:rgb(var(--color-secondary))"></span>Combustível · R$ {{ number_format($custo['combustivel'], 0, ',', '.') }}</span>
                    <span class="flex items-center gap-1.5 text-xs text-text-secondary"><span class="h-2.5 w-2.5 rounded" style="background:rgb(var(--color-primary))"></span>Manutenção · R$ {{ number_format($custo['manutencao'], 0, ',', '.') }}</span>
                </div>
                <div class="mt-4 flex items-center justify-between rounded-md bg-surface-elevated px-3 py-2.5">
                    <span class="text-sm text-text-secondary">Custo por km (mês)</span>
                    <span class="font-mono text-sm font-semibold text-text">{{ $custo['rs_km'] !== null ? 'R$ ' . number_format($custo['rs_km'], 2, ',', '.') : '—' }}</span>
                </div>
                <p class="mt-2 text-[11px] text-text-muted">Km por proxy dos abastecimentos de tanque cheio. O R$/km definitivo virá das viagens.</p>
            </div>
        </x-card>
    </div>

    {{-- ── Conformidade + Ocorrências ── --}}
    <div class="mb-4 grid grid-cols-1 gap-4 xl:grid-cols-[1.15fr_1fr] items-start">
        <x-card padding="none" class="overflow-hidden">
            <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                <x-icon name="calendar" class="h-4 w-4 text-text-secondary" />
                <h2 class="text-sm font-semibold text-text">Conformidade — o que vence primeiro</h2>
                <span class="flex-1"></span>
                @if (\Illuminate\Support\Facades\Route::has('vencimentos.index'))
                    <a href="{{ route('vencimentos.index') }}" wire:navigate class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">Ver painel <x-icon name="chevron-right" class="h-3.5 w-3.5" /></a>
                @endif
            </div>
            {{-- semáforo --}}
            <div class="grid grid-cols-3 gap-2.5 px-5 py-4">
                <div class="rounded-lg py-3 text-center" style="background:rgb(var(--color-danger) / .12)">
                    <div class="text-2xl font-bold tabular-nums text-danger">{{ $venc['vencidos'] }}</div>
                    <div class="text-[11px] font-semibold text-danger">Vencidos</div>
                </div>
                <div class="rounded-lg py-3 text-center" style="background:rgb(var(--color-warning) / .14)">
                    <div class="text-2xl font-bold tabular-nums" style="color:rgb(var(--color-warning))">{{ $venc['ate30'] }}</div>
                    <div class="text-[11px] font-semibold" style="color:rgb(var(--color-warning))">Em 30 dias</div>
                </div>
                <div class="rounded-lg py-3 text-center" style="background:rgb(var(--color-info) / .12)">
                    <div class="text-2xl font-bold tabular-nums text-info">{{ $venc['ate60'] }}</div>
                    <div class="text-[11px] font-semibold text-info">31–60 dias</div>
                </div>
            </div>
            @if ($this->proximosVencimentos->isNotEmpty())
                <div class="divide-y divide-border border-t border-border">
                    @foreach ($this->proximosVencimentos as $item)
                        @php
                            $dias = $item['dias'];
                            $cor = $dias < 0 ? 'text-danger' : ($dias <= 30 ? 'text-amber-700' : 'text-text-secondary');
                            $dot = $dias < 0 ? 'bg-danger' : ($dias <= 30 ? 'bg-warning' : 'bg-info');
                            $txt = $dias < 0 ? 'Vencido há ' . abs($dias) . 'd' : ($dias === 0 ? 'Vence hoje' : 'Vence em ' . $dias . 'd');
                        @endphp
                        <div class="flex items-center gap-3 px-5 py-2.5">
                            <x-icon :name="$catIcones[$item['categoria']] ?? 'calendar'" class="h-4 w-4 flex-shrink-0 text-text-muted" />
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-medium text-text">{{ $item['referencia'] }}</div>
                                <div class="text-xs text-text-muted">{{ $item['documento'] }}</div>
                            </div>
                            @if ($item['bloqueia'] && $dias < 0)<x-badge variant="danger" class="text-[10px]">Bloqueia</x-badge>@endif
                            <div class="flex items-center gap-1.5 whitespace-nowrap text-xs font-medium {{ $cor }}"><span class="h-1.5 w-1.5 rounded-full {{ $dot }}"></span>{{ $txt }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>

        <x-card padding="none" class="overflow-hidden">
            <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                <x-icon name="alert-triangle" class="h-4 w-4 text-text-secondary" />
                <h2 class="text-sm font-semibold text-text">Ocorrências recentes</h2>
                <span class="flex-1"></span>
                @if (\Illuminate\Support\Facades\Route::has('ocorrencias.index'))
                    <a href="{{ route('ocorrencias.index') }}" wire:navigate class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">Ver todas <x-icon name="chevron-right" class="h-3.5 w-3.5" /></a>
                @endif
            </div>
            @if ($this->ocorrenciasRecentes->isEmpty())
                <x-empty-state icon="check" title="Sem ocorrências" description="Operação sem intercorrências registradas." />
            @else
                <div class="divide-y divide-border">
                    @foreach ($this->ocorrenciasRecentes as $ocr)
                        <div class="flex items-start gap-3 px-5 py-2.5">
                            <x-badge :variant="config('ocorrencias.tipos_cores.' . $ocr->tipo, 'gray')" class="mt-0.5 text-[10px]">{{ config('ocorrencias.tipos.' . $ocr->tipo, $ocr->tipo) }}</x-badge>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm text-text">{{ $ocr->descricao }}</div>
                                <div class="mt-0.5 text-xs text-text-muted">{{ $ocr->data_hora?->format('d/m/Y') }}@if($ocr->valor_prejuizo) · R$ {{ number_format((float) $ocr->valor_prejuizo, 2, ',', '.') }}@endif</div>
                            </div>
                            <x-badge :variant="config('ocorrencias.status_cores.' . $ocr->status, 'gray')" class="text-[10px]">{{ config('ocorrencias.status.' . $ocr->status, $ocr->status) }}</x-badge>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>
    </div>

    {{-- ── Ações rápidas ── --}}
    @php
        $acoes = [
            ['veiculos.criar', 'truck', 'Novo veículo', 'Cadastrar unidade'],
            ['abastecimentos.criar', 'fuel', 'Lançar abastecimento', 'Registrar consumo'],
            ['ocorrencias.criar', 'alert-triangle', 'Nova ocorrência', 'Avaria, multa, atraso'],
            ['manutencao.criar', 'wrench', 'Abrir OS', 'Manutenção'],
        ];
    @endphp
    <div class="mb-4">
        <div class="mb-2 text-[10px] font-bold uppercase tracking-wider text-text-muted">Ações rápidas</div>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ($acoes as [$rota, $icone, $titulo, $desc])
                @php $existe = \Illuminate\Support\Facades\Route::has($rota); @endphp
                <a href="{{ $existe ? route($rota) : '#' }}" @if($existe) wire:navigate @endif
                   class="group flex items-center gap-3 rounded-xl border border-border bg-surface px-4 py-3.5 transition-colors {{ $existe ? 'hover:border-primary hover:bg-primary-soft' : 'cursor-not-allowed opacity-50' }}">
                    <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-primary-soft text-amber-700"><x-icon name="{{ $icone }}" class="h-4 w-4" /></span>
                    <div>
                        <div class="text-sm font-semibold text-text">{{ $titulo }}</div>
                        <div class="text-[11px] text-text-muted">{{ $desc }}</div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>

    {{-- ── Próximas manutenções ── --}}
    @if ($this->proximasManutencoes->isNotEmpty())
        <x-card padding="none" class="mb-4 overflow-hidden">
            <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                <x-icon name="wrench" class="h-4 w-4 text-text-secondary" />
                <h2 class="text-sm font-semibold text-text">Manutenções em andamento</h2>
                <span class="flex-1"></span>
                @if (\Illuminate\Support\Facades\Route::has('manutencao.index'))
                    <a href="{{ route('manutencao.index') }}" wire:navigate class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">Ver todas <x-icon name="chevron-right" class="h-3.5 w-3.5" /></a>
                @endif
            </div>
            <div class="divide-y divide-border">
                @foreach ($this->proximasManutencoes as $os)
                    <div class="flex items-center gap-3 px-5 py-2.5">
                        <x-icon name="truck" class="h-4 w-4 flex-shrink-0 text-text-muted" />
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium text-text">{{ $os->veiculo?->placaFormatada() ?? '—' }} · {{ $os->numero }} · {{ config('manutencao.tipos.' . $os->tipo, $os->tipo) }}</div>
                            <div class="truncate text-xs text-text-muted">{{ $os->observacoes ?? 'Aberta em ' . $os->abertura?->format('d/m/Y') }}</div>
                        </div>
                        <x-badge :variant="config('manutencao.status_cores.' . $os->status, 'gray')" class="text-[10px]">{{ config('manutencao.status.' . $os->status, $os->status) }}</x-badge>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

    {{-- ── Atalhos por módulo ── --}}
    @php $modulos = collect(config('navegacao'))->reject(fn (array $g): bool => $g['solo'] ?? false); @endphp
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($modulos as $grupo)
            <x-card padding="none" class="overflow-hidden">
                <div class="border-b border-border px-5 py-3.5"><h2 class="text-sm font-semibold text-text">{{ $grupo['label'] }}</h2></div>
                <div class="px-2 py-2">
                    @foreach ($grupo['items'] as $item)
                        @continue(isset($item['can']) && ! auth()->user()?->can($item['can']))
                        @php $existe = \Illuminate\Support\Facades\Route::has($item['route']); @endphp
                        <a href="{{ $existe ? route($item['route']) : '#' }}" @if($existe) wire:navigate @endif
                           class="flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors {{ $existe ? 'text-text hover:bg-surface-elevated' : 'cursor-not-allowed text-text-muted opacity-50' }}"
                           @unless($existe) title="Ainda não implementado" @endunless>
                            <x-icon name="{{ $item['icon'] }}" class="h-4 w-4 flex-shrink-0" />
                            <span class="flex-1">{{ $item['label'] }}</span>
                            <span class="font-mono text-[10px] tabular-nums text-text-muted">{{ $item['codigo'] }}</span>
                        </a>
                    @endforeach
                </div>
            </x-card>
        @endforeach
    </div>
</div>
