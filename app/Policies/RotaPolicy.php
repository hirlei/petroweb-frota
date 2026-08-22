<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Rota;
use App\Models\User;
use App\Support\TenantContext;

/**
 * Autorização das rotas. Permissão `rota.*`; isolamento por empresa no
 * EmpresaScope, com `mesmaEmpresa()` para registros fora de escopo.
 */
class RotaPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can('rota.consultar');
    }

    public function view(User $usuario, Rota $rota): bool
    {
        return $usuario->can('rota.consultar') && $this->mesmaEmpresa($usuario, $rota);
    }

    public function create(User $usuario): bool
    {
        return $usuario->can('rota.gerenciar');
    }

    public function update(User $usuario, Rota $rota): bool
    {
        return $usuario->can('rota.gerenciar') && $this->mesmaEmpresa($usuario, $rota);
    }

    public function delete(User $usuario, Rota $rota): bool
    {
        return $usuario->can('rota.gerenciar') && $this->mesmaEmpresa($usuario, $rota);
    }

    private function mesmaEmpresa(User $usuario, Rota $rota): bool
    {
        $empresaAtual = TenantContext::empresaId();

        if ($empresaAtual === null) {
            return true;
        }

        return $rota->empresa_id === $empresaAtual;
    }
}
