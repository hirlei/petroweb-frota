{{-- DAMDFE — Documento Auxiliar do MDF-e (A4 retrato). Mockup aprovado em 03/10/2026. --}}
@php
    $m = $doc;
    $fmt = fn ($v, $d = 2) => $v === null ? '—' : number_format((float) $v, $d, ',', '.');
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>DAMDFE {{ $numero }}</title>
    @include('fiscal.partials.estilo')
</head>
<body>
@if ($modoHtml)<button class="imprimir" onclick="window.print()">Imprimir</button>@endif
<div class="folha">
    @if ($marca)<div class="marca">{{ $marca }}</div>@endif
    @if ($homologacao)<div class="homolog">EMITIDO EM AMBIENTE DE HOMOLOGAÇÃO — SEM VALOR FISCAL</div>@endif

    {{-- Cabeçalho --}}
    <table>
        <tr>
            <td style="width:44%;padding:3pt">
                <table class="sem"><tr>
                    @if ($emitente['logo'])
                        <td style="width:52pt;vertical-align:middle"><img src="{{ $emitente['logo'] }}" style="max-width:50pt;max-height:50pt"></td>
                    @endif
                    <td style="{{ $emitente['logo'] ? 'padding-left:4pt' : '' }}">
                        <span class="v b" style="font-size:8.6pt">{{ $emitente['nome'] }}</span>
                        @foreach ($emitente['linhas'] as $linha)<span class="v" style="font-size:6.8pt">{{ $linha }}</span>@endforeach
                        @if ($emitente['rntrc'] ?? null)<span class="v" style="font-size:6.8pt">RNTRC {{ $emitente['rntrc'] }}</span>@endif
                    </td>
                </tr></table>
            </td>
            <td style="vertical-align:middle;text-align:center">
                <div class="b" style="font-size:10pt">DAMDFE</div>
                <div style="font-size:5.8pt">Documento Auxiliar de Manifesto Eletrônico de Documentos Fiscais</div>
            </td>
            <td style="width:80pt;padding:2pt;text-align:center;vertical-align:middle">
                @if ($qr)<img src="{{ $qr }}" style="width:74pt;height:74pt">@endif
            </td>
        </tr>
    </table>
    <table class="bloco">
        <tr>
            <td style="width:62%;text-align:center;padding:3pt">
                @if ($barras)<img src="{{ $barras }}" style="width:100%;height:30pt">@endif
                <span class="l" style="margin-top:2pt">Chave de acesso</span>
                <span class="chave">{{ $chaveFormatada ?? 'Sem chave — documento não autorizado' }}</span>
            </td>
            <td style="vertical-align:middle">
                <span class="l">Protocolo de autorização de uso</span>
                <span class="v b">{{ $m->protocolo ?? '—' }}</span>
                <span class="v">{{ $m->data_autorizacao?->format('d/m/Y H:i:s') }}</span>
                <span class="v" style="font-size:6pt;margin-top:3pt">Consulta pela chave no portal do MDF-e ou pelo QR Code.</span>
            </td>
        </tr>
    </table>
    <table class="bloco">
        <tr>
            <td style="width:9%"><span class="l">Modelo</span><span class="v">{{ $m->modelo }}</span></td>
            <td style="width:8%"><span class="l">Série</span><span class="v">{{ $m->serie }}</span></td>
            <td style="width:13%"><span class="l">Número</span><span class="v b">{{ $numero }}</span></td>
            <td style="width:8%"><span class="l">Folha</span><span class="v">1/1</span></td>
            <td><span class="l">Data e hora de emissão</span><span class="v">{{ $m->emissao?->format('d/m/Y H:i:s') ?? '—' }}</span></td>
            <td style="width:13%"><span class="l">UF carreg.</span><span class="v b">{{ $m->uf_inicio ?? '—' }}</span></td>
            <td style="width:13%"><span class="l">UF descarreg.</span><span class="v b">{{ $m->uf_fim ?? '—' }}</span></td>
        </tr>
    </table>

    <table class="bloco"><tr><td class="tit">Modal rodoviário de carga</td></tr></table>
    <table class="bloco">
        <tr>
            <td><span class="l">Qtd. CT-e</span><span class="v g">{{ $qtdCte }}</span></td>
            <td><span class="l">Qtd. NF-e</span><span class="v g">{{ $qtdNfe }}</span></td>
            <td><span class="l">Peso total ({{ (int) $m->unidade_peso === 2 ? 't' : 'kg' }})</span><span class="v g">{{ $fmt($m->peso_bruto_total, 4) }}</span></td>
            <td style="width:30%"><span class="l">Valor total da carga</span><span class="v g">R$ {{ $fmt($m->valor_carga_total) }}</span></td>
        </tr>
    </table>

    <table class="bloco">
        <tr>
            <td style="width:50%;padding:0">
                <div class="tit" style="border-bottom:0.7pt solid #000">Veículos</div>
                <table class="lista">
                    <tr class="cab"><td>Placa</td><td>Tipo</td><td>RNTRC</td></tr>
                    @forelse ($veiculos as $v)
                        <tr><td class="mono">{{ $v['placa'] }}</td><td>{{ $v['tipo'] }}</td><td class="mono">{{ $v['rntrc'] ?? '—' }}</td></tr>
                    @empty
                        <tr><td colspan="3">—</td></tr>
                    @endforelse
                </table>
            </td>
            <td style="padding:0">
                <div class="tit" style="border-bottom:0.7pt solid #000">Condutores</div>
                <table class="lista">
                    <tr class="cab"><td style="width:38%">CPF</td><td>Nome</td></tr>
                    @forelse ($condutores as $cd)
                        <tr><td class="mono">{{ $cd['cpf'] }}</td><td>{{ $cd['nome'] }}</td></tr>
                    @empty
                        <tr><td colspan="2">—</td></tr>
                    @endforelse
                </table>
            </td>
        </tr>
    </table>

    <table class="bloco">
        <tr>
            <td style="width:58%;padding:0">
                <div class="tit" style="border-bottom:0.7pt solid #000">Vale-pedágio</div>
                <table class="lista" style="font-size:6.4pt">
                    <tr class="cab"><td style="width:31%">Fornecedora (CNPJ)</td><td style="width:31%">Responsável</td><td style="width:22%">Nº compra</td><td class="r" style="width:16%">Valor</td></tr>
                    @forelse ($vales as $vp)
                        <tr><td class="mono" style="font-size:6.2pt">{{ $vp['fornecedora'] }}</td><td class="mono" style="font-size:6.2pt">{{ $vp['responsavel'] }}</td><td class="mono">{{ $vp['compra'] }}</td><td class="r">{{ $vp['valor'] !== null ? $fmt($vp['valor']) : '—' }}</td></tr>
                    @empty
                        <tr><td colspan="4">—</td></tr>
                    @endforelse
                </table>
            </td>
            <td style="padding:0">
                <div class="tit" style="border-bottom:0.7pt solid #000">CIOT</div>
                <table class="lista">
                    <tr class="cab"><td>Número</td><td>Responsável (CPF/CNPJ)</td></tr>
                    <tr><td class="mono">{{ $ciot ?? '—' }}</td><td class="mono">{{ $ciotResponsavel ?? '—' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="bloco">
        <tr>
            <td><span class="l">Município de carregamento</span><span class="v">{{ $carregamento ?: '—' }}</span></td>
            <td style="width:22%"><span class="l">Percurso (UFs)</span><span class="v">{{ $percurso ?: '—' }}</span></td>
            <td><span class="l">Município de descarregamento</span><span class="v">{{ $descarregamento ?: '—' }}</span></td>
        </tr>
    </table>

    <table class="bloco"><tr><td class="tit">Documentos vinculados</td></tr></table>
    <table class="bloco"><tr><td style="padding:0">
        <table class="lista">
            <tr class="cab"><td style="width:10%">Tipo</td><td>Chave de acesso</td><td style="width:24%">Descarregamento</td></tr>
            @forelse ($documentos as $d)
                <tr><td>{{ $d['tipo'] }}</td><td class="mono">{{ $d['chave'] }}</td><td>{{ $d['descarga'] }}</td></tr>
            @empty
                <tr><td colspan="3">—</td></tr>
            @endforelse
        </table>
    </td></tr></table>

    <table class="bloco"><tr><td class="tit">Observações</td></tr></table>
    <table class="bloco"><tr><td style="height:32pt">
        {{ $viagem ? 'Viagem ' . $viagem . '.' : '' }}
        @if (is_array($seguro) && ($seguro['apolice'] ?? null)) Seguro: apólice {{ $seguro['apolice'] }}{{ ($seguro['seguradora'] ?? null) ? ' (' . $seguro['seguradora'] . ')' : '' }}. @endif
    </td></tr></table>

    <p class="rodape">DAMDFE gerado em {{ now()->format('d/m/Y H:i') }} · PetroWeb Frota</p>
</div>
</body>
</html>
