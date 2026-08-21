<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Empresa;
use App\Models\Scopes\EmpresaScope;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Todo model de negócio usa esta trait. Ela faz duas coisas que, juntas,
 * são o isolamento por empresa:
 *
 *   1. aplica o EmpresaScope em toda leitura;
 *   2. preenche `empresa_id` na criação, a partir do contexto.
 *
 * O item 2 é o que evita o bug clássico: um registro criado sem empresa_id
 * fica órfão e passa a aparecer para todo mundo (porque o scope filtra por
 * igualdade, e NULL nunca casa — mas um id errado casa com o tenant errado).
 * Por isso, criar sem contexto e sem empresa_id explícito é ERRO, não default.
 */
trait PertenceAEmpresa
{
    public static function bootPertenceAEmpresa(): void
    {
        static::addGlobalScope(new EmpresaScope());

        static::creating(function ($model): void {
            if ($model->empresa_id !== null) {
                return;
            }

            $empresaId = TenantContext::empresaId();

            if ($empresaId === null) {
                throw new \RuntimeException(sprintf(
                    'Tentativa de criar %s sem empresa_id e sem contexto de empresa. '
                    . 'Informe empresa_id explicitamente ou rode dentro de um contexto.',
                    static::class,
                ));
            }

            $model->empresa_id = $empresaId;
        });
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
