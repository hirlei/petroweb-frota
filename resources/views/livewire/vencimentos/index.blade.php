{{--
    Rotina 2040 — painel de vencimentos.
    Um lugar só para o que expira e bloqueia a operação: documentos de veículo,
    habilitação e exames do motorista, e o certificado da filial. Leitura — cada
    linha leva de volta ao cadastro para corrigir.
--}}
@php
    $catRotulos = ['veiculo' => 'Veículos', 'motorista' => 'Motoristas', 'certificado' => 'Certificados'];
    $catIcones  = ['veiculo' => 'truck', 'motorista' => 'id-card', 'certificado' => 'shield-check'];
@endphp
<div>
    <x-page-header
        title="Vencimentos"
        subtitle="Documentos, habilitações e certificados que expiram — o que já venceu e o que vence nos próximos {{ $horizonte }} dias">
        <x-slot:actions>
            <div class="flex items-center gap-1 rounded-lg border border-border bg-surface p-0.5">
                @foreach ([30, 60, 90] as $dias)
                    <button type="button" wire:click="definirHorizonte({{ $dias }})"
                            class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors
                                   {{ $horizonte === $dias ? 'bg-primary text-white' : 'text-text-secondary hover:bg-surface-elevated' }}">
                        {{ $dias }} dias
                    </button>
                @endforeach
            </div>
        </x-slot:actions>
    </x-page-header>

    {{-- KPIs --}}
    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-kpi label="Vencidos" :valor="(string) $this->resumo['vencidos']" cor="vermelho"
               sub="Já fora da validade" />
        <x-kpi label="Vencem em 30 dias" :valor="(string) $this->resumo['ate30']" cor="ambar"
               sub="Janela de renovação" />
        <x-kpi label="Vencem em 31–60 dias" :valor="(string) $this->resumo['ate60']" cor="azul"
               sub="No radar" />
        <x-kpi label="Vencidos que bloqueiam" :valor="(string) $this->resumo['bloqueantes']"
               :alerta="$this->resumo['bloqueantes'] > 0" sub="Impedem a viagem hoje" />
    </div>

    {{-- Filtros --}}
    <div class="mb-4 flex flex-wrap items-center gap-1.5">
        @foreach (['todos' => 'Todos', 'vencido' => 'Vencidos', 'ate30' => 'Até 30 dias', 'ate60' => '31–60 dias'] as $chave => $rotulo)
            <button type="button" wire:click="filtrarFaixa('{{ $chave }}')"
                    class="rounded-full px-3 py-1.5 text-sm font-medium transition-colors
                           {{ $faixa === $chave ? 'bg-primary-soft text-amber-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' }}">
                {{ $rotulo }}
            </button>
        @endforeach

        <div class="mx-1 h-5 w-px bg-border"></div>

        @foreach ($catRotulos as $chave => $rotulo)
            <button type="button" wire:click="filtrarCategoria('{{ $chave }}')"
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium transition-colors
                           {{ $categoria === $chave ? 'bg-primary-soft text-amber-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' }}">
                <x-icon :name="$catIcones[$chave]" class="h-3.5 w-3.5" />
                {{ $rotulo }}
            </button>
        @endforeach
    </div>

    <x-card padding="none" class="overflow-hidden">
        <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
            <x-icon name="calendar" class="h-4 w-4 text-text-secondary" />
            <h2 class="text-sm font-semibold text-text">Agenda de vencimentos</h2>
            <span class="text-sm text-text-muted">({{ $this->itensFiltrados->count() }})</span>
        </div>

        @if ($this->itensFiltrados->isEmpty())
            <x-empty-state icon="check" title="Nada vencendo nesta faixa"
                           description="Nenhum documento, habilitação ou certificado no filtro atual. Amplie o horizonte se quiser enxergar mais longe." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-border bg-surface-elevated">
                        <tr>
                            @foreach (['Item', 'Referência', 'Documento', 'Vencimento', 'Prazo', ''] as $cabecalho)
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-text-muted first:pl-5">
                                    {{ $cabecalho }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->itensFiltrados as $item)
                            @php
                                $href = $item['rota'] && \Illuminate\Support\Facades\Route::has($item['rota'][0])
                                    ? route($item['rota'][0], $item['rota'][1]) : null;
                                $dias = $item['dias'];
                                $prazoCor = $dias < 0 ? 'text-danger' : ($dias <= 30 ? 'text-amber-700' : 'text-text-secondary');
                                $prazoTxt = $dias < 0
                                    ? 'Vencido há ' . abs($dias) . ' ' . (abs($dias) === 1 ? 'dia' : 'dias')
                                    : ($dias === 0 ? 'Vence hoje' : 'Vence em ' . $dias . ' ' . ($dias === 1 ? 'dia' : 'dias'));
                            @endphp
                            <tr wire:key="venc-{{ $loop->index }}" class="border-t border-border hover:bg-surface-elevated">
                                <td class="px-4 py-3 pl-5">
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-icon :name="$catIcones[$item['categoria']]" class="h-4 w-4 text-text-muted" />
                                        <span class="text-xs text-text-secondary">{{ $catRotulos[$item['categoria']] }}</span>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-sm font-medium text-text">{{ $item['referencia'] }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-sm text-text-secondary">{{ $item['documento'] }}</span>
                                    @if ($item['bloqueia'])
                                        <x-badge variant="danger" class="ml-1.5 text-[10px]">Bloqueia</x-badge>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="font-mono text-sm {{ $dias < 0 ? 'font-semibold text-danger' : 'text-text' }}">
                                        {{ $item['vencimento']->format('d/m/Y') }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium {{ $prazoCor }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $dias < 0 ? 'bg-danger' : ($dias <= 30 ? 'bg-warning' : 'bg-info') }}"></span>
                                        {{ $prazoTxt }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($href)
                                        <a href="{{ $href }}" wire:navigate
                                           class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-primary hover:bg-primary-soft">
                                            Abrir <x-icon name="chevron-right" class="h-3.5 w-3.5" />
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="mt-3 flex items-start gap-2 rounded-r-md border-l-[3px] border-info bg-blue-50 px-3 py-2.5
                text-xs text-blue-800 dark:bg-blue-950/40 dark:text-blue-300">
        <x-icon name="info" class="mt-px h-3.5 w-3.5 flex-shrink-0" />
        <span>Os itens marcados como <b>Bloqueia</b> impedem a viagem enquanto vencidos — é a diferença entre o que apenas avisa e o que trava a operação.</span>
    </div>
</div>
