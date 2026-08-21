<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filtra toda query pela empresa do ator, via TenantContext.
 *
 * Herdado do EmpresaScope do PetroWeb, com a mesma semântica: empresa nula
 * significa "sem filtro" — leitura privilegiada de console ou suporte.
 *
 * Bypass explícito: Model::withoutGlobalScope(EmpresaScope::class)
 */
final class EmpresaScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $empresaId = TenantContext::empresaId();

        if ($empresaId === null) {
            return;
        }

        $builder->where($model->getTable() . '.empresa_id', $empresaId);
    }
}
