<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Autenticação do tenant.
 *
 * Três decisões que valem comentário:
 *
 * 1. A chave do rate limit inclui o IP E o e-mail. Só e-mail permite que um
 *    atacante tranque a conta de um cliente de fora; só IP não segura
 *    tentativa distribuída contra a mesma conta.
 * 2. Usuário inativo recebe a MESMA mensagem de credencial inválida. Dizer
 *    "sua conta está desativada" confirma que o e-mail existe.
 * 3. Quem tem poder fiscal e não configurou 2FA entra, mas cai na
 *    configuração obrigatória — bloquear o login deixaria o cliente de fora
 *    do próprio sistema sem caminho de volta.
 */
class LoginController extends Controller
{
    private const MAXIMO_TENTATIVAS = 5;
    private const JANELA_SEGUNDOS = 60;

    /**
     * Tela de login. É um método de CONTROLLER de propósito, não um
     * `Route::view()`: a rota de view não recebia o mesmo tratamento de
     * ordenação de middleware que a rota POST, e a tenancy inicializava DEPOIS
     * do StartSession no GET — gravando a sessão (e o token CSRF) no banco
     * CENTRAL, enquanto o POST lia do banco do TENANT. Resultado: 419 Page
     * Expired. Com GET e POST no mesmo controller, os dois passam pela mesma
     * pilha, na mesma ordem, e a sessão vive sempre no banco do tenant.
     */
    public function mostrar(): View
    {
        return view('auth.login');
    }

    public function autenticar(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'email' => ['required', 'string', 'email'],
            'senha' => ['required', 'string'],
        ], [], [
            'email' => 'e-mail',
            'senha' => 'senha',
        ]);

        $chave = $this->chaveThrottle($request, $dados['email']);

        if (RateLimiter::tooManyAttempts($chave, self::MAXIMO_TENTATIVAS)) {
            $segundos = RateLimiter::availableIn($chave);

            throw ValidationException::withMessages([
                'email' => "Tentativas demais. Tente novamente em {$segundos} segundos.",
            ]);
        }

        $autenticou = Auth::attempt(
            ['email' => $dados['email'], 'password' => $dados['senha'], 'ativo' => true],
            $request->boolean('lembrar'),
        );

        if (! $autenticou) {
            RateLimiter::hit($chave, self::JANELA_SEGUNDOS);

            throw ValidationException::withMessages([
                'email' => 'E-mail ou senha incorretos.',
            ]);
        }

        RateLimiter::clear($chave);
        $request->session()->regenerate();

        $usuario = $request->user();

        $aviso2fa = 'Seu perfil emite documento fiscal. Configure a autenticação em duas etapas para liberar essas rotinas.';

        if ($usuario->exige2fa() && ! $usuario->tem2faAtivo()) {
            // A tela de configuração é do sprint de segurança. Enquanto ela
            // não existe, o aviso aparece no início — nunca em silêncio.
            return Route::has('2fa.configurar')
                ? redirect()->route('2fa.configurar')->with('aviso', $aviso2fa)
                : redirect()->route('inicio')->with('aviso', $aviso2fa);
        }

        return redirect()->intended(route('inicio'));
    }

    public function sair(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function chaveThrottle(Request $request, string $email): string
    {
        return Str::lower($email) . '|' . $request->ip();
    }
}
