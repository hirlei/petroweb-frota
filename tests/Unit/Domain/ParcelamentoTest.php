<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Financeiro\Encargos;
use App\Domain\Financeiro\Parcelamento;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * A fatura é o que o cliente confere centavo por centavo: a soma das parcelas
 * tem de bater exatamente com o total, e os vencimentos com a condição.
 */
final class ParcelamentoTest extends TestCase
{
    public function testLeACondicaoComoOFinanceiroEscreve(): void
    {
        $this->assertSame([28, 56], Parcelamento::prazos('28/56'));
        $this->assertSame([30, 60, 90], Parcelamento::prazos(' 30 / 60, 90 '));
        $this->assertSame([0], Parcelamento::prazos('0'));
        $this->assertSame('28/56', Parcelamento::condicao([28, 56]));
        $this->assertSame('À vista', Parcelamento::rotulo([0]));
        $this->assertSame('28/56 dias', Parcelamento::rotulo([28, 56]));
    }

    public function testRecusaCondicaoInvalida(): void
    {
        foreach (['', 'trinta', '60/30', '30/30', '400', '-5'] as $ruim) {
            $falhou = false;
            try {
                Parcelamento::prazos($ruim);
            } catch (InvalidArgumentException) {
                $falhou = true;
            }
            $this->assertTrue($falhou, "Deveria recusar “{$ruim}”");
        }
    }

    public function testSomaDasParcelasEhExatamenteOTotal(): void
    {
        $emissao = new DateTimeImmutable('2026-10-02');
        $p = Parcelamento::dividir(100.00, [30, 60, 90], $emissao);

        $this->assertCount(3, $p);
        // 10000 centavos / 3 = 3333 + sobra 1 na primeira
        $this->assertSame(33.34, $p[0]['valor']);
        $this->assertSame(33.33, $p[1]['valor']);
        $this->assertSame(33.33, $p[2]['valor']);
        $this->assertSame(10000, (int) round(array_sum(array_column($p, 'valor')) * 100));
    }

    public function testVencimentosSeguemOsPrazos(): void
    {
        $p = Parcelamento::dividir(21460.00, [28, 56], new DateTimeImmutable('2026-10-02'));

        $this->assertSame('2026-10-30', $p[0]['vencimento']->format('Y-m-d'));
        $this->assertSame('2026-11-27', $p[1]['vencimento']->format('Y-m-d'));
        $this->assertSame(10730.0, $p[0]['valor']);
        $this->assertSame(2, $p[1]['parcelas']);
    }

    public function testAVistaVenceNaEmissao(): void
    {
        $p = Parcelamento::dividir(11420.00, [0], new DateTimeImmutable('2026-10-01'));

        $this->assertCount(1, $p);
        $this->assertSame('2026-10-01', $p[0]['vencimento']->format('Y-m-d'));
    }

    public function testEncargosSoComAtraso(): void
    {
        $semAtraso = Encargos::calcular(6390.00, new DateTimeImmutable('2026-10-10'), new DateTimeImmutable('2026-10-10'), 2.0, 1.0);
        $this->assertSame(0.0, $semAtraso['total']);

        // 19 dias: multa 2% = 127,80; juros 1% a.m. = 6390 × 0,01 ÷ 30 × 19 = 40,47
        $atraso = Encargos::calcular(6390.00, new DateTimeImmutable('2026-09-13'), new DateTimeImmutable('2026-10-02'), 2.0, 1.0);
        $this->assertSame(19, $atraso['dias_atraso']);
        $this->assertSame(127.8, $atraso['multa']);
        $this->assertSame(40.47, $atraso['juros']);
        $this->assertSame(168.27, $atraso['total']);
    }

    public function testEncargosSemConfiguracaoNaoCobramNada(): void
    {
        $r = Encargos::calcular(1000.00, new DateTimeImmutable('2026-09-01'), new DateTimeImmutable('2026-10-01'), null, null);

        $this->assertSame(30, $r['dias_atraso']);
        $this->assertSame(0.0, $r['total']);
    }
}
