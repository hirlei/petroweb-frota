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
| Registrado ANTES das rotas de tenant (ver bootstrap/app.php) para que o
| domínio raiz não caia na resolução de tenant.
*/

foreach (config('tenancy.central_domains') as $indice => $dominio) {
    Route::domain($dominio)->middleware('central')->group(function () use ($indice): void {
        // O nome precisa ser ÚNICO por domínio, senão route:cache estoura com
        // "Another route has already been assigned name [central.entrada]".
        // Só o primeiro leva o nome canônico, que é o que templates referenciam.
        $nome = $indice === 0 ? 'central.entrada' : "central.entrada.{$indice}";

        Route::view('/', 'central.entrada')->name($nome);
    });
}
