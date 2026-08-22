<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Ocorrencia;
use App\Models\User;
use App\Support\TenantContext;

/**
 * Autorização das ocorrências. Permissão `ocorrencia.*`; isolamento por empresa
 * no EmpresaScope, com `mesmaEmpresa()` para registros fora de escopo.
 */
class OcorrenciaPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can('ocorrencia.consultar');
    }

    public function view(User $usuario, Ocorrencia $ocorrencia): bool
    {
        return $usuario->can('ocorrencia.consultar') && $this->mesmaEmpresa($usuario, $ocorrencia);
    }

    public function create(User $usuario): bool
    {
        return $usuario->can('ocorrencia.gerenciar');
    }

    public function update(User $usuario, Ocorrencia $ocorrencia): bool
    {
        return $usuario->can('ocorrencia.gerenciar') && $this->mesmaEmpresa($usuario, $ocorrencia);
    }

    public function delete(User $usuario, Ocorrencia $ocorrencia): bool
    {
        return $usuario->can('ocorrencia.gerenciar') && $this->mesmaEmpresa($usuario, $ocorrencia);
    }

    private function mesmaEmpresa(User $usuario, Ocorrencia $ocorrencia): bool
    {
        $empresaAtual = TenantContext::empresaId();

        if ($empresaAtual === null) {
            return true;
        }

        return $ocorrencia->empresa_id === $empresaAtual;
    }
}
