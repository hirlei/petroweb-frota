<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OrdemColeta;
use App\Models\User;
use App\Support\TenantContext;

/**
 * Autorização das ordens de coleta. Permissão `ordem-coleta.*`; isolamento por
 * empresa no EmpresaScope, com `mesmaEmpresa()` para o registro fora de escopo.
 */
class OrdemColetaPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can('ordem-coleta.consultar');
    }

    public function view(User $usuario, OrdemColeta $ordem): bool
    {
        return $usuario->can('ordem-coleta.consultar') && $this->mesmaEmpresa($usuario, $ordem);
    }

    public function create(User $usuario): bool
    {
        return $usuario->can('ordem-coleta.gerenciar');
    }

    public function update(User $usuario, OrdemColeta $ordem): bool
    {
        return $usuario->can('ordem-coleta.gerenciar') && $this->mesmaEmpresa($usuario, $ordem);
    }

    public function delete(User $usuario, OrdemColeta $ordem): bool
    {
        return $usuario->can('ordem-coleta.gerenciar') && $this->mesmaEmpresa($usuario, $ordem);
    }

    private function mesmaEmpresa(User $usuario, OrdemColeta $ordem): bool
    {
        $empresaAtual = TenantContext::empresaId();

        if ($empresaAtual === null) {
            return true;
        }

        return $ordem->empresa_id === $empresaAtual;
    }
}
