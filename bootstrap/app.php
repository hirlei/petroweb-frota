<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\Middleware\StartSession;
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
             *    da rota "/" genérica do tenant.
             */
            require base_path('routes/central.php');

            /*
             * 2) Todo o resto é tenant, resolvido pelo subdomínio. O grupo
             *    `web` (sessão, cookie, CSRF) é aplicado aqui; as próprias
             *    rotas acrescentam o grupo `tenant`.
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

        /*
         * CRÍTICO. A tenancy PRECISA inicializar ANTES do StartSession.
         *
         * Com SESSION_DRIVER e a autenticação em banco, se o StartSession (e o
         * guard de auth) rodar primeiro, ele lê `users` e `sessions` no banco
         * CENTRAL — que não tem essas tabelas do tenant — e a tela dá 500 com
         * "relation users does not exist" logo após o login.
         *
         * prependToPriorityList põe o InitializeTenancyByDomain na lista de
         * prioridade logo antes do StartSession, garantindo que o banco do
         * tenant já esteja ativo quando a sessão e o guard forem lidos.
         */
        $middleware->prependToPriorityList(
            before: StartSession::class,
            prepend: InitializeTenancyByDomain::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
