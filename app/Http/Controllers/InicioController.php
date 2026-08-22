<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Tela inicial do tenant.
 *
 * É um controller de propósito, NÃO um `Route::view()`: rotas de view não
 * recebem a mesma ordenação de middleware que rotas de controller, e a tenancy
 * inicializava DEPOIS do StartSession — a sessão caía no banco central e o
 * guard de auth via o usuário como deslogado, criando laço com o /login
 * (ERR_TOO_MANY_REDIRECTS). Com controller, a sessão vive sempre no tenant.
 */
class InicioController extends Controller
{
    public function __invoke(): View
    {
        return view('inicio');
    }
}
