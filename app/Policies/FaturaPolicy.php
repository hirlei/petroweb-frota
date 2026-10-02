<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Fatura;
use App\Models\User;
use App\Support\TenantContext;

/**
 * Faturas (rotina 5010). `fatura.consultar` vê; `fatura.gerenciar` gera e
 * cancela. Isolamento por empresa no EmpresaScope, com `mesmaEmpresa()` para
 * o registro fora de escopo.
 */
class FaturaPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can('fatura.consultar');
    }

    public function view(User $usuario, Fatura $fatura): bool
    {
        return $usuario->can('fatura.consultar') && $this->mesmaEmpresa($fatura);
    }

    public function create(User $usuario): bool
    {
        return $usuario->can('fatura.gerenciar');
    }

    public function cancelar(User $usuario, Fatura $fatura): bool
    {
        return $usuario->can('fatura.gerenciar') && $this->mesmaEmpresa($fatura);
    }

    private function mesmaEmpresa(Fatura $fatura): bool
    {
        $empresaAtual = TenantContext::empresaId();

        return $empresaAtual === null || $fatura->empresa_id === $empresaAtual;
    }
}
