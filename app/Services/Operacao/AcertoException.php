<?php

declare(strict_types=1);

namespace App\Services\Operacao;

use RuntimeException;

/** Regra do acerto de viagem não atendida — a mensagem vai direto para a tela. */
final class AcertoException extends RuntimeException
{
}
