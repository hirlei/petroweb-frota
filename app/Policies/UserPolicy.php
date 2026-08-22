<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Support\TenantContext;

/**
 * Autorização de usuários. Gerir usuários é uma permissão sensível
 * (`usuario.gerenciar`); o isolamento por empresa é conferido aqui porque o
 * model User não usa o EmpresaScope (é Authenticatable, não model de negócio).
 */
class UserPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can('usuario.gerenciar');
    }

    public function view(User $usuario, User $alvo): bool
    {
        return $usuario->can('usuario.gerenciar') && $this->mesmaEmpresa($usuario, $alvo);
    }

    public function create(User $usuario): bool
    {
        return $usuario->can('usuario.gerenciar');
    }

    public function update(User $usuario, User $alvo): bool
    {
        return $usuario->can('usuario.gerenciar') && $this->mesmaEmpresa($usuario, $alvo);
    }

    /**
     * Ninguém se exclui — some do próprio caminho de gestão. Desativar é a via;
     * a exclusão real não é oferecida.
     */
    public function delete(User $usuario, User $alvo): bool
    {
        return $usuario->can('usuario.gerenciar')
            && $usuario->id !== $alvo->id
            && $this->mesmaEmpresa($usuario, $alvo);
    }

    private function mesmaEmpresa(User $usuario, User $alvo): bool
    {
        $empresaAtual = TenantContext::empresaId();

        if ($empresaAtual === null) {
            return true;
        }

        return $alvo->empresa_id === $empresaAtual;
    }
}
