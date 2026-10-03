<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Cadastro\Documento;
use App\Models\AcertoViagem;
use App\Models\Adiantamento;
use App\Models\Despesa;
use App\Models\Viagem;
use App\Services\Fiscal\Impressao\DocumentoAuxiliar;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Recibo do acerto de viagem (3070) em PDF A4 — abre em nova aba. Só do acerto
 * FECHADO (é o que o motorista assina). `?html=1` devolve a página em HTML.
 */
class ReciboAcertoController extends Controller
{
    public function __invoke(Request $request, Viagem $viagem, DocumentoAuxiliar $auxiliar): Response
    {
        Gate::authorize('viewAny', AcertoViagem::class);

        $acerto = AcertoViagem::query()->with('fechadoPor')
            ->where('viagem_id', $viagem->id)->where('status', 'fechado')->latest('id')->first();
        abort_if($acerto === null, 404, 'O acerto desta viagem não está fechado.');

        $viagem->load(['filial.municipio', 'motorista.pessoa', 'veiculoTracao', 'municipioOrigem', 'municipioDestino']);
        $pessoa = $viagem->motorista?->pessoa;

        $dados = [
            'emitente' => $auxiliar->emitente($viagem->filial),
            'viagem' => $viagem,
            'acerto' => $acerto,
            'motorista' => $pessoa?->razao_social ?? '—',
            'cpf' => $pessoa?->documento ? Documento::formatar((string) $pessoa->documento) : null,
            'adiantamentos' => Adiantamento::query()->where('viagem_id', $viagem->id)->orderBy('data')->orderBy('id')->get(),
            'despesas' => Despesa::query()->where('viagem_id', $viagem->id)
                ->whereIn('forma_pagamento', ['adiantamento', 'reembolso'])->orderBy('data')->orderBy('id')->get(),
        ];
        $arquivo = 'Acerto-' . $viagem->numero . '.pdf';

        if ($request->boolean('html') || ! class_exists(Pdf::class)) {
            return response()->view('operacao.recibo-acerto', $dados + ['modoHtml' => true]);
        }

        return Pdf::loadView('operacao.recibo-acerto', $dados + ['modoHtml' => false])
            ->setPaper('a4', 'portrait')
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false, 'dpi' => 96])
            ->stream($arquivo);
    }
}
