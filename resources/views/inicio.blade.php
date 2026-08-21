@extends('layouts.app')

@section('title', 'Início')
@section('breadcrumb')
    <x-icon name="layout-dashboard" class="h-4 w-4" /> <b class="text-text font-semibold">Início</b>
@endsection

@section('content')
    <x-page-header
        title="PetroWeb Frota"
        subtitle="Gestão de transporte rodoviário de cargas — {{ \App\Support\TenantContext::empresa()?->razao_social ?? 'sem empresa no contexto' }}" />

    @php
        // Enquanto os módulos não existem, a tela diz a verdade sobre o que
        // está pronto. Painel com número inventado é pior que painel vazio.
        $modulos = collect(config('navegacao'))
            ->reject(fn (array $grupo): bool => $grupo['solo'] ?? false);
    @endphp

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($modulos as $grupo)
            <x-card padding="none" class="overflow-hidden">
                <div class="border-b border-border px-5 py-3.5">
                    <h2 class="text-sm font-semibold text-text">{{ $grupo['label'] }}</h2>
                </div>
                <div class="px-2 py-2">
                    @foreach ($grupo['items'] as $item)
                        @continue(isset($item['can']) && ! auth()->user()?->can($item['can']))
                        @php $existe = Route::has($item['route']); @endphp

                        <a href="{{ $existe ? route($item['route']) : '#' }}"
                           @if($existe) wire:navigate @endif
                           class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm transition-colors
                                  {{ $existe ? 'text-text hover:bg-surface-elevated' : 'cursor-not-allowed text-text-muted opacity-50' }}"
                           @unless($existe) title="Ainda não implementado" @endunless>
                            <x-icon name="{{ $item['icon'] }}" class="h-4 w-4 flex-shrink-0" />
                            <span class="flex-1">{{ $item['label'] }}</span>
                            <span class="font-mono text-[10px] tabular-nums text-text-muted">{{ $item['codigo'] }}</span>
                        </a>
                    @endforeach
                </div>
            </x-card>
        @endforeach
    </div>
@endsection
