<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use RuntimeException;

/** Regra da CC-e violada antes de transmitir. `erros` = por linha da tela. */
final class CartaCorrecaoException extends RuntimeException
{
    /** @param array<int|string,string> $erros */
    public function __construct(string $mensagem, public readonly array $erros = [])
    {
        parent::__construct($mensagem);
    }
}
