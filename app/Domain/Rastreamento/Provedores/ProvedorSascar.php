<?php

declare(strict_types=1);

namespace App\Domain\Rastreamento\Provedores;

use App\Domain\Rastreamento\ProvedorRastreamento;
use RuntimeException;

/**
 * Adaptador da Sascar (esqueleto).
 *
 * A Sascar integra por "direcionamento de sinal": cadastra-se no portal dela o
 * endpoint do webhook, e ela passa a enviar as posições. Quando a integração for
 * fechada, mapear aqui o payload real dela para a forma canônica — é só preencher
 * `normalizar()`. O resto do sistema (webhook, armazenamento, mapa) já funciona.
 */
class ProvedorSascar implements ProvedorRastreamento
{
    public function nome(): string
    {
        return 'sascar';
    }

    public function normalizar(array $payload): array
    {
        throw new RuntimeException('Adaptador Sascar ainda não implementado — depende do contrato de integração e da documentação do provedor.');
    }
}
