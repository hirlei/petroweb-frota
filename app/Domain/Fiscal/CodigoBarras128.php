<?php

declare(strict_types=1);

namespace App\Domain\Fiscal;

use InvalidArgumentException;

/**
 * Código de barras CODE-128 conjunto C — o que o MOC exige para a chave de
 * acesso no DACTE e no DAMDFE (44 dígitos = 22 pares).
 *
 * Lógica pura: devolve as larguras dos módulos (barra, espaço, barra…) e um
 * SVG de retângulos — sem biblioteca, para o PDF não depender de extensão.
 */
final class CodigoBarras128
{
    /** Padrões 0–105 (cada um soma 11 módulos). 103–105 = Start A, B, C. */
    private const PADROES = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232',
    ];

    private const START_C = 105;

    private const STOP = '2331112';

    /**
     * Larguras em módulos, alternando barra/espaço, começando por barra.
     *
     * @throws InvalidArgumentException se não forem só dígitos em quantidade par
     */
    public static function larguras(string $digitos): string
    {
        if ($digitos === '' || preg_match('/^\d+$/', $digitos) !== 1 || strlen($digitos) % 2 !== 0) {
            throw new InvalidArgumentException('O código 128C precisa de dígitos em quantidade par.');
        }

        $codigos = [self::START_C];
        $soma = self::START_C;
        foreach (str_split($digitos, 2) as $i => $par) {
            $valor = (int) $par;
            $codigos[] = $valor;
            $soma += $valor * ($i + 1);
        }
        $codigos[] = $soma % 103;

        $saida = '';
        foreach ($codigos as $c) {
            $saida .= self::PADROES[$c];
        }

        return $saida . self::STOP;
    }

    /** Dígito de verificação do símbolo (o que entra antes do stop). */
    public static function checksum(string $digitos): int
    {
        $soma = self::START_C;
        foreach (str_split($digitos, 2) as $i => $par) {
            $soma += (int) $par * ($i + 1);
        }

        return $soma % 103;
    }

    /** SVG de retângulos pretos; largura total em módulos × $modulo. */
    public static function svg(string $digitos, float $modulo = 1.0, int $altura = 40): string
    {
        $larguras = self::larguras($digitos);
        $x = 0.0;
        $rects = '';
        foreach (str_split($larguras) as $i => $w) {
            $lw = (int) $w * $modulo;
            if ($i % 2 === 0) {
                $rects .= sprintf('<rect x="%.2F" y="0" width="%.2F" height="%d" fill="#000"/>', $x, $lw, $altura);
            }
            $x += $lw;
        }

        return sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%.2F" height="%d" viewBox="0 0 %.2F %d" preserveAspectRatio="none" shape-rendering="crispEdges">%s</svg>', $x, $altura, $x, $altura, $rects);
    }
}
