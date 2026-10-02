{{-- Fatura impressa (rotina 5010). Documento comercial: o documento fiscal de cada frete é o CT-e. --}}
@php $fmt = fn ($v) => number_format((float) $v, 2, ',', '.'); $end = $f->tomador?->enderecoPrincipal; @endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fatura {{ $f->numero }} — {{ $f->tomador?->razao_social }}</title>
    <link rel="icon" type="image/png" href="{{ asset('img/petroweb-icone.png') }}">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font: 12px/1.45 'Segoe UI', Arial, sans-serif; color: #1c1917; background: #f3f4f6; }
        .folha { max-width: 800px; margin: 24px auto; background: #fff; padding: 32px 36px; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        .barra { max-width: 800px; margin: 16px auto 0; display: flex; justify-content: flex-end; gap: 8px; }
        .barra button { font: 600 13px 'Segoe UI', Arial, sans-serif; padding: 8px 14px; border-radius: 6px; border: 0; cursor: pointer; background: #FAC775; color: #412402; }
        header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; border-bottom: 2px solid #1A3DA3; padding-bottom: 14px; }
        header h1 { font-size: 20px; color: #1A3DA3; } header .num { font: 700 22px Consolas, monospace; text-align: right; }
        header small, .muted { color: #6b7280; }
        h2 { font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: #6b7280; margin: 18px 0 6px; }
        .dois { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .box { border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 12px; }
        table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
        th { text-align: left; font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; border-bottom: 1px solid #d1d5db; padding: 6px 6px; }
        td { border-bottom: 1px solid #eef0f3; padding: 6px 6px; }
        .r { text-align: right; } .mono { font-family: Consolas, monospace; }
        .tot td { border: 0; padding: 3px 6px; } .tot .grande td { font-size: 15px; font-weight: 700; border-top: 2px solid #1c1917; padding-top: 8px; }
        footer { margin-top: 24px; font-size: 10.5px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 10px; }
        .cancelada { margin-top: 12px; padding: 8px 12px; border: 2px solid #dc2626; color: #b91c1c; font-weight: 700; text-align: center; border-radius: 6px; }
        @media print { body { background: #fff; } .barra { display: none; } .folha { box-shadow: none; margin: 0; max-width: none; padding: 0; } }
    </style>
</head>
<body>
<div class="barra"><button type="button" onclick="window.print()">Imprimir ou salvar em PDF</button></div>
<div class="folha">
    <header>
        <div>
            <h1>{{ $emitente?->razao_social ?? 'Fatura' }}</h1>
            @if ($emitente)
                <small>CNPJ {{ $cnpjEmitente }} · IE {{ $emitente->ie }}<br>
                    {{ $emitente->logradouro }}, {{ $emitente->numero }}{{ $emitente->bairro ? ' — ' . $emitente->bairro : '' }} · {{ $emitente->municipio?->nomeComUf() }}
                    {{ $emitente->telefone ? ' · ' . $emitente->telefone : '' }}{{ $emitente->email ? ' · ' . $emitente->email : '' }}</small>
            @endif
        </div>
        <div>
            <div class="muted" style="text-align:right">Fatura de serviço de transporte</div>
            <div class="num">Nº {{ $f->numero }}</div>
            <div class="muted" style="text-align:right">Emissão {{ $f->emissao?->format('d/m/Y') }}</div>
        </div>
    </header>

    @if ($f->cancelada())
        <div class="cancelada">FATURA CANCELADA EM {{ $f->cancelada_em?->format('d/m/Y') }} — SEM VALOR PARA COBRANÇA</div>
    @endif

    <div class="dois">
        <div>
            <h2>Cliente</h2>
            <div class="box">
                <b>{{ $f->tomador?->razao_social }}</b><br>
                {{ $f->tomador?->documentoFormatado() }}{{ $f->tomador?->ie ? ' · IE ' . $f->tomador->ie : '' }}<br>
                @if ($end)
                    {{ $end->logradouro }}{{ $end->numero ? ', ' . $end->numero : '' }}{{ $end->bairro ? ' — ' . $end->bairro : '' }}<br>
                    {{ $end->municipio?->nomeComUf() }}
                @endif
            </div>
        </div>
        <div>
            <h2>Condição de pagamento</h2>
            <div class="box">
                <b>{{ $f->rotuloCondicao() }}</b> · {{ $f->titulos->count() }} {{ $f->titulos->count() === 1 ? 'parcela' : 'parcelas' }}<br>
                <span class="muted">Valor total</span> <b class="mono">R$ {{ $fmt($f->valor_total) }}</b>
            </div>
        </div>
    </div>

    <h2>Conhecimentos de transporte (CT-e)</h2>
    <table>
        <thead><tr><th>CT-e</th><th>Emissão</th><th>Origem → destino</th><th>Chave de acesso</th><th class="r">Valor</th></tr></thead>
        <tbody>
            @foreach ($f->itens as $it)
                <tr>
                    <td class="mono">{{ number_format((int) $it->cte?->numero, 0, ',', '.') }}</td>
                    <td>{{ $it->cte?->emissao?->format('d/m/Y') }}</td>
                    <td>{{ $it->cte?->municipioInicio?->nomeComUf() }} → {{ $it->cte?->municipioFim?->nomeComUf() }}</td>
                    <td class="mono" style="font-size:9.5px">{{ $it->cte?->chave }}</td>
                    <td class="r mono">R$ {{ $fmt($it->valor) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="tot" style="width:280px;margin:10px 0 0 auto">
        <tr><td>Valor dos CT-e</td><td class="r mono">R$ {{ $fmt($f->valor_ctes) }}</td></tr>
        @if ((float) $f->desconto > 0)<tr><td>Desconto</td><td class="r mono">− R$ {{ $fmt($f->desconto) }}</td></tr>@endif
        @if ((float) $f->acrescimo > 0)<tr><td>Acréscimo</td><td class="r mono">+ R$ {{ $fmt($f->acrescimo) }}</td></tr>@endif
        <tr class="grande"><td>Total</td><td class="r mono">R$ {{ $fmt($f->valor_total) }}</td></tr>
    </table>

    <h2>Vencimentos</h2>
    <table>
        <thead><tr><th>Parcela</th><th>Título</th><th>Vencimento</th><th class="r">Valor</th></tr></thead>
        <tbody>
            @foreach ($f->titulos as $t)
                <tr><td>{{ $t->parcela }}/{{ $t->parcelas }}</td><td class="mono">{{ $t->numero }}</td><td>{{ $t->vencimento?->format('d/m/Y') }}</td><td class="r mono">R$ {{ $fmt($t->valor) }}</td></tr>
            @endforeach
        </tbody>
    </table>

    @if ($f->observacoes)
        <h2>Observações</h2>
        <p style="white-space:pre-line">{{ $f->observacoes }}</p>
    @endif

    <footer>
        Documento comercial de cobrança. Os documentos fiscais dos serviços são os CT-e listados acima.
        Emitida pelo PetroWeb Frota em {{ now()->format('d/m/Y H:i') }}.
    </footer>
</div>
</body>
</html>
