<?php

declare(strict_types=1);

namespace App\Services\Financeiro;

use RuntimeException;

/** Regra de faturamento violada — a mensagem vai direto para a tela. */
final class FaturamentoException extends RuntimeException
{
}
