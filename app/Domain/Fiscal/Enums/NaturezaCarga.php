<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\Enums;

/**
 * Natureza da carga — domínio OPERACIONAL, editável pelo cliente.
 *
 * Não confundir com `tpCarga`, que é o enum fechado da SEFAZ. A natureza é o
 * que o operador escolhe; o `tpCarga` é o que o XML recebe, DERIVADO do
 * cruzamento natureza × periculosidade. Ver TipoCarga.
 */
enum NaturezaCarga: string
{
    case CargaGeralSolta     = 'carga_geral_solta';
    case CargaGeralUnitizada = 'carga_geral_unitizada';
    case GranelSolido        = 'granel_solido';
    case GranelLiquido       = 'granel_liquido';
    case GranelPressurizado  = 'granel_pressurizado';
    case Neogranel           = 'neogranel';
    case Frigorificada       = 'frigorificada';
    case CargaViva           = 'carga_viva';
    case Conteinerizada      = 'conteinerizada';
    case Indivisivel         = 'indivisivel';

    public function rotulo(): string
    {
        return match ($this) {
            self::CargaGeralSolta     => 'Carga geral solta',
            self::CargaGeralUnitizada => 'Carga geral unitizada',
            self::GranelSolido        => 'Granel sólido',
            self::GranelLiquido       => 'Granel líquido',
            self::GranelPressurizado  => 'Granel pressurizado',
            self::Neogranel           => 'Neogranel',
            self::Frigorificada       => 'Frigorificada',
            self::CargaViva           => 'Carga viva',
            self::Conteinerizada      => 'Conteinerizada',
            self::Indivisivel         => 'Carga indivisível',
        };
    }

    /** Carga indivisível excede peso ou dimensão legal — exige AET. */
    public function exigeAet(): bool
    {
        return $this === self::Indivisivel;
    }

    /** Animal vivo exige Guia de Trânsito Animal. */
    public function exigeGta(): bool
    {
        return $this === self::CargaViva;
    }

    public function exigeTemperaturaControlada(): bool
    {
        return $this === self::Frigorificada;
    }
}
