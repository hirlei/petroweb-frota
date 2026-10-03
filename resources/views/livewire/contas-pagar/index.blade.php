{{--
    Rotina 5030 — Contas a pagar (mockup aprovado em 03/10/2026).
    Faixas por vencimento, lançamentos esperando, pagar, estornar e cancelar.
--}}
@php
    $fmt = fn ($v, $d = 2) => number_format((float) $v, $d, ',', '.');
    $fx = $this->faixas;
    $esp = $this->esperando;
    $cartoes = [
        'abertas' => ['Todas em aberto', '', 'conta', 'contas'],
        'a_vencer' => ['A vencer', '', 'conta', 'contas'],
        'hoje' => ['Vence hoje', 'text-amber-600 dark:text-amber-400', 'conta', 'contas'],
        'vencidas' => ['Vencidas', 'text-red-600 dark:text-red-400', 'conta', 'contas'],
        'pagas' => ['Pago no mês', '', 'pagamento', 'pagamentos'],
    ];
    $cats = config('financeiro.pagar.categorias');
    $catCores = config('financeiro.pagar.categoria_cores');
@endphp
<div>
    <x-page-header title="Contas a pagar" subtitle="Fornecedores, postos, oficinas e frete de terceiros, por vencimento.">
        <x-slot:actions>
            @can('create', \App\Models\ContaPagar::class)
                <x-button variant="primary" size="sm" icon="plus" :href="route('contas-pagar.criar')" wire:navigate>Nova conta</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if (session('sucesso'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800/50 dark:bg-green-950/50 dark:text-green-300">{{ session('sucesso') }}</div>
    @endif
    @if (session('erro'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800/50 dark:bg-red-950/50 dark:text-red-300">{{ session('erro') }}</div>
    @endif

    @if ($esp['itens'] > 0)
        @can('create', \App\Models\ContaPagar::class)
            <a href="{{ route('contas-pagar.lancar') }}" wire:navigate
               class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-[var(--h6-azul)] bg-[var(--h6-campo-bg)] px-4 py-3 hover:bg-[var(--h6-hover-bg)]">
                <x-icon name="file-text" class="h-5 w-5 flex-shrink-0 text-[var(--h6-azul-tx)]" />
                <div class="min-w-0 flex-1">
                    <b class="block text-[13.5px] text-text">{{ $esp['itens'] }} {{ $esp['itens'] === 1 ? 'lançamento esperando' : 'lançamentos esperando' }}</b>
                    <small class="text-xs text-text-secondary">
                        {{ collect([
                            $esp['abastecimentos'] ? $esp['abastecimentos'] . ' ' . ($esp['abastecimentos'] === 1 ? 'abastecimento' : 'abastecimentos') : null,
                            $esp['ordens'] ? $esp['ordens'] . ' ' . ($esp['ordens'] === 1 ? 'ordem de serviço' : 'ordens de serviço') : null,
                            $esp['ciots'] ? $esp['ciots'] . ' ' . ($esp['ciots'] === 1 ? 'frete de TAC (CIOT)' : 'fretes de TAC (CIOT)') : null,
                        ])->filter()->implode(' · ') }}
                        — {{ $esp['contas'] === 1 ? 'vira 1 conta' : 'viram ' . $esp['contas'] . ' contas' }}, R$ {{ $fmt($esp['valor']) }}
                    </small>
                </div>
                <span class="rounded-md bg-amber-b px-3 py-1.5 text-sm font-medium text-amber-b-fg dark:bg-amber-500/15 dark:text-amber-400">Revisar e lançar</span>
            </a>
        @endcan
    @endif

    <div class="mb-3 grid grid-cols-2 gap-2 lg:grid-cols-5" style="font-variant-numeric:tabular-nums">
        @foreach ($cartoes as $chave => [$rotulo, $cor, $um, $varios])
            <button type="button" wire:click="$set('faixa', '{{ $chave }}')"
                    class="rounded-[10px] border bg-surface px-3 py-2.5 text-left transition-colors {{ $faixa === $chave ? 'border-[var(--h6-azul)] shadow-[inset_0_0_0_1px_var(--h6-azul)]' : 'border-border hover:border-border-strong' }}">
                <small class="block text-[10px] font-semibold uppercase tracking-wide text-text-muted">{{ $rotulo }}</small>
                <b class="mt-0.5 block font-mono text-[15px] font-medium {{ $fx[$chave]['qtd'] > 0 ? $cor : '' }}">R$ {{ $fmt($fx[$chave]['valor'], 0) }}</b>
                <span class="text-[11px] text-text-secondary">{{ $fx[$chave]['qtd'] === 0 ? 'Nenhuma' : $fx[$chave]['qtd'] . ' ' . ($fx[$chave]['qtd'] === 1 ? $um : $varios) }}</span>
            </button>
        @endforeach
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <div class="relative min-w-[220px] flex-1">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
            <input type="search" wire:model.live.debounce.400ms="busca" placeholder="Buscar por favorecido, documento ou CNPJ"
                   class="w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>
        <div class="flex flex-wrap gap-1">
            @foreach (['' => 'Todas'] + config('financeiro.pagar.filtros') as $chave => $rotulo)
                <button type="button" wire:click="$set('categoria', '{{ $chave }}')"
                        class="rounded-full px-3 py-1.5 text-sm font-medium {{ $categoria === $chave ? 'bg-primary-soft text-amber-700 dark:text-amber-300' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' }}">{{ $rotulo }}</button>
            @endforeach
        </div>
    </div>

    <x-card padding="none" class="overflow-hidden">
        @if ($this->contas->isEmpty())
            <x-empty-state icon="wallet" title="Nenhuma conta aqui"
                           description="Lance os abastecimentos, as ordens de serviço e os fretes de TAC esperando, ou crie uma conta nova." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full" style="font-variant-numeric:tabular-nums">
                    <thead class="border-b border-border bg-surface-elevated">
                        <tr>
                            @foreach (['Favorecido', 'Categoria', 'Documento', 'Vencimento', 'Valor', 'Situação', ''] as $i => $cab)
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-text-muted first:pl-5 {{ $i === 4 ? 'text-right' : 'text-left' }}">{{ $cab }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->contas as $c)
                            @php
                                $ultimoPago = $c->pagamentos->filter(fn ($p) => ! $p->estornado())->last();
                                $temEstornavel = $c->pagamentos->contains(fn ($p) => ! $p->estornado() && ! $p->doCiot());
                            @endphp
                            <tr wire:key="cp-{{ $c->id }}" class="border-t border-border hover:bg-surface-elevated">
                                <td class="px-4 py-3 pl-5 text-sm">
                                    <span class="font-medium text-text">{{ $c->favorecido?->razao_social ?? '—' }}</span>
                                    <span class="block text-[11px] text-text-secondary">{{ $c->descricao ?: ($c->favorecido ? $c->favorecido->documentoFormatado() : '') }}</span>
                                </td>
                                <td class="px-4 py-3"><x-badge :variant="$catCores[$c->categoria] ?? 'gray'" class="whitespace-nowrap text-[11px]">{{ $cats[$c->categoria] ?? $c->categoria }}</x-badge></td>
                                <td class="px-4 py-3 font-mono text-[13px] text-text">
                                    {{ $c->documento ?: '—' }}
                                    @if ($c->parcelas > 1)<span class="block font-sans text-[11px] text-text-secondary">Parcela {{ $c->parcela }}/{{ $c->parcelas }}</span>@endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-text-secondary">{{ $c->vencimento?->format('d/m/Y') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-text">R$ {{ $fmt($c->valor) }}</td>
                                <td class="px-4 py-3">
                                    @if ($c->status === 'cancelado')
                                        <x-badge variant="gray" class="text-[11px] line-through">Cancelada</x-badge>
                                    @elseif ($c->vencida())
                                        <x-badge variant="danger" class="text-[11px]">Vencida há {{ $c->diasAtraso() }} {{ $c->diasAtraso() === 1 ? 'dia' : 'dias' }}</x-badge>
                                    @elseif ($c->emAberto() && $c->vencimento?->isToday())
                                        <x-badge variant="warning" class="text-[11px]">Vence hoje</x-badge>
                                    @elseif ($c->status === 'parcial')
                                        <x-badge variant="info" class="text-[11px]">Parcial · falta R$ {{ $fmt($c->saldo()) }}</x-badge>
                                    @elseif ($c->status === 'pago')
                                        <x-badge variant="success" class="text-[11px]">Paga{{ $ultimoPago ? ' · ' . (config('financeiro.pagar.formas.' . $ultimoPago->forma) ?? 'CIOT') . ' ' . $ultimoPago->data->format('d/m') : '' }}</x-badge>
                                    @else
                                        <x-badge variant="gray" class="text-[11px]">A vencer</x-badge>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    @if ($c->pagaNoCiot() && $c->emAberto())
                                        @if ($c->ciotId())
                                            <x-button variant="neutral" size="xs" :href="route('ciot.ver', $c->ciotId())" wire:navigate>Pagar no 4050</x-button>
                                        @endif
                                    @elseif ($c->emAberto())
                                        @can('pagar', $c)
                                            <x-button variant="success" size="xs" wire:click="abrirPagamento({{ $c->id }})">Pagar</x-button>
                                        @endcan
                                    @endif
                                    @if ($temEstornavel && $c->status !== 'cancelado')
                                        @can('estornar', $c)
                                            <x-button variant="neutral" size="xs" wire:click="abrirEstorno({{ $c->id }})">Estornar</x-button>
                                        @endcan
                                    @endif
                                    @if ($c->status === 'aberto' && $c->valor_baixado <= 0 && ! $c->pagaNoCiot() && $c->origem !== 'acerto')
                                        @can('cancelar', $c)
                                            <button type="button" wire:click="abrirCancelamento({{ $c->id }})" title="Cancelar conta" aria-label="Cancelar conta"
                                                    class="ml-1 inline-flex items-center rounded px-1.5 py-1 text-text-muted hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-500/10 dark:hover:text-red-400"><x-icon name="x" class="h-3.5 w-3.5" /></button>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-border px-5 py-3">{{ $this->contas->links() }}</div>
        @endif
    </x-card>

    {{-- Pagar --}}
    @if ($pagContaId && ($cp = $this->contaEmPagamento))
        <div class="fixed inset-0 z-[90] flex items-start justify-center bg-[rgba(15,26,58,.45)] px-4 pt-[10vh]" wire:keydown.escape.window="fecharJanelas">
            <div class="w-full max-w-md rounded-2xl bg-surface p-5 shadow-2xl" role="dialog" aria-modal="true" aria-label="Registrar pagamento">
                <h3 class="text-base font-bold text-text">Registrar pagamento</h3>
                <p class="mt-0.5 text-[12.5px] text-text-secondary">{{ $cp->favorecido?->razao_social }}{{ $cp->documento ? ' · ' . $cp->documento : '' }} · {{ $cp->vencida() ? 'venceu' : 'vence' }} em {{ $cp->vencimento?->format('d/m/Y') }}</p>
                <p class="mt-1 text-[12.5px] text-text-secondary">Saldo da conta: <b class="font-mono text-text">R$ {{ $fmt($cp->saldo()) }}</b></p>

                <p class="mb-1.5 mt-4 text-[11px] font-semibold uppercase tracking-wide text-text-muted">Forma</p>
                <div class="grid grid-cols-5 gap-1.5">
                    @foreach (config('financeiro.pagar.formas') as $codigo => $rotulo)
                        <button type="button" wire:click="$set('pagForma', '{{ $codigo }}')"
                                class="rounded-lg border py-2 text-xs font-medium {{ $pagForma === $codigo ? 'border-[var(--h6-azul)] bg-[var(--h6-hover-bg)] font-semibold text-[var(--h6-azul-tx)]' : 'border-border text-text-secondary' }}">{{ $rotulo }}</button>
                    @endforeach
                </div>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <x-input label="Data do pagamento" type="date" wire:model="pagData" :error="$errors->first('pagData')" />
                    <x-input label="Conta" wire:model="pagConta" placeholder="Ex.: Sicoob c/c 1234-5" />
                </div>
                <div class="mt-3 grid grid-cols-3 gap-3">
                    <x-input label="Valor pago" type="number" step="0.01" wire:model.live.debounce.400ms="pagPrincipal" />
                    <x-input label="Juros e multa" type="number" step="0.01" wire:model.live.debounce.400ms="pagJuros" />
                    <x-input label="Desconto" type="number" step="0.01" wire:model.live.debounce.400ms="pagDesconto" />
                </div>
                @error('pagPrincipal') <p class="mt-2 text-xs text-danger">{{ $message }}</p> @enderror
                <div class="mt-3 flex items-center justify-between border-t border-border pt-3">
                    <span class="text-sm text-text-secondary">Total que sai do caixa</span>
                    <b class="font-mono text-lg font-bold text-text">R$ {{ $fmt($this->totalPago()) }}</b>
                </div>
                <p class="mt-2 text-[11.5px] leading-relaxed text-text-secondary">Valor menor que o saldo deixa a conta parcial. Estorno não apaga: marca e reabre o saldo.</p>
                <x-input class="mt-3" label="Observação" wire:model="pagObs" placeholder="Opcional" />
                <div class="mt-5 flex justify-end gap-2">
                    <x-button variant="neutral" size="sm" wire:click="fecharJanelas">Cancelar</x-button>
                    <x-button variant="success" size="sm" icon="check" wire:click="confirmarPagamento" wire:loading.attr="disabled">Confirmar pagamento</x-button>
                </div>
            </div>
        </div>
    @endif

    {{-- Estornar --}}
    @if ($estornoId && ($pe = $this->pagamentoEmEstorno))
        <div class="fixed inset-0 z-[90] flex items-start justify-center bg-[rgba(15,26,58,.45)] px-4 pt-[10vh]" wire:keydown.escape.window="fecharJanelas">
            <div class="w-full max-w-sm rounded-2xl bg-surface p-5 shadow-2xl" role="dialog" aria-modal="true" aria-label="Estornar pagamento">
                <h3 class="text-base font-bold text-text">Estornar pagamento</h3>
                <p class="mt-0.5 text-[12.5px] text-text-secondary">{{ $pe->contaPagar?->favorecido?->razao_social }} · R$ {{ $fmt($pe->valor_total) }} em {{ $pe->data->format('d/m/Y') }} ({{ config('financeiro.pagar.formas.' . $pe->forma) }})</p>
                <x-input class="mt-4" label="Motivo" wire:model="estornoMotivo" placeholder="Ex.: Pagamento lançado em duplicidade" :error="$errors->first('estornoMotivo')" />
                <div class="mt-5 flex justify-end gap-2">
                    <x-button variant="neutral" size="sm" wire:click="fecharJanelas">Voltar</x-button>
                    <x-button variant="danger" size="sm" wire:click="confirmarEstorno" wire:loading.attr="disabled">Estornar</x-button>
                </div>
            </div>
        </div>
    @endif

    {{-- Cancelar --}}
    @if ($cancelarId)
        <div class="fixed inset-0 z-[90] flex items-start justify-center bg-[rgba(15,26,58,.45)] px-4 pt-[10vh]" wire:keydown.escape.window="fecharJanelas">
            <div class="w-full max-w-sm rounded-2xl bg-surface p-5 shadow-2xl" role="dialog" aria-modal="true" aria-label="Cancelar conta">
                <h3 class="text-base font-bold text-text">Cancelar conta</h3>
                <p class="mt-0.5 text-[12.5px] text-text-secondary">Cancela todas as parcelas do lançamento. Se nasceu de abastecimento, OS ou CIOT, o item volta para os lançamentos esperando.</p>
                <x-input class="mt-4" label="Motivo" wire:model="cancelarMotivo" placeholder="Ex.: Lançada no fornecedor errado" :error="$errors->first('cancelarMotivo')" />
                <div class="mt-5 flex justify-end gap-2">
                    <x-button variant="neutral" size="sm" wire:click="fecharJanelas">Voltar</x-button>
                    <x-button variant="danger" size="sm" wire:click="confirmarCancelamento" wire:loading.attr="disabled">Cancelar conta</x-button>
                </div>
            </div>
        </div>
    @endif
</div>
