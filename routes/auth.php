<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

/*
| Autenticação. O 2FA é exigido de quem tem permissão fiscal ou financeira —
| ver App\Models\User::exige2fa(). A verificação é da permissão, não do papel.
*/

Route::middleware(['tenant', 'guest'])->group(function (): void {
    // GET e POST no MESMO controller: garante que ambos passem pela mesma
    // pilha de middleware, na mesma ordem (tenancy antes do StartSession), e a
    // sessão fique sempre no banco do tenant. Ver LoginController::mostrar().
    Route::get('login', [LoginController::class, 'mostrar'])->name('login');
    Route::post('login', [LoginController::class, 'autenticar']);
});

Route::middleware(['tenant', 'auth'])->group(function (): void {
    Route::post('logout', [LoginController::class, 'sair'])->name('logout');
});
