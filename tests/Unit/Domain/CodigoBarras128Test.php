<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Fiscal\CodigoBarras128;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * A chave no DACTE/DAMDFE tem de ler no leitor: CODE-128C, start C, checksum
 * módulo 103 e stop. Errar um padrão é código de barras que não lê.
 */
final class CodigoBarras128Test extends TestCase
{
    public function testEstruturaDaChaveDe44Digitos(): void
    {
        $chave = '35261023456789000110570010000012951000012950';
        $w = CodigoBarras128::larguras($chave);

        // start + 22 pares + checksum = 24 símbolos de 6 larguras, + stop de 7.
        $this->assertSame(24 * 6 + 7, strlen($w));
        $this->assertSame('211232', substr($w, 0, 6), 'start C');
        $this->assertSame('2331112', substr($w, -7), 'stop');
        // Cada símbolo soma 11 módulos; o stop, 13.
        $this->assertSame(24 * 11 + 13, array_sum(str_split($w)));
    }

    public function testChecksumConhecido(): void
    {
        // "1234": 105 + 12×1 + 34×2 = 185 → 185 mod 103 = 82.
        $this->assertSame(82, CodigoBarras128::checksum('1234'));
        $w = CodigoBarras128::larguras('1234');
        $this->assertSame('211232' . '112232' . '131123' . '121241' . '2331112', $w);
    }

    public function testSvgTemUmRetanguloPorBarra(): void
    {
        $svg = CodigoBarras128::svg('1234');
        // 4 símbolos × 3 barras + stop com 4 barras.
        $this->assertSame(16, substr_count($svg, '<rect'));
        $this->assertTrue(str_starts_with($svg, '<svg'));
    }

    public function testRecusaEntradaInvalida(): void
    {
        foreach (['', '123', '12a4'] as $ruim) {
            try {
                CodigoBarras128::larguras($ruim);
                $this->assertTrue(false, "aceitou '{$ruim}'");
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }
}
