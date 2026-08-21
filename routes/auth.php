<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

/*
| Autenticação. O 2FA é exigido de quem tem permissão fiscal ou financeira —
| ver App\Models\User::exige2fa(). A verificação é da permissão, não do papel.
*/

Route::middleware('guest')->group(function (): void {
    Route::view('login', 'auth.login')->name('login');
    Route::post('login', [LoginController::class, 'autenticar']);
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [LoginController::class, 'sair'])->name('logout');
});
