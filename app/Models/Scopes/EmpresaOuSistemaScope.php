<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Escopo das TABELAS DE DOMÍNIO (carrocerias, naturezas de carga, CVC).
 *
 * Diferente do EmpresaScope: aqui `empresa_id IS NULL` significa "registro
 * padrão do sistema", visível a todas as empresas do tenant. A empresa vê o
 * catálogo do sistema mais o que ela mesma criou — nunca o catálogo de outra.
 *
 * O seed do sistema roda com `TenantContext::semEscopo()`, senão o
 * PertenceAEmpresa carimbaria `empresa_id` e o padrão viraria privado.
 */
final class EmpresaOuSistemaScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $empresaId = TenantContext::empresaId();

        if ($empresaId === null) {
            return;
        }

        $coluna = $model->getTable() . '.empresa_id';

        $builder->where(
            fn (Builder $q) => $q->whereNull($coluna)->orWhere($coluna, $empresaId)
        );
    }
}
