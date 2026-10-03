{{-- DACTE — Documento Auxiliar do CT-e (A4 retrato). Mockup aprovado em 03/10/2026. --}}
@php
    $c = $doc;
    $fmt = fn ($v, $d = 2) => $v === null ? '—' : number_format((float) $v, $d, ',', '.');
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>DACTE {{ $numero }}</title>
    @include('fiscal.partials.estilo')
</head>
<body>
@if ($modoHtml)<button class="imprimir" onclick="window.print()">Imprimir</button>@endif
<div class="folha">
    @if ($marca)<div class="marca">{{ $marca }}</div>@endif
    @if ($homologacao)<div class="homolog">EMITIDO EM AMBIENTE DE HOMOLOGAÇÃO — SEM VALOR FISCAL</div>@endif

    {{-- Canhoto --}}
    <table class="canhoto" style="margin-bottom:4pt">
        <tr><td colspan="6" style="font-size:6.4pt">DECLARO QUE RECEBI OS VOLUMES DESTE CONHECIMENTO EM PERFEITO ESTADO PELO QUE DOU POR CUMPRIDO O PRESENTE CONTRATO DE TRANSPORTE</td></tr>
        <tr style="height:22pt">
            <td style="width:22%"><span class="l">Nome</span></td>
            <td style="width:13%"><span class="l">RG</span></td>
            <td style="width:22%"><span class="l">Assinatura / carimbo</span></td>
            <td style="width:15%"><span class="l">Término da prestação — data/hora</span></td>
            <td style="width:15%"><span class="l">Início da prestação — data/hora</span></td>
            <td style="width:13%"><span class="l">CT-e</span><span class="v b">Nº {{ $numero }} · Série {{ $c->serie }}</span></td>
        </tr>
    </table>

    {{-- Cabeçalho --}}
    <table>
        <tr>
            <td style="width:33%;padding:3pt">
                <table class="sem"><tr>
                    @if ($emitente['logo'])
                        <td style="width:52pt;vertical-align:middle"><img src="{{ $emitente['logo'] }}" style="max-width:50pt;max-height:50pt"></td>
                    @endif
                    <td style="{{ $emitente['logo'] ? 'padding-left:4pt' : '' }}">
                        <span class="v b" style="font-size:8.6pt">{{ $emitente['nome'] }}</span>
                        @foreach ($emitente['linhas'] as $linha)<span class="v" style="font-size:6.8pt">{{ $linha }}</span>@endforeach
                    </td>
                </tr></table>
            </td>
            <td style="width:33%;padding:0">
                <div class="c b" style="font-size:9.6pt;padding-top:2pt">DACTE</div>
                <div class="c" style="font-size:5.6pt;padding:0 2pt 2pt">Documento Auxiliar do Conhecimento de Transporte Eletrônico</div>
                <table>
                    <tr>
                        <td style="width:31%"><span class="l">Modal</span><span class="v b" style="font-size:6.8pt">Rodoviário</span></td>
                        <td style="width:15%"><span class="l">Mod.</span><span class="v">{{ $c->modelo }}</span></td>
                        <td style="width:15%"><span class="l">Série</span><span class="v">{{ $c->serie }}</span></td>
                        <td style="width:24%"><span class="l">Número</span><span class="v b">{{ $numero }}</span></td>
                        <td style="width:15%"><span class="l">Folha</span><span class="v">1/1</span></td>
                    </tr>
                    <tr>
                        <td colspan="3"><span class="l">Emissão</span><span class="v">{{ $c->emissao?->format('d/m/Y H:i:s') ?? '—' }}</span></td>
                        <td colspan="2"><span class="l">Suframa dest.</span><span class="v">—</span></td>
                    </tr>
                </table>
            </td>
            <td style="width:34%;padding:0">
                <table class="sem"><tr>
                    <td style="padding:3pt;vertical-align:middle;text-align:center">
                        @if ($barras)<img src="{{ $barras }}" style="width:100%;height:30pt">@endif
                        <span class="l" style="margin-top:2pt">Chave de acesso</span>
                        <span class="chave">{{ $chaveFormatada ?? 'Sem chave — documento não autorizado' }}</span>
                    </td>
                    <td style="width:66pt;padding:2pt;vertical-align:middle;text-align:center">
                        @if ($qr)<img src="{{ $qr }}" style="width:62pt;height:62pt">@endif
                    </td>
                </tr></table>
            </td>
        </tr>
    </table>
    <table class="bloco">
        <tr>
            <td style="width:22%"><span class="l">Tipo do CT-e</span><span class="v b">{{ $tipoCte }}</span></td>
            <td style="width:22%"><span class="l">Tipo do serviço</span><span class="v b">{{ $tipoServico }}</span></td>
            <td><span class="l">Protocolo de autorização de uso</span><span class="v b">{{ $c->protocolo ? $c->protocolo . ' · ' . $c->data_autorizacao?->format('d/m/Y H:i:s') : '—' }}</span></td>
        </tr>
        <tr><td colspan="3" style="font-size:6.2pt">Consulta de autenticidade no portal nacional do CT-e, no site da Sefaz autorizadora ou pelo QR Code.</td></tr>
        <tr><td colspan="3"><span class="l">CFOP — Natureza da prestação</span><span class="v">{{ $c->cfop }} — {{ $c->natureza_operacao }}</span></td></tr>
    </table>
    <table class="bloco">
        <tr>
            <td><span class="l">Início da prestação</span><span class="v b">{{ $c->municipioInicio ? mb_strtoupper($c->municipioInicio->nome) . ' — ' . $c->municipioInicio->uf : '—' }}</span></td>
            <td><span class="l">Término da prestação</span><span class="v b">{{ $c->municipioFim ? mb_strtoupper($c->municipioFim->nome) . ' — ' . $c->municipioFim->uf : '—' }}</span></td>
        </tr>
    </table>

    {{-- Partes --}}
    @foreach (array_chunk($partes, 2, true) as $iPar => $par)
        <table class="bloco">
            <tr>
                @foreach ($par as $rotulo => $p)
                    <td style="width:50%;height:{{ $iPar === 0 ? '40pt' : '30pt' }}">
                        <span class="l">{{ $rotulo }}</span>
                        @if ($p)
                            <span class="v b">{{ $p['nome'] }}</span>
                            @foreach ($p['linhas'] as $linha)<span class="v" style="font-size:6.8pt">{{ $linha }}</span>@endforeach
                        @else
                            <span class="v">—</span>
                        @endif
                    </td>
                @endforeach
            </tr>
        </table>
    @endforeach
    <table class="bloco">
        <tr><td><span class="l">Tomador do serviço</span>
            @if ($tomador)<span class="v"><b>{{ $tomador['nome'] }}</b> ({{ mb_strtolower($tomadorPapel) }}) · {{ implode(' · ', array_slice($tomador['linhas'], -1)) }}</span>@else<span class="v">—</span>@endif
        </td></tr>
    </table>

    {{-- Carga --}}
    <table class="bloco">
        <tr>
            <td style="width:40%"><span class="l">Produto predominante</span><span class="v b">{{ mb_strtoupper((string) $c->produto_predominante) ?: '—' }}</span></td>
            <td style="width:35%"><span class="l">Outras características da carga</span><span class="v">—</span></td>
            <td><span class="l">Valor total da carga</span><span class="v b">R$ {{ $fmt($c->valor_mercadoria) }}</span></td>
        </tr>
    </table>
    <table class="bloco">
        <tr>
            <td><span class="l">Peso bruto (kg)</span><span class="v">{{ $fmt($c->peso_bruto, 4) }}</span></td>
            <td><span class="l">Peso base de cálculo (kg)</span><span class="v">{{ $fmt($c->peso_base_calculo, 4) }}</span></td>
            <td><span class="l">Volumes</span><span class="v">{{ $c->volumes ? $fmt($c->volumes, 4) : '—' }}</span></td>
            <td><span class="l">Cubagem (m³)</span><span class="v">—</span></td>
        </tr>
    </table>

    {{-- Componentes --}}
    <table class="bloco"><tr><td class="tit">Componentes do valor da prestação do serviço</td></tr></table>
    <table class="bloco">
        @forelse ($componentes->chunk(4) as $linha)
            <tr>
                @foreach ($linha as $comp)
                    <td style="width:25%"><table class="sem"><tr><td>{{ $comp->nome }}</td><td class="r b">{{ $fmt($comp->valor) }}</td></tr></table></td>
                @endforeach
                @for ($i = $linha->count(); $i < 4; $i++)<td style="width:25%">&nbsp;</td>@endfor
            </tr>
        @empty
            <tr><td colspan="4">—</td></tr>
        @endforelse
    </table>
    <table class="bloco">
        <tr>
            <td><span class="l">Valor total do serviço</span><span class="v g">R$ {{ $fmt($c->valor_total_servico) }}</span></td>
            <td><span class="l">Valor a receber</span><span class="v g">R$ {{ $fmt($c->valor_receber) }}</span></td>
        </tr>
    </table>

    {{-- Impostos --}}
    <table class="bloco"><tr><td class="tit">Informações relativas ao imposto</td></tr></table>
    <table class="bloco">
        <tr>
            <td style="width:34%"><span class="l">Situação tributária</span><span class="v">{{ $cst }}</span></td>
            <td><span class="l">Base de cálculo</span><span class="v">{{ $fmt($c->icms_base) }}</span></td>
            <td><span class="l">Alíq. ICMS</span><span class="v">{{ $c->icms_aliquota !== null ? $fmt($c->icms_aliquota) . '%' : '—' }}</span></td>
            <td><span class="l">Valor ICMS</span><span class="v">{{ $fmt($c->icms_valor) }}</span></td>
            <td><span class="l">% red. BC</span><span class="v">—</span></td>
            <td><span class="l">ICMS ST</span><span class="v">—</span></td>
        </tr>
        @if ($c->cbs_valor !== null || $c->ibs_uf_valor !== null || $c->ibs_mun_valor !== null)
            <tr>
                <td colspan="2"><span class="l">CBS</span><span class="v">{{ $fmt($c->cbs_valor) }}</span></td>
                <td colspan="2"><span class="l">IBS UF</span><span class="v">{{ $fmt($c->ibs_uf_valor) }}</span></td>
                <td colspan="2"><span class="l">IBS municipal</span><span class="v">{{ $fmt($c->ibs_mun_valor) }}</span></td>
            </tr>
        @endif
    </table>

    {{-- Documentos originários --}}
    <table class="bloco"><tr><td class="tit">Documentos originários</td></tr></table>
    <table class="bloco"><tr><td style="padding:0">
        <table class="lista">
            <tr class="cab"><td style="width:12%">Tipo</td><td>Chave de acesso / série e número</td><td style="width:18%" class="r">Valor</td></tr>
            @forelse ($documentos as $d)
                <tr>
                    <td>{{ strtoupper((string) $d->tipo) === 'NFE' ? 'NF-e' : strtoupper((string) $d->tipo) }}</td>
                    <td class="mono">{{ $d->chave ? trim(implode(' ', str_split($d->chave, 4))) : trim(($d->serie ? 'Série ' . $d->serie . ' · ' : '') . 'Nº ' . $d->numero) }}</td>
                    <td class="r">{{ $d->valor !== null ? $fmt($d->valor) : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="3">—</td></tr>
            @endforelse
        </table>
    </td></tr></table>

    {{-- Observações --}}
    <table class="bloco"><tr><td class="tit">Observações</td></tr></table>
    <table class="bloco"><tr><td style="height:32pt">{{ $viagem ? 'Viagem ' . $viagem . '.' : '' }} {{ data_get($c->payload, 'observacoes') }}</td></tr></table>

    {{-- Modal rodoviário --}}
    <table class="bloco"><tr><td class="tit">Informações específicas do modal rodoviário — Lei 11.442/07</td></tr></table>
    <table class="bloco">
        <tr>
            <td style="width:20%"><span class="l">RNTRC da empresa</span><span class="v b">{{ $rntrc ?? '—' }}</span></td>
            <td style="width:22%"><span class="l">CIOT</span><span class="v">{{ $ciot ?? '—' }}</span></td>
            <td style="width:20%"><span class="l">Data prevista de entrega</span><span class="v">{{ $previsaoEntrega ?? '—' }}</span></td>
            <td style="font-size:6pt;vertical-align:middle">Este conhecimento de transporte atende à legislação de transporte rodoviário em vigor.</td>
        </tr>
    </table>
    <table class="bloco">
        <tr style="height:26pt">
            <td style="width:60%"><span class="l">Uso exclusivo do emissor do CT-e</span><span class="v">Emitido pelo PetroWeb Frota</span></td>
            <td><span class="l">Reservado ao fisco</span></td>
        </tr>
    </table>
    <p class="rodape">DACTE gerado em {{ now()->format('d/m/Y H:i') }} · PetroWeb Frota</p>
</div>
</body>
</html>
