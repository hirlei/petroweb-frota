{{-- Rotina 4060 — Exportar XML (mockup aprovado em 03/10/2026). --}}
@php
    $r = $this->resumo;
    $fmt = fn ($v, $d = 2) => number_format((float) $v, $d, ',', '.');
    $kpiT = 'text-text-muted uppercase tracking-wide text-[10px] font-semibold';
    $kpiV = 'text-[21px] font-medium leading-tight mt-0.5';
    $rotulo = 'mb-1.5 block text-[10.5px] font-semibold uppercase tracking-wide text-text-muted';
    $th = 'px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-text-muted';
    $chk = fn ($on) => 'inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-[12.5px] ' . ($on ? 'border-[var(--h6-azul)] bg-[var(--h6-hover-bg)] font-medium text-[var(--h6-azul-tx)]' : 'border-border text-text-secondary');
    $abertos = $r['mdfe_abertos'];
    $tiposEv = collect($r['eventos_por_tipo'])->map(fn ($n, $t) => $n . ' ' . mb_strtolower($t))->implode(' · ');
    $nomeMes = $this->meses[$mes] ?? $mes;
@endphp
<div>
    <x-page-header title="Exportar XML" subtitle="Os arquivos do mês para o contador: CT-e, MDF-e e eventos, num ZIP só." />

    @if (session('erro'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800/50 dark:bg-red-950/50 dark:text-red-300">{{ session('erro') }}</div>
    @endif

    <div class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <span class="{{ $rotulo }}">Mês</span>
            <select wire:model.live="mes" class="min-w-[180px] rounded-md border border-border bg-white px-3 py-2 text-sm text-text dark:bg-surface-elevated">
                @foreach ($this->meses as $valor => $nome)
                    <option value="{{ $valor }}">{{ $nome }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <span class="{{ $rotulo }}">Filial</span>
            <select wire:model.live="filial" class="min-w-[180px] rounded-md border border-border bg-white px-3 py-2 text-sm text-text dark:bg-surface-elevated">
                <option value="">Todas</option>
                @foreach ($this->filiais as $f)
                    <option value="{{ $f->id }}">{{ $f->nome_fantasia ?: $f->razao_social }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <span class="{{ $rotulo }}">Incluir</span>
            <div class="flex flex-wrap gap-1.5">
                <button type="button" wire:click="$toggle('cte')" class="{{ $chk($cte) }}"><x-icon name="check" class="h-3.5 w-3.5 {{ $cte ? '' : 'opacity-0' }}" />CT-e</button>
                <button type="button" wire:click="$toggle('mdfe')" class="{{ $chk($mdfe) }}"><x-icon name="check" class="h-3.5 w-3.5 {{ $mdfe ? '' : 'opacity-0' }}" />MDF-e</button>
                <button type="button" wire:click="$toggle('eventos')" class="{{ $chk($eventos) }}"><x-icon name="check" class="h-3.5 w-3.5 {{ $eventos ? '' : 'opacity-0' }}" />Eventos</button>
            </div>
        </div>
    </div>

    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4" style="font-variant-numeric:tabular-nums">
        <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
            <p class="{{ $kpiT }}">CT-e autorizados</p>
            <p class="{{ $kpiV }} text-text">{{ $fmt($r['cte_autorizados'], 0) }}</p>
            <p class="truncate text-[11px] text-text-secondary">R$ {{ $fmt($r['cte_valor']) }} em prestações</p>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
            <p class="{{ $kpiT }}">CT-e cancelados</p>
            <p class="{{ $kpiV }} text-text">{{ $fmt($r['cte_cancelados'], 0) }}</p>
            <p class="truncate text-[11px] text-text-secondary">Vão com o evento de cancelamento</p>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
            <p class="{{ $kpiT }}">Eventos</p>
            <p class="{{ $kpiV }} text-text">{{ $fmt($r['eventos'], 0) }}</p>
            <p class="truncate text-[11px] text-text-secondary" title="{{ $tiposEv }}">{{ $tiposEv !== '' ? ucfirst($tiposEv) : 'Nenhum no mês' }}</p>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
            <p class="{{ $kpiT }}">MDF-e</p>
            <p class="{{ $kpiV }} text-text">{{ $fmt($r['mdfe'], 0) }}</p>
            <p class="truncate text-[11px] {{ $abertos->isNotEmpty() ? 'text-amber-600 dark:text-amber-400' : 'text-text-secondary' }}">{{ $abertos->isNotEmpty() ? $abertos->count() . ($abertos->count() === 1 ? ' ainda aberto' : ' ainda abertos') : 'Todos encerrados' }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div class="space-y-4">
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center justify-between gap-2 border-b border-border px-5 py-3.5">
                    <h2 class="text-sm font-semibold text-text">Conferência da numeração</h2>
                    <span class="text-[11.5px] text-text-secondary">Por filial e série</span>
                </div>
                @if ($r['numeracao'] === [])
                    <p class="px-5 py-6 text-center text-sm text-text-muted">Nenhum documento numerado em {{ $nomeMes }}.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full" style="font-variant-numeric:tabular-nums">
                            <thead class="bg-surface-elevated">
                                <tr>
                                    <th class="{{ $th }} text-left">Documento</th><th class="{{ $th }} text-left">Filial · série</th>
                                    <th class="{{ $th }} text-right">Primeiro</th><th class="{{ $th }} text-right">Último</th><th class="{{ $th }} text-right">Emitidos</th>
                                    <th class="{{ $th }} text-left">Números sem documento autorizado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($r['numeracao'] as $n)
                                    <tr class="border-t border-border align-top text-sm">
                                        <td class="px-4 py-2.5">{{ $n['documento'] }}</td>
                                        <td class="px-4 py-2.5">{{ $n['filial'] }} · {{ $n['serie'] }}</td>
                                        <td class="px-4 py-2.5 text-right font-mono">{{ $fmt($n['primeiro'], 0) }}</td>
                                        <td class="px-4 py-2.5 text-right font-mono">{{ $fmt($n['ultimo'], 0) }}</td>
                                        <td class="px-4 py-2.5 text-right font-mono">{{ $fmt($n['emitidos'], 0) }}</td>
                                        <td class="px-4 py-2.5">
                                            @if ($n['total_faltando'] === 0)
                                                <span class="text-green-600 dark:text-green-400">Nenhum</span>
                                            @else
                                                @foreach ($n['faltando'] as $f)
                                                    <span class="block"><b class="font-mono text-[12px] font-semibold text-amber-700 dark:text-amber-400">{{ $fmt($f['numero'], 0) }}</b> <small class="text-[11.5px] text-text-secondary">{{ $f['motivo'] }}</small></span>
                                                @endforeach
                                                @if ($n['total_faltando'] > count($n['faltando']))
                                                    <small class="text-[11.5px] text-text-secondary">E mais {{ $n['total_faltando'] - count($n['faltando']) }}.</small>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <p class="border-t border-border px-5 py-3 text-[11.5px] leading-relaxed text-text-secondary">Desde o CT-e 4.00 não existe mais inutilização de numeração (Ajuste SINIEF 31/2022): número pulado não exige nada, basta a sequência seguir em ordem. A lista serve para o contador conferir — e para você reenviar o rejeitado, se ainda for o caso.</p>
            </x-card>

            <x-card padding="none" class="overflow-hidden">
                <div class="border-b border-border px-5 py-3.5"><h2 class="text-sm font-semibold text-text">Exportações anteriores</h2></div>
                @if ($this->anteriores->isEmpty())
                    <p class="px-5 py-6 text-center text-sm text-text-muted">Nenhuma exportação ainda.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full" style="font-variant-numeric:tabular-nums">
                            <thead class="bg-surface-elevated">
                                <tr><th class="{{ $th }} text-left">Mês</th><th class="{{ $th }} text-left">Filial</th><th class="{{ $th }} text-left">Gerado em</th><th class="{{ $th }} text-left">Por</th><th class="{{ $th }} text-right">Arquivos</th><th class="{{ $th }}"></th></tr>
                            </thead>
                            <tbody>
                                @foreach ($this->anteriores as $e)
                                    <tr class="border-t border-border text-sm" wire:key="exp-{{ $e->id }}">
                                        <td class="px-4 py-2.5">{{ $e->mesRotulo() }}</td>
                                        <td class="px-4 py-2.5 text-text-secondary">{{ $e->filial ? ($e->filial->nome_fantasia ?: $e->filial->razao_social) : 'Todas' }}</td>
                                        <td class="whitespace-nowrap px-4 py-2.5 text-text-secondary">{{ $e->created_at?->format('d/m/Y H:i') }}</td>
                                        <td class="px-4 py-2.5 text-text-secondary">{{ $e->criadoPor?->name ?? '—' }}</td>
                                        <td class="px-4 py-2.5 text-right font-mono">{{ $fmt($e->arquivos, 0) }}@if ($e->sem_xml > 0)<span class="block font-sans text-[11px] text-amber-600 dark:text-amber-400">{{ $e->sem_xml }} sem XML</span>@endif</td>
                                        <td class="whitespace-nowrap px-4 py-2.5 text-right"><a href="{{ route('fiscal.xml.baixar', $e) }}" class="text-xs font-semibold text-[var(--h6-azul-tx)] hover:underline">Baixar de novo</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

        <aside class="lg:sticky lg:top-0">
            <x-card padding="sm">
                <p class="mb-2 text-[14px] font-semibold text-text">Antes de baixar</p>
                @php
                    $pend = 'flex gap-2.5 border-t border-border py-2.5 text-[12.5px] first:border-0';
                    $icoWa = 'flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-md bg-amber-50 text-[11px] font-bold text-amber-700 dark:bg-amber-950/50 dark:text-amber-300';
                    $icoOk = 'flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-md bg-green-50 text-green-700 dark:bg-green-950/50 dark:text-green-300';
                @endphp
                <div>
                    @if ($abertos->isNotEmpty())
                        <div class="{{ $pend }}"><span class="{{ $icoWa }}">!</span><div class="text-text">{{ $abertos->count() === 1 ? '1 MDF-e ainda aberto' : $abertos->count() . ' MDF-e ainda abertos' }}<small class="block text-[11.5px] text-text-secondary">MDF-e {{ $abertos->take(5)->pluck('numero')->implode(', ') }}{{ $abertos->count() > 5 ? '…' : '' }}. O evento de encerramento entra no mês em que acontecer.</small></div></div>
                    @endif
                    @if ($r['sem_numero'] > 0)
                        <div class="{{ $pend }}"><span class="{{ $icoWa }}">!</span><div class="text-text">{{ $r['sem_numero'] === 1 ? '1 número sem documento autorizado' : $r['sem_numero'] . ' números sem documento autorizado' }}<small class="block text-[11.5px] text-text-secondary">Veja a conferência ao lado.</small></div></div>
                    @endif
                    @if ($r['sem_xml'] > 0)
                        <div class="{{ $pend }}"><span class="{{ $icoWa }}">!</span><div class="text-text">{{ $r['sem_xml'] === 1 ? '1 documento sem XML guardado' : $r['sem_xml'] . ' documentos sem XML guardado' }}<small class="block text-[11.5px] text-text-secondary">{{ config('fiscal.sefaz.driver') === 'fake' ? 'O emissor de teste não gera XML — com a SEFAZ real, cada autorização guarda o seu.' : 'Eles saem no resumo.csv marcados como "Não guardado".' }}</small></div></div>
                    @elseif ($r['arquivos'] > 0)
                        <div class="{{ $pend }}"><span class="{{ $icoOk }}"><x-icon name="check" class="h-3 w-3" /></span><div class="text-text">Todos os documentos têm XML guardado<small class="block text-[11.5px] text-text-secondary">{{ $fmt($r['arquivos'], 0) }} arquivos conferidos</small></div></div>
                    @endif
                    @if ($abertos->isEmpty() && $r['sem_numero'] === 0 && $r['arquivos'] === 0)
                        <p class="text-[12.5px] text-text-muted">Nada emitido em {{ $nomeMes }}{{ $filial !== '' ? ' nesta filial' : '' }}.</p>
                    @endif
                </div>

                <span class="mb-1.5 mt-3.5 block text-[11px] font-semibold uppercase tracking-wide text-text-muted">O ZIP</span>
                <div class="overflow-x-auto whitespace-pre rounded-lg bg-surface-elevated px-3 py-2.5 font-mono text-[12px] leading-relaxed text-text-secondary">xml-{{ $mes }}.zip
@foreach ($r['pastas'] as $pasta => $qtd)├─ {{ str_pad($pasta . '/', 20) }}{{ str_pad((string) $qtd, 5, ' ', STR_PAD_LEFT) }}
@endforeach└─ resumo.csv</div>
                <x-button class="mt-3 h-[42px] w-full justify-center" variant="primary" icon="download" wire:click="gerar" wire:loading.attr="disabled" :disabled="! $cte && ! $mdfe">
                    Baixar ZIP · {{ $fmt($r['arquivos'] - $r['sem_xml'] + 1, 0) }} {{ $r['arquivos'] - $r['sem_xml'] + 1 === 1 ? 'arquivo' : 'arquivos' }}
                </x-button>
                <p class="mt-2.5 text-[11.5px] leading-relaxed text-text-secondary">O resumo.csv lista chave, número, data, tomador, valor e situação de cada documento — pronto para o contador bater com a escrita fiscal.</p>
            </x-card>
        </aside>
    </div>
</div>
