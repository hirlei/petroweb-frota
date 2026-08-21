<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Fiscal\Enums\CategoriaCombinacaoVeicular as Categoria;
use App\Domain\Fiscal\Enums\NaturezaCarga;
use App\Domain\Fiscal\Enums\TipoCarga;
use PHPUnit\Framework\TestCase;

/**
 * Derivações fiscais puras — sem banco, sem Laravel.
 *
 * São as regras em que um erro silencioso vira XML rejeitado pela SEFAZ dias
 * depois, com o caminhão na estrada. Por isso o teste é exaustivo em vez de
 * amostral: percorre TODOS os casos possíveis.
 */
final class DerivacaoFiscalTest extends TestCase
{
    // ── categCombVeic ──────────────────────────────────────────────────────

    public function testCategoriaNaoUsaOsCodigosInexistentes(): void
    {
        // 03, 05 e 09 não existem na tabela da ANTT. Se algum dia alguém
        // "completar a sequência", este teste quebra.
        $codigos = Categoria::codigosValidos();

        $this->assertNotContains('03', $codigos);
        $this->assertNotContains('05', $codigos);
        $this->assertNotContains('09', $codigos);
        $this->assertCount(10, $codigos);
    }

    public function testCategoriaDerivaDosEixos(): void
    {
        $esperado = [
            1 => '02', 2 => '02', 3 => '04', 4 => '06', 5 => '07',
            6 => '08', 7 => '10', 8 => '11', 9 => '12', 10 => '13',
            11 => '14', 14 => '14',
        ];

        foreach ($esperado as $eixos => $codigo) {
            $this->assertSame(
                $codigo,
                Categoria::paraEixos($eixos)->value,
                "Composição de {$eixos} eixos deveria gerar categCombVeic {$codigo}",
            );
        }
    }

    public function testCategoriaNuncaProduzCodigoInvalido(): void
    {
        $validos = Categoria::codigosValidos();

        for ($eixos = 1; $eixos <= 30; $eixos++) {
            $this->assertContains(
                Categoria::paraEixos($eixos)->value,
                $validos,
                "categCombVeic gerado para {$eixos} eixos não existe na tabela da ANTT",
            );
        }
    }

    /** As configurações reais do documento 05, seção 3.3. */
    public function testCategoriaDasConfiguracoesReais(): void
    {
        $cvc = [
            'Toco' => [2, '02'],  'Truck' => [3, '04'],
            'Bitruck' => [4, '06'], 'Carreta simples 3 eixos' => [5, '07'],
            'Carreta LS' => [6, '08'], 'Romeu e Julieta' => [6, '08'],
            'Bitrem 7 eixos' => [7, '10'], 'Bitrem 8 eixos' => [8, '11'],
            'Rodotrem 9 eixos' => [9, '12'], 'Tritrem' => [9, '12'],
        ];

        foreach ($cvc as $nome => [$eixos, $codigo]) {
            $this->assertSame($codigo, Categoria::paraEixos($eixos)->value, $nome);
        }
    }

    // ── tpCarga ────────────────────────────────────────────────────────────

    public function testTipoCargaDerivaDaNaturezaMaisPericulosidade(): void
    {
        $casos = [
            [NaturezaCarga::GranelSolido,   false, '01'],
            [NaturezaCarga::GranelSolido,   true,  '07'],
            [NaturezaCarga::GranelLiquido,  false, '02'],
            [NaturezaCarga::GranelLiquido,  true,  '08'],
            [NaturezaCarga::Frigorificada,  false, '03'],
            [NaturezaCarga::Frigorificada,  true,  '09'],
            [NaturezaCarga::Conteinerizada, false, '04'],
            [NaturezaCarga::Conteinerizada, true,  '10'],
            [NaturezaCarga::CargaGeralSolta, false, '05'],
            [NaturezaCarga::CargaGeralSolta, true,  '11'],
            [NaturezaCarga::Neogranel,      false, '06'],
            [NaturezaCarga::GranelPressurizado, false, '12'],
        ];

        foreach ($casos as [$natureza, $perigosa, $codigo]) {
            $this->assertSame(
                $codigo,
                TipoCarga::derivar($natureza, $perigosa)->value,
                sprintf('%s + perigosa=%s', $natureza->value, $perigosa ? 'sim' : 'não'),
            );
        }
    }

    /**
     * O bug que a derivação existe para impedir: marcar "carga geral" com
     * produto perigoso e o sistema enviar 05 em vez de 11.
     */
    public function testCargaGeralComProdutoPerigosoNaoEnviaCincoZero(): void
    {
        $tipo = TipoCarga::derivar(NaturezaCarga::CargaGeralSolta, true);

        $this->assertNotSame('05', $tipo->value);
        $this->assertSame('11', $tipo->value);
        $this->assertTrue($tipo->perigosa());
    }

    public function testTodaNaturezaDerivaParaUmTipoValido(): void
    {
        foreach (NaturezaCarga::cases() as $natureza) {
            foreach ([true, false] as $perigosa) {
                $tipo = TipoCarga::derivar($natureza, $perigosa);
                $this->assertInstanceOf(TipoCarga::class, $tipo);
            }
        }
    }

    /** Diesel a granel — o exemplo do mockup de produto. */
    public function testOleoDieselEmTanqueGeraOitoZero(): void
    {
        $this->assertSame(
            '08',
            TipoCarga::derivar(NaturezaCarga::GranelLiquido, true)->value,
        );
    }
}
