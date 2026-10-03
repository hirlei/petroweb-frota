{{-- Recibo do acerto de viagem (3070) — A4 retrato, só tabelas (dompdf). --}}
@php
    $a = $acerto;
    $v = $viagem;
    $fmt = fn ($x, $d = 2) => number_format((float) $x, $d, ',', '.');
    $formasAd = ['pix' => 'Pix', 'dinheiro' => 'Dinheiro', 'cartao_frota' => 'Cartão frota', 'deposito' => 'Depósito'];
    $finalidades = ['geral' => 'Geral', 'pedagio' => 'Pedágio', 'combustivel' => 'Combustível', 'alimentacao' => 'Alimentação'];
    $formasDev = ['pix' => 'Pix', 'dinheiro' => 'Dinheiro', 'deposito' => 'Depósito', 'desconto_folha' => 'Desconto em folha'];
    $ini = $v->saida_real ?? $v->saida_prevista;
    $fim = $v->chegada_real ?? $v->chegada_prevista;
    $saldo = (float) $a->saldo;
    $ficou = (float) $a->total_adiantado - (float) $a->gasto_adiantamento;
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Acerto {{ $v->numero }}</title>
    @include('fiscal.partials.estilo')
    <style>
        body { font-size: 8.4pt; }
        .v { font-size: 8.4pt; }
        .lista td { font-size: 7.8pt; padding: 2pt 3pt; }
        .res td { border: 0; padding: 2.2pt 3pt; font-size: 8.4pt; }
        .res tr.sub td { border-top: 0.5pt solid #000; font-weight: bold; }
        .res tr.tot td { border-top: 1pt solid #000; font-size: 10.4pt; font-weight: bold; padding-top: 4pt; }
        .assin td { border: 0; padding: 0 10pt; text-align: center; font-size: 7.4pt; }
        .linha-ass { border-top: 0.7pt solid #000; padding-top: 2pt; }
        .texto { font-size: 8.4pt; line-height: 1.45; padding: 4pt 3pt; }
    </style>
</head>
<body>
@if ($modoHtml)<button class="imprimir" onclick="window.print()">Imprimir</button>@endif
<div class="folha">
    <table>
        <tr>
            <td style="width:64%;padding:3pt">
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
            <td style="vertical-align:middle" class="c">
                <span class="g" style="font-size:11pt">RECIBO DE ACERTO</span>
                <span class="v">Viagem {{ $v->numero }}</span>
                <span class="v" style="font-size:7pt">Fechado em {{ $a->fechado_em?->format('d/m/Y H:i') }}</span>
            </td>
        </tr>
    </table>

    <table class="bloco">
        <tr>
            <td style="width:46%"><span class="l">Motorista</span><span class="v b">{{ mb_strtoupper($motorista) }}</span></td>
            <td style="width:22%"><span class="l">CPF</span><span class="v mono">{{ $cpf ?? '—' }}</span></td>
            <td><span class="l">Veículo</span><span class="v mono">{{ $v->veiculoTracao?->placaFormatada() ?? '—' }}</span></td>
        </tr>
        <tr>
            <td><span class="l">Percurso</span><span class="v">{{ $v->municipioOrigem?->nome ?? '—' }}{{ $v->municipioOrigem ? '/' . $v->municipioOrigem->uf : '' }} → {{ $v->municipioDestino?->nome ?? '—' }}{{ $v->municipioDestino ? '/' . $v->municipioDestino->uf : '' }}</span></td>
            <td><span class="l">Período</span><span class="v">{{ $ini?->format('d/m/Y') ?? '—' }} a {{ $fim?->format('d/m/Y') ?? '—' }}</span></td>
            <td><span class="l">Dias</span><span class="v">{{ $a->dias }}</span></td>
        </tr>
    </table>

    <table class="bloco"><tr><td class="tit">Adiantamentos recebidos</td></tr></table>
    <table class="bloco"><tr><td style="padding:0">
        <table class="lista">
            <tr class="cab"><td style="width:18%">Data</td><td style="width:28%">Forma</td><td>Para</td><td style="width:22%" class="r">Valor</td></tr>
            @forelse ($adiantamentos as $ad)
                <tr><td>{{ $ad->data->format('d/m/Y') }}</td><td>{{ $formasAd[$ad->forma] ?? $ad->forma }}</td><td>{{ $finalidades[$ad->finalidade] ?? $ad->finalidade }}</td><td class="r mono">{{ $fmt($ad->valor) }}</td></tr>
            @empty
                <tr><td colspan="4">Nenhum adiantamento.</td></tr>
            @endforelse
        </table>
    </td></tr></table>

    <table class="bloco"><tr><td class="tit">Despesas prestadas</td></tr></table>
    <table class="bloco"><tr><td style="padding:0">
        <table class="lista">
            <tr class="cab"><td style="width:12%">Data</td><td>Despesa</td><td style="width:18%">Pago com</td><td style="width:22%">Conferência</td><td style="width:16%" class="r">Valor</td></tr>
            @forelse ($despesas as $d)
                <tr>
                    <td>{{ $d->data?->format('d/m/Y') }}</td>
                    <td>{{ config('despesas.tipos.' . $d->tipo, $d->tipo) }}{{ $d->descricao ? ' — ' . $d->descricao : '' }}</td>
                    <td>{{ $d->forma_pagamento === 'reembolso' ? 'Do bolso' : 'Adiantamento' }}</td>
                    <td>{{ $d->glosada ? 'Glosa' . ($d->motivo_glosa ? ': ' . $d->motivo_glosa : '') : 'Aceita' }}</td>
                    <td class="r mono">{{ $fmt($d->valor) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Nenhuma despesa prestada.</td></tr>
            @endforelse
        </table>
    </td></tr></table>

    <table class="bloco"><tr><td class="tit">Saldo do acerto</td></tr></table>
    <table class="bloco"><tr><td style="padding:2pt 3pt">
        <table class="res">
            <tr><td>Adiantado</td><td class="r mono" style="width:30%">R$ {{ $fmt($a->total_adiantado) }}</td></tr>
            <tr><td>(−) Gasto do adiantamento, aceito</td><td class="r mono">R$ {{ $fmt($a->gasto_adiantamento) }}</td></tr>
            <tr class="sub"><td>= Ficou com o motorista</td><td class="r mono">R$ {{ $fmt($ficou) }}</td></tr>
            <tr><td>(+) Do bolso, aceito</td><td class="r mono">R$ {{ $fmt($a->bolso_aceito) }}</td></tr>
            <tr><td>(+) Diárias · {{ $a->dias }} × R$ {{ $fmt($a->diaria_valor) }}</td><td class="r mono">R$ {{ $fmt($a->diarias_total) }}</td></tr>
            <tr><td>(+) Comissão · {{ $fmt($a->comissao_percentual) }}% de R$ {{ $fmt($a->comissao_base) }}</td><td class="r mono">R$ {{ $fmt($a->comissao_valor) }}</td></tr>
            <tr class="tot">
                <td>{{ $saldo > 0 ? 'A empresa paga ao motorista' : ($saldo < 0 ? 'O motorista devolve à empresa' : 'Acerto zerado') }}</td>
                <td class="r mono">R$ {{ $fmt(abs($saldo)) }}</td>
            </tr>
        </table>
        @if ((float) $a->glosado > 0)
            <span class="v" style="font-size:7pt;margin-top:2pt">Glosado: R$ {{ $fmt($a->glosado) }} — não aceito como gasto da empresa.</span>
        @endif
    </td></tr></table>

    <table class="bloco"><tr><td class="texto">
        @if ($saldo > 0)
            Declaro estar de acordo com o acerto acima. O valor de <b>R$ {{ $fmt($saldo) }}</b> será pago pela empresa conforme a conta a pagar lançada.
        @elseif ($saldo < 0)
            Declaro estar de acordo com o acerto acima e devolver à empresa o valor de <b>R$ {{ $fmt(-$saldo) }}</b>@if ($a->devolvido_em), devolvido em {{ $a->devolvido_em->format('d/m/Y') }} por {{ mb_strtolower($formasDev[$a->devolvido_forma] ?? (string) $a->devolvido_forma) }}@endif.
        @else
            Declaro estar de acordo com o acerto acima, sem saldo a pagar ou a devolver.
        @endif
        @if ($a->observacoes)<br><b>Observações:</b> {{ $a->observacoes }}@endif
    </td></tr></table>

    <table class="assin" style="margin-top:34pt">
        <tr>
            <td><div class="linha-ass">{{ mb_strtoupper($motorista) }}<br>Motorista{{ $cpf ? ' · CPF ' . $cpf : '' }}</div></td>
            <td><div class="linha-ass">{{ $a->fechadoPor?->name ? mb_strtoupper($a->fechadoPor->name) : 'RESPONSÁVEL' }}<br>Pela empresa</div></td>
        </tr>
    </table>

    <p class="rodape">PetroWeb Frota · Acerto de viagem 3070 · Viagem {{ $v->numero }} · Emitido em {{ now()->format('d/m/Y H:i') }}</p>
</div>
</body>
</html>
