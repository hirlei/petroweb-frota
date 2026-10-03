{{-- Rotina 3070 — Acerto de uma viagem (mockup aprovado em 03/10/2026). --}}
@php
    $fmt = fn ($v, $d = 2) => number_format((float) $v, $d, ',', '.');
    $v = $viagem;
    $a = $this->acerto;
    $r = $this->resumo;
    $pode = auth()->user()?->can('gerenciar', \App\Models\AcertoViagem::class) ?? false;
    $acertavel = in_array($v->status, \App\Services\Operacao\Acertos::STATUS_ACERTAVEIS, true);
    $motorista = $v->motorista?->pessoa?->razao_social ?? '—';
    $ini = $v->saida_real ?? $v->saida_prevista;
    $fim = $v->chegada_real ?? $v->chegada_prevista;
    $formasAd = ['pix' => 'Pix', 'dinheiro' => 'Dinheiro', 'cartao_frota' => 'Cartão frota', 'deposito' => 'Depósito'];
    $finalidades = ['geral' => 'Geral', 'pedagio' => 'Pedágio', 'combustivel' => 'Combustível', 'alimentacao' => 'Alimentação'];
    $formasDev = ['pix' => 'Pix', 'dinheiro' => 'Dinheiro', 'deposito' => 'Depósito', 'desconto_folha' => 'Desconto em folha'];
    $secT = 'mb-3 flex items-center justify-between gap-2 text-[14px] font-semibold text-text';
    $th = 'px-3 py-2 text-[11px] font-semibold uppercase tracking-wider text-text-muted';
    $td = 'px-3 py-2 text-[13px]';
    $chip = fn ($on) => 'rounded-lg border py-2 text-xs font-medium ' . ($on ? 'border-[var(--h6-azul)] bg-[var(--h6-hover-bg)] font-semibold text-[var(--h6-azul-tx)]' : 'border-border text-text-secondary');
    $kpiT = 'text-text-muted uppercase tracking-wide text-[10px] font-semibold';
    $kpiV = 'text-[21px] font-medium leading-tight mt-0.5';
