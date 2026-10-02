<?php

declare(strict_types=1);

namespace App\Domain\Frota;

/**
 * Regras do motorista SEM Eloquent — para poderem ser testadas isoladas e,
 * sobretudo, para que a RN-12 tenha um único lugar onde vive.
 *
 * O model `App\Models\Motorista` delega para cá. Nenhuma tela decide por
 * conta própria se mostra jornada.
 */
final class RegrasMotorista
{
    public const VINCULO_CLT = 'clt';
    public const VINCULO_AGREGADO = 'agregado';
    public const VINCULO_AUTONOMO = 'autonomo';
    public const VINCULO_TERCEIRO = 'terceiro';

    public const VINCULOS = [
        self::VINCULO_CLT,
        self::VINCULO_AGREGADO,
        self::VINCULO_AUTONOMO,
        self::VINCULO_TERCEIRO,
    ];

    /**
     * RN-12 — jornada existe SÓ para CLT.
     *
     * Lei 11.442/2007 art. 5º e ADC 48 do STF põem o transportador autônomo
     * fora do vínculo empregatício. Registrar jornada, escala ou ponto de um
     * agregado é produzir, no próprio sistema do cliente, a prova de
     * subordinação que uma reclamatória trabalhista precisa. A regra é
     * jurídica antes de ser de produto.
     */
    public static function controlaJornada(string $vinculo): bool
    {
        return $vinculo === self::VINCULO_CLT;
    }

    /** Agregado e autônomo são TAC — RNTRC próprio, CIOT pela instituição de pagamento. */
    public static function ehTac(string $vinculo): bool
    {
        return in_array($vinculo, [self::VINCULO_AGREGADO, self::VINCULO_AUTONOMO], true);
    }

    /**
     * CIOT pela INSTITUIÇÃO DE PAGAMENTO, com o frete pago ao próprio motorista:
     * só quando ele é TAC (Lei 11.442/2007 art. 5º-A). Desde o "CIOT para Todos"
     * (Res. ANTT 6.078/2026) a viagem de motorista CLT também tem CIOT, mas
     * registrado direto na ANTT e sem pagamento a ele — ver App\Domain\Fiscal\RegrasCiot.
     */
    public static function exigeCiot(string $vinculo): bool
    {
        return self::ehTac($vinculo);
    }

    /**
     * Exame toxicológico: obrigatório para as categorias C, D e E
     * (Lei 13.103/2015 art. 148-A do CTB). Independe do vínculo.
     */
    public static function exigeToxicologico(string $categoriaCnh): bool
    {
        return preg_match('/[CDE]/i', $categoriaCnh) === 1;
    }

    /**
     * Categoria mínima para conduzir a combinação. Acima de 6.000 kg de PBT ou
     * com unidade acoplada, a exigência sobe para E.
     */
    public static function categoriaMinima(int $unidadesAcopladas, float $pbtKg): string
    {
        if ($unidadesAcopladas > 0) {
            return 'E';
        }

        return $pbtKg > 6_000.0 ? 'C' : 'B';
    }

    public static function categoriaAtende(string $categoriaCnh, string $minima): bool
    {
        $ordem = ['A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4];
        $maior = -1;

        foreach (str_split(strtoupper($categoriaCnh)) as $letra) {
            $maior = max($maior, $ordem[$letra] ?? -1);
        }

        return $maior >= ($ordem[strtoupper($minima)] ?? PHP_INT_MAX);
    }

    public static function vinculoValido(string $vinculo): bool
    {
        return in_array($vinculo, self::VINCULOS, true);
    }
}
