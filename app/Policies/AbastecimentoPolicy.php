<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Abastecimento;
use App\Models\User;
use App\Support\TenantContext;

/**
 * Autorização dos abastecimentos. Permissão `abastecimento.*`; isolamento por
 * empresa no EmpresaScope, com `mesmaEmpresa()` para registros fora de escopo.
 */
class AbastecimentoPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can('abastecimento.consultar');
    }

    public function view(User $usuario, Abastecimento $abastecimento): bool
    {
        return $usuario->can('abastecimento.consultar') && $this->mesmaEmpresa($usuario, $abastecimento);
    }

    public function create(User $usuario): bool
    {
        return $usuario->can('abastecimento.gerenciar');
    }

    public function update(User $usuario, Abastecimento $abastecimento): bool
    {
        return $usuario->can('abastecimento.gerenciar') && $this->mesmaEmpresa($usuario, $abastecimento);
    }

    public function delete(User $usuario, Abastecimento $abastecimento): bool
    {
        return $usuario->can('abastecimento.gerenciar') && $this->mesmaEmpresa($usuario, $abastecimento);
    }

    private function mesmaEmpresa(User $usuario, Abastecimento $abastecimento): bool
    {
        $empresaAtual = TenantContext::empresaId();

        if ($empresaAtual === null) {
            return true;
        }

        return $abastecimento->empresa_id === $empresaAtual;
    }
}
