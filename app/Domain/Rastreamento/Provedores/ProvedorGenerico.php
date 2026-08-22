<?php

declare(strict_types=1);

namespace App\Domain\Rastreamento\Provedores;

use App\Domain\Rastreamento\ProvedorRastreamento;

/**
 * Provedor genérico — aceita o formato canônico do próprio PetroWeb Frota.
 *
 * Serve para testes de integração e para provedores que aceitem postar já no
 * nosso formato. Payload esperado:
 *   { "posicoes": [ { "placa": "OKZ1A34", "latitude": -12.26, "longitude": -38.96,
 *     "velocidade_kmh": 78, "rumo": 210, "ignicao": true,
 *     "capturado_em": "2026-08-22T15:30:00-03:00" }, ... ] }
 * Também aceita uma lista pura de posições no lugar de { "posicoes": [...] }.
 */
class ProvedorGenerico implements ProvedorRastreamento
{
    public function nome(): string
    {
        return 'generico';
    }

    public function normalizar(array $payload): array
    {
        $lista = $payload['posicoes'] ?? $payload;

        if (! is_array($lista) || array_is_list($lista) === false && ! isset($lista[0])) {
            // objeto único vira lista de um.
            $lista = isset($lista['latitude']) ? [$lista] : [];
        }

        $posicoes = [];

        foreach ($lista as $p) {
            if (! is_array($p) || ! isset($p['placa'], $p['latitude'], $p['longitude'])) {
                continue;
            }

            $posicoes[] = [
                'placa' => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $p['placa'])),
                'latitude' => (float) $p['latitude'],
                'longitude' => (float) $p['longitude'],
                'velocidade_kmh' => isset($p['velocidade_kmh']) ? (float) $p['velocidade_kmh'] : (isset($p['velocidade']) ? (float) $p['velocidade'] : null),
                'rumo' => isset($p['rumo']) ? (int) $p['rumo'] : null,
                'ignicao' => isset($p['ignicao']) ? (bool) $p['ignicao'] : null,
                'capturado_em' => isset($p['capturado_em']) ? (string) $p['capturado_em'] : null,
                'bruto' => $p,
            ];
        }

        return $posicoes;
    }
}
