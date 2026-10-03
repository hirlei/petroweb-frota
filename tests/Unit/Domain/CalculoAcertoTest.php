<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Operacao\CalculoAcerto;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * O acerto é o que o motorista confere com a calculadora na mão: a glosa não
 * pode ser descontada duas vezes, e o saldo tem de fechar no centavo.
 */
final class CalculoAcertoTest extends TestCase
{
    public function testExemploDoMockup(): void
    {
        $r = CalculoAcerto::calcular(
            [1000.00, 500.00],
            [
                ['valor' => 412.30, 'forma' => 'adiantamento', 'conferencia' => 'aceita'],
                ['valor' => 86.00, 'forma' => 'adiantamento', 'conferencia' => 'aceita'],
                ['valor' => 180.00, 'forma' => 'adiantamento', 'conferencia' => 'aceita'],
                ['valor' => 250.00, 'forma' => 'reembolso', 'conferencia' => 'pendente'],
                ['valor' => 256.00, 'forma' => 'adiantamento', 'conferencia' => 'glosa'],
            ],
            3, 120.00, 0, 5260.00,
        );

        $this->assertSame(1500.0, $r['adiantado']);
        $this->assertSame(678.3, $r['gasto_adiantamento']);
        $this->assertSame(821.7, $r['ficou']);
        $this->assertSame(256.0, $r['glosado']);
        $this->assertSame(360.0, $r['diarias']);
        $this->assertSame(-461.7, $r['saldo'], 'motorista devolve; a glosa não é descontada de novo');
        $this->assertSame(1, $r['pendentes']);
    }

    public function testBolsoAceitoDiariaEComissaoViramCreditoDoMotorista(): void
    {
        $r = CalculoAcerto::calcular(
            [600.00],
            [
                ['valor' => 642.10, 'forma' => 'adiantamento', 'conferencia' => 'aceita'],
                ['valor' => 30.00, 'forma' => 'reembolso', 'conferencia' => 'aceita'],
                ['valor' => 99.99, 'forma' => 'reembolso', 'conferencia' => 'glosa'],
                ['valor' => 500.00, 'forma' => 'empresa', 'conferencia' => 'aceita'],
                ['valor' => 80.00, 'forma' => 'cartao', 'conferencia' => 'aceita'],
            ],
            2, 100.00, 2.5, 3140.00,
        );

        // ficou = 600 − 642,10 = −42,10; crédito = 30 + 200 + 78,50 = 308,50.
        $this->assertSame(-42.1, $r['ficou']);
        $this->assertSame(78.5, $r['comissao']);
        $this->assertSame(308.5, $r['credito']);
        $this->assertSame(350.6, $r['saldo']);
        $this->assertSame(2, $r['ignoradas'], 'empresa e cartão não entram');
        $this->assertSame(99.99, $r['glosado']);
    }

    public function testSemNadaOSaldoEZero(): void
    {
        $r = CalculoAcerto::calcular([], [], 0, 0, 0, 0);
        $this->assertSame(0.0, $r['saldo']);
        $this->assertSame(0, $r['pendentes']);
    }

    public function testDias(): void
    {
        $this->assertSame(3, CalculoAcerto::dias(new DateTimeImmutable('2026-10-01 06:02'), new DateTimeImmutable('2026-10-03 17:40')));
        $this->assertSame(1, CalculoAcerto::dias(new DateTimeImmutable('2026-10-01 06:00'), new DateTimeImmutable('2026-10-01 20:00')));
        $this->assertSame(1, CalculoAcerto::dias(new DateTimeImmutable('2026-10-03'), new DateTimeImmutable('2026-10-01')));
    }

    public function testRecusaComissaoAcimaDe100(): void
    {
        try {
            CalculoAcerto::calcular([], [], 1, 10, 150, 100);
            $this->assertTrue(false, 'aceitou 150%');
        } catch (\InvalidArgumentException) {
            $this->assertTrue(true);
        }
    }
}
