<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Frota\CatalogoPadrao;
use App\Models\Carroceria;
use App\Models\CvcConfiguracao;
use App\Models\NaturezaCargaTabela;
use Illuminate\Database\Seeder;

/**
 * Catálogo PADRÃO do sistema — `empresa_id` nulo, visível a todas as empresas
 * do tenant. O cliente não edita estes; cria os dele por cima.
 *
 * Os dados vivem em `App\Domain\Frota\CatalogoPadrao`, fora do Eloquent, para
 * serem conferidos por teste antes de virarem linha no banco. Este seeder só
 * transporta.
 *
 * Idempotente: pode rodar em `tenants:seed` quantas vezes for preciso.
 */
class TabelasDominioFrotaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CatalogoPadrao::naturezas() as $natureza) {
            NaturezaCargaTabela::withoutGlobalScopes()->updateOrCreate(
                ['empresa_id' => null, 'codigo' => $natureza['codigo']],
                $natureza + ['ativo' => true],
            );
        }

        foreach (CatalogoPadrao::carrocerias() as $carroceria) {
            Carroceria::withoutGlobalScopes()->updateOrCreate(
                ['empresa_id' => null, 'codigo' => $carroceria['codigo']],
                $carroceria + ['ativo' => true],
            );
        }

        foreach (CatalogoPadrao::combinacoes() as $cvc) {
            CvcConfiguracao::withoutGlobalScopes()->updateOrCreate(
                ['empresa_id' => null, 'slug' => $cvc['slug']],
                $cvc + ['ativo' => true],
            );
        }
    }
}
