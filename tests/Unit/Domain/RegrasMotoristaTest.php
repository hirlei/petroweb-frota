<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Frota\RegrasMotorista;
use PHPUnit\Framework\TestCase;

/**
 * A RN-12 é a regra mais cara de errar do sistema: telas de jornada para
 * agregado viram prova de vínculo empregatício. Por isso ela é testada
 * exaustivamente, e não só no caminho feliz.
 */
final class RegrasMotoristaTest extends TestCase
{
    public function testApenasClTemJornada(): void
    {
        $this->assertTrue(RegrasMotorista::controlaJornada('clt'));

        foreach (['agregado', 'autonomo', 'terceiro'] as $vinculo) {
            $this->assertFalse(
                RegrasMotorista::controlaJornada($vinculo),
                "RN-12 violada: {$vinculo} não pode ter jornada"
            );
        }
    }

    public function testVinculoDesconhecidoNaoGanhaJornadaPorAcidente(): void
    {
        foreach (['', 'CLT', 'pj', 'estagiario', 'clt '] as $lixo) {
            $this->assertFalse(RegrasMotorista::controlaJornada($lixo));
        }
    }

    public function testTacEhAgregadoOuAutonomo(): void
    {
        $this->assertTrue(RegrasMotorista::ehTac('agregado'));
        $this->assertTrue(RegrasMotorista::ehTac('autonomo'));
        $this->assertFalse(RegrasMotorista::ehTac('clt'));
        // Terceiro é OUTRA transportadora: há subcontrato, não TAC.
        $this->assertFalse(RegrasMotorista::ehTac('terceiro'));
    }

    public function testCiotAcompanhaTac(): void
    {
        foreach (RegrasMotorista::VINCULOS as $vinculo) {
            $this->assertSame(
                RegrasMotorista::ehTac($vinculo),
                RegrasMotorista::exigeCiot($vinculo),
                "CIOT deve seguir exatamente a condição de TAC ({$vinculo})"
            );
        }
    }

    public function testJornadaECiotSaoMutuamenteExclusivos(): void
    {
        foreach (RegrasMotorista::VINCULOS as $vinculo) {
            $this->assertFalse(
                RegrasMotorista::controlaJornada($vinculo) && RegrasMotorista::exigeCiot($vinculo),
                "Nenhum vínculo pode ter jornada E CIOT ({$vinculo})"
            );
        }
    }

    public function testToxicologicoExigidoNasCategoriasCDE(): void
    {
        foreach (['C', 'D', 'E', 'AC', 'AE', 'ACE'] as $categoria) {
            $this->assertTrue(
                RegrasMotorista::exigeToxicologico($categoria),
                "Categoria {$categoria} exige toxicológico"
            );
        }

        foreach (['A', 'B', 'AB'] as $categoria) {
            $this->assertFalse(RegrasMotorista::exigeToxicologico($categoria));
        }
    }

    public function testCategoriaMinimaSobeParaEComUnidadeAcoplada(): void
    {
        $this->assertSame('E', RegrasMotorista::categoriaMinima(1, 23_000));
        $this->assertSame('E', RegrasMotorista::categoriaMinima(2, 74_000));
        // Truck sem reboque: C basta.
        $this->assertSame('C', RegrasMotorista::categoriaMinima(0, 23_000));
        // Utilitário leve.
        $this->assertSame('B', RegrasMotorista::categoriaMinima(0, 3_500));
        // Exatamente 6.000 kg ainda é B — o limite legal é "acima de".
        $this->assertSame('B', RegrasMotorista::categoriaMinima(0, 6_000));
    }

    public function testHierarquiaDeCategorias(): void
    {
        $this->assertTrue(RegrasMotorista::categoriaAtende('E', 'E'));
        $this->assertTrue(RegrasMotorista::categoriaAtende('AE', 'E'));
        $this->assertTrue(RegrasMotorista::categoriaAtende('E', 'C'));
        $this->assertFalse(RegrasMotorista::categoriaAtende('D', 'E'));
        $this->assertFalse(RegrasMotorista::categoriaAtende('AB', 'C'));
        $this->assertTrue(RegrasMotorista::categoriaAtende('C', 'B'));
        // Minúscula não pode furar a regra.
        $this->assertTrue(RegrasMotorista::categoriaAtende('e', 'E'));
    }

    public function testVinculoValidoEspelhaOCheckDoBanco(): void
    {
        foreach (RegrasMotorista::VINCULOS as $vinculo) {
            $this->assertTrue(RegrasMotorista::vinculoValido($vinculo));
        }

        foreach (['pj', 'cooperado', 'CLT', ''] as $invalido) {
            $this->assertFalse(RegrasMotorista::vinculoValido($invalido));
        }
    }
}
