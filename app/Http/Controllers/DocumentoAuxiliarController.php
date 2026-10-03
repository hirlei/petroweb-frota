<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Cte;
use App\Models\Mdfe;
use App\Services\Fiscal\Impressao\DocumentoAuxiliar;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * DACTE (4010) e DAMDFE (4020) em PDF A4 — abrem em nova aba.
 *
 * `?html=1` devolve a mesma página em HTML (conferência/ajuste de leiaute, ou
 * impressão pelo navegador se o dompdf não estiver instalado).
 */
class DocumentoAuxiliarController extends Controller
{
    public function __construct(private readonly DocumentoAuxiliar $auxiliar)
    {
    }

    public function dacte(Request $request, Cte $cte): Response
    {
        Gate::authorize('view', $cte);

        return $this->responder($request, 'fiscal.dacte', $this->auxiliar->dacte($cte),
            'DACTE-' . ($cte->chave ?: 'rascunho-' . $cte->id) . '.pdf');
    }

    public function damdfe(Request $request, Mdfe $mdfe): Response
    {
        Gate::authorize('view', $mdfe);

        return $this->responder($request, 'fiscal.damdfe', $this->auxiliar->damdfe($mdfe),
            'DAMDFE-' . ($mdfe->chave ?: 'rascunho-' . $mdfe->id) . '.pdf');
    }

    /** @param array<string,mixed> $dados */
    private function responder(Request $request, string $view, array $dados, string $arquivo): Response
    {
        if ($request->boolean('html') || ! class_exists(Pdf::class)) {
            return response()->view($view, $dados + ['modoHtml' => true]);
        }

        return Pdf::loadView($view, $dados + ['modoHtml' => false])
            ->setPaper('a4', 'portrait')
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false, 'dpi' => 96])
            ->stream($arquivo);
    }
}
