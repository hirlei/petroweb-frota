<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OrdemServico;
use App\Models\User;
use App\Support\TenantContext;

/**
 * Autorização das ordens de serviço. Permissão `manutencao.*`; isolamento por
 * empresa no EmpresaScope, com `mesmaEmpresa()` para registros fora de escopo.
 */
class OrdemServicoPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can('manutencao.consultar');
    }

    public function view(User $usuario, OrdemServico $os): bool
    {
        return $usuario->can('manutencao.consultar') && $this->mesmaEmpresa($usuario, $os);
    }

    public function create(User $usuario): bool
    {
        return $usuario->can('manutencao.gerenciar');
    }

    public function update(User $usuario, OrdemServico $os): bool
    {
        return $usuario->can('manutencao.gerenciar') && $this->mesmaEmpresa($usuario, $os);
    }

    public function delete(User $usuario, OrdemServico $os): bool
    {
        return $usuario->can('manutencao.gerenciar') && $this->mesmaEmpresa($usuario, $os);
    }

    private function mesmaEmpresa(User $usuario, OrdemServico $os): bool
    {
        $empresaAtual = TenantContext::empresaId();

        if ($empresaAtual === null) {
            return true;
        }

        return $os->empresa_id === $empresaAtual;
    }
}
