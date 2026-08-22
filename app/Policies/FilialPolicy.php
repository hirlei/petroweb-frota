<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Filial;
use App\Models\User;
use App\Support\TenantContext;

/**
 * Autorização das filiais (emitentes fiscais). Gerir filial é sensível
 * (`filial.gerenciar`): cada filial tem CNPJ, IE, certificado e séries próprias.
 */
class FilialPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can('filial.gerenciar');
    }

    public function view(User $usuario, Filial $filial): bool
    {
        return $usuario->can('filial.gerenciar') && $this->mesmaEmpresa($usuario, $filial);
    }

    public function create(User $usuario): bool
    {
        return $usuario->can('filial.gerenciar');
    }

    public function update(User $usuario, Filial $filial): bool
    {
        return $usuario->can('filial.gerenciar') && $this->mesmaEmpresa($usuario, $filial);
    }

    public function delete(User $usuario, Filial $filial): bool
    {
        // Filial emitente não se apaga — desativa-se. Documento fiscal aponta
        // para ela para sempre.
        return false;
    }

    private function mesmaEmpresa(User $usuario, Filial $filial): bool
    {
        $empresaAtual = TenantContext::empresaId();

        if ($empresaAtual === null) {
            return true;
        }

        return $filial->empresa_id === $empresaAtual;
    }
}
