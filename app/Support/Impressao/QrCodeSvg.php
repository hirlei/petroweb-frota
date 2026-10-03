<?php

declare(strict_types=1);

namespace App\Support\Impressao;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Throwable;

/**
 * QR Code como SVG de retângulos (DACTE e DAMDFE).
 *
 * Usa só o codificador do bacon/bacon-qr-code e desenha os módulos à mão: o
 * dompdf entende retângulo simples melhor que os paths dos renderers prontos, e
 * não depende de Imagick nem GD. Margem de 2 módulos (zona de silêncio mínima
 * pedida pelo MOC é garantida pelo espaço em volta no leiaute).
 */
final class QrCodeSvg
{
    public static function gerar(string $conteudo, int $margem = 2): ?string
    {
        if (! class_exists(Encoder::class)) {
            return null;
        }

        try {
            $matriz = Encoder::encode($conteudo, ErrorCorrectionLevel::M(), 'ISO-8859-1')->getMatrix();
        } catch (Throwable) {
            return null;
        }

        $n = $matriz->getWidth();
        $total = $n + 2 * $margem;
        $rects = '';

        for ($y = 0; $y < $n; $y++) {
            $x = 0;
            while ($x < $n) {
                if ($matriz->get($x, $y) !== 1) {
                    $x++;

                    continue;
                }
                $inicio = $x;
                while ($x < $n && $matriz->get($x, $y) === 1) {
                    $x++;
                }
                $rects .= sprintf('<rect x="%d" y="%d" width="%d" height="1" fill="#000"/>', $inicio + $margem, $y + $margem, $x - $inicio);
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 %1$d %1$d" shape-rendering="crispEdges"><rect width="%1$d" height="%1$d" fill="#fff"/>%2$s</svg>',
            $total, $rects,
        );
    }
}
