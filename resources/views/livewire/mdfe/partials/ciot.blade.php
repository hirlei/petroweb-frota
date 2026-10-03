{{-- Bloco CIOT da tela do MDF-e (4020). Registra junto com a emissão; depois só mostra. --}}
@php
    $fmt = fn ($v) => number_format((float) $v, 2, ',', '.');
    $mod = $this->modalidadeCiot;
    $atual = $this->ciotAtual;
    $sug = $this->sugestaoCiot;
    $contr = $sug['contratado'] ?? [];
    $cores = config('ciot.modalidade_cores');
    $doc = fn (?string $d) => match (strlen((string) $d)) {
        11 => vsprintf('%s%s%s.%s%s%s.%s%s%s-%s%s', str_split((string) $d)),
        14 => vsprintf('%s%s.%s%s%s.%s%s%s/%s%s%s%s-%s%s', str_split((string) $d)),
        default => (string) $d,
    };
@endphp
<x-card padding="none" class="overflow-hidden">
    <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
        <x-icon name="key" class="h-4 w-4 text-text-secondary" />
        <h2 class="text-sm font-semibold text-text">CIOT</h2>
        <span class="ml-auto">
            @if ($atual?->valido())
                <x-badge variant="success" class="text-[11px]">Registrado</x-badge>
            @elseif ($mod === 'dispensado')
                <x-badge variant="gray" class="text-[11px]">Não se aplica</x-badge>
            @elseif ($atual?->status === 'recusado')
                <x-badge variant="danger" class="text-[11px]">Recusado</x-badge>
            @elseif ($this->emitivel())
                <x-badge variant="info" class="text-[11px]">{{ $mod === 'informado' ? 'Informe o número' : 'Registra ao emitir' }}</x-badge>
            @else
                <x-badge variant="gray" class="text-[11px]">Sem CIOT</x-badge>
            @endif
        </span>
    </div>

    <div class="px-5 py-4">
        @if ($atual?->valido())
            {{-- Já registrado: só mostra. Mudanças (saldo, cancelamento) no 4050. --}}
            <div class="mb-2 flex flex-wrap items-center gap-2">
                <x-badge :variant="$cores[$atual->modalidade] ?? 'gray'" class="text-[11px]">{{ config('ciot.modalidades.' . $atual->modalidade) }}</x-badge>
                <span class="font-mono text-base font-semibold text-text">{{ $atual->numeroFormatado() }}</span>
            </div>
            <div class="grid grid-cols-1 gap-x-6 gap-y-1 sm:grid-cols-2">
                <x-linha-ficha rotulo="Contratado" :valor="$atual->contratado_nome ?? 'Frota própria'" />
                <x-linha-ficha rotulo="Responsável" :valor="$doc($atual->responsavel_documento)" mono />
                @if ($atual->temPagamento())
                    <x-linha-ficha rotulo="Frete" :valor="'R$ ' . $fmt($atual->valor_frete)" />
                    <x-linha-ficha rotulo="Pago" :valor="'R$ ' . $fmt($atual->valor_pago) . ($atual->adiantamentoPendente() ? ' · adiantamento pendente' : '')" />
                    <x-linha-ficha rotulo="Saldo até" :valor="$atual->prazo_quitacao?->format('d/m/Y') ?? '—'" />
                @endif
            </div>
            @can('viewAny', \App\Models\Ciot::class)
                <a href="{{ route('ciot.ver', $atual) }}" wire:navigate class="mt-2 inline-block text-xs font-semibold text-[var(--h6-azul-tx)] hover:underline">Abrir no 4050 →</a>
            @endcan

        @elseif ($mod === 'dispensado')
            <p class="text-sm text-text-secondary">Carga própria em veículo próprio, com motorista da empresa: não há frete pago a ninguém, então não há CIOT nem trava.</p>

        @elseif (! $this->emitivel())
            <p class="text-sm text-text-muted">Este MDF-e saiu sem CIOT.</p>

        @else
            @if ($atual?->status === 'recusado')
                <div class="mb-3 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800 dark:border-red-800/50 dark:bg-red-950/40 dark:text-red-300">
                    <x-icon name="alert-triangle" class="mt-px h-3.5 w-3.5 flex-shrink-0" />
                    <span><b>Recusado:</b> {{ $atual->motivo }} Corrija abaixo e emita de novo.</span>
                </div>
            @endif

            <p class="mb-3 flex flex-wrap items-center gap-2 text-[12.5px] text-text-secondary">
                <x-badge :variant="$cores[$mod] ?? 'gray'" class="text-[11px]">{{ config('ciot.modalidades.' . $mod) }}</x-badge>
                @if ($mod === 'ipef')
                    {{ $contr['nome'] ?? 'O contratado' }} é TAC{{ ($contr['vinculo'] ?? null) ? ' (' . $contr['vinculo'] . ')' : '' }}: o frete só pode ser pago na conta dele ou em cartão frete.
                @elseif ($mod === 'antt')
                    Caminhão e motorista da empresa: registro direto na ANTT, sem pagamento a terceiro.
                @else
                    O caminhão é de outra transportadora: quem executa registra o CIOT e passa o número.
                @endif
            </p>

            @if ($mod === 'ipef')
                @if (! empty($contr))
                    <p class="mb-3 text-xs text-text-muted">
                        {{ $contr['nome'] }} · {{ $doc($contr['documento'] ?? '') }} ·
                        @if (! empty($contr['rntrc']))
                            RNTRC {{ $contr['rntrc'] }}
                        @else
                            <span class="font-semibold text-red-600 dark:text-red-400">Sem RNTRC no cadastro — a instituição recusa</span>
                        @endif
                    </p>
                @endif
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <x-input label="Frete contratado (R$)" type="number" step="0.01" min="0" wire:model.live.debounce.500ms="ciotFrete" placeholder="0,00" mono />
                    <div>
                        <span class="mb-1.5 block text-sm font-medium text-text-secondary">Adiantamento</span>
                        <div class="flex flex-wrap gap-1">
                            @foreach (config('ciot.adiantamentos') as $pct)
                                <button type="button" wire:click="definirPercentual('{{ $pct }}')"
                                        class="rounded-md border px-2.5 py-1.5 text-xs font-medium {{ (string) $ciotPercentual === (string) $pct ? 'border-transparent bg-primary text-white' : 'border-border text-text-secondary' }}">
                                    {{ $pct === 0 ? 'Sem' : $pct . '%' }}
                                </button>
                            @endforeach
                            <input type="number" min="0" max="100" step="1" wire:model.live.debounce.500ms="ciotPercentual" aria-label="Percentual de adiantamento"
                                   class="w-16 rounded-md border border-border bg-white px-2 py-1 text-xs text-text dark:bg-surface-elevated">
                        </div>
                    </div>
                    <x-input label="Saldo até" type="date" wire:model.live="ciotPrazo" :max="$sug['prazo_maximo'] ?? null" />
                </div>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <span class="mb-1.5 block text-sm font-medium text-text-secondary">Pagar em</span>
                        <div class="grid grid-cols-3 gap-1.5">
                            @foreach (config('ciot.formas') as $codigo => $rotulo)
                                <button type="button" wire:click="$set('ciotForma', '{{ $codigo }}')"
                                        class="rounded-lg border py-2 text-xs font-medium {{ $ciotForma === $codigo ? 'border-[var(--h6-azul)] bg-[var(--h6-hover-bg)] font-semibold text-[var(--h6-azul-tx)]' : 'border-border text-text-secondary' }}">
                                    {{ $rotulo }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <x-input :label="$ciotForma === 'cartao_frete' ? 'Cartão frete do TAC' : ($ciotForma === 'pix' ? 'Chave Pix do TAC' : 'Conta do TAC')" wire:model="ciotChave" placeholder="Do próprio TAC" />
                </div>
                <p class="mt-2 text-[11.5px] leading-relaxed text-text-secondary">
                    @if ($d = $this->divisaoCiot)
                        Adiantamento de R$ {{ $fmt($d['adiantamento']) }} sai na emissão; o saldo, de R$ {{ $fmt($d['saldo']) }}, é pago no 4050 depois da entrega.
                    @endif
                    Pedágio não entra no frete. Prazo máximo do saldo: {{ isset($sug['prazo_maximo']) ? \Illuminate\Support\Carbon::parse($sug['prazo_maximo'])->format('d/m/Y') : '—' }} (30 dias úteis).
                </p>

            @elseif ($mod === 'antt')
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <x-input label="Frete da operação (R$)" type="number" step="0.01" min="0" wire:model="ciotFrete" mono
                             :help="'Soma dos CT-e da viagem: R$ ' . $fmt($sug['receita'] ?? 0)" />
                    <x-linha-ficha rotulo="Responsável" :valor="$doc($sug['contratante_documento'] ?? '')" mono />
                </div>

            @else
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <x-input label="Número do CIOT" wire:model="ciotNumero" placeholder="12 dígitos" maxlength="16" mono />
                    <x-input label="CPF ou CNPJ de quem registrou" wire:model="ciotResponsavel" mono />
                </div>
            @endif
        @endif
    </div>
</x-card>
