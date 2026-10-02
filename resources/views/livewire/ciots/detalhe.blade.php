{{-- Detalhe do CIOT (rotina 4050) — mockup aprovado em 02/10/2026. --}}
@php
    $c = $this->dados;
    $v = $c->viagem;
    $sit = $c->situacao();
    $fmt = fn ($x) => number_format((float) $x, 2, ',', '.');
    $doc = fn (?string $d) => match (strlen((string) $d)) {
        11 => vsprintf('%s%s%s.%s%s%s.%s%s%s-%s%s', str_split((string) $d)),
        14 => vsprintf('%s%s.%s%s%s.%s%s%s/%s%s%s%s-%s%s', str_split((string) $d)),
        default => (string) $d,
    };
    $kpiT = 'text-text-muted uppercase tracking-wide text-[10px] font-semibold';
    $kpiV = 'text-[21px] font-medium leading-tight mt-0.5';
    $podeGerenciar = auth()->user()?->can('gerenciar', $c);
    $temPag = $c->temPagamento();
    $adiantPago = $c->pagamentos->first(fn ($p) => $p->tipo === 'adiantamento' && $p->status === 'confirmado');
    $saldoPago = $c->pagamentos->first(fn ($p) => $p->tipo === 'saldo' && $p->status === 'confirmado');
    $entregue = in_array($v?->status, ['entregue', 'encerrada'], true);
    $dias = $this->diasUteisRestantes;
