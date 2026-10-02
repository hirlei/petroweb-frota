<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TituloReceber;
use App\Models\User;
use App\Support\TenantContext;

/**
 * Contas a receber (rotina 5020). Ver exige `fatura.consultar`; registrar e
 * estornar recebimento exigem `recebimento.registrar`.
 */
class TituloReceberPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can('fatura.consultar');
    }

    public function receber(User $usuario, TituloReceber $titulo): bool
    {
        return $usuario->can('recebimento.registrar') && $this->mesmaEmpresa($titulo);
    }

    public function estornar(User $usuario, TituloReceber $titulo): bool
    {
        return $usuario->can('recebimento.registrar') && $this->mesmaEmpresa($titulo);
    }

    private function mesmaEmpresa(TituloReceber $titulo): bool
    {
        $empresaAtual = TenantContext::empresaId();

        return $empresaAtual === null || $titulo->empresa_id === $empresaAtual;
    }
}
