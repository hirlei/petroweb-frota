<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Mdfe;
use App\Models\User;
use App\Support\TenantContext;

/**
 * Autorização do MDF-e. Emitir e encerrar têm permissões próprias; emitir exige
 * 2FA. Encerramento é o evento 110112.
 */
class MdfePolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can('mdfe.consultar');
    }

    public function view(User $usuario, Mdfe $mdfe): bool
    {
        return $usuario->can('mdfe.consultar') && $this->mesmaEmpresa($usuario, $mdfe);
    }

    public function create(User $usuario): bool
    {
        return $usuario->can('mdfe.emitir');
    }

    public function update(User $usuario, Mdfe $mdfe): bool
    {
        return $usuario->can('mdfe.emitir') && $this->mesmaEmpresa($usuario, $mdfe);
    }

    public function emitir(User $usuario, Mdfe $mdfe): bool
    {
        return $usuario->can('mdfe.emitir') && $this->mesmaEmpresa($usuario, $mdfe);
    }

    public function encerrar(User $usuario, Mdfe $mdfe): bool
    {
        return $usuario->can('mdfe.encerrar') && $this->mesmaEmpresa($usuario, $mdfe);
    }

    private function mesmaEmpresa(User $usuario, Mdfe $mdfe): bool
    {
        $empresaAtual = TenantContext::empresaId();

        return $empresaAtual === null || $mdfe->empresa_id === $empresaAtual;
    }
}
