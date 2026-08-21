<?php

declare(strict_types=1);

use App\Livewire\Pessoas;
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

Route::middleware(['tenant', 'auth', 'verified'])->group(function (): void {
    Route::view('/', 'inicio')->name('inicio');

    /*
     * 1010 — Pessoas. O nome da rota é o que o config/navegacao.php procura e
     * o que o Ctrl+K resolve; mudá-lo apaga o item da sidebar.
     */
    Route::get('/pessoas', Pessoas\Index::class)->name('pessoas.index');
    Route::get('/pessoas/nova', Pessoas\Formulario::class)->name('pessoas.criar');
    Route::get('/pessoas/{pessoa}', Pessoas\Formulario::class)->name('pessoas.editar');
});

require __DIR__ . '/auth.php';
