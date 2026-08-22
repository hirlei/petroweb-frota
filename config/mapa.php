<?php

declare(strict_types=1);

/**
 * Configuração do mapa e da roteirização.
 *
 * Tiles: OSM (mapa) e Esri World Imagery (satélite), ambos gratuitos com
 * atribuição. Roteirização: OpenRouteService (perfil driving-hgv, adequado a
 * caminhão) — precisa de chave gratuita em OPENROUTESERVICE_KEY. Rastreamento:
 * token do webhook que os provedores usam para nos enviar posições.
 */
return [
    'tiles' => [
        'mapa' => [
            'url' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            'atribuicao' => '&copy; OpenStreetMap',
            'max_zoom' => 19,
        ],
        'satelite' => [
            'url' => 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            'atribuicao' => 'Tiles &copy; Esri',
            'max_zoom' => 19,
        ],
    ],

    'roteirizacao' => [
        'driver' => env('ROTEIRIZADOR', 'ors'),
        'ors' => [
            'base_url' => env('OPENROUTESERVICE_URL', 'https://api.openrouteservice.org'),
            'chave' => env('OPENROUTESERVICE_KEY'),
            // driving-hgv = veículo de carga (respeita restrições de caminhão).
            'perfil' => env('OPENROUTESERVICE_PERFIL', 'driving-hgv'),
            'timeout' => 30,
        ],
    ],

    'rastreamento' => [
        // Token que o provedor envia no header X-Rastreamento-Token do webhook.
        'webhook_token' => env('RASTREAMENTO_WEBHOOK_TOKEN'),
    ],
];
