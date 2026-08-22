@props([
    // list<array{lat:float|null,lng:float|null,label:string,tipo?:string,cor?:string}>
    'pontos'       => [],
    'linha'        => true,      // desenha a linha do percurso ligando os pontos
    'percorridoAte' => null,     // índice até onde o trecho é "percorrido" (sólido); resto tracejado
    'altura'       => '360px',
    'id'           => null,
])

@php
    $mapId = $id ?? 'mapa-' . uniqid();
    $temCoords = collect($pontos)->contains(fn ($p) => ($p['lat'] ?? null) !== null && ($p['lng'] ?? null) !== null);
    $config = [
        'pontos'        => array_values($pontos),
        'linha'         => (bool) $linha,
        'percorridoAte' => $percorridoAte,
    ];
@endphp

@if ($temCoords)
    <div wire:ignore
         x-data="mapaPercurso(@js($config))"
         class="overflow-hidden rounded-lg border border-border">
        <div x-ref="mapa" id="{{ $mapId }}" style="height: {{ $altura }};"></div>
    </div>
@else
    <div class="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border bg-surface-elevated text-center"
         style="height: {{ $altura }};">
        <x-icon name="map" class="h-6 w-6 text-text-muted" />
        <p class="max-w-xs text-sm text-text-secondary">Sem coordenadas para exibir no mapa.</p>
        <p class="max-w-xs text-xs text-text-muted">Informe latitude e longitude nos pontos do percurso (ou nos municípios) para ver a rota desenhada.</p>
    </div>
@endif
