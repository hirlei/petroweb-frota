<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Fiscal\RegrasCiot;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * CIOT para Todos: toda viagem remunerada tem CIOT. Errar a modalidade é
 * mandar o frete do TAC por fora da instituição de pagamento — ou travar a
 * transferência entre filiais à toa.
 */
final class RegrasCiotTest extends TestCase
{
    public function testMotoristaTacSempreVaiPelaInstituicao(): void
    {
        $this->assertSame(RegrasCiot::IPEF, RegrasCiot::modalidade('carga_lotacao', true, null, true));
        // Mesmo levando carga própria: há frete pago ao TAC.
        $this->assertSame(RegrasCiot::IPEF, RegrasCiot::modalidade('carga_propria', true, null, true));
    }

    public function testVeiculoDeTerceiroDependeDoDono(): void
    {
        $this->assertSame(RegrasCiot::IPEF, RegrasCiot::modalidade('fracionada', false, '2', false));
        $this->assertSame(RegrasCiot::INFORMADO, RegrasCiot::modalidade('fracionada', false, '1', false));
        $this->assertSame(RegrasCiot::INFORMADO, RegrasCiot::modalidade('fracionada', false, '3', false));
        $this->assertSame(RegrasCiot::INFORMADO, RegrasCiot::modalidade('fracionada', false, null, false));
    }

    public function testFrotaPropriaRegistraNaAnttOuEDispensada(): void
    {
        $this->assertSame(RegrasCiot::ANTT, RegrasCiot::modalidade('carga_lotacao', true, null, false));
        $this->assertSame(RegrasCiot::ANTT, RegrasCiot::modalidade('fracionada', true, null, false));
        foreach (['carga_propria', 'transferencia', 'retorno_vazio'] as $tipo) {
            $this->assertSame(RegrasCiot::DISPENSADO, RegrasCiot::modalidade($tipo, true, null, false), $tipo);
        }
    }

    public function testSoIpefTemPagamentoESoIpefEAnttPassamPeloSistema(): void
    {
        $this->assertTrue(RegrasCiot::temPagamento(RegrasCiot::IPEF));
        $this->assertFalse(RegrasCiot::temPagamento(RegrasCiot::ANTT));
        $this->assertTrue(RegrasCiot::registraPeloSistema(RegrasCiot::ANTT));
        $this->assertFalse(RegrasCiot::registraPeloSistema(RegrasCiot::INFORMADO));
        $this->assertFalse(RegrasCiot::exige(RegrasCiot::DISPENSADO));
        $this->assertTrue(RegrasCiot::exige(RegrasCiot::INFORMADO));
    }

    public function testTravaDoMdfe(): void
    {
        $desde = new DateTimeImmutable('2026-11-23');
        $antes = new DateTimeImmutable('2026-10-02');
        $depois = new DateTimeImmutable('2026-11-23');

        $this->assertSame('bloqueia', RegrasCiot::trava(RegrasCiot::ANTT, false, 2, $antes, $desde), 'homologação já exige');
        $this->assertSame('avisa', RegrasCiot::trava(RegrasCiot::ANTT, false, 1, $antes, $desde));
        $this->assertSame('bloqueia', RegrasCiot::trava(RegrasCiot::ANTT, false, 1, $depois, $desde));
        $this->assertSame('ok', RegrasCiot::trava(RegrasCiot::ANTT, true, 1, $depois, $desde));
        $this->assertSame('ok', RegrasCiot::trava(RegrasCiot::DISPENSADO, false, 2, $depois, $desde));
    }

    public function testDivisaoFechaNoCentavo(): void
    {
        $this->assertSame(['adiantamento' => 2400.0, 'saldo' => 2400.0], RegrasCiot::dividir(4800, 50));
        $this->assertSame(['adiantamento' => 0.0, 'saldo' => 1000.0], RegrasCiot::dividir(1000, 0));
        $d = RegrasCiot::dividir(1000.01, 30);
        $this->assertSame(300.0, $d['adiantamento']);
        $this->assertSame(700.01, $d['saldo']);
        $this->assertSame(1000.01, round($d['adiantamento'] + $d['saldo'], 2));
    }

    public function testDivisaoRecusaPercentualForaDaFaixa(): void
    {
        foreach ([120.0, -1.0] as $pct) {
            try {
                RegrasCiot::dividir(100, $pct);
                $this->assertTrue(false, "aceitou {$pct}%");
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function testPrazoDeQuitacaoEmDiasUteis(): void
    {
        // Sexta 02/10/2026 + 30 dias úteis = sexta 13/11/2026.
        $this->assertSame('2026-11-13', RegrasCiot::prazoMaximo(new DateTimeImmutable('2026-10-02'))->format('Y-m-d'));
        // Sábado conta a partir da segunda.
        $this->assertSame('2026-10-05', RegrasCiot::prazoMaximo(new DateTimeImmutable('2026-10-03'), 1)->format('Y-m-d'));
        $this->assertSame(9, RegrasCiot::diasUteisAte(new DateTimeImmutable('2026-10-02'), new DateTimeImmutable('2026-10-15')));
        $this->assertSame(-1, RegrasCiot::diasUteisAte(new DateTimeImmutable('2026-10-05'), new DateTimeImmutable('2026-10-02')));
        $this->assertSame(0, RegrasCiot::diasUteisAte(new DateTimeImmutable('2026-10-05'), new DateTimeImmutable('2026-10-05')));
        // Venceu na sexta, hoje é sábado: vencido, não "vence hoje".
        $this->assertSame(-1, RegrasCiot::diasUteisAte(new DateTimeImmutable('2026-10-03'), new DateTimeImmutable('2026-10-02')));
    }

    public function testNumero(): void
    {
        $this->assertTrue(RegrasCiot::numeroValido('482177300912'));
        $this->assertFalse(RegrasCiot::numeroValido('4821773009'));
        $this->assertSame('482177300912', RegrasCiot::limpar('4821 7730-0912'));
        $this->assertSame('4821 7730 0912', RegrasCiot::formatar('482177300912'));
        $this->assertSame('', RegrasCiot::formatar(null));
    }
}
