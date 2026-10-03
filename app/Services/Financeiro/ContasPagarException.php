<?php

declare(strict_types=1);

namespace App\Services\Financeiro;

use RuntimeException;

/** Regra do contas a pagar não atendida — a mensagem vai direto para a tela. */
final class ContasPagarException extends RuntimeException
{
}
