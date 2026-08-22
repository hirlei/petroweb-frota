<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Mercadoria;
use App\Models\User;
use App\Support\TenantContext;

/**
 * Autorização do catálogo de mercadorias. Permissão `produto.*`; isolamento por
 * empresa no EmpresaScope, com `mesmaEmpresa()` para registros fora de escopo.
 */
class MercadoriaPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can('produto.consultar');
    }

    public function view(User $usuario, Mercadoria $mercadoria): bool
    {
        return $usuario->can('produto.consultar') && $this->mesmaEmpresa($usuario, $mercadoria);
    }

    public function create(User $usuario): bool
    {
        return $usuario->can('produto.gerenciar');
    }

    public function update(User $usuario, Mercadoria $mercadoria): bool
    {
        return $usuario->can('produto.gerenciar') && $this->mesmaEmpresa($usuario, $mercadoria);
    }

    public function delete(User $usuario, Mercadoria $mercadoria): bool
    {
        return $usuario->can('produto.gerenciar') && $this->mesmaEmpresa($usuario, $mercadoria);
    }

    private function mesmaEmpresa(User $usuario, Mercadoria $mercadoria): bool
    {
        $empresaAtual = TenantContext::empresaId();

        if ($empresaAtual === null) {
            return true;
        }

        return $mercadoria->empresa_id === $empresaAtual;
    }
}
