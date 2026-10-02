<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Entrega;
use App\Models\User;
use App\Support\TenantContext;

/**
 * Autorização das entregas (POD). Permissão `entrega.*`; isolamento por empresa
 * no EmpresaScope, com `mesmaEmpresa()` para o registro fora de escopo.
 */
class EntregaPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can('entrega.consultar');
    }

    public function view(User $usuario, Entrega $entrega): bool
    {
        return $usuario->can('entrega.consultar') && $this->mesmaEmpresa($usuario, $entrega);
    }

    public function create(User $usuario): bool
    {
        return $usuario->can('entrega.gerenciar');
    }

    public function update(User $usuario, Entrega $entrega): bool
    {
        return $usuario->can('entrega.gerenciar') && $this->mesmaEmpresa($usuario, $entrega);
    }

    public function delete(User $usuario, Entrega $entrega): bool
    {
        return $usuario->can('entrega.gerenciar') && $this->mesmaEmpresa($usuario, $entrega);
    }

    private function mesmaEmpresa(User $usuario, Entrega $entrega): bool
    {
        $empresaAtual = TenantContext::empresaId();

        if ($empresaAtual === null) {
            return true;
        }

        return $entrega->empresa_id === $empresaAtual;
    }
}
