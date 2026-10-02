{{--
    Rotina 5010 — ficha da fatura (mockup aprovado em 02/10/2026).
--}}
@php
    $f = $this->dados;
    $fmt = fn ($v, $d = 2) => number_format((float) $v, $d, ',', '.');
    $sit = $f->situacao();
    $kpiT = 'text-text-muted uppercase tracking-wide text-[10px] font-semibold';
    $kpiV = 'text-[21px] font-medium leading-tight mt-0.5';
    $proximo = $f->titulos->first(fn ($t) => $t->emAberto());
    $recebidas = $f->titulos->where('status', 'recebido')->count();
    $ultimoRec = $f->titulos->flatMap->recebimentos->reject->estornado()->sortByDesc('data')->first();
@endphp
<div>
    <a href="{{ route('faturas.index') }}" wire:navigate class="mb-1.5 inline-flex items-center gap-1 text-[12.5px] text-text-secondary hover:text-text">‹ Faturas</a>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-text">
                Fatura {{ $f->numero }}
                <x-badge :variant="config('financeiro.fatura_cores.' . $sit, 'gray')" class="ml-1 align-middle text-[11px]">{{ config('financeiro.fatura_status.' . $sit, $sit) }}</x-badge>
            </h1>
            <p class="mt-1 text-sm text-text-secondary">
                {{ $f->tomador?->razao_social }} · {{ $f->tomador?->documentoFormatado() }} · emitida em {{ $f->emissao?->format('d/m/Y') }}{{ $f->criadaPor ? ' por ' . $f->criadaPor->name : '' }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-button variant="neutral" size="sm" icon="file-text" :href="route('faturas.imprimir', $f)" target="_blank">Imprimir fatura</x-button>
            @can('cancelar', $f)
                @unless ($f->cancelada())
                    <x-button variant="danger-outline" size="sm" wire:click="abrirCancelamento" :disabled="! $f->cancelavel()"
                              :title="$f->cancelavel() ? 'Cancelar e liberar os CT-e' : 'Tem recebimento registrado: estorne antes de cancelar'">Cancelar fatura</x-button>
                @endunless
            @endcan
            @if ($proximo)
                @can('receber', $proximo)
                    <x-button variant="success" size="sm" icon="check" wire:click="abrirRecebimento({{ $proximo->id }})">Registrar recebimento</x-button>
                @endcan
            @endif
        </div>
    </div>

    @include('livewire.financeiro.alertas')

    @if ($f->cancelada())
        <div class="mb-4 flex items-start gap-2 rounded-r-md border-l-[3px] border-danger bg-red-50 px-4 py-2.5 text-sm text-red-800 dark:bg-red-950/40 dark:text-red-300">
            <x-icon name="alert-triangle" class="mt-0.5 h-4 w-4 flex-shrink-0" />
            <span>Cancelada em {{ $f->cancelada_em?->format('d/m/Y H:i') }}{{ $f->canceladaPor ? ' por ' . $f->canceladaPor->name : '' }}. Motivo: {{ $f->motivo_cancelamento }}. Os CT-e voltaram a poder ser faturados.</span>
        </div>
    @endif

    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4" style="font-variant-numeric:tabular-nums">
        <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
            <p class="{{ $kpiT }}">Valor da fatura</p>
            <p class="{{ $kpiV }} text-text">R$ {{ $fmt($f->valor_total) }}</p>
            <p class="truncate text-[11px] text-text-secondary">
                {{ $f->itens->count() }} CT-e · {{ $f->rotuloCondicao() }}
                @if ((float) $f->desconto > 0) · desconto R$ {{ $fmt($f->desconto) }} @endif
                @if ((float) $f->acrescimo > 0) · acréscimo R$ {{ $fmt($f->acrescimo) }} @endif
            </p>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
            <p class="{{ $kpiT }}">Recebido</p>
            <p class="{{ $kpiV }} text-green-600 dark:text-green-400">R$ {{ $fmt($f->valor_recebido) }}</p>
            <p class="truncate text-[11px] text-text-secondary">{{ $recebidas }} de {{ $f->titulos->count() }} {{ $f->titulos->count() === 1 ? 'parcela' : 'parcelas' }}{{ $ultimoRec ? ' · ' . config('financeiro.formas.' . $ultimoRec->forma) . ' em ' . $ultimoRec->data?->format('d/m') : '' }}</p>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
            <p class="{{ $kpiT }}">Saldo</p>
            <p class="{{ $kpiV }} text-text">R$ {{ $fmt($f->saldo()) }}</p>
            <p class="truncate text-[11px] text-text-secondary">{{ $proximo ? 'Parcela ' . $proximo->parcela . '/' . $proximo->parcelas : ($f->cancelada() ? 'Fatura cancelada' : 'Quitada') }}</p>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
            <p class="{{ $kpiT }}">Próximo vencimento</p>
            @if ($proximo)
                <p class="{{ $kpiV }} {{ $proximo->vencido() ? 'text-red-600 dark:text-red-400' : '' }}" @unless ($proximo->vencido()) style="color:#2a78d6" @endunless>{{ $proximo->vencimento?->format('d/m/Y') }}</p>
                <p class="truncate text-[11px] text-text-secondary">
                    @if ($proximo->vencido())
                        Venceu há {{ $proximo->diasAtraso() }} {{ $proximo->diasAtraso() === 1 ? 'dia' : 'dias' }}
                    @elseif ($proximo->vencimento?->isToday())
                        Vence hoje
                    @else
                        Daqui a {{ (int) today()->diffInDays($proximo->vencimento) }} dias
                    @endif
                </p>
            @else
                <p class="{{ $kpiV }} text-text-muted">—</p>
                <p class="truncate text-[11px] text-text-secondary">Nada em aberto</p>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-[minmax(0,1fr)_300px]">
        <div class="flex flex-col gap-4">
            {{-- Parcelas --}}
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center justify-between border-b border-border px-5 py-3.5">
                    <h2 class="text-sm font-semibold text-text">Parcelas</h2>
                    <span class="text-xs text-text-muted">Cada parcela é um título em Contas a receber</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full" style="font-variant-numeric:tabular-nums">
                        <thead class="border-b border-border bg-surface-elevated">
                            <tr>
                                @foreach (['Título', 'Vencimento', 'Valor', 'Recebido', 'Situação', ''] as $i => $cab)
                                    <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-text-muted first:pl-5 {{ in_array($i, [2, 3], true) ? 'text-right' : 'text-left' }}">{{ $cab }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($f->titulos as $t)
                                <tr wire:key="tt-{{ $t->id }}" class="border-t border-border align-top">
                                    <td class="whitespace-nowrap px-4 py-3 pl-5 font-mono text-sm text-text">{{ $t->numero }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-text-secondary">{{ $t->vencimento?->format('d/m/Y') }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-text">R$ {{ $fmt($t->valor) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-text-secondary">{{ (float) $t->valor_baixado > 0 ? 'R$ ' . $fmt($t->valor_baixado) : '—' }}</td>
                                    <td class="px-4 py-3">
                                        @if ($t->vencido())
                                            <x-badge variant="danger" class="text-[11px]">Vencida há {{ $t->diasAtraso() }} {{ $t->diasAtraso() === 1 ? 'dia' : 'dias' }}</x-badge>
                                        @else
                                            <x-badge :variant="['aberto' => 'gray', 'parcial' => 'info', 'recebido' => 'success', 'cancelado' => 'gray'][$t->status] ?? 'gray'" class="text-[11px]">{{ config('financeiro.titulo_status.' . $t->status) }}</x-badge>
                                        @endif
                                        @foreach ($t->recebimentos as $r)
                                            <div class="mt-1.5 flex items-center gap-2 text-[11.5px] {{ $r->estornado() ? 'text-text-muted line-through' : 'text-text-secondary' }}">
                                                <span>{{ $r->data?->format('d/m') }} · {{ config('financeiro.formas.' . $r->forma) }} · R$ {{ $fmt($r->valor_total) }}</span>
                                                @if (! $r->estornado() && ! $f->cancelada())
                                                    @can('estornar', $t)
                                                        <button type="button" wire:click="abrirEstorno({{ $r->id }})" class="font-medium text-red-700 no-underline hover:underline dark:text-red-400">Estornar</button>
                                                    @endcan
                                                @endif
                                            </div>
                                        @endforeach
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">
                                        @if ($t->emAberto())
                                            @can('receber', $t)
                                                <x-button variant="success" size="xs" wire:click="abrirRecebimento({{ $t->id }})">Receber</x-button>
                                            @endcan
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            {{-- CT-e --}}
            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center justify-between border-b border-border px-5 py-3.5">
                    <h2 class="text-sm font-semibold text-text">CT-e nesta fatura</h2>
                    <span class="text-xs text-text-muted">{{ $f->itens->count() }} {{ $f->itens->count() === 1 ? 'documento' : 'documentos' }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full" style="font-variant-numeric:tabular-nums">
                        <thead class="border-b border-border bg-surface-elevated">
                            <tr>
                                @foreach (['CT-e', 'Emissão', 'Origem → destino', 'Viagem', 'Valor'] as $i => $cab)
                                    <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-text-muted first:pl-5 {{ $i === 4 ? 'text-right' : 'text-left' }}">{{ $cab }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($f->itens as $it)
                                <tr wire:key="fc-{{ $it->id }}" class="border-t border-border">
                                    <td class="whitespace-nowrap px-4 py-2.5 pl-5">
                                        @if ($it->cte)
                                            <a href="{{ route('cte.editar', $it->cte) }}" wire:navigate class="font-mono text-sm text-[var(--h6-azul-tx)] hover:underline">{{ number_format((int) $it->cte->numero, 0, ',', '.') }}</a>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-sm text-text-secondary">{{ $it->cte?->emissao?->format('d/m/Y') }}</td>
                                    <td class="px-4 py-2.5 text-sm text-text">{{ (int) $it->cte?->tipo_cte === 1 ? 'Complementar · ' : '' }}{{ $it->cte?->municipioInicio?->nomeComUf() ?? '—' }} → {{ $it->cte?->municipioFim?->nomeComUf() ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 font-mono text-sm text-text-secondary">{{ $it->cte?->viagens->first()?->numero ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right font-mono text-sm text-text">R$ {{ $fmt($it->valor) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            @if ($f->observacoes)
                <x-card padding="sm">
                    <h2 class="mb-1 text-sm font-semibold text-text">Observações</h2>
                    <p class="whitespace-pre-line text-sm text-text-secondary">{{ $f->observacoes }}</p>
                </x-card>
            @endif
        </div>

        {{-- Histórico --}}
        <x-card padding="sm">
            <h2 class="mb-2 text-sm font-semibold text-text">Histórico</h2>
            <div class="flex flex-col">
                @foreach ($this->historico as $i => $e)
                    <div class="relative flex gap-2.5 py-2 text-[12.5px]">
                        <i class="mt-1 h-[9px] w-[9px] flex-shrink-0 rounded-full" style="background: {{ $e['cor'] }}"></i>
                        @unless ($loop->last)
                            <span class="absolute bottom-[-6px] left-[4px] top-[18px] w-px bg-border"></span>
                        @endunless
                        <div class="min-w-0 flex-1">
                            <p class="text-text">{{ $e['titulo'] }}</p>
                            <p class="text-[11px] text-text-muted">{{ $e['quando']->format('d/m/Y H:i') }}{{ $e['sub'] ? ' · ' . $e['sub'] : '' }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>
    </div>

    {{-- Cancelar fatura --}}
    @if ($cancelando)
        <div class="fixed inset-0 z-[90] flex items-start justify-center bg-[rgba(15,26,58,.45)] px-4 pt-[14vh]" wire:keydown.escape.window="$set('cancelando', false)">
            <div class="w-full max-w-sm rounded-2xl bg-surface p-5 shadow-modal" role="dialog" aria-modal="true" aria-label="Cancelar fatura">
                <h3 class="text-base font-bold text-text">Cancelar a fatura {{ $f->numero }}</h3>
                <p class="mt-0.5 text-[12.5px] text-text-secondary">As parcelas são canceladas e os {{ $f->itens->count() }} CT-e voltam a poder ser faturados. A fatura fica no histórico.</p>
                <x-input class="mt-4" label="Motivo do cancelamento" required wire:model="motivoCancelamento" placeholder="Ex.: Cliente pediu para separar por filial" :error="$errors->first('motivoCancelamento')" />
                <div class="mt-5 flex justify-end gap-2">
                    <x-button variant="neutral" size="sm" wire:click="$set('cancelando', false)">Voltar</x-button>
                    <x-button variant="danger" size="sm" wire:click="cancelar" wire:loading.attr="disabled">Cancelar fatura</x-button>
                </div>
            </div>
        </div>
    @endif

    @include('livewire.financeiro.modal-recebimento')
</div>
