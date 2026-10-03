<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Fiscal\ConferenciaNumeracao;
use PHPUnit\Framework\TestCase;

final class ConferenciaNumeracaoTest extends TestCase
{
    public function testSequenciaCompleta(): void
    {
        $r = ConferenciaNumeracao::conferir([3 => 'autorizado', 1 => 'autorizado', 2 => 'cancelado']);
        $this->assertSame(1, $r['primeiro']);
        $this->assertSame(3, $r['ultimo']);
        $this->assertSame(3, $r['emitidos']);
        $this->assertSame([], $r['faltando']);
    }

    public function testRejeitadoENumeroPulado(): void
    {
        $r = ConferenciaNumeracao::conferir([410 => 'autorizado', 411 => 'rejeitado', 413 => 'encerrado']);
        $this->assertSame(2, $r['emitidos']);
        $this->assertSame(2, $r['total_faltando']);
        $this->assertSame(['numero' => 411, 'motivo' => 'Rejeitado, não reenviado'], $r['faltando'][0]);
        $this->assertSame(['numero' => 412, 'motivo' => 'Número não usado'], $r['faltando'][1]);
    }

    public function testLimiteDaLista(): void
    {
        $r = ConferenciaNumeracao::conferir([1 => 'autorizado', 100 => 'autorizado'], 5);
        $this->assertCount(5, $r['faltando']);
        $this->assertSame(98, $r['total_faltando']);
    }

    public function testVazio(): void
    {
        $r = ConferenciaNumeracao::conferir([]);
        $this->assertNull($r['primeiro']);
        $this->assertSame(0, $r['emitidos']);
    }
}
