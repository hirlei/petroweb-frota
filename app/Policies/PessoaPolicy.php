<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Pessoa;
use App\Models\User;
use App\Support\TenantContext;

/**
 * Autorização do cadastro unificado.
 *
 * A policy trata de PERMISSÃO. O isolamento por empresa é do EmpresaScope —
 * são coisas diferentes e confundi-las produz vazamento: um usuário com
 * `pessoa.consultar` continuaria vendo só a empresa dele, e é isso que o
 * método `mesmaEmpresa()` garante para o caso em que o registro chegou por
 * fora do escopo (job, console, suporte).
 */
class PessoaPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can('pessoa.consultar');
    }

    public function view(User $usuario, Pessoa $pessoa): bool
    {
        return $usuario->can('pessoa.consultar') && $this->mesmaEmpresa($usuario, $pessoa);
    }

    public function create(User $usuario): bool
    {
        return $usuario->can('pessoa.gerenciar');
    }

    public function update(User $usuario, Pessoa $pessoa): bool
    {
        return $usuario->can('pessoa.gerenciar') && $this->mesmaEmpresa($usuario, $pessoa);
    }

    /**
     * Pessoa referenciada por documento fiscal não some — some do cadastro
     * ativo. O soft delete existe para isso; a exclusão real nunca é oferecida.
     */
    public function delete(User $usuario, Pessoa $pessoa): bool
    {
        return $usuario->can('pessoa.gerenciar')
            && $this->mesmaEmpresa($usuario, $pessoa)
            && ! $pessoa->motorista()->exists();
    }

    private function mesmaEmpresa(User $usuario, Pessoa $pessoa): bool
    {
        $empresaAtual = TenantContext::empresaId();

        // Sem empresa derivável, quem responde é o escopo — suporte e console
        // operam assim, e a leitura já é privilegiada por definição.
        if ($empresaAtual === null) {
            return true;
        }

        return (int) $pessoa->empresa_id === $empresaAtual;
    }
}
