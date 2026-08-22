<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Viagem;
use App\Support\TenantContext;

/**
 * Autorização das viagens. Permissão `viagem.*`; isolamento por empresa no
 * EmpresaScope, com `mesmaEmpresa()` para o registro fora de escopo.
 */
class ViagemPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can('viagem.consultar');
    }

    public function view(User $usuario, Viagem $viagem): bool
    {
        return $usuario->can('viagem.consultar') && $this->mesmaEmpresa($usuario, $viagem);
    }

    public function create(User $usuario): bool
    {
        return $usuario->can('viagem.gerenciar');
    }

    public function update(User $usuario, Viagem $viagem): bool
    {
        return $usuario->can('viagem.gerenciar') && $this->mesmaEmpresa($usuario, $viagem);
    }

    public function delete(User $usuario, Viagem $viagem): bool
    {
        return $usuario->can('viagem.gerenciar') && $this->mesmaEmpresa($usuario, $viagem);
    }

    private function mesmaEmpresa(User $usuario, Viagem $viagem): bool
    {
        $empresaAtual = TenantContext::empresaId();

        if ($empresaAtual === null) {
            return true;
        }

        return $viagem->empresa_id === $empresaAtual;
    }
}
