<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Operacao\CustoViagem;
use PHPUnit\Framework\TestCase;

final class CustoViagemTest extends TestCase
{
    public function testExemploDoMockup(): void
    {
        $r = CustoViagem::somar([
            'combustivel' => [1412.40, 1017.70],
            'pedagio' => [412.30],
            'motorista' => [86.00, 180.00, 360.00],
            'manutencao' => [1180.00],
        ]);
        $this->assertSame(2430.1, $r['componentes']['combustivel']['valor']);
        $this->assertSame(626.0, $r['componentes']['motorista']['valor']);
        $this->assertSame(0.0, $r['componentes']['terceiro']['valor']);
        $this->assertSame(4648.4, $r['total']);

        $res = CustoViagem::resultado(5260.00, $r['total'], 438.0);
        $this->assertSame(611.6, $res['margem']);
        $this->assertSame(11.6, $res['percentual']);
        $this->assertSame(10.6128, $res['custo_km']);
    }

    public function testDigitadoSoValeSemLancamento(): void
    {
        $r = CustoViagem::somar(['combustivel' => [100.0]], ['combustivel' => 999, 'manutencao' => 50]);
        $this->assertSame(100.0, $r['componentes']['combustivel']['valor']);
        $this->assertFalse($r['componentes']['combustivel']['digitado']);
        $this->assertSame(50.0, $r['componentes']['manutencao']['valor']);
        $this->assertTrue($r['componentes']['manutencao']['digitado']);
        $this->assertSame(150.0, $r['total']);
    }

    public function testSemKmESemReceita(): void
    {
        $res = CustoViagem::resultado(0, 100, null);
        $this->assertNull($res['percentual']);
        $this->assertNull($res['custo_km']);
        $this->assertSame(-100.0, $res['margem']);
    }

    public function testRateioFechaNoCentavo(): void
    {
        $r = CustoViagem::ratear(100.00, [7 => 1000.0, 9 => 2000.0]);
        $this->assertSame(33.33, $r[7]);
        $this->assertSame(66.67, $r[9]);
        $this->assertSame(100.0, round($r[7] + $r[9], 2));

        $this->assertSame(['' => 50.0], CustoViagem::ratear(50.0, []));
    }
}
