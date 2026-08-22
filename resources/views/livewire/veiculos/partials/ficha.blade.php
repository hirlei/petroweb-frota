{{--
    Ficha técnica resumida do veículo selecionado — somente leitura.
    Os campos de proprietário só aparecem quando a propriedade é de terceiro:
    é o RNTRC dele que vai ao MDF-e, nunca o da empresa.
--}}
@php
    $ehTerceiro = $veiculo->propriedade !== 'propria';
    $cargaUtil = $veiculo->cargaUtilKg();
@endphp

<x-card padding="none" class="overflow-hidden">
    <div class="flex items-center gap-2 border-b border-border bg-primary-soft px-5 py-3.5">
        <x-icon name="truck" class="h-4 w-4 text-amber-700" />
        <h2 class="flex-1 truncate font-mono text-sm font-semibold text-amber-700">{{ $veiculo->placaFormatada() }}</h2>
        @can('update', $veiculo)
            <a href="{{ route('veiculos.editar', $veiculo) }}" wire:navigate
               class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-amber-700 hover:bg-amber-100">
                <x-icon name="pencil" class="h-3.5 w-3.5" /> Editar
            </a>
        @endcan
    </div>

    <div class="px-5 py-4">
        <div class="mb-3 flex flex-wrap gap-1.5">
            <x-badge :variant="config('veiculos.tipos_cores.' . $veiculo->tipo, 'gray')" class="px-2.5 py-1">
                {{ config('veiculos.tipos.' . $veiculo->tipo) }}
            </x-badge>
            <x-badge :variant="config('veiculos.propriedades_cores.' . $veiculo->propriedade, 'gray')" class="px-2.5 py-1">
                {{ config('veiculos.propriedades.' . $veiculo->propriedade) }}
            </x-badge>
            @if ($veiculo->exige_aet)
                <x-badge variant="warning" class="px-2.5 py-1">Exige AET</x-badge>
            @endif
        </div>

        <p class="mb-2 mt-4 text-[11px] font-bold uppercase tracking-wider text-text-muted">Identificação</p>
        <x-linha-ficha rotulo="Marca / modelo" :valor="trim(($veiculo->marca ?? '') . ' ' . ($veiculo->modelo ?? '')) ?: '—'" :mono="false" />
        <x-linha-ficha rotulo="Ano" :valor="$veiculo->ano_fabricacao ? $veiculo->ano_fabricacao . '/' . $veiculo->ano_modelo : '—'" />
        <x-linha-ficha rotulo="RENAVAM" :valor="$veiculo->renavam ?? '—'" />
        <x-linha-ficha rotulo="Chassi" :valor="$veiculo->chassi ?? '—'" />
        <x-linha-ficha rotulo="Licenciamento" :valor="$veiculo->uf_licenciamento ?? '—'" :mono="false" />

        <p class="mb-2 mt-4 text-[11px] font-bold uppercase tracking-wider text-text-muted">Ficha técnica</p>
        <x-linha-ficha rotulo="Eixos" :valor="(string) $veiculo->eixos" />
        <x-linha-ficha rotulo="Carroceria" :valor="$veiculo->carroceria?->nome ?? '—'" :mono="false" />
        <x-linha-ficha rotulo="Tara" :valor="number_format((float) $veiculo->tara_kg, 0, ',', '.') . ' kg'" />
        <x-linha-ficha rotulo="PBTC" :valor="$veiculo->pbtc_kg ? number_format((float) $veiculo->pbtc_kg, 0, ',', '.') . ' kg' : '—'" />

        <div class="my-1.5 flex items-center justify-between rounded-md bg-secondary-soft px-2.5 py-2">
            <span class="text-sm font-semibold text-green-800">Carga útil</span>
            <span class="font-mono text-sm font-semibold text-green-800">
                {{ $cargaUtil !== null ? number_format($cargaUtil, 0, ',', '.') . ' kg' : '—' }}
            </span>
        </div>

        <div class="my-1.5 flex items-center justify-between rounded-md bg-primary-soft px-2.5 py-2">
            <span class="text-sm font-semibold text-amber-700">Categoria (categCombVeic)</span>
            <x-badge variant="primary" class="text-[11px]">{{ $veiculo->categoriaCombinacaoVeicular()->value }}</x-badge>
        </div>
        <p class="mb-3 text-xs text-text-muted">
            Derivada dos eixos. Para a combinação real, quem manda é a soma dos eixos da composição.
        </p>

        @if ($ehTerceiro)
            <div class="my-2.5 h-px bg-border"></div>
            <p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-text-muted">Proprietário (terceiro)</p>
            <x-linha-ficha rotulo="Nome" :valor="$veiculo->proprietario?->razao_social ?? '—'" :mono="false" />
            <x-linha-ficha rotulo="RNTRC" :valor="$veiculo->proprietario_rntrc ?? '—'" />
            <x-linha-ficha rotulo="Tipo transportador"
                           :valor="match ($veiculo->proprietario_tp_transp) {
                               '1' => '1 — ETC', '2' => '2 — TAC', '3' => '3 — CTC', default => '—',
                           }" :mono="false" />
            <p class="mt-1.5 text-xs text-text-muted">
                É o RNTRC do proprietário que vai ao MDF-e — nunca o da transportadora.
            </p>
        @endif
    </div>
</x-card>
