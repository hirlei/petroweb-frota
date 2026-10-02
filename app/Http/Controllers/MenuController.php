<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Navegacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Ações do menu H6 (igual ao ERP, 02/10/2026):
 *  - ir: "3020" + Enter no campo do menu abre a rotina pelo código;
 *  - fixar: a estrela ☆/★ põe ou tira a rotina dos Favoritos do usuário.
 *
 * Só rotinas que o usuário enxerga (o mapa já vem filtrado por permissão).
 */
class MenuController extends Controller
{
    public function ir(string $codigo): RedirectResponse
    {
        $mapa = Navegacao::mapa();

        if (! isset($mapa[$codigo])) {
            return redirect()->route('inicio')->with('aviso', "Rotina {$codigo} não encontrada ou sem permissão.");
        }

        return redirect()->to($mapa[$codigo]['url']);
    }

    public function fixar(Request $request, string $codigo): JsonResponse
    {
        $user = $request->user();
        $mapa = Navegacao::mapa();
        abort_unless(isset($mapa[$codigo]), 404);

        $prefs = (array) ($user->preferencias ?? []);
        $favs = array_values((array) ($prefs['favoritos'] ?? []));

        $favs = in_array($codigo, $favs, true)
            ? array_values(array_diff($favs, [$codigo]))
            : array_merge($favs, [$codigo]);

        $prefs['favoritos'] = array_slice($favs, 0, 12);
        $user->forceFill(['preferencias' => $prefs])->save();

        return response()->json(['favoritos' => $prefs['favoritos']]);
    }
}
