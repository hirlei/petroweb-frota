<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Fiscal\Enums\CategoriaCombinacaoVeicular;
use App\Domain\Fiscal\Enums\NaturezaCarga;
use App\Domain\Fiscal\Enums\TipoCarga;
use App\Domain\Frota\CatalogoPadrao;
use PHPUnit\Framework\TestCase;

/**
 * Confere o catálogo que vai para o banco ANTES de ele ir.
 *
 * Um `tpCar` digitado errado no seed não estoura em lugar nenhum: vira MDF-e
 * rejeitado meses depois, num cliente, num sábado. Estes testes são o único
 * momento barato de pegar isso.
 */
final class CatalogoPadraoTest extends TestCase
{
    public function testNaturezasCobremOVocabularioFiscalInteiro(): void
    {
        $codigos = array_column(CatalogoPadrao::naturezas(), 'codigo');

        $this->assertCount(10, $codigos);
        $this->assertSame(count($codigos), count(array_unique($codigos)), 'Há código repetido');

        foreach (NaturezaCarga::cases() as $caso) {
            $this->assertTrue(
                in_array($caso->value, $codigos, true),
                "A natureza {$caso->value} existe no enum e falta no catálogo"
            );
        }
    }

    public function testTodaNaturezaApontaParaUmTpCargaValido(): void
    {
        foreach (CatalogoPadrao::naturezas() as $natureza) {
            $this->assertNotNull(
                TipoCarga::tryFrom($natureza['tp_carga_base']),
                "tp_carga_base inválido em {$natureza['codigo']}: {$natureza['tp_carga_base']}"
            );
        }
    }

    /**
     * O catálogo não pode divergir da derivação do domínio — se divergir, a
     * tela mostraria uma coisa e o XML sairia com outra.
     */
    public function testTpCargaBaseBateComADerivacaoDoDominio(): void
    {
        foreach (CatalogoPadrao::naturezas() as $natureza) {
            $enum = NaturezaCarga::from($natureza['codigo']);

            $this->assertSame(
                TipoCarga::derivar($enum, false)->value,
                $natureza['tp_carga_base'],
                "Divergência em {$natureza['codigo']}"
            );
        }
    }

    public function testFlagsDaNaturezaBatemComOEnum(): void
    {
        foreach (CatalogoPadrao::naturezas() as $natureza) {
            $enum = NaturezaCarga::from($natureza['codigo']);

            $this->assertSame($enum->exigeAet(), $natureza['exige_aet'], "AET em {$natureza['codigo']}");
            $this->assertSame($enum->exigeGta(), $natureza['exige_gta'], "GTA em {$natureza['codigo']}");
            $this->assertSame(
                $enum->exigeTemperaturaControlada(),
                $natureza['exige_temperatura'],
                "Temperatura em {$natureza['codigo']}"
            );
        }
    }

    public function testCarroceriasSao19ComTpCarValido(): void
    {
        $carrocerias = CatalogoPadrao::carrocerias();
        $codigos = array_column($carrocerias, 'codigo');

        $this->assertCount(19, $carrocerias);
        $this->assertSame(count($codigos), count(array_unique($codigos)));

        foreach ($carrocerias as $c) {
            $this->assertTrue(
                in_array($c['tp_car_fiscal'], ['00', '01', '02', '03', '04', '05'], true),
                "tpCar inválido em {$c['codigo']}: {$c['tp_car_fiscal']}"
            );
        }
    }

    public function testTodaCarroceriaApontaParaNaturezaExistente(): void
    {
        $naturezas = array_column(CatalogoPadrao::naturezas(), 'codigo');

        foreach (CatalogoPadrao::carrocerias() as $c) {
            $this->assertTrue(
                in_array($c['natureza_padrao'], $naturezas, true),
                "{$c['codigo']} aponta para natureza inexistente: {$c['natureza_padrao']}"
            );
        }
    }

    /**
     * A convenção de tanque e silo está fixada de propósito — eles não têm
     * `tpCar` próprio. Se alguém mudar, que mude conscientemente, quebrando
     * este teste.
     */
    public function testConvencaoFixadaParaTanqueESilo(): void
    {
        $por = array_column(CatalogoPadrao::carrocerias(), null, 'codigo');

        $this->assertSame('01', $por['tanque']['tp_car_fiscal']);
        $this->assertSame('03', $por['silo']['tp_car_fiscal']);

        // Tanque e silo carregam a granel: inspeção CIV/CIPP é obrigatória.
        $this->assertTrue($por['tanque']['exige_civ_cipp']);
        $this->assertTrue($por['silo']['exige_civ_cipp']);
    }

