<?php

declare(strict_types=1);

use App\Models\Tenant;

/**
 * Tenancy do PetroWeb Frota — banco por tenant, no mesmo desenho do PetroWeb.
 *
 * DUAS CAMADAS, e confundi-las custa caro:
 *
 *   Tenant   → um CLIENTE do provedor. Banco próprio (frota_<slug>) e
 *              subdomínio próprio. Isolamento FÍSICO.
 *   Empresa  → uma transportadora dentro do banco do tenant, pelo
 *              discriminador `empresa_id`. Isolamento LÓGICO.
 *
 * Um grupo com três transportadoras é UM tenant com TRÊS empresas — não três
 * tenants. Ver app/Support/TenantContext.php.
 */
return [
    'tenant_model' => Tenant::class,
    'id_generator' => null, // o id é o slug escolhido no painel, não um UUID

    'domain_model' => Stancl\Tenancy\Database\Models\Domain::class,

    /*
     * Domínios que NUNCA são de tenant. O painel do provedor e o domínio
     * institucional respondem no banco central.
     */
    'central_domains' => [
        env('PROVEDOR_DOMAIN', 'admin.frota.petroweb.app'),
        'frota.petroweb.app',
        'localhost',
        '127.0.0.1',
    ],

    'bootstrappers' => [
        Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
    ],

    'database' => [
        'central_connection' => 'central',
        'template_tenant_connection' => null,

        // frota_serraazul, frota_transrocha…
        'prefix' => 'frota_',
        'suffix' => '',

        'managers' => [
            'pgsql' => Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager::class,
        ],
    ],

    'cache' => [
        'tag_base' => 'frota',
    ],

    'filesystem' => [
        'suffix_base' => 'tenant',
        'disks' => [
            'local',
            'public',
        ],
        'root_override' => [
            'local'  => '%storage_path%/app/',
            'public' => '%storage_path%/app/public/',
        ],
        'suffix_storage_path' => true,
        'asset_helper_tenancy' => false,
    ],

    'redis' => [
        'prefix_base' => 'frota_',
        'prefixed_connections' => [],
    ],

    'features' => [
        Stancl\Tenancy\Features\UserImpersonation::class,
    ],

    /*
     * Migrations do tenant ficam separadas das centrais. As centrais rodam com
     * `--path=database/migrations/central --database=central`; as de tenant são
     * aplicadas pelo job de criação e por `tenants:migrate`.
     */
    'migration_parameters' => [
        '--force' => true,
        '--path'  => [database_path('migrations/tenant')],
        '--realpath' => true,
    ],

    'seeder_parameters' => [
        '--class' => 'DatabaseSeeder',
    ],
];
