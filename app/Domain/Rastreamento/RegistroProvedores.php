<?php

declare(strict_types=1);

namespace App\Domain\Rastreamento;

use App\Domain\Rastreamento\Provedores\ProvedorGenerico;
use App\Domain\Rastreamento\Provedores\ProvedorSascar;
use InvalidArgumentException;

/**
 * Resolve o adaptador de rastreamento pelo nome do provedor (o slug que vem na
 * URL do webhook). Acrescentar um provedor é registrar uma classe aqui.
 */
class RegistroProvedores
{
    /** @var array<string,class-string<ProvedorRastreamento>> */
    private const PROVEDORES = [
        'generico' => ProvedorGenerico::class,
        'sascar'   => ProvedorSascar::class,
        // 'onixsat' => ProvedorOnixsat::class,
        // 'cobli'   => ProvedorCobli::class,
    ];

    public function resolver(string $nome): ProvedorRastreamento
    {
        $classe = self::PROVEDORES[strtolower(trim($nome))] ?? null;

        if ($classe === null) {
            throw new InvalidArgumentException("Provedor de rastreamento desconhecido: {$nome}");
        }

        return new $classe();
    }

    /** @return list<string> */
    public function disponiveis(): array
    {
        return array_keys(self::PROVEDORES);
    }
}
