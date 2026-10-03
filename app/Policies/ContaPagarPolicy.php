<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ContaPagar;
use App\Models\User;
use App\Support\TenantContext;

/**
 * Contas a pagar (rotina 5030). `conta-pagar.consultar` vê; `conta-pagar.gerenciar`
 * lança e cancela; `pagamento.registrar` paga e estorna.
 */
class ContaPagarPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can('conta-pagar.consultar');
    }

    public function create(User $usuario): bool
    {
        return $usuario->can('conta-pagar.gerenciar');
    }

    public function cancelar(User $usuario, ContaPagar $conta): bool
    {
        return $usuario->can('conta-pagar.gerenciar') && $this->mesmaEmpresa($conta);
    }

    public function pagar(User $usuario, ContaPagar $conta): bool
    {
        return $usuario->can('pagamento.registrar') && $this->mesmaEmpresa($conta);
    }

    public function estornar(User $usuario, ContaPagar $conta): bool
    {
        return $usuario->can('pagamento.registrar') && $this->mesmaEmpresa($conta);
    }

    private function mesmaEmpresa(ContaPagar $conta): bool
    {
        $empresaAtual = TenantContext::empresaId();

        return $empresaAtual === null || $conta->empresa_id === $empresaAtual;
    }
}
