{{-- Rotina 5030 — Lançamentos esperando (mockup aprovado em 03/10/2026). --}}
@php
    $fmt = fn ($v, $d = 2) => number_format((float) $v, $d, ',', '.');
    $s = $this->sugestoes;
    $r = $this->resumo;
    $abastSel = array_map('intval', $abastecimentos);
    $linha = 'grid cursor-pointer grid-cols-[22px_86px_minmax(0,1fr)_110px] items-center gap-2.5 border-b border-border px-3 py-2.5 text-[13px] last:border-b-0 sm:grid-cols-[22px_86px_minmax(0,1fr)_80px_110px]';
    $cab = 'flex items-center justify-between gap-2 border-b border-border bg-surface-elevated px-3 py-2 text-xs text-text-secondary';
    $vazio = $s['abastecimentos']->isEmpty() && $s['ordens']->isEmpty() && $s['ciots']->isEmpty();
@endphp
<div>
    <a href="{{ route('contas-pagar.index') }}" wire:navigate class="mb-1.5 inline-flex items-center gap-1 text-[12.5px] text-text-secondary hover:text-text">‹ Contas a pagar</a>
    <x-page-header title="Lançamentos esperando" subtitle="O que o sistema já sabe que vai ser pago. Confira o vencimento e lance — vira conta a pagar." />

    @if ($vazio)
        <x-card padding="none">
            <x-empty-state icon="check" title="Nada esperando"
                           description="Abastecimento com posto, OS encerrada em oficina externa e frete de TAC do CIOT aparecem aqui para virar conta a pagar." />
        </x-card>
    @else
        <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
            <div class="flex flex-col gap-4" style="font-variant-numeric:tabular-nums">
                @if ($s['abastecimentos']->isNotEmpty())
                    <x-card padding="sm">
                        <p class="mb-2.5 flex items-center justify-between text-[14px] font-semibold text-text">Abastecimentos com posto<span class="text-xs font-normal text-text-muted">Agrupados por posto: uma conta por posto</span></p>
                        <div class="flex flex-col gap-2">
                            @foreach ($s['abastecimentos'] as $g)
                                @php
                                    $ids = $g['itens']->pluck('id')->all();
                                    $todos = array_diff($ids, $abastSel) === [];
                                @endphp
                                <div wire:key="ps-{{ $g['favorecido']->id }}" class="overflow-hidden rounded-[10px] border border-border">
                                    <div class="{{ $cab }}">
                                        <label class="flex items-center gap-2 font-semibold text-text">
                                            <input type="checkbox" wire:click="alternarPosto({{ $g['favorecido']->id }})" @checked($todos) class="h-4 w-4 rounded border-border text-[var(--h6-azul)]">
                                            {{ $g['favorecido']->razao_social }}
                                        </label>
                                        <span>{{ $g['favorecido']->prazo_faturamento ? 'Prazo do posto: ' . $g['condicao'] . ' dias' : 'Sem prazo no cadastro' }} · vence {{ $this->vencimentoDe($g['condicao']) }}</span>
                                    </div>
                                    @foreach ($g['itens'] as $a)
                                        @php $on = in_array($a->id, $abastSel, true); @endphp
                                        <label wire:key="ab-{{ $a->id }}" class="{{ $linha }} {{ $on ? 'bg-[var(--h6-hover-bg)]' : '' }}">
                                            <input type="checkbox" value="{{ $a->id }}" wire:model.live="abastecimentos" class="h-4 w-4 rounded border-border text-[var(--h6-azul)]">
                                            <span class="font-mono text-xs font-semibold text-[var(--h6-azul-tx)]">{{ $a->data_hora?->format('d/m') }}<small class="block font-sans text-[11px] font-normal text-text-muted">{{ $a->veiculo?->placaFormatada() }}</small></span>
                                            <span class="min-w-0"><b class="block truncate font-medium text-text">{{ $fmt($a->litros, 2) }} L {{ str_replace('_', ' ', (string) $a->combustivel) }}</b><small class="text-[11px] text-text-secondary">{{ $a->nota_fiscal ? 'NF ' . $a->nota_fiscal : 'Sem nota' }}</small></span>
                                            <span class="hidden font-mono text-[11.5px] text-text-secondary sm:block">Abast.</span>
                                            <span class="text-right font-mono font-medium text-text">R$ {{ $fmt($a->valor_total) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </x-card>
                @endif

                @if ($s['ordens']->isNotEmpty())
                    <x-card padding="sm">
                        <p class="mb-2.5 flex items-center justify-between text-[14px] font-semibold text-text">Ordens de serviço em oficina externa<span class="text-xs font-normal text-text-muted">Uma conta por OS</span></p>
                        <div class="overflow-hidden rounded-[10px] border border-border">
                            @foreach ($s['ordens'] as $o)
                                @php $on = in_array((string) $o->id, $ordens, true); @endphp
                                <label wire:key="os-{{ $o->id }}" class="{{ $linha }} {{ $on ? 'bg-[var(--h6-hover-bg)]' : '' }}">
                                    <input type="checkbox" value="{{ $o->id }}" wire:model.live="ordens" class="h-4 w-4 rounded border-border text-[var(--h6-azul)]">
                                    <span class="font-mono text-xs font-semibold text-[var(--h6-azul-tx)]">OS {{ $o->numero }}<small class="block font-sans text-[11px] font-normal text-text-muted">{{ $o->veiculo?->placaFormatada() }}</small></span>
                                    <span class="min-w-0"><b class="block truncate font-medium text-text">{{ $o->oficina?->razao_social }}</b><small class="text-[11px] text-text-secondary">{{ ucfirst((string) $o->tipo) }} · encerrada {{ $o->encerramento?->format('d/m') ?? '—' }}</small></span>
                                    <span class="hidden font-mono text-[11.5px] text-text-secondary sm:block">vence {{ $this->vencimentoDe(app(\App\Services\Financeiro\ContasPagar::class)->condicaoDe($o->oficina)) }}</span>
                                    <span class="text-right font-mono font-medium text-text">R$ {{ $fmt($o->valor_total) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </x-card>
                @endif

                @if ($s['ciots']->isNotEmpty())
                    <x-card padding="sm">
                        <p class="mb-2.5 flex items-center justify-between text-[14px] font-semibold text-text">Frete de TAC (CIOT)<span class="text-xs font-normal text-text-muted">O pagamento sai pela instituição, no 4050</span></p>
                        <div class="overflow-hidden rounded-[10px] border border-border">
                            @foreach ($s['ciots'] as $c)
                                @php $on = in_array((string) $c->id, $ciots, true); @endphp
                                <label wire:key="ci-{{ $c->id }}" class="{{ $linha }} {{ $on ? 'bg-[var(--h6-hover-bg)]' : '' }}">
                                    <input type="checkbox" value="{{ $c->id }}" wire:model.live="ciots" class="h-4 w-4 rounded border-border text-[var(--h6-azul)]">
                                    <span class="font-mono text-xs font-semibold text-[var(--h6-azul-tx)]">{{ $c->viagem?->numero }}<small class="block font-sans text-[11px] font-normal text-text-muted">{{ $c->viagem?->veiculoTracao?->placaFormatada() }}</small></span>
                                    <span class="min-w-0"><b class="block truncate font-medium text-text">{{ $c->contratado_nome }}</b><small class="text-[11px] text-text-secondary">CIOT {{ $c->numeroFormatado() }}{{ (float) $c->valor_pago > 0 ? ' · já pago R$ ' . $fmt($c->valor_pago) : '' }}</small></span>
                                    <span class="hidden font-mono text-[11.5px] text-text-secondary sm:block">{{ $c->prazo_quitacao?->format('d/m') }}</span>
                                    <span class="text-right font-mono font-medium text-text">R$ {{ $fmt($c->valor_frete) }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-2 text-[11.5px] text-text-secondary">Entra com o que já foi pago ao TAC baixado. Quando o saldo for pago no 4050, a conta baixa sozinha.</p>
                    </x-card>
                @endif
            </div>

            <aside class="sticky top-0">
                <x-card padding="sm">
                    <p class="mb-2 text-[14px] font-semibold text-text">Resumo</p>
                    <div class="flex justify-between py-1 text-[13px]"><span class="text-text-secondary">Abastecimentos</span><b class="font-mono font-medium">R$ {{ $fmt($r['abast']) }}</b></div>
                    <div class="flex justify-between py-1 text-[13px]"><span class="text-text-secondary">Ordens de serviço</span><b class="font-mono font-medium">R$ {{ $fmt($r['os']) }}</b></div>
                    <div class="flex justify-between py-1 text-[13px]"><span class="text-text-secondary">Frete de TAC</span><b class="font-mono font-medium">R$ {{ $fmt($r['ciot']) }}</b></div>
                    <div class="mt-1.5 flex justify-between border-t border-border pt-2.5 text-[15px]"><span class="text-text-secondary">{{ $r['contas'] }} {{ $r['contas'] === 1 ? 'conta nova' : 'contas novas' }}</span><b class="font-mono text-[17px] font-bold">R$ {{ $fmt($r['total']) }}</b></div>
                    @error('lancar') <p class="mt-2 text-xs text-danger">{{ $message }}</p> @enderror
                    <x-button class="mt-3 h-[42px] w-full justify-center" variant="primary" icon="check" wire:click="lancar" wire:loading.attr="disabled" :disabled="$r['contas'] === 0">
                        {{ $r['contas'] === 1 ? 'Lançar 1 conta' : 'Lançar ' . $r['contas'] . ' contas' }}
                    </x-button>
                    <p class="mt-2.5 text-[11.5px] leading-relaxed text-text-secondary">Vencimento pelo prazo do fornecedor no cadastro (Pessoas 1010); sem prazo, 30 dias. Item lançado não volta a aparecer aqui — só se a conta for cancelada.</p>
                </x-card>
            </aside>
        </div>
    @endif
</div>
