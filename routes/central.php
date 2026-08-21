<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas CENTRAIS — painel do provedor
|--------------------------------------------------------------------------
| Respondem nos domínios de config('tenancy.central_domains'), no banco
| central. É daqui que a HALC cria tenants, define licença e dá suporte.
|
| NUNCA misture com as rotas do tenant: um bug aqui vaza dado entre clientes.
*/

foreach (config('tenancy.central_domains') as $dominio) {
    Route::domain($dominio)->middleware('central')->group(function (): void {
        Route::view('/', 'central.entrada')->name('central.entrada');
    });
}
