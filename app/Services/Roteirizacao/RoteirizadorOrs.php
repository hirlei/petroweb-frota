<?php

declare(strict_types=1);

namespace App\Services\Roteirizacao;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Roteirizador via OpenRouteService.
 *
 * Usa o perfil driving-hgv (veículo de carga). O ORS trabalha em [lng,lat];
 * convertemos na entrada e na saída para manter [lat,lng] em todo o sistema.
 * A chamada externa é rápida e sob demanda (botão "calcular traçado"), e o
 * resultado é guardado na rota — não recalcula a cada abertura do mapa.
 */
class RoteirizadorOrs implements Roteirizador
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $chave,
        private readonly string $perfil,
        private readonly int $timeout,
    ) {
    }

    public function rotear(array $coordenadas): array
    {
        if ($this->chave === null || $this->chave === '') {
            throw new RoteirizacaoException('Chave do OpenRouteService não configurada (OPENROUTESERVICE_KEY).');
        }

        $validas = array_values(array_filter(
            $coordenadas,
            fn ($c) => isset($c[0], $c[1]) && is_numeric($c[0]) && is_numeric($c[1]),
        ));

        if (count($validas) < 2) {
            throw new RoteirizacaoException('São necessários ao menos dois pontos com coordenada para traçar a rota.');
        }

        // ORS espera [lng,lat].
        $coordsOrs = array_map(fn ($c) => [(float) $c[1], (float) $c[0]], $validas);

        try {
            $resposta = Http::timeout($this->timeout)
                ->withHeaders(['Authorization' => $this->chave])
                ->post("{$this->baseUrl}/v2/directions/{$this->perfil}/geojson", [
                    'coordinates' => $coordsOrs,
                ]);
        } catch (Throwable $e) {
            throw new RoteirizacaoException('Não foi possível falar com o OpenRouteService: ' . $e->getMessage(), 0, $e);
        }

        if (! $resposta->successful()) {
            throw new RoteirizacaoException('OpenRouteService respondeu ' . $resposta->status() . '.');
        }

        $feature = $resposta->json('features.0');

        if (! is_array($feature) || ! isset($feature['geometry']['coordinates'])) {
            throw new RoteirizacaoException('Resposta do OpenRouteService sem geometria.');
        }

        // Geometria volta em [lng,lat] — convertemos para [lat,lng].
        $pontos = array_map(
            fn ($c) => [(float) $c[1], (float) $c[0]],
            $feature['geometry']['coordinates'],
        );

        $resumo = $feature['properties']['summary'] ?? [];
        $distanciaM = (float) ($resumo['distance'] ?? 0);
        $duracaoS = (float) ($resumo['duration'] ?? 0);

        return [
            'pontos' => $pontos,
            'distancia_km' => round($distanciaM / 1000, 2),
            'duracao_min' => (int) round($duracaoS / 60),
        ];
    }
}
