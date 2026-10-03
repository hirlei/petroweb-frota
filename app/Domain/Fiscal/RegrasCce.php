<?php

declare(strict_types=1);

namespace App\Domain\Fiscal;

/**
 * Carta de Correção Eletrônica do CT-e (evento 110110) — lógica pura.
 *
 * Base: Convênio SINIEF 06/89, art. 58-B, e NT 2024.001 do CT-e. A CC-e NÃO
 * corrige: variáveis do imposto (base, alíquota, valor da prestação, CST…),
 * dados que mudem emitente, tomador, remetente ou destinatário (CNPJ/CPF/IE,
 * UF), data de emissão, identificação do documento, chaves de NF-e e CFOP.
 *
 * Até 20 CC-e por CT-e (nSeqEvento 1..20). A mais recente SUBSTITUI as
 * anteriores — por isso cada nova leva todas as correções que continuam
 * valendo (`consolidar`).
 */
final class RegrasCce
{
    public const MAXIMO = 20;

    public const CONDICAO_USO = 'A Carta de Correção é disciplinada pelo Art. 58-B do CONVÊNIO/SINIEF 06/89: Fica permitida a utilização de carta de correção, para regularização de erro ocorrido na emissão de documentos fiscais relativos à prestação de serviço de transporte, desde que o erro não esteja relacionado com: I - as variáveis que determinam o valor do imposto tais como: base de cálculo, alíquota, diferença de preço, quantidade, valor da prestação;II - a correção de dados cadastrais que implique mudança do emitente, tomador, remetente ou do destinatário;III - a data de emissão ou de saída.';

    /**
     * Campos oferecidos na tela (os que de fato se corrigem no dia a dia).
     * chave => [grupo, campo, rótulo, tamanho máximo].
     *
     * @var array<string, array{0:string,1:string,2:string,3:int}>
     */
    public const CATALOGO = [
        'proPred'          => ['infCarga', 'proPred', 'Produto predominante', 60],
        'xOutCat'          => ['infCarga', 'xOutCat', 'Outras características da carga', 30],
        'xObs'             => ['compl', 'xObs', 'Observações', 2000],
        'xCaracAd'         => ['compl', 'xCaracAd', 'Característica adicional do transporte', 15],
        'xCaracSer'        => ['compl', 'xCaracSer', 'Característica adicional do serviço', 30],
        'RNTRC'            => ['rodo', 'RNTRC', 'RNTRC', 8],
        'rem.xLgr'         => ['enderReme', 'xLgr', 'Endereço do remetente — logradouro', 255],
        'rem.nro'          => ['enderReme', 'nro', 'Endereço do remetente — número', 60],
        'rem.xCpl'         => ['enderReme', 'xCpl', 'Endereço do remetente — complemento', 60],
        'rem.xBairro'      => ['enderReme', 'xBairro', 'Endereço do remetente — bairro', 60],
        'rem.CEP'          => ['enderReme', 'CEP', 'Endereço do remetente — CEP', 8],
        'dest.xLgr'        => ['enderDest', 'xLgr', 'Endereço do destinatário — logradouro', 255],
        'dest.nro'         => ['enderDest', 'nro', 'Endereço do destinatário — número', 60],
        'dest.xCpl'        => ['enderDest', 'xCpl', 'Endereço do destinatário — complemento', 60],
        'dest.xBairro'     => ['enderDest', 'xBairro', 'Endereço do destinatário — bairro', 60],
        'dest.CEP'         => ['enderDest', 'CEP', 'Endereço do destinatário — CEP', 8],
        'exped.xLgr'       => ['enderExped', 'xLgr', 'Endereço do expedidor — logradouro', 255],
        'receb.xLgr'       => ['enderReceb', 'xLgr', 'Endereço do recebedor — logradouro', 255],
    ];

    /**
     * Vedados: `grupo.campo`, `grupo.*` (o grupo inteiro) ou `*.campo` (o
     * campo em qualquer grupo). Comparação sem diferenciar maiúsculas.
     *
     * @var array<string, string>  padrão => motivo (vai para a tela)
     */
    public const VEDADOS = [
        'ide.*'          => 'Identificação do CT-e (número, série, modelo, emissão, modal) não se corrige.',
        '*.dhEmi'        => 'Data de emissão não se corrige por carta de correção.',
        '*.CFOP'         => 'CFOP não se corrige por carta de correção.',
        'vPrest.*'       => 'Valor não se corrige por carta de correção.',
        'Comp.*'         => 'Componente do valor não se corrige por carta de correção.',
        '*.vTPrest'      => 'Valor não se corrige por carta de correção.',
        '*.vRec'         => 'Valor não se corrige por carta de correção.',
        '*.vComp'        => 'Valor não se corrige por carta de correção.',
        'imp.*'          => 'Imposto não se corrige por carta de correção.',
        'ICMS00.*'       => 'Imposto não se corrige por carta de correção.',
        'ICMS20.*'       => 'Imposto não se corrige por carta de correção.',
        'ICMS45.*'       => 'Imposto não se corrige por carta de correção.',
        'ICMS60.*'       => 'Imposto não se corrige por carta de correção.',
        'ICMS90.*'       => 'Imposto não se corrige por carta de correção.',
        'ICMSOutraUF.*'  => 'Imposto não se corrige por carta de correção.',
        'ICMSSN.*'       => 'Imposto não se corrige por carta de correção.',
        'IBSCBS.*'       => 'Imposto não se corrige por carta de correção.',
        '*.CST'          => 'Imposto não se corrige por carta de correção.',
        '*.vBC'          => 'Imposto não se corrige por carta de correção.',
        '*.pICMS'        => 'Imposto não se corrige por carta de correção.',
        '*.vICMS'        => 'Imposto não se corrige por carta de correção.',
        '*.pRedBC'       => 'Imposto não se corrige por carta de correção.',
        '*.vCred'        => 'Imposto não se corrige por carta de correção.',
        '*.cBenef'       => 'Código de benefício fiscal não se corrige por carta de correção.',
        '*.CNPJ'         => 'CNPJ não se corrige: mudaria quem é a parte do documento.',
        '*.CPF'          => 'CPF não se corrige: mudaria quem é a parte do documento.',
        '*.IE'           => 'Inscrição estadual não se corrige: mudaria quem é a parte do documento.',
        '*.UF'           => 'UF não se corrige por carta de correção.',
        'toma3.*'        => 'Tomador não se corrige por carta de correção.',
        'toma4.*'        => 'Tomador não se corrige por carta de correção.',
        'toma.*'         => 'Tomador não se corrige por carta de correção.',
        'infNFe.*'       => 'Chave da NF-e não se corrige por carta de correção.',
        '*.chave'        => 'Chave de documento não se corrige por carta de correção.',
        'infQ.*'         => 'Quantidade da carga não se corrige: entra no cálculo do frete.',
        '*.qCarga'       => 'Quantidade da carga não se corrige: entra no cálculo do frete.',
        '*.vCarga'       => 'Valor da carga não se corrige por carta de correção.',
    ];

