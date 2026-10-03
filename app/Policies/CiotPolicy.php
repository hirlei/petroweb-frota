<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Ciot;
use App\Models\User;
use App\Support\TenantContext;

/**
 * CIOT (rotina 4050). `ciot.consultar` vê; `ciot.gerenciar` paga o saldo ao
 * TAC, cancela e reenvia. O REGISTRO acontece na emissão do MDF-e e segue a
 * permissão de emitir MDF-e (MdfePolicy::emitir).
 */
class CiotPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can('ciot.consultar');
    }

    public function view(User $usuario, Ciot $ciot): bool
    {
        return $usuario->can('ciot.consultar') && $this->mesmaEmpresa($ciot);
    }

    public function gerenciar(User $usuario, Ciot $ciot): bool
    {
        return $usuario->can('ciot.gerenciar') && $this->mesmaEmpresa($ciot);
    }

    private function mesmaEmpresa(Ciot $ciot): bool
    {
        $empresaAtual = TenantContext::empresaId();

        return $empresaAtual === null || $ciot->empresa_id === $empresaAtual;
    }
}
