{{-- Resultado da emissão em três etapas (CIOT → vale → MDF-e). --}}
@if ($resultado)
    @php
        $icones = ['ok' => ['check', 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-300'],
                   'aviso' => ['alert-triangle', 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300'],
                   'erro' => ['x', 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300'],
                   'pulado' => ['chevron-right', 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400']];
        $ciot = $this->ciotAtual;
    @endphp
    <div class="fixed inset-0 z-[90] flex items-start justify-center bg-[rgba(15,26,58,.45)] px-4 pt-[10vh]" wire:keydown.escape.window="fecharResultado">
        <div class="w-full max-w-md rounded-2xl bg-surface p-5 shadow-2xl" role="dialog" aria-modal="true" aria-label="Resultado da emissão">
            <h3 class="text-base font-bold text-text">{{ $resultado['autorizado'] ? 'MDF-e autorizado' : 'MDF-e não autorizado' }}</h3>
            <p class="mt-0.5 text-[12.5px] text-text-secondary">Viagem {{ $mdfe->viagem?->numero }} · {{ $mdfe->veiculoTracao?->placaFormatada() }}</p>
            <ol class="mt-4 flex flex-col gap-3">
                @foreach ($resultado['etapas'] as $e)
                    @php [$ic, $cor] = $icones[$e['estado']] ?? $icones['pulado']; @endphp
                    <li class="flex items-start gap-2.5">
                        <span class="flex h-[22px] w-[22px] flex-shrink-0 items-center justify-center rounded-full {{ $cor }}"><x-icon :name="$ic" class="h-3 w-3" /></span>
                        <div class="min-w-0"><b class="block text-[13px] font-semibold text-text">{{ $e['titulo'] }}</b><small class="break-words text-[11.5px] text-text-secondary">{{ $e['detalhe'] }}</small></div>
                    </li>
                @endforeach
            </ol>
            <div class="mt-5 flex justify-end gap-2">
                <x-button variant="neutral" size="sm" wire:click="fecharResultado">Fechar</x-button>
                @if ($ciot && $ciot->valido())
                    @can('viewAny', \App\Models\Ciot::class)
                        <x-button variant="primary" size="sm" :href="route('ciot.ver', $ciot)" wire:navigate>Ver o CIOT</x-button>
                    @endcan
                @endif
            </div>
        </div>
    </div>
@endif