    public function testFrigorificoExigeTemperaturaControlada(): void
    {
        $por = array_column(CatalogoPadrao::carrocerias(), null, 'codigo');

        $this->assertTrue($por['bau_frigorifico']['exige_temperatura_controlada']);
        $this->assertTrue($por['bau_refrigerado']['exige_temperatura_controlada']);
        $this->assertFalse($por['bau']['exige_temperatura_controlada']);
    }

    public function testApenasPortaContainerAceitaConteiner(): void
    {
        foreach (CatalogoPadrao::carrocerias() as $c) {
            $this->assertSame(
                $c['codigo'] === 'porta_container',
                $c['permite_conteiner'],
                "permite_conteiner errado em {$c['codigo']}"
            );
        }
    }

    public function testCombinacoesSao19ComSlugUnico(): void
    {
        $cvcs = CatalogoPadrao::combinacoes();
        $slugs = array_column($cvcs, 'slug');

        $this->assertCount(19, $cvcs);
        $this->assertSame(count($slugs), count(array_unique($slugs)));
    }

    public function testTodaCombinacaoTemCategoriaFiscalValida(): void
    {
        foreach (CatalogoPadrao::combinacoes() as $cvc) {
            $categoria = CategoriaCombinacaoVeicular::paraEixos($cvc['eixos']);

            $this->assertTrue(
                in_array($categoria->value, CategoriaCombinacaoVeicular::codigosValidos(), true),
                "{$cvc['slug']} derivou categoria inválida"
            );
        }
    }

    /**
     * O flag `exige_aet` do catálogo tem de bater com o art. 17 da
     * Res. 882/2021 — não com o que a tabela de mercado diz. Treminhão e
     * tritrem exigem AET, ainda que muita fonte afirme o contrário.
     */
    public function testAetDoCatalogoBateComANorma(): void
    {
        foreach (CatalogoPadrao::combinacoes() as $cvc) {
            $this->assertSame(
                CatalogoPadrao::aetDerivada(
                    $cvc['qtd_unidades'],
                    $cvc['pbtc_kg'],
                    $cvc['comprimento_max_m'],
                ),
                $cvc['exige_aet'],
                "AET divergente da norma em {$cvc['slug']}"
            );
        }
    }

    public function testTreminhaoTritremERodotremExigemAet(): void
    {
        $por = array_column(CatalogoPadrao::combinacoes(), null, 'slug');

        foreach (['treminhao', 'tritrem', 'rodotrem_9e', 'bitrem_8e', 'bitrem_9e'] as $slug) {
            $this->assertTrue($por[$slug]['exige_aet'], "{$slug} exige AET");
        }

        // Bitrem de 7 eixos dentro de 19,80 m e 57 t NÃO exige.
        $this->assertFalse($por['bitrem_7e']['exige_aet']);
    }

    public function testBitruckRespeitaOTetoLegalDe29Toneladas(): void
    {
        $por = array_column(CatalogoPadrao::combinacoes(), null, 'slug');

        // Fontes de mercado citam 32 t. O art. 6º, "a", da Res. 882/2021 diz 29.
        $this->assertSame(29_000.0, $por['bitruck']['pbtc_kg']);
    }

    public function testCapacidadeEhFaixaCoerenteENuncaExcedeOPbtc(): void
    {
        foreach (CatalogoPadrao::combinacoes() as $cvc) {
            $this->assertTrue(
                $cvc['capacidade_min_kg'] <= $cvc['capacidade_max_kg'],
                "Faixa invertida em {$cvc['slug']}"
            );

            $this->assertTrue(
                $cvc['capacidade_max_kg'] < $cvc['pbtc_kg'],
                "Capacidade de {$cvc['slug']} não deixa espaço para a tara"
            );
        }
    }

    public function testCombinacaoArticuladaExigeCnhE(): void
    {
        foreach (CatalogoPadrao::combinacoes() as $cvc) {
            if ($cvc['qtd_unidades'] > 1) {
                $this->assertSame('E', $cvc['cnh_minima'], "{$cvc['slug']} é articulada e exige CNH E");
            }
        }
    }

    public function testNenhumaCombinacaoPassaDos74Toneladas(): void
    {
        foreach (CatalogoPadrao::combinacoes() as $cvc) {
            $this->assertTrue(
                $cvc['pbtc_kg'] <= 74_000.0,
                "{$cvc['slug']} passa do teto de 74 t admitido pela AET"
            );
        }
    }
}
