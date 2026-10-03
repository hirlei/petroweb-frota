<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Acerto de viagem (rotina 3070). `acerto.consultar` vê; `acerto.gerenciar`
 * lança adiantamento, confere despesa, fecha, reabre e registra devolução.
 */
class AcertoViagemPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can('acerto.consultar');
    }

    public function gerenciar(User $usuario): bool
    {
        return $usuario->can('acerto.gerenciar');
    }
}
