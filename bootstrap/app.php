<?php

declare(strict_types=1);

use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
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
             * 2) Todo o resto é tenant, resolvido pelo subdomínio. As próprias
             *    rotas carregam o grupo `tenant`, que JÁ inclui a pilha de
             *    sessão/cookie/CSRF na ordem certa (ver withMiddleware abaixo).
             *
             *    NÃO envolvemos mais em `Route::middleware('web')`: o grupo
             *    `web` colocava o StartSession ANTES da tenancy em algumas
             *    rotas (notadamente as de `Route::view`), gravando a sessão no
             *    banco CENTRAL no GET e lendo do TENANT no POST — o que causava
             *    o 419 Page Expired no login.
             */
            require base_path('routes/web.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Grupo do TENANT com a pilha COMPLETA e explícita.
         *
         * A tenancy é o PRIMEIRO middleware — antes de qualquer coisa que toque
         * a sessão. Assim o StartSession sempre encontra o banco do tenant já
         * ativo, e a sessão (com o token CSRF) é gravada e lida SEMPRE no mesmo
         * banco, tanto no GET quanto no POST. Era essa divisão central/tenant
         * que derrubava o login com 419.
         *
         * A ordem dos 6 middlewares seguintes é a mesma do grupo `web` padrão
         * do Laravel 12 — só antecedida pela tenancy.
         */
        $middleware->group('tenant', [
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            ValidateCsrfToken::class,
            SubstituteBindings::class,
        ]);

        // Painel do provedor: só o grupo `web` padrão, nenhuma tenancy.
        $middleware->group('central', ['web']);

        /*
         * API do tenant (máquina-a-máquina): resolve o tenant pelo subdomínio,
         * SEM sessão, cookies ou CSRF. É o que o webhook de rastreamento usa —
         * um provedor de GPS que POSTa posições não tem token CSRF; a
         * autenticação é por token no header, verificada no controller.
         */
        $middleware->group('tenant-api', [
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            SubstituteBindings::class,
        ]);

        /*
         * Cinto e suspensório. Mesmo com a ordem explícita acima, garantimos na
         * lista de prioridade que o InitializeTenancyByDomain fique ANTES do
         * StartSession — assim a ordenação interna do Laravel nunca o move para
         * depois da sessão.
         */
        $middleware->prependToPriorityList(
            before: StartSession::class,
            prepend: InitializeTenancyByDomain::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
