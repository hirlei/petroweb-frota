<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Para tabelas-filhas que não carregam `empresa_id` próprio — itens de uma
 * ordem de coleta, componentes de um CT-e, documentos de um MDF-e. Filtra
 * pelo pai, que tem o discriminador.
 *
 * Espelha o EmpresaViaPaiScope do PetroWeb. Use com parcimônia: a regra deste
 * projeto é `empresa_id` em toda tabela de negócio. Este scope existe para as
 * poucas tabelas em que a coluna seria redundância pura.
 *
 * Bypass: Model::withoutGlobalScope(EmpresaViaPaiScope::class)
 */
final class EmpresaViaPaiScope implements Scope
{
    public function __construct(
        private readonly string $relacao,
    ) {}

    public function apply(Builder $builder, Model $model): void
    {
        $empresaId = TenantContext::empresaId();

        if ($empresaId === null) {
            return;
        }

        $builder->whereHas(
            $this->relacao,
            fn (Builder $q) => $q->where('empresa_id', $empresaId),
        );
    }
}
