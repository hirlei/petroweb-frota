<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Cte;
use App\Models\User;
use App\Support\TenantContext;

/**
 * Autorização do CT-e. Consultar, emitir, cancelar e inutilizar têm permissões
 * próprias — emitir/cancelar exigem 2FA (ver PermissoesSeeder::EXIGEM_2FA).
 */
class CtePolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can('cte.consultar');
    }

    public function view(User $usuario, Cte $cte): bool
    {
        return $usuario->can('cte.consultar') && $this->mesmaEmpresa($usuario, $cte);
    }

    public function create(User $usuario): bool
    {
        return $usuario->can('cte.emitir');
    }

    public function update(User $usuario, Cte $cte): bool
    {
        return $usuario->can('cte.emitir') && $this->mesmaEmpresa($usuario, $cte);
    }

    public function emitir(User $usuario, Cte $cte): bool
    {
        return $usuario->can('cte.emitir') && $this->mesmaEmpresa($usuario, $cte);
    }

    public function cancelar(User $usuario, Cte $cte): bool
    {
        return $usuario->can('cte.cancelar') && $this->mesmaEmpresa($usuario, $cte);
    }

    /** Carta de correção (110110). */
    public function corrigir(User $usuario, Cte $cte): bool
    {
        return $usuario->can('cte.corrigir') && $this->mesmaEmpresa($usuario, $cte);
    }

    private function mesmaEmpresa(User $usuario, Cte $cte): bool
    {
        $empresaAtual = TenantContext::empresaId();

        return $empresaAtual === null || $cte->empresa_id === $empresaAtual;
    }
}
