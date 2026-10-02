<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Cadastro\Documento;
use App\Models\Fatura;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Fatura para imprimir ou salvar em PDF pelo navegador (rotina 5010).
 * Página própria, sem o menu, com o botão "Imprimir" que some no papel.
 */
class FaturaImpressaoController extends Controller
{
    public function __invoke(Fatura $fatura): View
    {
        Gate::authorize('view', $fatura);

        $fatura->load(['tomador.enderecoPrincipal.municipio', 'filial.municipio', 'titulos',
            'itens.cte.municipioInicio', 'itens.cte.municipioFim']);

        return view('faturas.imprimir', [
            'f' => $fatura,
            'emitente' => $fatura->filial,
            'cnpjEmitente' => $fatura->filial ? Documento::formatar((string) $fatura->filial->cnpj) : null,
        ]);
    }
}
