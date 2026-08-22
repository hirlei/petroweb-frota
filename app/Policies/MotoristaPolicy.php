<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Motorista;
use App\Models\User;
use App\Support\TenantContext;

/**
 * Autorização dos motoristas. Permissão aqui; isolamento por empresa no
 * EmpresaScope. `mesmaEmpresa()` cobre o caso do registro que chegou por fora
 * do escopo (job, console, suporte).
 */
class MotoristaPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can('motorista.consultar');
    }

    public function view(User $usuario, Motorista $motorista): bool
    {
        return $usuario->can('motorista.consultar') && $this->mesmaEmpresa($usuario, $motorista);
    }

    public function create(User $usuario): bool
    {
        return $usuario->can('motorista.gerenciar');
    }

    public function update(User $usuario, Motorista $motorista): bool
    {
        return $usuario->can('motorista.gerenciar') && $this->mesmaEmpresa($usuario, $motorista);
    }

    public function delete(User $usuario, Motorista $motorista): bool
    {
        return $usuario->can('motorista.gerenciar') && $this->mesmaEmpresa($usuario, $motorista);
    }

    private function mesmaEmpresa(User $usuario, Motorista $motorista): bool
    {
        $empresaAtual = TenantContext::empresaId();

        if ($empresaAtual === null) {
            return true;
        }

        return $motorista->empresa_id === $empresaAtual;
    }
}
