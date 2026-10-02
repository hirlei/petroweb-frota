@props([
    // list<array{lat:float|null,lng:float|null,label:string,tipo?:string,cor?:string}>
    'pontos'    => [],
    // traçado pela estrada: array{pontos:list<[lat,lng]>,...} | list<[lat,lng]> | null
    'geometria' => null,
    // caminhão na posição: array{lat:float,lng:float,popup?:string} | null
    'caminhao'  => null,
    'linha'     => true,   // liga os pontos em reta quando não há geometria
    'altura'    => '360px',
    'id'        => null,
])

@php
    $mapId = $id ?? 'mapa-' . uniqid();

    $temGeometria = is_array($geometria)
        && (isset($geometria['pontos']) ? count($geometria['pontos']) > 1 : count($geometria) > 1);
    $temPontos = collect($pontos)->contains(fn ($p) => ($p['lat'] ?? null) !== null && ($p['lng'] ?? null) !== null);
    $temCaminhao = is_array($caminhao) && ($caminhao['lat'] ?? null) !== null;
    $temAlgo = $temGeometria || $temPontos || $temCaminhao;

    $config = [
        'pontos'    => array_values($pontos),
        'geometria' => $geometria,
        'caminhao'  => $caminhao,
        'linha'     => (bool) $linha,
        'tiles'     => config('mapa.tiles'),
    ];
@endphp

@if ($temAlgo)
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
        <p class="max-w-xs text-xs text-text-muted">Informe latitude e longitude nos pontos e use “Calcular traçado” para desenhar a rota pela estrada.</p>
    </div>
@endif
