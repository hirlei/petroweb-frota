<?php

declare(strict_types=1);

namespace App\Services\Fiscal\Ciot;

use RuntimeException;

/** Regra de CIOT ou de vale-pedágio não atendida — a mensagem vai direto para a tela. */
final class CiotException extends RuntimeException
{
}
