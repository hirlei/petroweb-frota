<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Rastreamento\Provedores\ProvedorGenerico;
use PHPUnit\Framework\TestCase;

/**
 * O provedor genérico é a porta de entrada do webhook de GPS — se ele normalizar
 * errado, a posição não chega ao mapa. Cobrimos formato, placa e campos opcionais.
 */
final class ProvedorGenericoTest extends TestCase
{
    public function testNormalizaFormatoComChavePosicoes(): void
    {
        $r = (new ProvedorGenerico())->normalizar([
            'posicoes' => [
                ['placa' => 'okz-1a34', 'latitude' => -12.5, 'longitude' => -45.5, 'velocidade_kmh' => 78, 'ignicao' => true],
            ],
        ]);

        $this->assertCount(1, $r);
        $this->assertSame('OKZ1A34', $r[0]['placa']); // normaliza para maiúsculas sem hífen
        $this->assertSame(-12.5, $r[0]['latitude']);
        $this->assertSame(78.0, $r[0]['velocidade_kmh']);
        $this->assertTrue($r[0]['ignicao']);
    }

    public function testAceitaListaPura(): void
    {
        $r = (new ProvedorGenerico())->normalizar([
            ['placa' => 'ABC1D23', 'latitude' => -1, 'longitude' => -2],
        ]);

        $this->assertCount(1, $r);
        $this->assertNull($r[0]['velocidade_kmh']);
        $this->assertNull($r[0]['ignicao']);
    }

    public function testDescartaPosicaoSemCoordenadaOuPlaca(): void
    {
        $r = (new ProvedorGenerico())->normalizar([
            'posicoes' => [
                ['placa' => 'ABC1D23'], // sem lat/lng
                ['latitude' => -1, 'longitude' => -2], // sem placa
                ['placa' => 'XYZ9Z99', 'latitude' => -3, 'longitude' => -4], // ok
            ],
        ]);

        $this->assertCount(1, $r);
        $this->assertSame('XYZ9Z99', $r[0]['placa']);
    }

    public function testAceitaCampoVelocidadeAlternativo(): void
    {
        $r = (new ProvedorGenerico())->normalizar([
            ['placa' => 'ABC1D23', 'latitude' => -1, 'longitude' => -2, 'velocidade' => 90],
        ]);

        $this->assertSame(90.0, $r[0]['velocidade_kmh']);
    }
}
