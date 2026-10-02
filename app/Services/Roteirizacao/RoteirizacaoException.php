<?php

declare(strict_types=1);

namespace App\Services\Roteirizacao;

use RuntimeException;

/** Falha ao calcular o traçado (sem chave, sem rota possível, provedor fora do ar…). */
class RoteirizacaoException extends RuntimeException
{
}