@endphp
<div>
    <a href="{{ route('ciot.index') }}" wire:navigate class="mb-1.5 inline-flex items-center gap-1 text-[12.5px] text-text-secondary hover:text-text">‹ CIOT</a>
    <x-page-header :title="'CIOT ' . ($c->numero ? $c->numeroFormatado() : '— sem número')"
                   :subtitle="'Viagem ' . ($v?->numero ?? '—') . ' · ' . ($c->contratado_nome ?? 'Frota própria') . ' · ' . config('ciot.modalidades.' . $c->modalidade) . ($c->registrado_em ? ' · registrado em ' . $c->registrado_em->format('d/m/Y H:i') : '') . ($c->criadoPor ? ' por ' . $c->criadoPor->name : '')">
        <x-slot:actions>
            <x-badge :variant="config('ciot.situacao_cores.' . $sit, 'gray')" class="text-[12px]">{{ config('ciot.situacoes.' . $sit, $sit) }}</x-badge>
            @if ($podeGerenciar)
                @if ($c->cancelavel())
                    <x-button variant="danger-outline" size="sm" wire:click="$set('cancelando', true)">Cancelar CIOT</x-button>
                @endif
                @if ($c->status === 'recusado' || ($c->valido() && $c->adiantamentoPendente()))
                    <x-button variant="neutral" size="sm" icon="upload" wire:click="reenviar" wire:loading.attr="disabled">Reenviar</x-button>
                @endif
                @if ($temPag && $c->status === 'registrado' && $c->saldoAPagar() > 0)
                    <x-button variant="success" size="sm" icon="check" wire:click="abrirPagamento">Pagar saldo</x-button>
                @endif
            @endif
        </x-slot:actions>
    </x-page-header>

    @if (session('sucesso'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800/50 dark:bg-green-950/50 dark:text-green-300">{{ session('sucesso') }}</div>
    @endif
    @if (session('erro'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800/50 dark:bg-red-950/50 dark:text-red-300">{{ session('erro') }}</div>
    @endif
    @if ($c->status === 'recusado')
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800/50 dark:bg-red-950/50 dark:text-red-300">
            <b>Recusado:</b> {{ $c->motivo }} Reenvie aqui ou corrija o frete na emissão do MDF-e.
        </div>
    @endif

    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4" style="font-variant-numeric:tabular-nums">
        <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
            <p class="{{ $kpiT }}">{{ $temPag ? 'Frete contratado' : 'Frete da operação' }}</p>
            <p class="{{ $kpiV }} text-text">R$ {{ $fmt($c->valor_frete) }}</p>
            <p class="truncate text-[11px] text-text-secondary">{{ $temPag ? 'Pedágio à parte, no vale' : 'Sem pagamento a terceiro' }}</p>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
            <p class="{{ $kpiT }}">Pago</p>
            <p class="{{ $kpiV }} {{ (float) $c->valor_pago > 0 ? 'text-green-600 dark:text-green-400' : 'text-text' }}">{{ $temPag ? 'R$ ' . $fmt($c->valor_pago) : '—' }}</p>
            <p class="truncate text-[11px] text-text-secondary">
                @if ($adiantPago) Adiantamento {{ rtrim(rtrim(number_format((float) $c->percentual_adiantamento, 2, ',', ''), '0'), ',') }}% · {{ config('ciot.formas.' . $adiantPago->forma) }} em {{ $adiantPago->data->format('d/m') }}
                @elseif ($temPag && (float) $c->valor_adiantamento > 0) Adiantamento pendente
                @else — @endif
            </p>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
            <p class="{{ $kpiT }}">Saldo</p>
            <p class="{{ $kpiV }} text-text">{{ $temPag ? 'R$ ' . $fmt($c->saldoAPagar()) : '—' }}</p>
            <p class="truncate text-[11px] text-text-secondary">{{ $temPag ? ($c->saldoAPagar() > 0 ? ($entregue ? 'Entrega comprovada — pode pagar' : 'Paga depois da entrega') : 'Quitado') : '—' }}</p>
        </div>
        <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
            <p class="{{ $kpiT }}">Quitar até</p>
            <p class="{{ $kpiV }} {{ $c->saldoVencido() ? 'text-red-600 dark:text-red-400' : '' }}" style="{{ $c->saldoVencido() ? '' : 'color:#2a78d6' }}">{{ $c->prazo_quitacao?->format('d/m/Y') ?? '—' }}</p>
            <p class="truncate text-[11px] text-text-secondary">
                @if ($dias === null || ! $temPag || $c->saldoAPagar() <= 0) —
                @elseif ($dias < 0) Venceu há {{ abs($dias) }} {{ abs($dias) === 1 ? 'dia útil' : 'dias úteis' }}
                @elseif ($dias === 0) Vence hoje
                @else Faltam {{ $dias }} {{ $dias === 1 ? 'dia útil' : 'dias úteis' }} @endif
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_300px]">
        <div class="flex flex-col gap-4">
            @if ($temPag)
                <x-card padding="none" class="overflow-hidden">
                    <div class="flex items-center gap-2 border-b border-border px-5 py-3.5"><h2 class="text-sm font-semibold text-text">Pagamentos ao TAC</h2><span class="ml-auto text-xs text-text-muted">Sempre na conta do próprio TAC ou em cartão frete</span></div>
                    <div class="overflow-x-auto">
                        <table class="w-full" style="font-variant-numeric:tabular-nums">
                            <thead class="border-b border-border bg-surface-elevated"><tr>
                                @foreach (['Parcela', 'Previsto', 'Valor', 'Pago em', 'Situação'] as $i => $cab)
                                    <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-text-muted first:pl-5 {{ $i === 2 ? 'text-right' : 'text-left' }}">{{ $cab }}</th>
                                @endforeach
                            </tr></thead>
                            <tbody>
                                @if ((float) $c->valor_adiantamento > 0)
                                    <tr class="border-t border-border">
                                        <td class="px-4 py-3 pl-5 text-sm">Adiantamento</td>
                                        <td class="px-4 py-3 text-sm text-text-secondary">Na saída</td>
                                        <td class="px-4 py-3 text-right font-mono text-sm">R$ {{ $fmt($c->valor_adiantamento) }}</td>
                                        <td class="px-4 py-3 text-sm text-text-secondary">{{ $adiantPago ? $adiantPago->data->format('d/m/Y') . ' · ' . config('ciot.formas.' . $adiantPago->forma) : '—' }}</td>
                                        <td class="px-4 py-3">@if ($adiantPago)<x-badge variant="success" class="text-[11px]">Pago · confirmado</x-badge>@else<x-badge variant="danger" class="text-[11px]">Pendente</x-badge>@endif</td>
                                    </tr>
                                @endif
                                <tr class="border-t border-border">
                                    <td class="px-4 py-3 pl-5 text-sm">Saldo</td>
                                    <td class="px-4 py-3 text-sm text-text-secondary">Até {{ $c->prazo_quitacao?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-sm">R$ {{ $fmt($c->valor_saldo) }}</td>
                                    <td class="px-4 py-3 text-sm text-text-secondary">{{ $saldoPago ? $saldoPago->data->format('d/m/Y') . ' · ' . config('ciot.formas.' . $saldoPago->forma) : '—' }}</td>
                                    <td class="px-4 py-3">
                                        @if ($saldoPago)<x-badge variant="success" class="text-[11px]">Pago · confirmado</x-badge>
                                        @elseif ($c->saldoVencido())<x-badge variant="danger" class="text-[11px]">Vencido</x-badge>
                                        @else<x-badge variant="gray" class="text-[11px]">{{ $entregue ? 'Pode pagar' : 'Aguardando entrega' }}</x-badge>@endif
                                    </td>
                                </tr>
                                @foreach ($c->pagamentos->where('status', 'recusado') as $p)
                                    <tr class="border-t border-border">
                                        <td class="px-4 py-2.5 pl-5 text-xs text-text-secondary" colspan="5">{{ ucfirst($p->tipo) }} recusado em {{ $p->created_at->format('d/m H:i') }}: {{ $p->motivo }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="border-t border-border px-5 py-2.5 text-[11.5px] text-text-secondary">Recebe em: {{ config('ciot.formas.' . $c->forma_pagamento, '—') }} · {{ $c->chave_pagamento ?? '—' }}</p>
                </x-card>
            @endif

            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5"><h2 class="text-sm font-semibold text-text">Partes</h2></div>
                <div class="grid grid-cols-1 gap-x-6 gap-y-1 px-5 py-4 sm:grid-cols-2">
                    <x-linha-ficha rotulo="Contratante" :valor="$doc($c->contratante_documento) ?: '—'" mono />
                    <x-linha-ficha rotulo="Responsável no MDF-e" :valor="$doc($c->responsavel_documento) ?: '—'" mono />
                    <x-linha-ficha rotulo="Contratado" :valor="$c->contratado_nome ?? 'Frota própria'" />
                    <x-linha-ficha rotulo="CPF/CNPJ do contratado" :valor="$doc($c->contratado_documento) ?: '—'" mono />
                    <x-linha-ficha rotulo="RNTRC do contratado" :valor="$c->contratado_rntrc ?? '—'" mono />
                    <x-linha-ficha rotulo="Registrado por" :valor="$c->instituicao ?? '—'" />
                </div>
            </x-card>

            <x-card padding="none" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-3.5"><h2 class="text-sm font-semibold text-text">Documentos da viagem</h2><span class="ml-auto text-xs text-text-muted">O CIOT segue com eles</span></div>
                <div class="flex flex-col gap-2 px-5 py-4">
                    @if ($v)
                        <a href="{{ route('viagens.editar', $v) }}" wire:navigate class="flex items-center gap-3 rounded-lg border border-border px-3 py-2.5 hover:bg-[var(--h6-hover-bg)]">
                            <span class="min-w-[84px] font-mono text-xs font-semibold text-[var(--h6-azul-tx)]">{{ $v->numero }}</span>
                            <div><b class="block text-[13px] font-medium text-text">Viagem</b><small class="text-[11.5px] text-text-secondary">{{ $v->municipioOrigem?->nome ?? '—' }} → {{ $v->municipioDestino?->nome ?? '—' }} · {{ $v->veiculoTracao?->placaFormatada() }}</small></div>
                        </a>
                    @endif
                    @foreach ($this->mdfes as $m)
                        <a href="{{ route('mdfe.editar', $m) }}" wire:navigate class="flex items-center gap-3 rounded-lg border border-border px-3 py-2.5 hover:bg-[var(--h6-hover-bg)]">
                            <span class="min-w-[84px] font-mono text-xs font-semibold text-[var(--h6-azul-tx)]">MDF-e {{ $m->numero ?? 'rascunho' }}</span>
                            <div><b class="block text-[13px] font-medium text-text">{{ config('fiscal.mdfe.status.' . $m->status, $m->status) }}{{ $m->ciot === $c->numero && $c->numero ? ' com este CIOT' : '' }}</b><small class="text-[11.5px] text-text-secondary">{{ $m->data_autorizacao?->format('d/m/Y H:i') ?? $m->created_at->format('d/m/Y H:i') }}{{ $m->protocolo ? ' · protocolo ' . $m->protocolo : '' }}</small></div>
                        </a>
                    @endforeach
                    @foreach ($v?->ctes ?? [] as $cte)
                        <a href="{{ route('cte.editar', $cte) }}" wire:navigate class="flex items-center gap-3 rounded-lg border border-border px-3 py-2.5 hover:bg-[var(--h6-hover-bg)]">
                            <span class="min-w-[84px] font-mono text-xs font-semibold text-[var(--h6-azul-tx)]">CT-e {{ $cte->numero ? number_format((int) $cte->numero, 0, ',', '.') : '—' }}</span>
                            <div><b class="block text-[13px] font-medium text-text">{{ $cte->tomador?->razao_social ?? '—' }}</b><small class="text-[11.5px] text-text-secondary">R$ {{ $fmt($cte->valor_total_servico) }}</small></div>
                        </a>
                    @endforeach
                </div>
            </x-card>
        </div>

        <x-card padding="none" class="h-fit overflow-hidden">
            <div class="border-b border-border px-5 py-3.5"><h2 class="text-sm font-semibold text-text">Histórico</h2></div>
            @php
                $hist = collect();
                $hist->push(['quando' => $c->created_at, 'texto' => 'Pedido de CIOT criado', 'det' => $c->criadoPor?->name, 'cor' => '']);
                if ($c->registrado_em) $hist->push(['quando' => $c->registrado_em, 'texto' => 'CIOT registrado · ' . ($c->instituicao ?? ''), 'det' => $c->protocolo ? 'Protocolo ' . $c->protocolo : null, 'cor' => '']);
                if ($c->status === 'recusado') $hist->push(['quando' => $c->updated_at, 'texto' => 'Recusado (' . $c->tentativas . ' tentativa(s))', 'det' => $c->motivo, 'cor' => '#b91c1c']);
                foreach ($c->pagamentos as $p) $hist->push(['quando' => $p->created_at, 'texto' => ucfirst($p->tipo) . ($p->status === 'confirmado' ? ' pago e confirmado' : ' recusado'), 'det' => 'R$ ' . $fmt($p->valor) . ' · ' . config('ciot.formas.' . $p->forma), 'cor' => $p->status === 'confirmado' ? '#16a34a' : '#b91c1c']);
                foreach ($this->mdfes->where('status', 'autorizado') as $m) $hist->push(['quando' => $m->data_autorizacao ?? $m->updated_at, 'texto' => 'MDF-e ' . $m->numero . ' autorizado', 'det' => null, 'cor' => '#16a34a']);
                if ($c->cancelado_em) $hist->push(['quando' => $c->cancelado_em, 'texto' => 'CIOT cancelado', 'det' => $c->motivo_cancelamento, 'cor' => '#6b7280']);
                $hist = $hist->filter(fn ($h) => $h['quando'] !== null)->sortByDesc('quando');
            @endphp
            <div class="flex flex-col px-5 py-3">
                @foreach ($hist as $h)
                    <div class="relative flex gap-2.5 py-2 text-[12.5px]">
                        <i class="mt-1 h-2 w-2 flex-shrink-0 rounded-full" style="background:{{ $h['cor'] ?: 'var(--h6-azul)' }}"></i>
                        <div>{{ $h['texto'] }}<small class="block text-[11px] text-text-muted">{{ $h['quando']->format('d/m/Y H:i') }}{{ $h['det'] ? ' · ' . $h['det'] : '' }}</small></div>
                    </div>
                @endforeach
            </div>
        </x-card>
    </div>

    {{-- Pagar saldo --}}
    @if ($pagando)
        <div class="fixed inset-0 z-[90] flex items-start justify-center bg-[rgba(15,26,58,.45)] px-4 pt-[10vh]" wire:keydown.escape.window="$set('pagando', false)">
            <div class="w-full max-w-md rounded-2xl bg-surface p-5 shadow-2xl" role="dialog" aria-modal="true" aria-label="Pagar saldo do frete">
                <h3 class="text-base font-bold text-text">Pagar saldo do frete</h3>
                <p class="mt-0.5 text-[12.5px] text-text-secondary">CIOT {{ $c->numeroFormatado() }} · {{ $c->contratado_nome }} · quitar até {{ $c->prazo_quitacao?->format('d/m/Y') }}</p>
                <p class="mb-1.5 mt-4 text-[11px] font-semibold uppercase tracking-wide text-text-muted">Pagar em</p>
                <div class="grid grid-cols-3 gap-1.5">
                    @foreach (config('ciot.formas') as $codigo => $rotulo)
                        <button type="button" wire:click="$set('pagForma', '{{ $codigo }}')"
                                class="rounded-lg border py-2 text-xs font-medium {{ $pagForma === $codigo ? 'border-[var(--h6-azul)] bg-[var(--h6-hover-bg)] font-semibold text-[var(--h6-azul-tx)]' : 'border-border text-text-secondary' }}">{{ $rotulo }}</button>
                    @endforeach
                </div>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <x-input label="Data" type="date" wire:model="pagData" />
                    <div>
                        <span class="mb-1.5 block text-sm font-medium text-text-secondary">Entrega</span>
                        <p class="py-2 text-sm {{ $entregue ? 'text-green-700 dark:text-green-400' : 'text-amber-700 dark:text-amber-400' }}">{{ $entregue ? 'Comprovada ✓' : 'Ainda não comprovada' }}</p>
                    </div>
                </div>
                <div class="mt-3 flex items-center justify-between border-t border-border pt-3">
                    <span class="text-sm text-text-secondary">Saldo a pagar</span>
                    <b class="font-mono text-lg font-bold text-text">R$ {{ $fmt($c->saldoAPagar()) }}</b>
                </div>
                @error('pagamento') <p class="mt-2 text-xs text-danger">{{ $message }}</p> @enderror
                <p class="mt-2 text-[11.5px] leading-relaxed text-text-secondary">Sai pela instituição de pagamento, na conta do próprio TAC ({{ $c->chave_pagamento }}). Com o saldo pago, o CIOT fica quitado.</p>
                <div class="mt-5 flex justify-end gap-2">
                    <x-button variant="neutral" size="sm" wire:click="$set('pagando', false)">Cancelar</x-button>
                    <x-button variant="success" size="sm" icon="check" wire:click="pagarSaldo" wire:loading.attr="disabled">Pagar saldo</x-button>
                </div>
            </div>
        </div>
    @endif

    {{-- Cancelar --}}
    @if ($cancelando)
        <div class="fixed inset-0 z-[90] flex items-start justify-center bg-[rgba(15,26,58,.45)] px-4 pt-[10vh]" wire:keydown.escape.window="$set('cancelando', false)">
            <div class="w-full max-w-sm rounded-2xl bg-surface p-5 shadow-2xl" role="dialog" aria-modal="true" aria-label="Cancelar CIOT">
                <h3 class="text-base font-bold text-text">Cancelar CIOT</h3>
                <p class="mt-0.5 text-[12.5px] text-text-secondary">A viagem fica sem CIOT; na próxima emissão do MDF-e um novo é registrado.</p>
                <x-input class="mt-4" label="Motivo" wire:model="motivoCancelamento" placeholder="Ex.: Viagem cancelada pelo cliente" :error="$errors->first('motivoCancelamento')" />
                <div class="mt-5 flex justify-end gap-2">
                    <x-button variant="neutral" size="sm" wire:click="$set('cancelando', false)">Voltar</x-button>
                    <x-button variant="danger" size="sm" wire:click="cancelar" wire:loading.attr="disabled">Cancelar CIOT</x-button>
                </div>
            </div>
        </div>
    @endif
</div>
