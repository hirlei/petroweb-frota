<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware;

/**
 * Liga os eventos do stancl aos jobs que criam, migram e semeiam o banco do
 * tenant. Sem este provider, criar um Tenant grava uma linha na tabela
 * `tenants` e mais nada — o banco do cliente nunca nasce, e a primeira
 * requisição no subdomínio dele estoura sem explicação.
 *
 * Decisão deliberada: a criação NÃO é enfileirada (`shouldBeQueued(false)`).
 * Criar tenant é ato administrativo, feito por gente da HALC olhando o
 * terminal — falhar na hora, com a mensagem na tela, vale mais do que falhar
 * calado numa fila que ninguém está olhando. Quando houver auto-atendimento,
 * isso muda.
 */
class TenancyServiceProvider extends ServiceProvider
{
    /**
     * @return array<class-string, list<class-string|callable>>
     */
    public static function events(): array
    {
        return [
            Events\TenantCreated::class => [
                JobPipeline::make([
                    Jobs\CreateDatabase::class,
                    Jobs\MigrateDatabase::class,
                    Jobs\SeedDatabase::class,
                ])->send(function (Events\TenantCreated $evento) {
                    return $evento->tenant;
                })->shouldBeQueued(false),
            ],

            Events\TenantDeleted::class => [
                JobPipeline::make([
                    Jobs\DeleteDatabase::class,
                ])->send(function (Events\TenantDeleted $evento) {
                    return $evento->tenant;
                })->shouldBeQueued(false),
            ],

            // Ciclo de vida da requisição — o que troca a conexão, o cache e
            // o disco para os do tenant, e desfaz tudo ao final.
            Events\TenancyInitialized::class => [
                Listeners\BootstrapTenancy::class,
            ],
            Events\TenancyEnded::class => [
                Listeners\RevertToCentralContext::class,
            ],

            Events\TenantMaintenanceModeEnabled::class => [],
            Events\TenantMaintenanceModeDisabled::class => [],
        ];
    }

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registrarEventos();
        $this->registrarAtalhosDeMiddleware();
    }

    private function registrarEventos(): void
    {
        foreach (self::events() as $evento => $ouvintes) {
            foreach ($ouvintes as $ouvinte) {
                if ($ouvinte instanceof JobPipeline) {
                    $ouvinte = $ouvinte->toListener();
                }

                Event::listen($evento, $ouvinte);
            }
        }
    }

    /**
     * Aliases curtos para as rotas. `tenant` resolve o cliente pelo
     * subdomínio; `central` barra acesso vindo de subdomínio de tenant.
     */
    private function registrarAtalhosDeMiddleware(): void
    {
        $router = $this->app['router'];

        $router->aliasMiddleware('tenant.dominio', Middleware\InitializeTenancyByDomain::class);
        $router->aliasMiddleware('tenant.somente', Middleware\PreventAccessFromCentralDomains::class);
    }
}
