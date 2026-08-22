<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Despesa;
use App\Models\User;
use App\Support\TenantContext;

/**
 * Autorização das despesas de viagem. Permissão `despesa-viagem.*`; isolamento
 * por empresa no EmpresaScope, com `mesmaEmpresa()` para o registro fora de
 * escopo. A aprovação usa a mesma permissão de gerência.
 */
class DespesaPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can('despesa-viagem.consultar');
    }

    public function view(User $usuario, Despesa $despesa): bool
    {
        return $usuario->can('despesa-viagem.consultar') && $this->mesmaEmpresa($usuario, $despesa);
    }

    public function create(User $usuario): bool
    {
        return $usuario->can('despesa-viagem.gerenciar');
    }

    public function update(User $usuario, Despesa $despesa): bool
    {
        return $usuario->can('despesa-viagem.gerenciar') && $this->mesmaEmpresa($usuario, $despesa);
    }

    public function delete(User $usuario, Despesa $despesa): bool
    {
        return $usuario->can('despesa-viagem.gerenciar') && $this->mesmaEmpresa($usuario, $despesa);
    }

    private function mesmaEmpresa(User $usuario, Despesa $despesa): bool
    {
        $empresaAtual = TenantContext::empresaId();

        if ($empresaAtual === null) {
            return true;
        }

        return $despesa->empresa_id === $empresaAtual;
    }
}
