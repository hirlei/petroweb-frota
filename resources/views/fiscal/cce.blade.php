{{-- Carta de correção do CT-e impressa (A4, só tabelas — dompdf). Mockup aprovado em 03/10/2026. --}}
@php
    $ev = $evento;
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Carta de correção {{ $ev->sequencia }} · CT-e {{ $numero }}</title>
    @include('fiscal.partials.estilo')
    <style>
        body { font-size: 8.4pt; }
        .v { font-size: 8.4pt; }
        .lista td { font-size: 8pt; padding: 2.4pt 3pt; }
        .cond { font-size: 7.4pt; line-height: 1.45; padding: 4pt; }
    </style>
</head>
<body>
@if ($modoHtml)<button class="imprimir" onclick="window.print()">Imprimir</button>@endif
<div class="folha">
    @if ($homologacao)<div class="homolog">EMITIDO EM AMBIENTE DE HOMOLOGAÇÃO — SEM VALOR FISCAL</div>@endif

    <table>
        <tr>
            <td style="width:62%;padding:3pt">
                <table class="sem"><tr>
                    @if ($emitente['logo'])
                        <td style="width:52pt;vertical-align:middle"><img src="{{ $emitente['logo'] }}" style="max-width:50pt;max-height:50pt"></td>
                    @endif
                    <td style="vertical-align:middle">
                        <span class="g">{{ $emitente['nome'] }}</span>
                        @foreach ($emitente['linhas'] as $l)<span class="v" style="font-size:7pt">{{ $l }}</span>@endforeach
                    </td>
                </tr></table>
            </td>
            <td class="c" style="vertical-align:middle">
                <span class="g" style="font-size:11pt">CARTA DE CORREÇÃO</span>
                <span class="v">CT-e nº {{ $numero }} · série {{ $cte->serie }}</span>
                <span class="v">Correção nº {{ $ev->sequencia }}</span>
            </td>
        </tr>
    </table>

    <table class="bloco">
        <tr>
            <td style="width:64%"><span class="l">Chave de acesso do CT-e</span><span class="v mono">{{ $chaveFormatada }}</span></td>
            <td><span class="l">Protocolo do evento</span><span class="v mono">{{ $ev->protocolo ?? '—' }}</span><span class="v">{{ $ev->data_evento?->format('d/m/Y H:i:s') }}</span></td>
        </tr>
    </table>

    <table class="bloco"><tr><td class="tit">Correções</td></tr></table>
    <table class="bloco"><tr><td style="padding:0">
        <table class="lista">
            <tr class="cab"><td style="width:18%">Grupo</td><td style="width:18%">Campo</td><td style="width:26%">Descrição</td><td>Valor corrigido</td></tr>
            @foreach ($correcoes as $c)
                @php $def = $catalogo[\App\Domain\Fiscal\RegrasCce::chaveDoCatalogo((string) ($c['grupo'] ?? ''), (string) ($c['campo'] ?? '')) ?? ''] ?? null; @endphp
                <tr>
                    <td class="mono">{{ $c['grupo'] ?? '' }}</td>
                    <td class="mono">{{ $c['campo'] ?? '' }}{{ ! empty($c['item']) ? ' (item ' . $c['item'] . ')' : '' }}</td>
                    <td>{{ $def[2] ?? '—' }}</td>
                    <td>{{ $c['valor'] ?? '' }}</td>
                </tr>
            @endforeach
        </table>
    </td></tr></table>

    <table class="bloco"><tr><td class="tit">Condição de uso</td></tr><tr><td class="cond">{{ $condicao }}</td></tr></table>

    <p class="rodape">Esta carta de correção substitui as anteriores do mesmo CT-e e reúne todas as correções em vigor. Transmitida{{ $ev->criadoPor ? ' por ' . $ev->criadoPor->name : '' }} pelo PetroWeb Frota.</p>
</div>
</body>
</html>