    /** Motivo do veto, ou null quando o campo pode ser corrigido. */
    public static function vetado(string $grupo, string $campo): ?string
    {
        $g = mb_strtolower(trim($grupo));
        $c = mb_strtolower(trim($campo));

        foreach (self::VEDADOS as $padrao => $motivo) {
            [$pg, $pc] = explode('.', mb_strtolower($padrao), 2);
            if (($pg === '*' || $pg === $g) && ($pc === '*' || $pc === $c)) {
                return $motivo;
            }
        }

        return null;
    }

    /**
     * Confere as correções da tela. Devolve erros por índice da linha
     * (vazio = pode transmitir).
     *
     * @param  list<array{grupo?:string,campo?:string,valor?:string,item?:int|string|null}>  $correcoes
     * @return array<int|string, string>  índice => mensagem; '_' = erro geral
     */
    public static function validar(array $correcoes, int $jaEmitidas = 0): array
    {
        $erros = [];
        if ($jaEmitidas >= self::MAXIMO) {
            $erros['_'] = 'Este CT-e já tem ' . self::MAXIMO . ' cartas de correção — o limite da SEFAZ.';
        }
        if ($correcoes === []) {
            $erros['_'] ??= 'Informe ao menos uma correção.';
        }

        $vistos = [];
        foreach (array_values($correcoes) as $i => $c) {
            $grupo = trim((string) ($c['grupo'] ?? ''));
            $campo = trim((string) ($c['campo'] ?? ''));
            $valor = trim((string) ($c['valor'] ?? ''));

            if ($grupo === '' || $campo === '') {
                $erros[$i] = 'Escolha o campo a corrigir.';

                continue;
            }
            if (! preg_match('/^[A-Za-z][A-Za-z0-9]{1,19}$/', $grupo) || ! preg_match('/^[A-Za-z][A-Za-z0-9]{1,19}$/', $campo)) {
                $erros[$i] = 'Grupo e campo são as tags do XML (só letras e números, até 20).';

                continue;
            }
            if (($motivo = self::vetado($grupo, $campo)) !== null) {
                $erros[$i] = $motivo;

                continue;
            }
            if ($valor === '') {
                $erros[$i] = 'Informe o valor corrigido.';

                continue;
            }
            $def = self::CATALOGO[self::chaveDoCatalogo($grupo, $campo) ?? ''] ?? null;
            $max = $def[3] ?? 500;
            if (mb_strlen($valor) > $max) {
                $erros[$i] = "Este campo vai até {$max} caracteres.";

                continue;
            }
            $item = $c['item'] ?? null;
            $chave = mb_strtolower($grupo . '.' . $campo . '#' . ($item ?? ''));
            if (isset($vistos[$chave])) {
                $erros[$i] = 'Campo repetido — deixe uma linha só.';

                continue;
            }
            $vistos[$chave] = true;
        }

        return $erros;
    }

    /**
     * As correções da CC-e anterior que continuam valendo, mais as novas: a
     * nova vence quando corrige o mesmo campo (mesmo item).
     *
     * @param  list<array{grupo:string,campo:string,valor:string,item?:int|string|null}>  $anteriores
     * @param  list<array{grupo:string,campo:string,valor:string,item?:int|string|null}>  $novas
     * @return list<array{grupo:string,campo:string,valor:string,item:int|string|null}>
     */
    public static function consolidar(array $anteriores, array $novas): array
    {
        $porChave = [];
        foreach ([...$anteriores, ...$novas] as $c) {
            $item = $c['item'] ?? null;
            $item = $item === '' ? null : $item;
            $porChave[mb_strtolower($c['grupo'] . '.' . $c['campo'] . '#' . ($item ?? ''))] = [
                'grupo' => trim($c['grupo']), 'campo' => trim($c['campo']), 'valor' => trim($c['valor']), 'item' => $item,
            ];
        }

        return array_values($porChave);
    }

    /** Chave do catálogo para um grupo/campo, ou null quando é campo livre. */
    public static function chaveDoCatalogo(string $grupo, string $campo): ?string
    {
        foreach (self::CATALOGO as $chave => [$g, $c]) {
            if (strcasecmp($g, $grupo) === 0 && strcasecmp($c, $campo) === 0) {
                return $chave;
            }
        }

        return null;
    }
}
