<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Composicao;
use App\Models\User;
use App\Support\TenantContext;

/**
 * Autorização das composições. Usa as permissões da frota (`veiculo.*`), como
 * o config/navegacao.php: montar combinação é uma operação sobre veículos.
 */
class ComposicaoPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can('veiculo.consultar');
    }

    public function view(User $usuario, Composicao $composicao): bool
    {
        return $usuario->can('veiculo.consultar') && $this->mesmaEmpresa($usuario, $composicao);
    }

    public function create(User $usuario): bool
    {
        return $usuario->can('veiculo.gerenciar');
    }

    public function update(User $usuario, Composicao $composicao): bool
    {
        return $usuario->can('veiculo.gerenciar') && $this->mesmaEmpresa($usuario, $composicao);
    }

    public function delete(User $usuario, Composicao $composicao): bool
    {
        return $usuario->can('veiculo.gerenciar') && $this->mesmaEmpresa($usuario, $composicao);
    }

    private function mesmaEmpresa(User $usuario, Composicao $composicao): bool
    {
        $empresaAtual = TenantContext::empresaId();

        if ($empresaAtual === null) {
            return true;
        }

        return $composicao->empresa_id === $empresaAtual;
    }
}
