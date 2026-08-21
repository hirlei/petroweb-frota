<?php

declare(strict_types=1);

namespace App\Domain\Frota;

/**
 * Os dados do catálogo padrão, SEM Eloquent — para que possam ser conferidos
 * por teste antes de virarem linha no banco.
 *
 * O seeder (`Database\Seeders\TabelasDominioFrotaSeeder`) só transporta o que
 * está aqui. Assim um erro de digitação em PBTC ou em `tpCar` é pego pelo
 * teste, e não seis meses depois numa rejeição da SEFAZ.
 *
 * Fonte: docs/05_CADASTROS_E_TABELAS_DE_DOMINIO.md §2.2, §3.3 e §4.2.
 * Limites: Resolução CONTRAN 882/2021.
 */
final class CatalogoPadrao
{
    /** Limiares do art. 17 da Res. 882/2021 que disparam a AET. */
    public const PBTC_LIMITE_AET_KG = 57_000.0;
    public const COMPRIMENTO_LIMITE_AET_M = 19.80;

    /**
     * Naturezas de carga. "Carga perigosa" NÃO é natureza: é atributo da
     * mercadoria. Granel líquido perigoso segue sendo granel líquido — o que
     * muda é o `tpCarga` (02 → 08).
     *
     * @return list<array{codigo:string,nome:string,tp_carga_base:string,permite_perigosa:bool,exige_temperatura:bool,exige_aet:bool,exige_gta:bool}>
     */
    public static function naturezas(): array
    {
        return array_map(
            static fn (array $l): array => [
                'codigo'            => $l[0],
                'nome'              => $l[1],
                'tp_carga_base'     => $l[2],
                'permite_perigosa'  => $l[3],
                'exige_temperatura' => $l[4],
                'exige_aet'         => $l[5],
                'exige_gta'         => $l[6],
            ],
            [
                ['carga_geral_solta',     'Carga geral solta',     '05', true,  false, false, false],
                ['carga_geral_unitizada', 'Carga geral unitizada', '05', true,  false, false, false],
                ['granel_solido',         'Granel sólido',         '01', true,  false, false, false],
                ['granel_liquido',        'Granel líquido',        '02', true,  false, false, false],
                ['granel_pressurizado',   'Granel pressurizado',   '12', true,  false, false, false],
                ['neogranel',             'Neogranel',             '06', false, false, false, false],
                ['frigorificada',         'Frigorificada',         '03', true,  true,  false, false],
                ['carga_viva',            'Carga viva',            '05', false, false, false, true],
                ['conteinerizada',        'Conteinerizada',        '04', true,  false, false, false],
                ['indivisivel',           'Carga indivisível',     '05', false, false, true,  false],
            ],
        );
    }

    /**
     * 19 carrocerias de mercado para 6 códigos fiscais.
     *
     * TANQUE e SILO não têm `tpCar` próprio. A convenção está FIXADA aqui para
     * que dois MDF-e da mesma frota não saiam com códigos diferentes conforme
     * quem digitou: tanque → 01 (aberta), silo → 03 (granelera).
     *
     * @return list<array{codigo:string,nome:string,tp_car_fiscal:string,natureza_padrao:string,exige_temperatura_controlada:bool,exige_civ_cipp:bool,exige_certificacao_inmetro:bool,aceita_produto_perigoso:bool,permite_conteiner:bool,capacidade_m3_referencia:float|null}>
     */
    public static function carrocerias(): array
    {
        return array_map(
            static fn (array $l): array => [
                'codigo'          => $l[0],
                'nome'            => $l[1],
                'tp_car_fiscal'   => $l[2],
                'natureza_padrao' => $l[3],
                'exige_temperatura_controlada' => $l[4],
                'exige_civ_cipp'  => $l[5],
                'exige_certificacao_inmetro' => $l[6],
                'aceita_produto_perigoso' => $l[7],
                'permite_conteiner' => $l[8],
                'capacidade_m3_referencia' => $l[9],
            ],
            [
                ['bau',            'Baú / furgão',            '02', 'carga_geral_unitizada', false, false, false, false, false, 90.0],
                ['bau_frigorifico','Baú frigorífico',         '02', 'frigorificada',         true,  false, true,  false, false, 85.0],
                ['bau_refrigerado','Baú refrigerado',         '02', 'frigorificada',         true,  false, true,  false, false, 85.0],
                ['sider',          'Sider',                   '05', 'carga_geral_unitizada', false, false, false, false, false, 90.0],
                ['graneleiro',     'Graneleiro',              '03', 'granel_solido',         false, false, false, false, false, 60.0],
                ['carga_seca',     'Carga seca',              '01', 'carga_geral_solta',     false, false, false, true,  false, 45.0],
                ['prancha',        'Prancha / lowboy',        '01', 'indivisivel',           false, false, false, false, false, null],
                ['porta_container','Porta-container',         '04', 'conteinerizada',        false, false, false, true,  true,  76.0],
                ['basculante',     'Basculante / caçamba',    '01', 'granel_solido',         false, false, false, false, false, 30.0],
                ['canavieiro',     'Canavieiro',              '01', 'granel_solido',         false, false, false, false, false, 80.0],
                ['florestal',      'Florestal / toreiro',     '01', 'neogranel',             false, false, false, false, false, null],
                ['boiadeiro',      'Boiadeiro / gaiola',      '01', 'carga_viva',            false, false, false, false, false, null],
                ['tanque',         'Tanque',                  '01', 'granel_liquido',        false, true,  true,  true,  false, 45.0],
                ['silo',           'Silo',                    '03', 'granel_solido',         false, true,  false, false, false, 40.0],
                ['cegonha',        'Cegonha',                 '01', 'neogranel',             false, false, false, false, false, null],
                ['gaiola_botijoes','Gaiola de botijões',      '01', 'carga_geral_unitizada', false, false, false, true,  false, null],
                ['munck',          'Munck',                   '01', 'carga_geral_solta',     false, false, true,  false, false, null],
                ['plataforma',     'Plataforma / guincho',    '01', 'neogranel',             false, false, false, false, false, null],
                ['poliguindaste',  'Poliguindaste',           '01', 'granel_solido',         false, false, false, false, false, null],
            ],
        );
    }

