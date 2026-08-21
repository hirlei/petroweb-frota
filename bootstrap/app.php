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
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function (): void {
            /*
             * Rotas do painel do provedor. Ficam FORA do arquivo do tenant de
             * propósito: um require no meio de web.php faria as duas árvores
             * compartilharem middleware sem ninguém perceber.
             */
            Route::group([], base_path('routes/central.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Grupo `tenant`: resolve o cliente pelo subdomínio e recusa o acesso
         * se a requisição veio de um domínio central.
         *
         * NÃO inclui `web` aqui: routes/web.php já roda dentro do grupo web
         * por conta do `withRouting(web: …)` acima. Repetir o grupo faria o
         * StartSession rodar duas vezes na mesma requisição — sessão que
         * grava e é sobrescrita é o tipo de bug que só aparece em produção,
         * de forma intermitente.
         */
        $middleware->group('tenant', [
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
        ]);

        // O painel do provedor responde nos domínios centrais e não inicializa
        // tenant nenhum — é o único lugar que enxerga o banco central.
        $middleware->group('central', ['web']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
