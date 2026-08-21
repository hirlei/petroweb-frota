<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas do TENANT
|--------------------------------------------------------------------------
| Respondem no subdomínio do cliente (<slug>.frota.petroweb.app), dentro do
| banco dele. As rotas do painel do provedor ficam em routes/central.php.
|
| Regra de tenancy: nenhuma rota recebe empresa_id como parâmetro. A empresa
| vem do usuário autenticado ou da sessão — ver app/Support/TenantContext.php.
*/

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::view('/', 'inicio')->name('inicio');
});

require __DIR__ . '/auth.php';