    /**
     * 19 configurações da Res. CONTRAN 882/2021.
     *
     * Capacidade vem como FAIXA, nunca número único: é `PBTC − tara`, e a tara
     * varia por fabricante. O cadastro do veículo calcula a real pelo CRLV.
     *
     * @return list<array{slug:string,nome_popular:string,eixos:int,qtd_unidades:int,tracao:string,pbtc_kg:float,comprimento_max_m:float,capacidade_min_kg:float,capacidade_max_kg:float,exige_aet:bool,tp_rod_default:string,cnh_minima:string}>
     */
    public static function combinacoes(): array
    {
        return array_map(
            static fn (array $l): array => [
                'slug'              => $l[0],
                'nome_popular'      => $l[1],
                'eixos'             => $l[2],
                'qtd_unidades'      => $l[3],
                'tracao'            => $l[4],
                'pbtc_kg'           => (float) $l[5],
                'comprimento_max_m' => $l[6],
                'capacidade_min_kg' => (float) $l[7],
                'capacidade_max_kg' => (float) $l[8],
                'exige_aet'         => $l[9],
                'tp_rod_default'    => $l[10],
                'cnh_minima'        => $l[11],
            ],
            [
                ['vuc',             'VUC',                          2, 1, '4x2',  6_300, 6.30,  3_000,  3_500, false, '05', 'B'],
                ['tres_quartos',    '3/4',                          2, 1, '4x2', 10_000, 14.00,  5_500,  6_500, false, '02', 'C'],
                ['toco',            'Toco',                         2, 1, '4x2', 16_000, 14.00,  9_000, 11_000, false, '02', 'C'],
                ['truck',           'Truck',                        3, 1, '6x2', 23_000, 14.00, 14_000, 16_000, false, '01', 'C'],
                ['bitruck',         'Bitruck',                      4, 1, '8x2', 29_000, 14.00, 18_000, 23_000, false, '01', 'C'],
                ['carreta_2e',      'Carreta simples 2 eixos',      4, 2, '4x2', 33_000, 18.60, 20_000, 24_000, false, '03', 'E'],
                ['carreta_2e_dist', 'Carreta 2 eixos distanciados', 4, 2, '4x2', 36_000, 18.60, 23_000, 26_000, false, '03', 'E'],
                ['carreta_3e',      'Carreta simples 3 eixos',      5, 2, '4x2', 41_500, 18.60, 27_000, 30_000, false, '03', 'E'],
                ['vanderleia',      'Vanderleia',                   5, 2, '4x2', 46_000, 18.60, 30_000, 33_000, false, '03', 'E'],
                ['carreta_ls',      'Carreta LS',                   6, 2, '6x2', 48_500, 18.60, 32_000, 35_000, false, '03', 'E'],
                ['vanderleia_truc', 'Vanderleia trucada',           6, 2, '6x2', 53_000, 18.60, 36_000, 39_000, false, '03', 'E'],
                ['romeu_julieta',   'Romeu e Julieta',              6, 2, '6x2', 50_000, 19.80, 33_000, 36_000, false, '01', 'E'],
                ['carreta_7e',      'Carreta 7 eixos',              7, 2, '6x4', 58_500, 18.60, 40_000, 43_000, false, '03', 'E'],
                ['bitrem_7e',       'Bitrem 7 eixos',               7, 3, '6x4', 57_000, 19.80, 37_000, 40_000, false, '03', 'E'],
                ['treminhao',       'Treminhão',                    7, 3, '6x4', 63_000, 30.00, 42_000, 46_000, true,  '01', 'E'],
                ['bitrem_8e',       'Bitrem 8 eixos',               8, 3, '6x4', 65_500, 30.00, 44_000, 48_000, true,  '03', 'E'],
                ['bitrem_9e',       'Bitrem 9 eixos',               9, 3, '6x4', 74_000, 30.00, 50_000, 54_000, true,  '03', 'E'],
                ['rodotrem_9e',     'Rodotrem 9 eixos',             9, 3, '6x4', 74_000, 30.00, 48_000, 53_000, true,  '03', 'E'],
                ['tritrem',         'Tritrem',                      9, 4, '6x4', 74_000, 30.00, 50_000, 54_000, true,  '03', 'E'],
            ],
        );
    }

    /**
     * A AET é derivável, não opinião: art. 17 da Res. 882/2021 — CVC de mais
     * de duas unidades acima de 57 t de PBTC OU de 19,80 m de comprimento.
     *
     * Existe para que o teste confira o flag do catálogo contra a norma, em
     * vez de confiar em quem digitou a tabela.
     */
    public static function aetDerivada(int $qtdUnidades, float $pbtcKg, float $comprimentoM): bool
    {
        if ($qtdUnidades <= 2) {
            return false;
        }

        return $pbtcKg > self::PBTC_LIMITE_AET_KG
            || $comprimentoM > self::COMPRIMENTO_LIMITE_AET_M;
    }
}
