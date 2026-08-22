<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Veiculo;
use App\Support\TenantContext;

/**
 * Autorização da frota. A policy trata de PERMISSÃO; o isolamento por empresa
 * é do EmpresaScope. `mesmaEmpresa()` fecha o caso em que o registro chegou
 * por fora do escopo (job, console, suporte).
 */
class VeiculoPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can('veiculo.consultar');
    }

    public function view(User $usuario, Veiculo $veiculo): bool
    {
        return $usuario->can('veiculo.consultar') && $this->mesmaEmpresa($usuario, $veiculo);
    }

    public function create(User $usuario): bool
    {
        return $usuario->can('veiculo.gerenciar');
    }

    public function update(User $usuario, Veiculo $veiculo): bool
    {
        return $usuario->can('veiculo.gerenciar') && $this->mesmaEmpresa($usuario, $veiculo);
    }

    /**
     * Veículo com histórico fiscal não some — vira inativo. A exclusão real
     * nunca é oferecida; o soft delete existe para isso.
     */
    public function delete(User $usuario, Veiculo $veiculo): bool
    {
        return $usuario->can('veiculo.gerenciar') && $this->mesmaEmpresa($usuario, $veiculo);
    }

    private function mesmaEmpresa(User $usuario, Veiculo $veiculo): bool
    {
        $empresaAtual = TenantContext::empresaId();

        if ($empresaAtual === null) {
            return true;
        }

        return $veiculo->empresa_id === $empresaAtual;
    }
}
