{{--
    Tela inicial — dashboard operacional.
    KPIs no topo, painéis de conformidade e ocorrências no meio, atalhos por
    módulo embaixo. Tudo isolado por empresa pelo EmpresaScope.
--}}
@php
    $catIcones = ['veiculo' => 'truck', 'motorista' => 'id-card'];
@endphp
<div>
    <x-page-header
        title="PetroWeb Frota"
        subtitle="Gestão de transporte rodoviário de cargas — {{ \App\Support\TenantContext::empresa()?->razao_social ?? 'sem empresa no contexto' }}" />

    {{-- ── KPIs ─────────────────────────────────────────────── --}}
    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-kpi label="Frota ativa" :valor="(string) $this->frota['ativos']"
               :sub="$this->frota['total'] . ' no total · ' . $this->frota['tracao'] . ' tração' . ($this->frota['manutencao'] ? ' · ' . $this->frota['manutencao'] . ' em manutenção' : '')" />
        <x-kpi label="Motoristas aptos" :valor="$this->motoristas['aptos'] . '/' . $this->motoristas['total']"
               :cor="$this->motoristas['aptos'] < $this->motoristas['total'] ? 'ambar' : 'verde'"
               sub="Aptos a viajar hoje" />
        <x-kpi label="Vencimentos" :valor="(string) ($this->vencimentosResumo['vencidos'] + $this->vencimentosResumo['ate30'])"
               :alerta="$this->vencimentosResumo['vencidos'] > 0"
               :sub="$this->vencimentosResumo['vencidos'] . ' vencidos · ' . $this->vencimentosResumo['ate30'] . ' em 30 dias'" />
        <x-kpi label="Ocorrências abertas" :valor="(string) $this->ocorrencias['abertas']"
               :cor="$this->ocorrencias['abertas'] > 0 ? 'vermelho' : null"
               :sub="'R$ ' . number_format($this->ocorrencias['prejuizo'], 2, ',', '.') . ' em prejuízo'" />
    </div>

    {{-- ── Painéis ──────────────────────────────────────────── --}}
    <div class="mb-4 grid grid-cols-1 gap-4 xl:grid-cols-[1.15fr_1fr] items-start">

        {{-- Vencimentos próximos --}}
        <x-card padding="none" class="overflow-hidden">
            <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                <x-icon name="calendar" class="h-4 w-4 text-text-secondary" />
                <h2 class="text-sm font-semibold text-text">Conformidade — o que vence primeiro</h2>
                <span class="flex-1"></span>
                @if (\Illuminate\Support\Facades\Route::has('vencimentos.index'))
                    <a href="{{ route('vencimentos.index') }}" wire:navigate
                       class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">
                        Ver painel <x-icon name="chevron-right" class="h-3.5 w-3.5" />
                    </a>
                @endif
            </div>

            @if ($this->proximosVencimentos->isEmpty())
                <x-empty-state icon="check" title="Nada vencendo em 60 dias"
                               description="Documentos de veículo, CNH e exames em dia." />
            @else
                <div class="divide-y divide-border">
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
                            @if ($item['bloqueia'] && $dias < 0)
                                <x-badge variant="danger" class="text-[10px]">Bloqueia</x-badge>
                            @endif
                            <div class="flex items-center gap-1.5 whitespace-nowrap text-xs font-medium {{ $cor }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $dot }}"></span>{{ $txt }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>

        {{-- Ocorrências recentes --}}
        <x-card padding="none" class="overflow-hidden">
            <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                <x-icon name="alert-triangle" class="h-4 w-4 text-text-secondary" />
                <h2 class="text-sm font-semibold text-text">Ocorrências recentes</h2>
                <span class="flex-1"></span>
                @if (\Illuminate\Support\Facades\Route::has('ocorrencias.index'))
                    <a href="{{ route('ocorrencias.index') }}" wire:navigate
                       class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">
                        Ver todas <x-icon name="chevron-right" class="h-3.5 w-3.5" />
                    </a>
                @endif
            </div>

            @if ($this->ocorrenciasRecentes->isEmpty())
                <x-empty-state icon="check" title="Sem ocorrências" description="Operação sem intercorrências registradas." />
            @else
                <div class="divide-y divide-border">
                    @foreach ($this->ocorrenciasRecentes as $oc)
                        <div class="flex items-start gap-3 px-5 py-2.5">
                            <x-badge :variant="config('ocorrencias.tipos_cores.' . $oc->tipo, 'gray')" class="mt-0.5 text-[10px]">
                                {{ config('ocorrencias.tipos.' . $oc->tipo, $oc->tipo) }}
                            </x-badge>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm text-text">{{ $oc->descricao }}</div>
                                <div class="mt-0.5 text-xs text-text-muted">
                                    {{ $oc->data_hora?->format('d/m/Y') }}
                                    @if ($oc->valor_prejuizo) · R$ {{ number_format((float) $oc->valor_prejuizo, 2, ',', '.') }} @endif
                                </div>
                            </div>
                            <x-badge :variant="config('ocorrencias.status_cores.' . $oc->status, 'gray')" class="text-[10px]">
                                {{ config('ocorrencias.status.' . $oc->status, $oc->status) }}
                            </x-badge>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>
    </div>

    {{-- Abastecimentos recentes --}}
    @if ($this->abastecimentosRecentes->isNotEmpty())
        <x-card padding="none" class="mb-4 overflow-hidden">
            <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                <x-icon name="fuel" class="h-4 w-4 text-text-secondary" />
                <h2 class="text-sm font-semibold text-text">Abastecimentos recentes</h2>
                <span class="text-sm text-text-muted">· gasto no mês R$ {{ number_format($this->abastecimento['gasto_mes'], 2, ',', '.') }}</span>
                <span class="flex-1"></span>
                @if ($this->abastecimento['alertas'] > 0)
                    <x-badge variant="danger" class="text-[10px]">{{ $this->abastecimento['alertas'] }} alerta(s) de desvio</x-badge>
                @endif
            </div>
            <div class="flex flex-wrap gap-x-8 gap-y-2 px-5 py-3">
                @foreach ($this->abastecimentosRecentes as $ab)
                    <div class="flex items-center gap-2 text-sm">
                        <span class="font-mono text-text">{{ $ab->veiculo?->placaFormatada() ?? '—' }}</span>
                        <span class="text-text-muted">{{ number_format((float) $ab->litros, 0, ',', '.') }} L</span>
                        @if ($ab->media_calculada)
                            <span class="{{ $ab->alerta ? 'font-semibold text-danger' : 'text-text-secondary' }}">
                                {{ number_format((float) $ab->media_calculada, 2, ',', '.') }} km/L
                            </span>
                        @endif
                        <span class="text-xs text-text-muted">{{ $ab->data_hora?->format('d/m') }}</span>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

    {{-- ── Atalhos por módulo ───────────────────────────────── --}}
    @php
        $modulos = collect(config('navegacao'))->reject(fn (array $grupo): bool => $grupo['solo'] ?? false);
    @endphp
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($modulos as $grupo)
            <x-card padding="none" class="overflow-hidden">
                <div class="border-b border-border px-5 py-3.5">
                    <h2 class="text-sm font-semibold text-text">{{ $grupo['label'] }}</h2>
                </div>
                <div class="px-2 py-2">
                    @foreach ($grupo['items'] as $item)
                        @continue(isset($item['can']) && ! auth()->user()?->can($item['can']))
                        @php $existe = \Illuminate\Support\Facades\Route::has($item['route']); @endphp
                        <a href="{{ $existe ? route($item['route']) : '#' }}"
                           @if($existe) wire:navigate @endif
                           class="flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors
                                  {{ $existe ? 'text-text hover:bg-surface-elevated' : 'cursor-not-allowed text-text-muted opacity-50' }}"
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
