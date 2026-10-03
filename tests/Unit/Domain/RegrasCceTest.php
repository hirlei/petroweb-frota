<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Fiscal\RegrasCce;
use PHPUnit\Framework\TestCase;

/**
 * A CC-e não pode virar porta dos fundos para mudar valor, imposto ou quem é
 * parte do CT-e — o bloqueio tem de acontecer antes de transmitir.
 */
final class RegrasCceTest extends TestCase
{
    public function testCamposDoCatalogoSaoCorrigiveis(): void
    {
        foreach (RegrasCce::CATALOGO as $chave => [$grupo, $campo]) {
            $this->assertNull(RegrasCce::vetado($grupo, $campo), "{$chave} deveria ser corrigível");
        }
    }

    public function testVedados(): void
    {
        $this->assertNotNull(RegrasCce::vetado('vPrest', 'vTPrest'));
        $this->assertNotNull(RegrasCce::vetado('ICMS00', 'vBC'));
        $this->assertNotNull(RegrasCce::vetado('rem', 'CNPJ'));
        $this->assertNotNull(RegrasCce::vetado('dest', 'ie'), 'sem diferenciar maiúsculas');
        $this->assertNotNull(RegrasCce::vetado('ide', 'dhEmi'));
        $this->assertNotNull(RegrasCce::vetado('ide', 'CFOP'));
        $this->assertNotNull(RegrasCce::vetado('enderDest', 'UF'));
        $this->assertNotNull(RegrasCce::vetado('infNFe', 'chave'));
        $this->assertNotNull(RegrasCce::vetado('toma3', 'toma'));
        $this->assertNotNull(RegrasCce::vetado('infQ', 'qCarga'));
        $this->assertNull(RegrasCce::vetado('enderDest', 'xMun'), 'município do endereço não é UF');
    }

    public function testValidar(): void
    {
        $ok = [['grupo' => 'infCarga', 'campo' => 'proPred', 'valor' => 'Fertilizante NPK']];
        $this->assertSame([], RegrasCce::validar($ok));

        $erros = RegrasCce::validar([
            ['grupo' => 'infCarga', 'campo' => 'proPred', 'valor' => 'A'],
            ['grupo' => 'vPrest', 'campo' => 'vTPrest', 'valor' => '10'],
            ['grupo' => 'compl', 'campo' => 'xObs', 'valor' => '  '],
            ['grupo' => 'infCarga', 'campo' => 'PROPRED', 'valor' => 'B'],
            ['grupo' => 'x y', 'campo' => 'z', 'valor' => 'B'],
        ]);
        $this->assertArrayNotHasKey(0, $erros);
        $this->assertStringContainsString('Valor', $erros[1]);
        $this->assertSame('Informe o valor corrigido.', $erros[2]);
        $this->assertStringContainsString('repetido', $erros[3]);
        $this->assertArrayHasKey(4, $erros);
    }

    public function testTamanhoDoCampoDoCatalogo(): void
    {
        $erros = RegrasCce::validar([['grupo' => 'rodo', 'campo' => 'RNTRC', 'valor' => '123456789']]);
        $this->assertStringContainsString('8 caracteres', $erros[0]);
        $this->assertSame([], RegrasCce::validar([['grupo' => 'rodo', 'campo' => 'RNTRC', 'valor' => '12345678']]));
    }

    public function testLimiteDeVinte(): void
    {
        $ok = [['grupo' => 'compl', 'campo' => 'xObs', 'valor' => 'Texto']];
        $this->assertSame([], RegrasCce::validar($ok, 19));
        $this->assertArrayHasKey('_', RegrasCce::validar($ok, 20));
        $this->assertArrayHasKey('_', RegrasCce::validar([]));
    }

    public function testConsolidarANovaVence(): void
    {
        $r = RegrasCce::consolidar(
            [['grupo' => 'infCarga', 'campo' => 'proPred', 'valor' => 'Velho'], ['grupo' => 'compl', 'campo' => 'xObs', 'valor' => 'Obs']],
            [['grupo' => 'infCarga', 'campo' => 'proPred', 'valor' => 'Novo']],
        );
        $this->assertCount(2, $r);
        $this->assertSame('Novo', $r[0]['valor']);
        $this->assertSame('Obs', $r[1]['valor']);
    }
}
