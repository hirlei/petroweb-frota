{{--
    Ficha resumida do motorista — somente leitura.
    As pendências bloqueantes vêm do model (CNH, EAR, toxicológico, RNTRC do
    TAC). O bloco de jornada só afirma o que a RN-12 decide: só CLT a tem.
--}}
@php
    $pendencias = $motorista->pendenciasBloqueantes();
@endphp

<x-card padding="none" class="overflow-hidden">
    <div class="flex items-center gap-2 border-b border-border bg-primary-soft px-5 py-3.5">
        <x-icon name="id-card" class="h-4 w-4 text-amber-700" />
        <h2 class="flex-1 truncate text-sm font-semibold text-amber-700">{{ $motorista->pessoa?->razao_social ?? 'Motorista' }}</h2>
        @can('update', $motorista)
            <a href="{{ route('motoristas.editar', $motorista) }}" wire:navigate
               class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-amber-700 hover:bg-amber-100">
                <x-icon name="pencil" class="h-3.5 w-3.5" /> Editar
            </a>
        @endcan
    </div>

    <div class="px-5 py-4">
        <div class="mb-3 flex flex-wrap gap-1.5">
            <x-badge :variant="config('motoristas.vinculos_cores.' . $motorista->vinculo, 'gray')" class="px-2.5 py-1">
                {{ config('motoristas.vinculos.' . $motorista->vinculo) }}
            </x-badge>
            @if ($motorista->ehTac())
                <x-badge variant="warning" class="px-2.5 py-1">TAC · CIOT obrigatório</x-badge>
            @endif
        </div>

        @if ($pendencias === [])
            <div class="mb-3 flex items-start gap-2.5 rounded-md bg-secondary-soft px-3 py-2.5">
                <x-icon name="check" class="mt-px h-4 w-4 flex-shrink-0 text-green-700" />
                <div class="text-sm font-semibold text-green-800">Apto a viajar</div>
            </div>
        @else
            <div class="mb-3">
                @foreach ($pendencias as $pendencia)
                    <div class="mb-1.5 flex items-start gap-2 rounded-r-md border-l-[3px] border-danger bg-red-50 px-3 py-2
                                text-xs text-red-800 dark:bg-red-950/40 dark:text-red-300">
                        <x-icon name="alert-triangle" class="mt-px h-3.5 w-3.5 flex-shrink-0" />
                        <span>{{ $pendencia }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <p class="mb-2 mt-4 text-[11px] font-bold uppercase tracking-wider text-text-muted">Habilitação</p>
        <x-linha-ficha rotulo="Categoria" :valor="$motorista->cnh_categoria" :mono="false" />
        <x-linha-ficha rotulo="Número" :valor="$motorista->cnh_numero" />
        <x-linha-ficha rotulo="Validade" :valor="$motorista->cnh_validade?->format('d/m/Y') ?? '—'" />
        <x-linha-ficha rotulo="EAR" :valor="$motorista->cnh_ear ? 'Sim' : 'Não'" :mono="false" />

        <p class="mb-2 mt-4 text-[11px] font-bold uppercase tracking-wider text-text-muted">Documentos e cursos</p>
        <x-linha-ficha rotulo="Toxicológico" :valor="$motorista->toxicologico_validade?->format('d/m/Y') ?? '—'" />
        <x-linha-ficha rotulo="MOPP" :valor="$motorista->mopp_validade?->format('d/m/Y') ?? '—'" />
        @if ($motorista->ehTac())
            <x-linha-ficha rotulo="RNTRC" :valor="$motorista->rntrc ?? '—'" />
            <x-linha-ficha rotulo="Validade RNTRC" :valor="$motorista->rntrc_validade?->format('d/m/Y') ?? '—'" />
        @endif

        <div class="my-2.5 h-px bg-border"></div>
        <div class="flex items-start gap-2 rounded-r-md border-l-[3px] {{ $motorista->controlaJornada() ? 'border-info bg-blue-50 text-blue-800 dark:bg-blue-950/40 dark:text-blue-300' : 'border-border bg-surface-elevated text-text-secondary' }} px-3 py-2.5 text-xs">
            <x-icon name="info" class="mt-px h-3.5 w-3.5 flex-shrink-0" />
            <span>
                @if ($motorista->controlaJornada())
                    Vínculo CLT: jornada, escala e ponto se aplicam a este motorista.
                @else
                    Vínculo {{ config('motoristas.vinculos.' . $motorista->vinculo) }}: <b>sem jornada</b>. Registrar ponto de TAC produziria prova de vínculo empregatício (RN-12).
                @endif
            </span>
        </div>
    </div>
</x-card>