@endphp
<div>
    <a href="{{ route('acertos.index') }}" wire:navigate class="mb-1.5 inline-flex items-center gap-1 text-[12.5px] text-text-secondary hover:text-text">‹ Acerto de viagem</a>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="flex flex-wrap items-center gap-2 text-2xl font-bold tracking-tight text-text">
                Acerto · viagem {{ $v->numero }}
                @if ($a)<x-badge variant="success" class="text-[11px]">Fechado</x-badge>@endif
            </h1>
            <p class="mt-1 text-sm text-text-secondary">
                @if ($a)
                    {{ $motorista }} (CLT) · fechado em {{ $a->fechado_em?->format('d/m/Y') }}{{ $a->fechadoPor ? ' por ' . $a->fechadoPor->name : '' }}
                @else
                    {{ $motorista }}{{ $this->elegivel ? ' (CLT)' : '' }} · <span class="font-mono">{{ $v->veiculoTracao?->placaFormatada() }}</span>
                    · {{ $v->municipioOrigem?->nome ?? '—' }} → {{ $v->municipioDestino?->nome ?? '—' }}
                    @if ($ini) · saída {{ $ini->format('d/m H:i') }} @endif
                    @if ($fim) · chegada {{ $fim->format('d/m H:i') }} @endif
                @endif
            </p>
        </div>
        @if ($a)
            <div class="flex flex-shrink-0 items-center gap-2">
                <x-button variant="neutral" size="sm" icon="file-text" :href="route('acertos.recibo', $v)" target="_blank">Recibo do acerto</x-button>
                @if ($pode)
                    <x-button variant="danger-outline" size="sm" wire:click="$set('reabrindo', true)">Reabrir</x-button>
                @endif
            </div>
        @endif
    </div>

    @if (session('sucesso'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800/50 dark:bg-green-950/50 dark:text-green-300">{{ session('sucesso') }}</div>
    @endif
    @if (session('erro'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800/50 dark:bg-red-950/50 dark:text-red-300">{{ session('erro') }}</div>
    @endif

    @if (! $a && ! $this->elegivel)
        {{-- ── Não é CLT ── --}}
        <x-card>
            <x-empty-state icon="wallet" title="Viagem sem acerto"
                           description="O motorista desta viagem não é da empresa (CLT). Agregado e autônomo recebem o frete pelo CIOT (4050) — não há adiantamento, diária nem acerto aqui." />
        </x-card>
    @elseif ($a)
        {{-- ── Fechado ── --}}
        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4" style="font-variant-numeric:tabular-nums">
            <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
                <p class="{{ $kpiT }}">Adiantado</p>
                <p class="{{ $kpiV }} text-text">R$ {{ $fmt($a->total_adiantado) }}</p>
                <p class="truncate text-[11px] text-text-secondary">{{ $this->adiantamentos->count() }} {{ $this->adiantamentos->count() === 1 ? 'adiantamento' : 'adiantamentos' }}</p>
            </div>
            <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
                <p class="{{ $kpiT }}">Despesas aceitas</p>
                <p class="{{ $kpiV }} text-text">R$ {{ $fmt((float) $a->gasto_adiantamento + (float) $a->bolso_aceito) }}</p>
                <p class="truncate text-[11px] text-text-secondary">{{ (float) $a->glosado > 0 ? 'Glosado: R$ ' . $fmt($a->glosado) : 'Nenhuma glosa' }}</p>
            </div>
            <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
                <p class="{{ $kpiT }}">Diárias e comissão</p>
                <p class="{{ $kpiV }} text-text">R$ {{ $fmt((float) $a->diarias_total + (float) $a->comissao_valor) }}</p>
                <p class="truncate text-[11px] text-text-secondary">{{ $a->dias }} × R$ {{ $fmt($a->diaria_valor) }}{{ (float) $a->comissao_valor > 0 ? ' + ' . $fmt($a->comissao_percentual, 2) . '% de comissão' : '' }}</p>
            </div>
            <div class="rounded-xl border border-border bg-surface px-3.5 py-3">
                @if ($a->empresaPaga())
                    <p class="{{ $kpiT }}">Empresa paga</p>
                    <p class="{{ $kpiV }}" style="color:#2a78d6">R$ {{ $fmt($a->saldo) }}</p>
                    @if ($a->contaPagar)
                        <p class="truncate text-[11px] text-text-secondary">Conta a pagar · {{ $a->contaPagar->status === 'pago' ? 'paga' : 'vence ' . $a->contaPagar->vencimento?->format('d/m') }}</p>
                        @can('viewAny', \App\Models\ContaPagar::class)
                            <a href="{{ route('contas-pagar.index', ['q' => $v->numero] + ($a->contaPagar->status === 'pago' ? ['faixa' => 'pagas'] : [])) }}" wire:navigate class="mt-1 inline-block text-[11.5px] font-semibold text-[var(--h6-azul-tx)] hover:underline">Abrir no 5030 →</a>
                        @endcan
                    @endif
                @elseif ($a->motoristaDevolve())
                    <p class="{{ $kpiT }}">Motorista devolve</p>
                    <p class="{{ $kpiV }} text-green-600 dark:text-green-400">R$ {{ $fmt(-(float) $a->saldo) }}</p>
                    @if ($a->devolvido_em)
                        <p class="truncate text-[11px] text-text-secondary">Devolvido em {{ $a->devolvido_em->format('d/m/Y') }} · {{ $formasDev[$a->devolvido_forma] ?? $a->devolvido_forma }}</p>
                        @if ($pode)
                            <button type="button" wire:click="desfazerDevolucao" wire:confirm="Desfazer o registro da devolução?" class="mt-1 text-[11.5px] font-semibold text-text-secondary hover:underline">Desfazer</button>
                        @endif
                    @else
                        <p class="truncate text-[11px] text-amber-600 dark:text-amber-400">Devolução pendente</p>
                        @if ($pode)
                            <button type="button" wire:click="abrirDevolucao" class="mt-1 text-[11.5px] font-semibold text-[var(--h6-azul-tx)] hover:underline">Registrar devolução →</button>
                        @endif
                    @endif
                @else
                    <p class="{{ $kpiT }}">Saldo</p>
                    <p class="{{ $kpiV }} text-text-secondary">R$ 0,00</p>
                    <p class="truncate text-[11px] text-text-secondary">Acerto zerado</p>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
            <x-card padding="sm">
                <p class="{{ $secT }}">Despesas conferidas</p>
                @include('livewire.acertos.partials.despesas', ['editavel' => false, 'despesas' => $this->despesas])
                @if ($a->observacoes)
                    <p class="mt-3 text-[12.5px] text-text-secondary"><b class="text-text">Observações:</b> {{ $a->observacoes }}</p>
                @endif
            </x-card>
            <x-card padding="sm">
                <p class="{{ $secT }}">Histórico</p>
                <ol class="space-y-3">
                    @foreach ($this->historico as $h)
                        <li class="flex gap-2.5" wire:key="h-{{ $h->id }}">
                            <i class="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full {{ $h->status === 'fechado' ? 'bg-green-600' : 'bg-gray-400' }}"></i>
                            <div class="text-[13px] text-text">
                                @if ($h->status === 'fechado')
                                    Acerto fechado ·
                                    @if ((float) $h->saldo > 0) conta a pagar de R$ {{ $fmt($h->saldo) }} lançada no 5030
                                    @elseif ((float) $h->saldo < 0) motorista devolve R$ {{ $fmt(-(float) $h->saldo) }}
                                    @else zerado @endif
                                @else
                                    Fechamento de R$ {{ $fmt($h->saldo) }} reaberto
                                    <small class="block text-[11.5px] text-text-secondary">{{ $h->reaberto_em?->format('d/m/Y H:i') }} · {{ $h->motivo_reabertura }}</small>
                                @endif
                                <small class="block text-[11.5px] text-text-secondary">{{ $h->fechado_em?->format('d/m/Y H:i') }}{{ $h->fechadoPor ? ' · ' . $h->fechadoPor->name : '' }}</small>
                            </div>
                        </li>
                    @endforeach
                </ol>
                <p class="mt-3 text-[11.5px] leading-relaxed text-text-secondary">Reabrir só enquanto a conta do 5030 não tiver pagamento — ela é cancelada junto. Com a devolução registrada, desfaça antes.</p>
            </x-card>
        </div>
    @else
        {{-- ── Em aberto ── --}}
        @if (! $acertavel)
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800/50 dark:bg-amber-950/40 dark:text-amber-300">
                A viagem ainda não foi entregue. Dá para lançar adiantamentos e conferir despesas; o acerto fecha depois da entrega.
            </div>
        @endif

        <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
            <div class="space-y-4">
                <x-card padding="sm">
                    <p class="{{ $secT }}">
                        Adiantamentos ao motorista
                        @if ($pode)
                            <button type="button" wire:click="abrirAdiantamento" class="text-xs font-semibold text-[var(--h6-azul-tx)] hover:underline">+ Adicionar</button>
                        @endif
                    </p>
                    @if ($this->adiantamentos->isEmpty())
                        <p class="rounded-lg border border-dashed border-border px-3 py-4 text-center text-[12.5px] text-text-muted">Nenhum adiantamento nesta viagem.</p>
                    @else
                        <div class="overflow-x-auto rounded-[10px] border border-border">
                            <table class="w-full" style="font-variant-numeric:tabular-nums">
                                <thead class="bg-surface-elevated">
                                    <tr><th class="{{ $th }} text-left">Data</th><th class="{{ $th }} text-left">Forma</th><th class="{{ $th }} text-left">Para</th><th class="{{ $th }} text-right">Valor</th><th class="{{ $th }}"></th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($this->adiantamentos as $ad)
                                        <tr class="border-t border-border" wire:key="ad-{{ $ad->id }}">
                                            <td class="{{ $td }} text-text-secondary">{{ $ad->data->format('d/m/Y') }}</td>
                                            <td class="{{ $td }}">{{ $formasAd[$ad->forma] ?? $ad->forma }}</td>
                                            <td class="{{ $td }}">{{ $finalidades[$ad->finalidade] ?? $ad->finalidade }}</td>
                                            <td class="{{ $td }} text-right font-mono">R$ {{ $fmt($ad->valor) }}</td>
                                            <td class="{{ $td }} w-8 text-right">
                                                @if ($pode)
                                                    <button type="button" wire:click="removerAdiantamento({{ $ad->id }})" wire:confirm="Remover este adiantamento?" aria-label="Remover adiantamento" class="text-text-muted hover:text-danger">
                                                        <x-icon name="trash-2" class="h-4 w-4" />
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>

                <x-card padding="sm">
                    <p class="{{ $secT }}">Despesas da viagem<span class="text-[11.5px] font-normal text-text-secondary">Lançadas no 3060 ou pelo app do motorista</span></p>
                    @include('livewire.acertos.partials.despesas', ['editavel' => $pode, 'despesas' => $this->despesas])
                    <p class="mt-3 text-[11.5px] leading-relaxed text-text-secondary">Despesa paga pela empresa ou no cartão da empresa não entra no acerto. Glosada não é aceita como gasto da empresa: o valor volta para a conta do motorista.</p>
                </x-card>

                <x-card padding="sm">
                    <p class="{{ $secT }}">Diárias e comissão</p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div>
                            <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-text-muted">Dias de viagem</span>
                            <div class="flex h-[38px] items-center justify-between rounded-md border border-border bg-surface-elevated px-3 text-sm">
                                <b class="font-mono">{{ $r['dias'] }}</b>
                                <span class="text-[11.5px] text-text-secondary">{{ $ini?->format('d/m') ?? '—' }} a {{ $fim?->format('d/m') ?? '—' }}</span>
                            </div>
                        </div>
                        <x-input label="Diária (R$)" type="number" step="0.01" min="0" wire:model.live.debounce.400ms="diaria" mono :disabled="! $pode" />
                        <div>
                            <x-input label="Comissão sobre o frete (%)" type="number" step="0.01" min="0" max="100" wire:model.live.debounce.400ms="comissaoPct" mono :disabled="! $pode" />
                            <p class="mt-1 text-[11px] text-text-secondary">{{ $fmt($r['comissao_pct']) }}% de R$ {{ $fmt($r['base_comissao']) }}</p>
                        </div>
                    </div>
                    <span class="mb-1.5 mt-3.5 block text-[11px] font-semibold uppercase tracking-wide text-text-muted">Observações</span>
                    <textarea wire:model="observacoes" rows="2" placeholder="Opcional — sai no recibo" @disabled(! $pode)
                              class="w-full rounded-md border border-border bg-white px-3 py-2 text-sm text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 dark:bg-surface-elevated"></textarea>
                    <p class="mt-2 text-[11.5px] leading-relaxed text-text-secondary">Diária e comissão padrão vêm do cadastro do motorista (2030). Agregado e autônomo não têm diária — RN-12.</p>
                </x-card>
            </div>

            <aside class="lg:sticky lg:top-0">
                <x-card padding="sm">
                    <p class="{{ $secT }}">Saldo do acerto</p>
                    @php
                        $lin = 'flex justify-between gap-3 py-1.5 text-[13px]';
                    @endphp
                    <div style="font-variant-numeric:tabular-nums">
                        <div class="{{ $lin }}"><span class="text-text-secondary">Adiantado</span><b class="font-mono font-medium">R$ {{ $fmt($r['adiantado']) }}</b></div>
                        <div class="{{ $lin }}"><span class="text-text-secondary">(−) Gasto do adiantamento, aceito</span><b class="font-mono font-medium">R$ {{ $fmt($r['gasto_adiantamento']) }}</b></div>
                        <div class="{{ $lin }} border-t border-border"><span class="font-semibold text-text">= Ficou com o motorista</span><b class="font-mono">R$ {{ $fmt($r['ficou']) }}</b></div>
                        <div class="{{ $lin }}"><span class="text-text-secondary">(+) Do bolso, aceito</span><b class="font-mono font-medium">R$ {{ $fmt($r['bolso_aceito']) }}</b></div>
                        <div class="{{ $lin }}"><span class="text-text-secondary">(+) Diárias · {{ $r['dias'] }} × {{ $fmt($r['diaria']) }}</span><b class="font-mono font-medium">R$ {{ $fmt($r['diarias']) }}</b></div>
                        <div class="{{ $lin }}"><span class="text-text-secondary">(+) Comissão</span><b class="font-mono font-medium">R$ {{ $fmt($r['comissao']) }}</b></div>
                        <div class="mt-1 flex items-baseline justify-between gap-3 border-t-2 border-border pt-2.5">
                            @if ($r['saldo'] > 0)
                                <span class="text-[14px] font-semibold text-text">Empresa paga</span><b class="font-mono text-lg" style="color:#2a78d6">R$ {{ $fmt($r['saldo']) }}</b>
                            @elseif ($r['saldo'] < 0)
                                <span class="text-[14px] font-semibold text-text">Motorista devolve</span><b class="font-mono text-lg text-green-600 dark:text-green-400">R$ {{ $fmt(-$r['saldo']) }}</b>
                            @else
                                <span class="text-[14px] font-semibold text-text">Zerado</span><b class="font-mono text-lg text-text-secondary">R$ 0,00</b>
                            @endif
                        </div>
                    </div>
                    @if ($r['glosado'] > 0)
                        <p class="mt-2 text-[11.5px] leading-relaxed text-text-secondary">Glosado: R$ {{ $fmt($r['glosado']) }}. Pago com o adiantamento e não aceito, fica na conta do motorista.</p>
                    @endif

                    @if ($pode)
                        <x-button class="mt-3 h-[42px] w-full justify-center" variant="primary" icon="check" wire:click="fechar"
                                  wire:confirm="Fechar o acerto? Depois disso as despesas e os adiantamentos da viagem não mudam mais."
                                  wire:loading.attr="disabled" :disabled="$r['pendentes'] > 0 || ! $acertavel">Fechar acerto</x-button>
                    @endif
                    @if ($r['pendentes'] > 0)
                        <p class="mt-2 text-[11.5px] font-semibold text-amber-600 dark:text-amber-400">{{ $r['pendentes'] === 1 ? 'Confira 1 despesa antes de fechar.' : 'Confira as ' . $r['pendentes'] . ' despesas pendentes antes de fechar.' }}</p>
                    @elseif (! $acertavel)
                        <p class="mt-2 text-[11.5px] font-semibold text-amber-600 dark:text-amber-400">Fecha depois da entrega da viagem.</p>
                    @endif
                    <p class="mt-2.5 text-[11.5px] leading-relaxed text-text-secondary">Ao fechar: se a empresa paga, vira conta a pagar no 5030 em nome do motorista; se o motorista devolve, fica registrada a devolução. Depois de fechado, as despesas da viagem não mudam mais.</p>
                </x-card>
            </aside>
        </div>
    @endif

    {{-- Adiantamento --}}
    @if ($novoAdiantamento)
        <div class="fixed inset-0 z-[90] flex items-start justify-center bg-[rgba(15,26,58,.45)] px-4 pt-[10vh]" wire:keydown.escape.window="fecharJanelas">
            <div class="w-full max-w-md rounded-2xl bg-surface p-5 shadow-2xl" role="dialog" aria-modal="true" aria-label="Adiantamento ao motorista">
                <h3 class="text-base font-bold text-text">Adiantamento ao motorista</h3>
                <p class="mt-0.5 text-[12.5px] text-text-secondary">Viagem {{ $v->numero }} · {{ $motorista }}</p>
                <p class="mb-1.5 mt-4 text-[11px] font-semibold uppercase tracking-wide text-text-muted">Forma</p>
                <div class="grid grid-cols-4 gap-1.5">
                    @foreach ($formasAd as $codigo => $rotulo)
                        <button type="button" wire:click="$set('adForma', '{{ $codigo }}')" class="{{ $chip($adForma === $codigo) }}">{{ $rotulo }}</button>
                    @endforeach
                </div>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <x-input label="Data" type="date" wire:model="adData" />
                    <x-input label="Valor (R$)" type="number" step="0.01" min="0" wire:model="adValor" mono />
                </div>
                <p class="mb-1.5 mt-3 text-[11px] font-semibold uppercase tracking-wide text-text-muted">Para</p>
                <div class="grid grid-cols-4 gap-1.5">
                    @foreach ($finalidades as $codigo => $rotulo)
                        <button type="button" wire:click="$set('adFinalidade', '{{ $codigo }}')" class="{{ $chip($adFinalidade === $codigo) }}">{{ $rotulo }}</button>
                    @endforeach
                </div>
                @error('adValor') <p class="mt-2 text-xs text-danger">{{ $message }}</p> @enderror
                <div class="mt-5 flex justify-end gap-2">
                    <x-button variant="neutral" size="sm" wire:click="fecharJanelas">Cancelar</x-button>
                    <x-button variant="success" size="sm" icon="check" wire:click="salvarAdiantamento" wire:loading.attr="disabled">Adicionar</x-button>
                </div>
            </div>
        </div>
    @endif

    {{-- Reabrir --}}
    @if ($reabrindo && $a)
        <div class="fixed inset-0 z-[90] flex items-start justify-center bg-[rgba(15,26,58,.45)] px-4 pt-[10vh]" wire:keydown.escape.window="fecharJanelas">
            <div class="w-full max-w-sm rounded-2xl bg-surface p-5 shadow-2xl" role="dialog" aria-modal="true" aria-label="Reabrir acerto">
                <h3 class="text-base font-bold text-text">Reabrir acerto</h3>
                <p class="mt-0.5 text-[12.5px] text-text-secondary">
                    {{ $a->conta_pagar_id ? 'A conta de R$ ' . $fmt($a->saldo) . ' no 5030 é cancelada junto. ' : '' }}O fechamento fica no histórico; o novo é outro registro.
                </p>
                <x-input class="mt-4" label="Motivo" wire:model="motivoReabertura" placeholder="Ex.: Despesa de hospedagem lançada depois" :error="$errors->first('motivoReabertura')" />
                <div class="mt-5 flex justify-end gap-2">
                    <x-button variant="neutral" size="sm" wire:click="fecharJanelas">Voltar</x-button>
                    <x-button variant="warning" size="sm" wire:click="reabrir" wire:loading.attr="disabled">Reabrir</x-button>
                </div>
            </div>
        </div>
    @endif

    {{-- Devolução --}}
    @if ($devolvendo && $a)
        <div class="fixed inset-0 z-[90] flex items-start justify-center bg-[rgba(15,26,58,.45)] px-4 pt-[10vh]" wire:keydown.escape.window="fecharJanelas">
            <div class="w-full max-w-md rounded-2xl bg-surface p-5 shadow-2xl" role="dialog" aria-modal="true" aria-label="Registrar devolução">
                <h3 class="text-base font-bold text-text">Registrar devolução</h3>
                <p class="mt-0.5 text-[12.5px] text-text-secondary">{{ $motorista }} devolve <b class="font-mono text-text">R$ {{ $fmt(-(float) $a->saldo) }}</b></p>
                <p class="mb-1.5 mt-4 text-[11px] font-semibold uppercase tracking-wide text-text-muted">Como</p>
                <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-4">
                    @foreach ($formasDev as $codigo => $rotulo)
                        <button type="button" wire:click="$set('devForma', '{{ $codigo }}')" class="{{ $chip($devForma === $codigo) }}">{{ $rotulo }}</button>
                    @endforeach
                </div>
                <x-input class="mt-3" label="Data" type="date" wire:model="devData" />
                @error('devForma') <p class="mt-2 text-xs text-danger">{{ $message }}</p> @enderror
                <div class="mt-5 flex justify-end gap-2">
                    <x-button variant="neutral" size="sm" wire:click="fecharJanelas">Cancelar</x-button>
                    <x-button variant="success" size="sm" icon="check" wire:click="registrarDevolucao" wire:loading.attr="disabled">Registrar</x-button>
                </div>
            </div>
        </div>
    @endif
</div>
