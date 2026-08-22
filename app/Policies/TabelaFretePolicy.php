<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TabelaFrete;
use App\Models\User;
use App\Support\TenantContext;

/**
 * Autorização das tabelas de frete. Permissão `tabela-frete.*`; isolamento por
 * empresa no EmpresaScope, com `mesmaEmpresa()` para o registro fora de escopo.
 */
class TabelaFretePolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can('tabela-frete.consultar');
    }

    public function view(User $usuario, TabelaFrete $tabela): bool
    {
        return $usuario->can('tabela-frete.consultar') && $this->mesmaEmpresa($usuario, $tabela);
    }

    public function create(User $usuario): bool
    {
        return $usuario->can('tabela-frete.gerenciar');
    }

    public function update(User $usuario, TabelaFrete $tabela): bool
    {
        return $usuario->can('tabela-frete.gerenciar') && $this->mesmaEmpresa($usuario, $tabela);
    }

    public function delete(User $usuario, TabelaFrete $tabela): bool
    {
        return $usuario->can('tabela-frete.gerenciar') && $this->mesmaEmpresa($usuario, $tabela);
    }

    private function mesmaEmpresa(User $usuario, TabelaFrete $tabela): bool
    {
        $empresaAtual = TenantContext::empresaId();

        if ($empresaAtual === null) {
            return true;
        }

        return $tabela->empresa_id === $empresaAtual;
    }
}
