<?php

declare(strict_types=1);

namespace App\Services\Roteirizacao;

/**
 * Motor de rotas: recebe os pontos na ordem e devolve o traçado pela estrada.
 *
 * A implementação concreta (OpenRouteService, OSRM…) fica atrás desta interface,
 * então trocar de motor não toca em Rota/Viagem. Coordenadas sempre em [lat,lng].
 */
interface Roteirizador
{
    /**
     * @param  list<array{0:float,1:float}>  $coordenadas  pontos [lat,lng] na ordem
     * @return array{pontos:list<array{0:float,1:float}>,distancia_km:float,duracao_min:int}
     *
     * @throws RoteirizacaoException quando não é possível calcular o traçado
     */
    public function rotear(array $coordenadas): array;
}
