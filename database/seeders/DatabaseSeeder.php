<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Seeder DO TENANT — é este que o `tenants:seed` e o job de criação de tenant
 * chamam, já dentro do banco `frota_<slug>`.
 *
 * Roda SEM escopo de empresa: o catálogo padrão nasce com `empresa_id` nulo,
 * e qualquer contexto ativo faria o `PertenceAEmpresa` carimbar a empresa,
 * transformando o padrão do sistema em registro privado de um cliente.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        TenantContext::semEscopo(function (): void {
            $this->call([
                TabelasDominioFrotaSeeder::class,
            ]);
        });
    }
}
