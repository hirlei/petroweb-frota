{{-- Bloco vale-pedágio da tela do MDF-e (4020). Compra/informa junto com a emissão. --}}
@php
    $fmt = fn ($v) => number_format((float) $v, 2, ',', '.');
    $vale = $this->valeAtual;
    $papel = $this->papelVale;
    $viagem = $mdfe->viagem;
@endphp
<x-card padding="none" class="overflow-hidden">
    <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
        <x-icon name="ticket" class="h-4 w-4 text-text-secondary" />
        <h2 class="text-sm font-semibold text-text">Vale-pedágio</h2>
        <span class="ml-auto">
            @if ($vale?->dispensado)
                <x-badge variant="gray" class="text-[11px]">Dispensado</x-badge>
            @elseif ($vale)
                <x-badge variant="success" class="text-[11px]">{{ $vale->origem === 'compra' ? 'Comprado' : 'Informado' }}</x-badge>
            @elseif ($this->emitivel())
                <x-badge variant="info" class="text-[11px]">{{ $valeModo === 'comprar' ? 'Compra ao emitir' : ($valeModo === 'dispensar' ? 'Dispensa ao emitir' : 'Informa ao emitir') }}</x-badge>
            @else
                <x-badge variant="gray" class="text-[11px]">Sem vale</x-badge>
            @endif
        </span>
    </div>

    <div class="px-5 py-4">
        @if ($vale?->dispensado)
            <p class="text-sm text-text-secondary">{{ \App\Models\ValePedagio::MOTIVOS_DISPENSA[$vale->motivo_dispensa] ?? $vale->motivo_dispensa }}.</p>
        @elseif ($vale)
            <div class="grid grid-cols-1 gap-x-6 gap-y-1 sm:grid-cols-2">
                <x-linha-ficha rotulo="Fornecedora" :valor="$vale->fornecedorVpo?->razao_social ?? '—'" />
                <x-linha-ficha rotulo="Compra" :valor="$vale->idvpo ?? '—'" mono />
                <x-linha-ficha rotulo="Valor" :valor="$vale->valor !== null ? 'R$ ' . $fmt($vale->valor) : '—'" />
                <x-linha-ficha rotulo="Quem paga" :valor="$vale->papel === 'fornecido' ? 'A transportadora (contratou TAC)' : 'O embarcador'" />
            </div>
            <a href="{{ route('vale-pedagio.index') }}" wire:navigate class="mt-2 inline-block text-xs font-semibold text-[var(--h6-azul-tx)] hover:underline">Abrir no 4030 →</a>
        @elseif (! $this->emitivel())
            <p class="text-sm text-text-muted">Este MDF-e saiu sem vale-pedágio.</p>
        @else
            <div class="mb-3 flex items-center gap-3 rounded-lg border border-border px-3 py-2.5 text-sm">
                <span class="rounded border border-border-strong px-1.5 font-mono text-[11px] font-semibold">{{ $mdfe->veiculoTracao?->placaFormatada() ?? '—' }}</span>
                <div class="min-w-0 flex-1">
                    <b class="block font-medium text-text">Composição · {{ $this->eixosVale }} {{ $this->eixosVale === 1 ? 'eixo' : 'eixos' }}</b>
                    <small class="text-[11.5px] text-text-secondary">{{ $viagem?->municipioOrigem?->nome ?? '—' }} → {{ $viagem?->municipioDestino?->nome ?? '—' }}</small>
                </div>
                @if ($this->cotacaoVale !== null)
                    <b class="font-mono text-sm" title="Estimativa da fornecedora">R$ {{ $fmt($this->cotacaoVale) }}</b>
                @endif
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <span class="mb-1.5 block text-sm font-medium text-text-secondary">Como</span>
                    <div class="grid grid-cols-3 gap-1.5">
                        @foreach (['comprar' => 'Comprar agora', 'informar' => 'Já comprado', 'dispensar' => 'Sem pedágio'] as $codigo => $rotulo)
                            <button type="button" wire:click="$set('valeModo', '{{ $codigo }}')"
                                    class="rounded-lg border py-2 text-xs font-medium {{ $valeModo === $codigo ? 'border-[var(--h6-azul)] bg-[var(--h6-hover-bg)] font-semibold text-[var(--h6-azul-tx)]' : 'border-border text-text-secondary' }}">
                                {{ $rotulo }}
                            </button>
                        @endforeach
                    </div>
                </div>
                @if ($valeModo === 'dispensar')
                    <x-select label="Motivo" wire:model="valeMotivo">
                        @foreach (\App\Models\ValePedagio::MOTIVOS_DISPENSA as $codigo => $rotulo)
                            <option value="{{ $codigo }}">{{ $rotulo }}</option>
                        @endforeach
                    </x-select>
                @else
                    <x-select label="Fornecedora" wire:model="valeFornecedorId">
                        <option value="">Selecione…</option>
                        @foreach ($this->fornecedoresVpo as $f)
                            <option value="{{ $f->id }}">{{ $f->razao_social }}</option>
                        @endforeach
                    </x-select>
                @endif
            </div>

            @if ($valeModo === 'informar')
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <x-input label="Número da compra" wire:model="valeIdvpo" maxlength="20" mono />
                    <x-input label="Valor (R$)" type="number" step="0.01" min="0" wire:model="valeValor" mono />
                    <x-select label="Tipo" wire:model="valeTipo">
                        <option value="01">TAG</option>
                        <option value="04">Leitura de placa</option>
                    </x-select>
                </div>
            @endif

            @if ($this->fornecedoresVpo->isEmpty() && $valeModo !== 'dispensar')
                <p class="mt-2 text-xs font-semibold text-red-600 dark:text-red-400">Nenhuma fornecedora de vale-pedágio cadastrada.</p>
            @endif
            <p class="mt-2 text-[11.5px] leading-relaxed text-text-secondary">
                @if ($papel === 'fornecido')
                    Quem paga é a transportadora: contratou TAC e vira embarcadora equiparada (multa de R$ 3.000 por veículo sem vale).
                @else
                    Normalmente o embarcador compra e passa o número: marque "Já comprado".
                @endif
                O vale não entra no frete.
            </p>
        @endif
    </div>
</x-card>
