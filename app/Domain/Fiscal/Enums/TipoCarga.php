<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\Enums;

/**
 * `tpCarga` — enum fechado da SEFAZ, em prodPred/tpCarga do MDF-e.
 *
 * O PADRÃO QUE ESTA CLASSE CODIFICA: "perigosa" NÃO é um tipo isolado. Os
 * códigos 07 a 11 são as versões perigosas de 01, 02, 03, 04 e 05. Por isso
 * o valor nunca é escolhido à mão — é DERIVADO de natureza × periculosidade.
 *
 * Derivar em vez de armazenar elimina a classe inteira de bugs em que o
 * operador marca "carga geral" e "produto perigoso" e o sistema envia 05
 * em vez de 11.
 *
 * Ver docs/05_CADASTROS_E_TABELAS_DE_DOMINIO.md, seção 2.1.
 */
enum TipoCarga: string
{
    case GranelSolido         = '01';
    case GranelLiquido        = '02';
    case Frigorificada        = '03';
    case Conteinerizada       = '04';
    case CargaGeral           = '05';
    case Neogranel            = '06';
    case PerigosaGranelSolido = '07';
    case PerigosaGranelLiquido = '08';
    case PerigosaFrigorificada = '09';
    case PerigosaConteinerizada = '10';
    case PerigosaCargaGeral   = '11';
    case GranelPressurizado   = '12';

    public function descricao(): string
    {
        return match ($this) {
            self::GranelSolido          => 'Granel sólido',
            self::GranelLiquido         => 'Granel líquido',
            self::Frigorificada         => 'Frigorificada',
            self::Conteinerizada        => 'Conteinerizada',
            self::CargaGeral            => 'Carga Geral',
            self::Neogranel             => 'Neogranel',
            self::PerigosaGranelSolido  => 'Perigosa (granel sólido)',
            self::PerigosaGranelLiquido => 'Perigosa (granel líquido)',
            self::PerigosaFrigorificada => 'Perigosa (carga frigorificada)',
            self::PerigosaConteinerizada => 'Perigosa (conteinerizada)',
            self::PerigosaCargaGeral    => 'Perigosa (carga geral)',
            self::GranelPressurizado    => 'Granel pressurizada',
        };
    }

    public function perigosa(): bool
    {
        return in_array($this, [
            self::PerigosaGranelSolido,
            self::PerigosaGranelLiquido,
            self::PerigosaFrigorificada,
            self::PerigosaConteinerizada,
            self::PerigosaCargaGeral,
        ], true);
    }

    /**
     * A derivação. É a única forma legítima de obter um tpCarga.
     *
     * Granel pressurizado e neogranel não têm variante perigosa própria no
     * layout: o pressurizado é 12 sempre (e na prática quase sempre É
     * perigoso), e o neogranel é 06.
     */
    public static function derivar(NaturezaCarga $natureza, bool $perigosa): self
    {
        return match ($natureza) {
            NaturezaCarga::GranelSolido =>
                $perigosa ? self::PerigosaGranelSolido : self::GranelSolido,

            NaturezaCarga::GranelLiquido =>
                $perigosa ? self::PerigosaGranelLiquido : self::GranelLiquido,

            NaturezaCarga::Frigorificada =>
                $perigosa ? self::PerigosaFrigorificada : self::Frigorificada,

            NaturezaCarga::Conteinerizada =>
                $perigosa ? self::PerigosaConteinerizada : self::Conteinerizada,

            // Toda carga geral cai em 05/11, seja solta, unitizada, viva ou
            // indivisível — o layout não distingue essas nuances operacionais.
            NaturezaCarga::CargaGeralSolta,
            NaturezaCarga::CargaGeralUnitizada,
            NaturezaCarga::CargaViva,
            NaturezaCarga::Indivisivel =>
                $perigosa ? self::PerigosaCargaGeral : self::CargaGeral,

            NaturezaCarga::Neogranel          => self::Neogranel,
            NaturezaCarga::GranelPressurizado => self::GranelPressurizado,
        };
    }
}
