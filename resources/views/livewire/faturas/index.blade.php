{{--
    Rotina 5010 — Faturas (mockup aprovado em 02/10/2026).
    CT-e autorizados agrupados por cliente, com vencimento e parcelas.
--}}
@php
    $r = $this->resumo;
    $fmt = fn ($v, $d = 2) => number_format((float) $v, $d, ',', '.');
    $kpiT = 'text-text-muted uppercase tracking-wide text-[10px] font-semibold';
    $kpiV = 'text-[21px] font-medium leading-tight mt-0.5';
@endphp
<div>
    <x-page-header title="Faturas" subtitle="CT-e autorizados agrupados por cliente, com vencimento e parcelas.">
        <x-slot:actions>
            @can('create', \App\Models\Fatura::class)
                <x-button variant="primary" size="sm" icon="plus" :href="route('faturas.criar')" wire:navigate>Nova fatura</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @include('livewire.financeiro.alertas')

    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4" style="font-variant-numeric:tabular-nums">
        <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
            <p class="{{ $kpiT }}">A receber</p>
            <p class="{{ $kpiV }} text-text">R$ {{ $fmt($r['a_receber']) }}</p>
            <p class="truncate text-[11px] text-text-secondary">{{ $r['a_receber_qtd'] }} {{ $r['a_receber_qtd'] === 1 ? 'parcela' : 'parcelas' }} em aberto</p>
            <a href="{{ route('contas-receber.index') }}" wire:navigate class="text-[10px] font-bold text-primary hover:underline">Contas a receber →</a>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
            <p class="{{ $kpiT }}">Vencido</p>
            <p class="{{ $kpiV }} {{ $r['vencido'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-text' }}">R$ {{ $fmt($r['vencido']) }}</p>
            <p class="truncate text-[11px] text-text-secondary">
                @if ($r['vencido_qtd'] > 0)
                    {{ $r['vencido_qtd'] }} {{ $r['vencido_qtd'] === 1 ? 'parcela' : 'parcelas' }} · a mais antiga há {{ (int) \Illuminate\Support\Carbon::parse($r['vencido_mais_antigo'])->diffInDays(today()) }} dias
                @else
                    Nada vencido
                @endif
            </p>
            <a href="{{ route('contas-receber.index', ['faixa' => 'vencidas']) }}" wire:navigate class="text-[10px] font-bold text-primary hover:underline">Ver vencidas →</a>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
            <p class="{{ $kpiT }}">Recebido no mês</p>
            <p class="{{ $kpiV }} text-green-600 dark:text-green-400">R$ {{ $fmt($r['recebido_mes']) }}</p>
            <p class="truncate text-[11px] text-text-secondary">
                {{ $r['recebido_mes_qtd'] }} {{ $r['recebido_mes_qtd'] === 1 ? 'recebimento' : 'recebimentos' }}
                @if ($r['recebido_comp'] !== null)
                    · <span class="font-semibold {{ $r['recebido_comp'] >= 0 ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">{{ $r['recebido_comp'] >= 0 ? '+' : '' }}{{ $fmt($r['recebido_comp'], 1) }}% vs mês ant.</span>
                @endif
            </p>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
            <p class="{{ $kpiT }}">CT-e sem fatura</p>
            <p class="{{ $kpiV }}" style="color:#2a78d6">{{ $r['sem_fatura_qtd'] }}</p>
            <p class="truncate text-[11px] text-text-secondary">R$ {{ $fmt($r['sem_fatura_valor']) }} autorizados e não faturados</p>
            @can('create', \App\Models\Fatura::class)
                <a href="{{ route('faturas.criar') }}" wire:navigate class="text-[10px] font-bold text-primary hover:underline">Faturar →</a>
            @endcan
        </div>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-1.5">
        @php
            $chips = ['' => 'Todas', 'abertas' => 'Em aberto', 'vencidas' => 'Vencidas', 'pagas' => 'Pagas', 'canceladas' => 'Canceladas'];
        @endphp
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
            <input type="search" wire:model.live.debounce.400ms="busca" placeholder="Número, cliente ou CNPJ…"
                   class="w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm text-text outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>
    </div>

    <x-card padding="none" class="overflow-hidden">
        @if ($this->faturas->isEmpty())
            <x-empty-state icon="file-text" title="Nenhuma fatura"
                           description="Junte os CT-e autorizados de um cliente numa fatura, com vencimento e parcelas." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full" style="font-variant-numeric:tabular-nums">
                    <thead class="border-b border-border bg-surface-elevated">
                        <tr>
                            @foreach (['Fatura', 'Cliente', 'Emissão', 'Vencimento', 'CT-e', 'Valor', 'Recebido', 'Situação', ''] as $i => $cab)
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-text-muted first:pl-5 {{ in_array($i, [4, 5, 6], true) ? 'text-right' : 'text-left' }}">{{ $cab }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->faturas as $f)
                            @php
                                $sit = $f->situacao();
                                $proximo = $f->titulos->first(fn ($t) => $t->emAberto()) ?? $f->titulos->last();
                            @endphp
                            <tr wire:key="ft-{{ $f->id }}" class="border-t border-border hover:bg-surface-elevated">
                                <td class="whitespace-nowrap px-4 py-3 pl-5"><a href="{{ route('faturas.ver', $f) }}" wire:navigate class="font-mono text-sm font-semibold text-[var(--h6-azul-tx)] hover:underline">{{ $f->numero }}</a></td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="font-medium text-text">{{ $f->tomador?->razao_social ?? '—' }}</span>
                                    <span class="block text-[11px] text-text-secondary">{{ $f->cancelada() ? 'Cancelada · CT-e liberados' : $f->titulos->count() . ' ' . ($f->titulos->count() === 1 ? 'parcela' : 'parcelas') . ' · ' . $f->rotuloCondicao() }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-text-secondary">{{ $f->emissao?->format('d/m/Y') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-text-secondary">{{ $f->cancelada() ? '—' : $proximo?->vencimento?->format('d/m/Y') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-text-secondary">{{ $f->cancelada() ? $f->ctes_total : $f->ctes_qtd }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-text">R$ {{ $fmt($f->valor_total) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-text-secondary">{{ $f->cancelada() ? '—' : 'R$ ' . $fmt($f->valor_recebido) }}</td>
                                <td class="px-4 py-3">
                                    <x-badge :variant="config('financeiro.fatura_cores.' . $sit, 'gray')" class="text-[11px] {{ $sit === 'cancelada' ? 'line-through' : '' }}">
                                        @if ($sit === 'vencida')
                                            @php $dias = $f->titulos->filter(fn ($t) => $t->vencido())->max(fn ($t) => $t->diasAtraso()); @endphp
                                            Vencida há {{ $dias }} {{ $dias === 1 ? 'dia' : 'dias' }}
                                        @else
                                            {{ config('financeiro.fatura_status.' . $sit, $sit) }}
                                        @endif
                                    </x-badge>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <x-button variant="neutral" size="xs" icon="eye" :href="route('faturas.ver', $f)" wire:navigate>Abrir</x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-border px-5 py-3">{{ $this->faturas->links() }}</div>
        @endif
    </x-card>
</div>
