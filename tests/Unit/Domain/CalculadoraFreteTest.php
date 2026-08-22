<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Operacao\CalculadoraFrete;
use PHPUnit\Framework\TestCase;

/**
 * O frete é a conta que o cliente confere primeiro — errá-la mina a confiança
 * no sistema. Cada base de cálculo, a faixa e os limites mínimo/máximo são
 * testados aqui, não só o caminho feliz.
 */
final class CalculadoraFreteTest extends TestCase
{
    public function testSomaComponentesDeBasesDiferentes(): void
    {
        $itens = [
            ['componente' => 'peso', 'base_calculo' => 'por_ton', 'valor' => 120],
            ['componente' => 'gris', 'base_calculo' => 'percentual_valor', 'valor' => 0.3],
            ['componente' => 'pedagio', 'base_calculo' => 'fixo', 'valor' => 80],
        ];

        $r = CalculadoraFrete::calcular($itens, [
            'peso_kg' => 25000,
            'valor_mercadoria' => 100000,
        ]);

        // 25 t × 120 = 3000; 100000 × 0,3% = 300; fixo 80 → 3380
        $this->assertSame(3380.0, $r['total']);
        $this->assertCount(3, $r['componentes']);
    }

    public function testCadaBaseDeCalculo(): void
    {
        $carga = ['peso_kg' => 1000, 'valor_mercadoria' => 5000, 'volumes' => 4, 'distancia_km' => 200];

        $casos = [
            ['por_kg', 2.0, 2000.0],           // 1000 × 2
            ['por_ton', 50.0, 50.0],           // 1 t × 50
            ['percentual_valor', 10.0, 500.0], // 5000 × 10%
            ['fixo', 300.0, 300.0],
            ['por_km', 1.5, 300.0],            // 200 × 1,5
            ['por_volume', 25.0, 100.0],       // 4 × 25
        ];

        foreach ($casos as [$base, $valor, $esperado]) {
            $r = CalculadoraFrete::calcular([['componente' => 'peso', 'base_calculo' => $base, 'valor' => $valor]], $carga);
            $this->assertSame($esperado, $r['total'], "Base {$base} calculou errado");
        }
    }

    public function testMinimoElevaOSubtotal(): void
    {
        $itens = [['componente' => 'peso', 'base_calculo' => 'por_ton', 'valor' => 120, 'minimo' => 150]];
        $r = CalculadoraFrete::calcular($itens, ['peso_kg' => 500]); // 0,5 t × 120 = 60 < 150

        $this->assertSame(150.0, $r['total']);
    }

    public function testMaximoLimitaOSubtotal(): void
    {
        $itens = [['componente' => 'peso', 'base_calculo' => 'por_kg', 'valor' => 1, 'maximo' => 800]];
        $r = CalculadoraFrete::calcular($itens, ['peso_kg' => 5000]); // 5000 > 800

        $this->assertSame(800.0, $r['total']);
    }

    public function testFaixaFiltraItemForaDaGrandeza(): void
    {
        $itens = [['componente' => 'peso', 'base_calculo' => 'por_kg', 'valor' => 1, 'faixa_de' => 0, 'faixa_ate' => 1000]];

        $dentro = CalculadoraFrete::calcular($itens, ['peso_kg' => 800]);
        $this->assertSame(800.0, $dentro['total']);

        $fora = CalculadoraFrete::calcular($itens, ['peso_kg' => 5000]);
        $this->assertSame(0.0, $fora['total']);
        $this->assertSame([], $fora['componentes']);
    }

    public function testTabelaVaziaDaZero(): void
    {
        $r = CalculadoraFrete::calcular([], ['peso_kg' => 1000]);

        $this->assertSame(0.0, $r['total']);
        $this->assertSame([], $r['componentes']);
    }
}
