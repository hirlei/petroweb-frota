{{-- Rotina 4010 — painel do CT-e: gera da OC, emite, corrige (CC-e) e cancela. --}}
<div>
    @if (! $cte)
        {{-- Modo: gerar rascunho a partir de uma ordem de coleta --}}
        <x-page-header title="Novo CT-e" subtitle="Gere o CT-e a partir de uma ordem de coleta">
            <x-slot:actions>
                <x-button variant="ghost" size="sm" :href="route('cte.index')" wire:navigate>Voltar</x-button>
            </x-slot:actions>
        </x-page-header>

        @if (session('erro'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800/50 dark:bg-red-950/50 dark:text-red-300">{{ session('erro') }}</div>
        @endif

        <x-card padding="none" class="overflow-hidden">
            <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                <x-icon name="file-text" class="h-4 w-4 text-text-secondary" />
                <h2 class="text-sm font-semibold text-text">Ordem de coleta</h2>
            </div>
            <div class="px-5 py-5">
                <x-select label="Ordem de coleta (sem CT-e)" wire:model="ordem_coleta_id">
                    <option value="">Selecione…</option>
                    @foreach ($this->ordens as $o)
                        <option value="{{ $o->id }}">OC {{ $o->numero }} · {{ $o->cliente?->razao_social }} · R$ {{ number_format((float) $o->valor_frete_calculado, 2, ',', '.') }}</option>
                    @endforeach
                </x-select>
                <x-button class="mt-4" variant="primary" size="sm" icon="files" wire:click="gerarRascunho" wire:loading.attr="disabled">Gerar rascunho do CT-e</x-button>
                <p class="mt-2 text-xs text-text-muted">O rascunho copia participantes, trecho, carga, componentes do frete e as NF-e da ordem — sem redigitar.</p>
            </div>
        </x-card>
    @else
        @php $editavel = $cte->editavel(); @endphp
        <x-page-header :title="'CT-e nº ' . ($cte->numero ? str_pad((string) $cte->numero, 6, '0', STR_PAD_LEFT) : 'rascunho')"
                       :subtitle="'Origem: ordem ' . ($cte->ordemColeta?->numero ?? '—') . ' · modelo 57 · série ' . $cte->serie">
            <x-slot:actions>
                <x-button variant="ghost" size="sm" :href="route('cte.index')" wire:navigate>Voltar</x-button>
                @if ($cte->exists)
                    <x-button variant="neutral" size="sm" icon="file-text" :href="route('cte.dacte', $cte)" target="_blank" rel="noopener"
                              title="{{ in_array($cte->status, ['autorizado', 'cancelado', 'contingencia'], true) ? 'Abrir o DACTE em PDF' : 'Prévia — não é documento fiscal' }}">
                        {{ in_array($cte->status, ['autorizado', 'cancelado', 'contingencia'], true) ? 'DACTE' : 'Prévia do DACTE' }}
                    </x-button>
                @endif
                @if ($editavel)
                    @can('emitir', $cte)
                        <x-button variant="primary" size="sm" icon="lock" wire:click="emitir" wire:loading.attr="disabled">Emitir (2FA)</x-button>
                    @endcan
                @endif
            </x-slot:actions>
        </x-page-header>

        @if (session('sucesso'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800/50 dark:bg-green-950/50 dark:text-green-300">{{ session('sucesso') }}</div>
        @endif
        @if (session('erro'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800/50 dark:bg-red-950/50 dark:text-red-300">{{ session('erro') }}</div>
        @endif

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="flex flex-col gap-4 lg:col-span-2">
                <x-card padding="none" class="overflow-hidden">
                    <div class="flex items-center gap-2 border-b border-border px-5 py-3.5"><x-icon name="users" class="h-4 w-4 text-text-secondary" /><h2 class="text-sm font-semibold text-text">Participantes e trecho</h2></div>
                    <div class="grid grid-cols-1 gap-x-6 gap-y-1 px-5 py-4 sm:grid-cols-2">
                        <x-linha-ficha rotulo="Tomador (RN-04)" :valor="ucfirst($cte->tomadorPapel()) . ' · ' . ($cte->tomador?->razao_social ?? '—')" />
                        <x-linha-ficha rotulo="Natureza" :valor="$cte->natureza_operacao ?? '—'" />
                        <x-linha-ficha rotulo="Remetente" :valor="$cte->remetente?->razao_social ?? '—'" />
                        <x-linha-ficha rotulo="Destinatário" :valor="$cte->destinatario?->razao_social ?? '—'" />
                        <x-linha-ficha rotulo="Início" :valor="$cte->municipioInicio?->nome ?? '—'" />
                        <x-linha-ficha rotulo="Fim" :valor="$cte->municipioFim?->nome ?? '—'" />
                        @php $proPredCorrigido = $this->corrigido('infCarga', 'proPred'); @endphp
                        <x-linha-ficha rotulo="Produto" :valor="($proPredCorrigido ?? $cte->produto_predominante ?? '—') . ($proPredCorrigido !== null ? ' (corrigido por CC-e)' : '')" />
                        <x-linha-ficha rotulo="Peso base" :valor="number_format((float) $cte->peso_bruto, 0, ',', '.') . ' kg'" />
                    </div>
                </x-card>

                <x-card padding="none" class="overflow-hidden">
                    <div class="flex items-center gap-2 border-b border-border px-5 py-3.5"><x-icon name="tag" class="h-4 w-4 text-text-secondary" /><h2 class="text-sm font-semibold text-text">Componentes do frete (vTPrest)</h2></div>
                    <div class="px-5 py-4">
                        @foreach ($cte->componentes as $comp)
                            <div class="flex items-center justify-between border-t border-border py-1.5 text-sm first:border-0"><span class="text-text-secondary">{{ $comp->nome }}</span><span class="font-medium text-text tabular-nums">R$ {{ number_format((float) $comp->valor, 2, ',', '.') }}</span></div>
                        @endforeach
                        <div class="mt-2 flex items-center justify-between border-t-2 border-border pt-2 text-sm"><span class="font-semibold text-text">Total da prestação</span><span class="font-bold text-text tabular-nums">R$ {{ number_format((float) $cte->valor_total_servico, 2, ',', '.') }}</span></div>
                        <div class="mt-3 flex items-start gap-2 rounded-r-md border-l-[3px] border-warning bg-yellow-50 px-3 py-2.5 text-xs text-yellow-800 dark:bg-yellow-950/40 dark:text-yellow-300">
                            <x-icon name="alert-triangle" class="mt-px h-3.5 w-3.5 flex-shrink-0" /><span>O pedágio pode ser componente do frete, mas o grupo valePed não existe no CT-e — é declarado no MDF-e — e não integra a base do ICMS.</span>
                        </div>
                    </div>
                </x-card>

                <x-card padding="none" class="overflow-hidden">
                    <div class="flex items-center gap-2 border-b border-border px-5 py-3.5"><x-icon name="file-text" class="h-4 w-4 text-text-secondary" /><h2 class="text-sm font-semibold text-text">Documentos (NF-e)</h2></div>
                    <div class="px-5 py-4">
                        @forelse ($cte->documentos as $doc)
                            <div class="flex items-center justify-between border-t border-border py-1.5 text-sm first:border-0"><span class="font-mono text-xs text-text-secondary">{{ $doc->chave ?? ('NF-e ' . $doc->numero) }}</span><span class="text-text-muted tabular-nums">R$ {{ number_format((float) $doc->valor, 2, ',', '.') }}</span></div>
                        @empty
                            <p class="text-sm text-text-muted">Sem NF-e vinculada.</p>
                        @endforelse
                    </div>
                </x-card>
            </div>

            <div class="flex flex-col gap-4">
                <x-card padding="none" class="overflow-hidden">
                    <div class="flex items-center gap-2 border-b border-border px-5 py-3.5"><x-icon name="upload" class="h-4 w-4 text-text-secondary" /><h2 class="text-sm font-semibold text-text">Transmissão</h2>@if ($this->ambiente === 2)<x-badge variant="warning" class="ml-auto text-[10px]">Homologação</x-badge>@endif</div>
                    <div class="px-5 py-4">
                        <x-linha-ficha rotulo="Situação" :valor="config('fiscal.cte.status.' . $cte->status, $cte->status)" />
                        <x-linha-ficha rotulo="Chave" :valor="$cte->chave ?? '—'" mono />
                        <x-linha-ficha rotulo="Protocolo" :valor="$cte->protocolo ?? '—'" />
                        <x-linha-ficha rotulo="cStat / xMotivo" :valor="$cte->codigo_status ? $cte->codigo_status . ' · ' . $cte->motivo_status : '—'" />
                        @if ($cte->status === 'rejeitado')
                            <div class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-800 dark:bg-red-950/40 dark:text-red-300">Corrija e emita novamente.</div>
                        @endif
                    </div>
                </x-card>

                @if ($cte->autorizado() || $this->cceEmitidas > 0)
                    @php $vig = $this->cceVigentes; $emit = $this->cceEmitidas; @endphp
                    <x-card padding="none" class="overflow-hidden">
                        <div class="flex items-center gap-2 border-b border-border px-5 py-3.5"><x-icon name="pencil" class="h-4 w-4 text-text-secondary" /><h2 class="text-sm font-semibold text-text">Carta de correção</h2><span class="ml-auto text-[11px] text-text-muted">Evento 110110</span></div>
                        <div class="px-5 py-4">
                            <div class="mb-2 flex items-baseline justify-between gap-2">
                                <span class="text-[22px] font-semibold tabular-nums text-text">{{ $emit }} <small class="text-xs font-normal text-text-secondary">de {{ \App\Domain\Fiscal\RegrasCce::MAXIMO }}</small></span>
                                <span class="text-[11.5px] text-text-secondary">{{ $emit > 0 ? 'A próxima substitui esta' : 'Nenhuma ainda' }}</span>
                            </div>
                            <div class="mb-3 h-1.5 overflow-hidden rounded-full bg-surface-elevated"><i class="block h-full bg-[var(--h6-azul)]" style="width: {{ min(100, $emit * 100 / \App\Domain\Fiscal\RegrasCce::MAXIMO) }}%"></i></div>
                            @if ($vig !== [])
                                <div class="overflow-hidden rounded-[10px] border border-border">
                                    @foreach ($vig as $c)
                                        @php $def = \App\Domain\Fiscal\RegrasCce::CATALOGO[\App\Domain\Fiscal\RegrasCce::chaveDoCatalogo($c['grupo'], $c['campo']) ?? ''] ?? null; @endphp
                                        <div class="flex justify-between gap-3 border-t border-border px-2.5 py-1.5 text-xs first:border-0">
                                            <span class="text-text-secondary">{{ $def[2] ?? ($c['grupo'] . ' · ' . $c['campo']) }}</span>
                                            <b class="text-right font-medium text-text">{{ $c['valor'] }}</b>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            @if ($cte->autorizado() && $emit < \App\Domain\Fiscal\RegrasCce::MAXIMO)
                                @can('corrigir', $cte)
                                    <x-button class="mt-2 w-full justify-center" variant="neutral" size="sm" icon="pencil" wire:click="abrirCce">Nova carta de correção</x-button>
                                @endcan
                            @endif
                            <p class="mt-2 text-[11.5px] leading-relaxed text-text-secondary">Não corrige valores, impostos, data de emissão nem quem são o emitente, o tomador, o remetente e o destinatário. Para isso: cancelar e emitir de novo.</p>
                        </div>
                    </x-card>
                @endif

                @if ($cte->autorizado())
                    @php $ate = $cte->cancelavelAte(); $noPrazo = $cte->noPrazoDeCancelamento(); @endphp
                    <x-card padding="none" class="overflow-hidden">
                        <div class="flex items-center gap-2 border-b border-border px-5 py-3.5"><x-icon name="x" class="h-4 w-4 text-text-secondary" /><h2 class="text-sm font-semibold text-text">Cancelar</h2></div>
                        <div class="px-5 py-4">
                            @if ($ate)
                                <div class="mb-3 flex items-center gap-2 rounded-lg px-3 py-2 text-xs {{ $noPrazo ? 'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300' : 'bg-red-50 text-red-800 dark:bg-red-950/40 dark:text-red-300' }}">
                                    <x-icon name="clock" class="h-3.5 w-3.5 flex-shrink-0" />
                                    @if ($noPrazo)
                                        Pode cancelar até {{ $ate->format('d/m H:i') }} · {{ now()->diffForHumans($ate, ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 1]) }} restantes
                                    @else
                                        Prazo de cancelamento encerrado em {{ $ate->format('d/m/Y H:i') }}
                                    @endif
                                </div>
                            @endif
                            @if ($noPrazo)
                                <x-input-label>Justificativa (15 a 255 caracteres)</x-input-label>
                                <textarea wire:model="justificativa" rows="2" maxlength="255" placeholder="Ex.: Frete lançado para o tomador errado"
                                          class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"></textarea>
                                @can('cancelar', $cte)
                                    <x-button class="mt-2 w-full justify-center" variant="danger-outline" size="sm" icon="trash-2" wire:click="cancelar" wire:confirm="Cancelar este CT-e na SEFAZ? Não dá para desfazer." wire:loading.attr="disabled">Cancelar CT-e</x-button>
                                @endcan
                            @else
                                <p class="text-xs text-text-secondary">Para mudar valor ou imposto agora, emita um CT-e complementar ou de substituição.</p>
                            @endif
                        </div>
                    </x-card>
                @endif

                <x-card padding="none" class="overflow-hidden">
                    <div class="flex items-center gap-2 border-b border-border px-5 py-3.5"><x-icon name="clock" class="h-4 w-4 text-text-secondary" /><h2 class="text-sm font-semibold text-text">Eventos</h2></div>
                    <div class="px-5 py-4">
                        @forelse ($cte->eventos->sortByDesc(fn ($e) => [$e->data_evento, $e->id]) as $ev)
                            @php $ok = $ev->status === 'registrado'; @endphp
                            <div class="flex items-start gap-2 border-t border-border py-2 text-sm first:border-0">
                                <x-icon :name="$ok ? 'check' : 'alert-triangle'" class="mt-0.5 h-3.5 w-3.5 flex-shrink-0 {{ $ok ? 'text-success' : 'text-danger' }}" />
                                <div class="min-w-0">
                                    <div class="font-medium text-text">{{ \App\Models\CteEvento::TIPOS[$ev->tipo_evento] ?? $ev->tipo_evento }}{{ $ev->tipo_evento === \App\Models\CteEvento::CCE ? ' nº ' . $ev->sequencia : '' }}{{ $ok ? '' : ' — recusada' }}</div>
                                    <div class="text-xs text-text-muted">{{ $ev->data_evento?->format('d/m/Y H:i') }}{{ $ev->protocolo ? ' · protocolo ' . $ev->protocolo : '' }}{{ $ev->criadoPor ? ' · ' . $ev->criadoPor->name : '' }}</div>
                                    @unless ($ok)<div class="text-xs text-danger">{{ $ev->codigo_status }} · {{ $ev->motivo_status }}</div>@endunless
                                    @if ($ok && $ev->tipo_evento === \App\Models\CteEvento::CCE)
                                        <a href="{{ route('cte.cce', [$cte, $ev]) }}" target="_blank" rel="noopener" class="text-xs font-semibold text-[var(--h6-azul-tx)] hover:underline">Imprimir</a>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-text-muted">Nenhum evento ainda.</p>
                        @endforelse
                    </div>
                </x-card>
            </div>
        </div>

        {{-- Nova carta de correção --}}
        @if ($cceAberta)
            @php $cat = \App\Domain\Fiscal\RegrasCce::CATALOGO; $vetos = $this->cceVetos; $bloqueado = $vetos !== []; @endphp
            <div class="fixed inset-0 z-[90] flex items-start justify-center overflow-y-auto bg-[rgba(15,26,58,.45)] px-4 pt-[8vh] pb-8" wire:keydown.escape.window="fecharCce">
                <div class="w-full max-w-3xl rounded-2xl bg-surface p-5 shadow-2xl" role="dialog" aria-modal="true" aria-label="Nova carta de correção">
                    <h3 class="text-base font-bold text-text">Carta de correção nº {{ $this->cceEmitidas + 1 }}</h3>
                    <p class="mt-0.5 text-[12.5px] text-text-secondary">CT-e {{ str_pad((string) $cte->numero, 6, '0', STR_PAD_LEFT) }}{{ $this->cceEmitidas > 0 ? ' · substitui a nº ' . $this->cceEmitidas . ', então leva as correções dela junto' : '' }}</p>

                    <div class="mt-3 overflow-x-auto rounded-[10px] border border-border">
                        <table class="w-full table-fixed text-[12.5px]">
                            <thead class="bg-surface-elevated">
                                <tr class="text-left text-[10.5px] font-semibold uppercase tracking-wider text-text-muted">
                                    <th class="w-[34%] px-2 py-2">Campo</th><th class="w-[24%] px-2 py-2">Como está</th><th class="px-2 py-2">Corrigir para</th><th class="w-8 px-2 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cceLinhas as $i => $l)
                                    @php $erro = $vetos[$i] ?? ($cceErros[$i] ?? null); $vet = isset($vetos[$i]); @endphp
                                    <tr class="border-t border-border align-top {{ $vet ? 'bg-red-50 dark:bg-red-950/30' : '' }}" wire:key="cce-{{ $i }}">
                                        <td class="px-2 py-2">
                                            <select wire:model.live="cceLinhas.{{ $i }}.chave" class="w-full rounded-md border border-border bg-white px-2 py-1.5 text-[12.5px] text-text dark:bg-surface-elevated">
                                                <option value="">Escolha…</option>
                                                @foreach ($cat as $chave => $def)
                                                    <option value="{{ $chave }}">{{ $def[2] }}</option>
                                                @endforeach
                                                <option value="outro">Outro campo (tag do XML)</option>
                                            </select>
                                            @if (($l['chave'] ?? '') === 'outro')
                                                <div class="mt-1.5 grid grid-cols-2 gap-1.5">
                                                    <input type="text" wire:model.live.debounce.400ms="cceLinhas.{{ $i }}.grupo" placeholder="Grupo (ex.: compl)" maxlength="20" class="w-full rounded-md border border-border bg-white px-2 py-1 font-mono text-[11.5px] dark:bg-surface-elevated">
                                                    <input type="text" wire:model.live.debounce.400ms="cceLinhas.{{ $i }}.campo" placeholder="Campo (ex.: xObs)" maxlength="20" class="w-full rounded-md border border-border bg-white px-2 py-1 font-mono text-[11.5px] dark:bg-surface-elevated">
                                                </div>
                                            @endif
                                            <small class="mt-1 block text-[11px] text-text-secondary">
                                                {{ ($l['grupo'] ?? '') !== '' ? $l['grupo'] . ' · ' . $l['campo'] : '' }}
                                                @if (! empty($l['origem']))<span class="ml-1 rounded-full bg-gray-100 px-1.5 text-[10px] font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">Da CC-e {{ $this->cceEmitidas }}</span>@endif
                                            </small>
                                        </td>
                                        <td class="break-words px-2 py-2 text-text-secondary">{{ isset($cat[$l['chave'] ?? '']) ? ($this->valorAtual($l['chave']) ?? '—') : '—' }}</td>
                                        <td class="px-2 py-2">
                                            <input type="text" wire:model.blur="cceLinhas.{{ $i }}.valor" maxlength="2000"
                                                   class="w-full rounded-md border bg-white px-2 py-1.5 text-[12.5px] text-text dark:bg-surface-elevated {{ $erro ? 'border-red-300 dark:border-red-500/50' : 'border-border' }}">
                                            @if ($erro)<p class="mt-1 text-[11.5px] leading-snug text-red-700 dark:text-red-300">{{ $erro }}@if ($vet) Para isso: cancele e emita de novo{{ $cte->noPrazoDeCancelamento() && $cte->cancelavelAte() ? ' (até ' . $cte->cancelavelAte()->format('d/m H:i') . ')' : '' }} ou emita um CT-e complementar.@endif</p>@endif
                                        </td>
                                        <td class="px-2 py-2 text-center">
                                            <button type="button" wire:click="removerLinhaCce({{ $i }})" aria-label="Tirar a linha" class="text-text-muted hover:text-danger"><x-icon name="x" class="h-4 w-4" /></button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <button type="button" wire:click="adicionarLinhaCce" class="mt-2.5 text-xs font-semibold text-[var(--h6-azul-tx)] hover:underline">+ Adicionar correção</button>

                    <details class="mt-3 text-[11.5px] text-text-secondary">
                        <summary class="cursor-pointer font-semibold text-text">Condição de uso que vai no XML</summary>
                        <p class="mt-1.5 leading-relaxed">{{ \App\Domain\Fiscal\RegrasCce::CONDICAO_USO }}</p>
                    </details>

                    @if (isset($cceErros['_']))
                        <div class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-800 dark:bg-red-950/40 dark:text-red-300">{{ $cceErros['_'] }}</div>
                    @endif

                    <div class="mt-5 flex items-center justify-end gap-2">
                        @if ($bloqueado)<span class="mr-auto text-[11.5px] text-text-secondary">Tire a linha bloqueada para transmitir.</span>@endif
                        <x-button variant="neutral" size="sm" wire:click="fecharCce">Cancelar</x-button>
                        <x-button variant="primary" size="sm" icon="check" wire:click="transmitirCce" wire:loading.attr="disabled" :disabled="$bloqueado">Transmitir carta de correção</x-button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
