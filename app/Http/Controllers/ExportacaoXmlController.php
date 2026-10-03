<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ExportacaoXml;
use App\Services\Fiscal\ExportadorXml;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Baixa um ZIP da rotina 4060 (o registro já vem filtrado pela empresa). */
class ExportacaoXmlController extends Controller
{
    public function __invoke(ExportacaoXml $exportacao, ExportadorXml $exportador): Response
    {
        Gate::authorize('xml.exportar');
        $disco = Storage::disk((string) config('fiscal.xml.disco', 'local'));
        abort_unless($disco->exists($exportacao->caminho), 404, 'O arquivo desta exportação não está mais no servidor. Gere de novo.');

        return $disco->download($exportacao->caminho, $exportador->nomeDoArquivo($exportacao), ['Content-Type' => 'application/zip']);
    }
}
