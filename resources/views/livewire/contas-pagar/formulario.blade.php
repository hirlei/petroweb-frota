{{-- Rotina 5030 — Nova conta a pagar (mockup aprovado em 03/10/2026). --}}
@php
    $fmt = fn ($v, $d = 2) => number_format((float) $v, $d, ',', '.');
    $fav = $this->favorecido;
    $prev = $this->previa;
    $cats = collect(config('financeiro.pagar.categorias'))->except(['frete_terceiro', 'acerto_viagem']);
    $rapidas = config('financeiro.pagar.condicoes_rapidas');
    if ($fav?->prazo_faturamento && ! array_key_exists($condicao, $rapidas)) {
        $rapidas = [$condicao => $condicao . ' dias'] + $rapidas;
    }
    $chip = fn ($on) => 'rounded-md border px-2.5 py-1.5 text-xs font-medium ' . ($on ? 'border-transparent bg-primary font-semibold text-white' : 'border-border bg-surface-elevated text-text-secondary');
    $rotulo = 'mb-1.5 mt-3.5 block text-[11px] font-semibold uppercase tracking-wide text-text-muted';
@endphp
<div>
    <a href="{{ route('contas-pagar.index') }}" wire:navigate class="mb-1.5 inline-flex items-center gap-1 text-[12.5px] text-text-secondary hover:text-text">‹ Contas a pagar</a>
    <x-page-header title="Nova conta a pagar" subtitle="Para o que não nasce de abastecimento, OS ou CIOT: aluguel, contabilidade, seguro, imposto…" />

    <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
        <x-card padding="sm">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <span class="{{ $rotulo }} mt-0">Favorecido</span>
                    @if ($fav)
                        <div class="flex items-center gap-3 rounded-[10px] border-[1.5px] border-[var(--h6-azul)] bg-[var(--h6-campo-bg)] px-3 py-2.5">
                            <span class="flex h-[34px] w-[34px] flex-shrink-0 items-center justify-center rounded-[9px] bg-[var(--h6-cod-bg)] text-[13px] font-bold text-[var(--h6-azul-tx)]">{{ \App\Support\Navegacao::iniciais($fav->razao_social) }}</span>
                            <div class="min-w-0 flex-1">
                                <b class="block truncate text-[13.5px] text-text">{{ $fav->razao_social }}</b>
                                <small class="text-[11.5px] text-text-secondary">{{ $fav->documentoFormatado() }} · {{ $fav->prazoFaturamentoRotulo() ? 'Prazo: ' . $fav->prazoFaturamentoRotulo() : 'Sem prazo no cadastro' }}</small>
                            </div>
                            <button type="button" wire:click="trocar" class="text-xs font-semibold text-[var(--h6-azul-tx)] hover:underline">Trocar</button>
                        </div>
                    @else
                        <div class="relative">
                            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                            <input type="search" wire:model.live.debounce.300ms="buscaFavorecido" placeholder="Nome, fantasia ou CNPJ"
                                   class="w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        </div>
                        @if ($this->encontrados->isNotEmpty())
                            <div class="mt-1.5 overflow-hidden rounded-[10px] border border-border">
                                @foreach ($this->encontrados as $p)
                                    <button type="button" wire:click="escolher({{ $p->id }})" wire:key="fv-{{ $p->id }}" class="block w-full border-b border-border px-3 py-2 text-left last:border-b-0 hover:bg-surface-elevated">
                                        <b class="block truncate text-[13px] font-medium text-text">{{ $p->razao_social }}</b>
                                        <small class="text-[11px] text-text-secondary">{{ $p->documentoFormatado() }}</small>
                                    </button>
                                @endforeach
                            </div>
                        @elseif (mb_strlen(trim($buscaFavorecido)) >= 2)
                            <p class="mt-1.5 text-xs text-text-muted">Ninguém encontrado. Cadastre em Pessoas (1010).</p>
                        @endif
                    @endif
                    @error('favorecido_id') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                </div>
                <div>
                    <span class="{{ $rotulo }} mt-0">Categoria</span>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($cats as $codigo => $nome)
                            <button type="button" wire:click="$set('categoria', '{{ $codigo }}')" class="{{ $chip($categoria === $codigo) }}">{{ $nome }}</button>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <x-input label="Documento" wire:model="documento" placeholder="Ex.: Boleto 10/2026" maxlength="60" />
                <x-input label="Emissão" type="date" wire:model.live="emissao" :error="$errors->first('emissao')" />
                <x-input label="Valor (R$)" type="number" step="0.01" min="0" wire:model.live.debounce.400ms="valor" mono :error="$errors->first('valor')" />
            </div>

            <span class="{{ $rotulo }}">Condição</span>
            <div class="flex flex-wrap items-center gap-1.5">
                @foreach ($rapidas as $cond => $nome)
                    <button type="button" wire:click="$set('condicao', '{{ $cond }}')" class="{{ $chip((string) $condicao === (string) $cond) }}">{{ $nome }}</button>
                @endforeach
                <input type="text" wire:model.live.debounce.400ms="condicao" aria-label="Outra condição" placeholder="Ex.: 15/45"
                       class="w-28 rounded-md border border-border bg-white px-2.5 py-1.5 font-mono text-xs text-text dark:bg-surface-elevated">
            </div>
            @error('condicao') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror

            <span class="{{ $rotulo }}">Observações</span>
            <textarea wire:model="observacoes" rows="2" placeholder="Opcional"
                      class="w-full rounded-md border border-border bg-white px-3 py-2 text-sm text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 dark:bg-surface-elevated"></textarea>
        </x-card>

        <aside class="sticky top-0">
            <x-card padding="sm">
                <p class="mb-2 text-[14px] font-semibold text-text">Parcelas</p>
                @if ($prev['erro'])
                    <p class="text-xs text-danger">{{ $prev['erro'] }}</p>
                @elseif ($prev['parcelas'] === [])
                    <p class="text-xs text-text-muted">Informe o valor para ver as parcelas.</p>
                @else
                    <div class="overflow-hidden rounded-lg border border-border" style="font-variant-numeric:tabular-nums">
                        @foreach ($prev['parcelas'] as $p)
                            <div class="flex justify-between border-b border-border px-2.5 py-1.5 text-[12.5px] last:border-b-0">
                                <span class="text-text-secondary">{{ $p['parcela'] }}/{{ $p['parcelas'] }} · vence {{ $p['vencimento']->format('d/m/Y') }}</span>
                                <b class="font-mono font-medium">R$ {{ $fmt($p['valor']) }}</b>
                            </div>
                        @endforeach
                    </div>
                @endif
                <x-button class="mt-3 h-[42px] w-full justify-center" variant="primary" icon="check" wire:click="salvar" wire:loading.attr="disabled">Lançar conta</x-button>
                <p class="mt-2.5 text-[11.5px] leading-relaxed text-text-secondary">Cada parcela vira uma conta. A soma fecha no centavo.</p>
            </x-card>
        </aside>
    </div>
</div>
