<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        using: function (): void {
            /*
             * ORDEM IMPORTA. As rotas são casadas na ordem de registro.
             *
             * 1) Painel do provedor — domínios centrais EXATOS, sem tenancy.
             *    Registrado PRIMEIRO: para frota.petroweb.app e
             *    admin.frota.petroweb.app, a rota com domínio fixo casa antes
             *    da rota "/" genérica do tenant. Sem isso, o domínio central
             *    caía no InitializeTenancyByDomain e dava
             *    "Tenant could not be identified on domain frota.petroweb.app".
             */
            require base_path('routes/central.php');

            /*
             * 2) Todo o resto é tenant, resolvido pelo subdomínio. O grupo
             *    `web` (sessão, cookie, CSRF) é aplicado aqui; as próprias
             *    rotas acrescentam o grupo `tenant`. Para serraazul.frota…, as
             *    rotas de domínio fixo acima não casam, então cai aqui e o
             *    stancl resolve o tenant pelo subdomínio.
             */
            Route::middleware('web')->group(base_path('routes/web.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Resolve o cliente pelo subdomínio e recusa domínio central.
        $middleware->group('tenant', [
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
        ]);

        // Painel do provedor: só sessão, nenhuma tenancy.
        $middleware->group('central', ['web']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
