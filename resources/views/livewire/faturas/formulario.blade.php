{{--
    Rotina 5010 — Nova fatura (mockup aprovado em 02/10/2026).
    1. cliente · 2. CT-e autorizados sem fatura · 3. resumo e vencimento.
--}}
@php
    $fmt = fn ($v, $d = 2) => number_format((float) $v, $d, ',', '.');
    $res = $this->resumo;
    $tom = $this->tomador;
    $passo = 'flex items-center gap-2 text-[13px] font-semibold text-text mb-2.5';
    $bola = 'flex h-[22px] w-[22px] items-center justify-center rounded-full bg-primary text-[11px] font-bold text-white';
    $rapidas = config('financeiro.condicoes_rapidas');
    if ($tom?->prazo_faturamento && ! array_key_exists($tom->prazo_faturamento, $rapidas)) {
        $rapidas = [$tom->prazo_faturamento => $tom->prazo_faturamento . ' dias'] + $rapidas;
    }
@endphp
<div>
    <a href="{{ route('faturas.index') }}" wire:navigate class="mb-1.5 inline-flex items-center gap-1 text-[12.5px] text-text-secondary hover:text-text">‹ Faturas</a>
    <x-page-header title="Nova fatura" subtitle="Escolha o cliente, marque os CT-e e defina o vencimento." />

    <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div class="flex flex-col gap-4">
            {{-- 1. Cliente --}}
            <x-card padding="sm">
                <p class="{{ $passo }}"><span class="{{ $bola }}">1</span>Cliente (tomador do CT-e)</p>
                @if ($tom)
                    <div class="flex items-center gap-3 rounded-[10px] border-[1.5px] border-[var(--h6-azul)] bg-[var(--h6-campo-bg)] px-3 py-2.5">
                        <span class="flex h-[34px] w-[34px] flex-shrink-0 items-center justify-center rounded-[9px] bg-[var(--h6-cod-bg)] text-[13px] font-bold text-[var(--h6-azul-tx)]">{{ \App\Support\Navegacao::iniciais($tom->razao_social) }}</span>
                        <div class="min-w-0 flex-1">
                            <b class="block text-[13.5px] text-text">{{ $tom->razao_social }}</b>
                            <small class="text-[11.5px] text-text-secondary">{{ $tom->documentoFormatado() }} · {{ $tom->prazoFaturamentoRotulo() ? 'Prazo do cliente: ' . $tom->prazoFaturamentoRotulo() : 'Sem prazo no cadastro' }}</small>
                        </div>
                        <button type="button" wire:click="trocarTomador" class="text-xs font-semibold text-[var(--h6-azul-tx)] hover:underline">Trocar</button>
                    </div>
                @elseif ($this->tomadores->isEmpty())
                    <x-empty-state icon="file-text" title="Nenhum CT-e para faturar"
                                   description="Só entram CT-e autorizados que ainda não estão em outra fatura." />
                @else
                    <div class="overflow-hidden rounded-[10px] border border-border">
                        @foreach ($this->tomadores as $t)
                            <button type="button" wire:click="escolherTomador({{ $t['id'] }})" wire:key="tm-{{ $t['id'] }}"
                                    class="flex w-full items-center gap-3 border-b border-border px-3 py-2.5 text-left last:border-b-0 hover:bg-surface-elevated">
                                <span class="min-w-0 flex-1">
                                    <b class="block truncate text-[13px] font-medium text-text">{{ $t['nome'] }}</b>
                                    <small class="text-[11px] text-text-secondary">{{ $t['documento'] }}</small>
                                </span>
                                <span class="text-right text-xs text-text-secondary">{{ $t['qtd'] }} CT-e<br><b class="font-mono font-medium text-text">R$ {{ $fmt($t['valor']) }}</b></span>
                            </button>
                        @endforeach
                    </div>
                @endif
                @error('tomador_id') <p class="mt-2 text-xs text-danger">{{ $message }}</p> @enderror
            </x-card>

            {{-- 2. CT-e --}}
            @if ($tom)
                <x-card padding="sm">
                    <p class="{{ $passo }}"><span class="{{ $bola }}">2</span>CT-e autorizados ainda sem fatura</p>
                    <div class="mb-2.5 flex flex-wrap gap-2">
                        <div class="relative min-w-[200px] flex-1">
                            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                            <input type="search" wire:model.live.debounce.400ms="busca" placeholder="Número, viagem ou cidade"
                                   class="w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        </div>
                        <input type="date" wire:model.live="de" title="Emitidos a partir de" class="rounded-md border border-border bg-surface px-2.5 py-2 text-sm text-text">
                        <input type="date" wire:model.live="ate" title="Emitidos até" class="rounded-md border border-border bg-surface px-2.5 py-2 text-sm text-text">
                    </div>

                    <div class="overflow-hidden rounded-[10px] border border-border" style="font-variant-numeric:tabular-nums">
                        <div class="flex items-center justify-between gap-2 border-b border-border bg-surface-elevated px-3 py-2 text-xs text-text-secondary">
                            <label class="flex items-center gap-2 font-semibold text-text">
                                <input type="checkbox" wire:click="marcarTodos" @checked($this->ctes->isNotEmpty() && $this->ctes->every(fn ($c) => in_array($c->id, $selecionados)))
                                       class="h-4 w-4 rounded border-border text-[var(--h6-azul)]"> Marcar todos
                            </label>
                            <span>{{ count(array_intersect($selecionados, $this->ctes->pluck('id')->all())) }} de {{ $this->ctes->count() }} marcados</span>
                        </div>
                        @forelse ($this->ctes as $c)
                            @php $on = in_array($c->id, $selecionados); @endphp
                            <label wire:key="ct-{{ $c->id }}" class="grid cursor-pointer grid-cols-[22px_92px_minmax(0,1fr)_110px] items-center gap-2.5 border-b border-border px-3 py-2.5 text-[13px] last:border-b-0 sm:grid-cols-[22px_92px_minmax(0,1fr)_90px_110px] {{ $on ? 'bg-[var(--h6-hover-bg)]' : '' }}">
                                <input type="checkbox" value="{{ $c->id }}" wire:model.live="selecionados" class="h-4 w-4 rounded border-border text-[var(--h6-azul)]">
                                <span class="font-mono text-xs font-semibold text-[var(--h6-azul-tx)]">{{ number_format((int) $c->numero, 0, ',', '.') }}<small class="block font-sans text-[11px] font-normal text-text-muted">{{ $c->emissao?->format('d/m') }}</small></span>
                                <span class="min-w-0">
                                    <b class="block truncate font-medium text-text">{{ $c->municipioInicio?->nomeComUf() ?? '—' }} → {{ $c->municipioFim?->nomeComUf() ?? '—' }}</b>
                                    <small class="text-[11px] text-text-secondary">{{ (int) $c->tipo_cte === 1 ? 'Complementar' : ($c->produto_predominante ?: 'CT-e normal') }}</small>
                                </span>
                                <span class="hidden font-mono text-[11.5px] text-text-secondary sm:block">{{ $c->viagens->first()?->numero ? 'V-' . $c->viagens->first()->numero : '—' }}</span>
                                <span class="text-right font-mono font-medium text-text">R$ {{ $fmt($c->valor_total_servico) }}</span>
                            </label>
                        @empty
                            <p class="px-3 py-4 text-sm text-text-muted">Nenhum CT-e com esse filtro.</p>
                        @endforelse
                    </div>
                    <p class="mt-2.5 text-[11.5px] text-text-secondary">Só aparecem CT-e autorizados deste cliente que ainda não estão em outra fatura. CT-e cancelado ou de anulação não entra.</p>
                </x-card>
            @endif
        </div>

        {{-- 3. Resumo --}}
        <x-card padding="sm" class="lg:sticky lg:top-0">
            <p class="{{ $passo }}"><span class="{{ $bola }}">3</span>Resumo e vencimento</p>
            <div style="font-variant-numeric:tabular-nums">
                <div class="flex justify-between py-1 text-[13px]"><span class="text-text-secondary">CT-e marcados</span><b class="font-mono font-medium">{{ $res['qtd'] }}</b></div>
                <div class="flex justify-between py-1 text-[13px]"><span class="text-text-secondary">Valor dos CT-e</span><b class="font-mono font-medium">R$ {{ $fmt($res['valor']) }}</b></div>
                <div class="mt-1.5 grid grid-cols-2 gap-2">
                    <x-input label="Desconto (R$)" type="number" step="0.01" min="0" wire:model.live.debounce.500ms="desconto" :error="$errors->first('desconto')" />
                    <x-input label="Acréscimo (R$)" type="number" step="0.01" min="0" wire:model.live.debounce.500ms="acrescimo" :error="$errors->first('acrescimo')" />
                </div>
                <div class="mt-2 flex items-baseline justify-between border-t border-border pt-2.5"><span class="text-[15px] text-text-secondary">Total da fatura</span><b class="font-mono text-[17px] font-bold text-text">R$ {{ $fmt($res['total']) }}</b></div>

                <p class="mb-1.5 mt-3.5 text-[11px] font-semibold uppercase tracking-wide text-text-muted">Condição de pagamento</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($rapidas as $cod => $rot)
                        <button type="button" wire:click="usarCondicao('{{ $cod }}')"
                                class="rounded-[7px] border px-2.5 py-1.5 text-xs font-medium {{ $condicao === (string) $cod ? 'border-transparent bg-primary font-semibold text-white' : 'border-border bg-surface-elevated text-text-secondary' }}">{{ $rot }}</button>
                    @endforeach
                </div>
                <x-input class="mt-2" label="Prazos em dias" wire:model.live.debounce.500ms="condicao" placeholder="Ex.: 30/60/90" mono
                         help="Separe as parcelas com barra. 0 é à vista." />
                @if ($res['erro'])
                    <p class="mt-1 text-xs text-danger">{{ $res['erro'] }}</p>
                @elseif ($res['parcelas'] !== [])
                    <div class="mt-2 overflow-hidden rounded-lg border border-border">
                        @foreach ($res['parcelas'] as $p)
                            <div class="flex justify-between border-b border-border px-2.5 py-1.5 text-[12.5px] last:border-b-0">
                                <span class="text-text-secondary">{{ $p['parcela'] }}/{{ $p['parcelas'] }} · vence {{ $p['vencimento']->format('d/m/Y') }}</span>
                                <b class="font-mono font-medium">R$ {{ $fmt($p['valor']) }}</b>
                            </div>
                        @endforeach
                    </div>
                @endif

                <x-input-label class="mt-3.5">Observações</x-input-label>
                <textarea wire:model="observacoes" rows="2" placeholder="Aparece na fatura impressa"
                          class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"></textarea>

                @error('gerar') <p class="mt-2 rounded-md bg-red-50 px-3 py-2 text-xs text-red-800 dark:bg-red-950/50 dark:text-red-300">{{ $message }}</p> @enderror

                <x-button variant="primary" icon="check" class="mt-3.5 w-full" wire:click="gerar" wire:loading.attr="disabled"
                          :disabled="! $tom || $res['qtd'] === 0 || $res['total'] <= 0 || $res['erro']">
                    Gerar fatura
                </x-button>
                <p class="mt-2.5 text-[11.5px] leading-relaxed text-text-secondary">Ao gerar, cada parcela vira um título em Contas a receber (5020). Os CT-e ficam presos a esta fatura até ela ser cancelada.</p>
            </div>
        </x-card>
    </div>
</div>
