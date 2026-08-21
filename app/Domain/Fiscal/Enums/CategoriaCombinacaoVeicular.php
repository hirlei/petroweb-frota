<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\Enums;

/**
 * `categCombVeic` — categoria de combinação veicular do grupo valePed do MDF-e.
 *
 * ARMADILHA QUE ESTA CLASSE EXISTE PARA EVITAR: a sequência NÃO é contínua.
 * Os códigos 03, 05 e 09 NÃO EXISTEM. A numeração vem da tabela tarifária de
 * pedágio da ANTT, onde os intermediários são categorias não comerciais
 * (motocicleta, automóvel, automóvel com reboque) que ficaram fora do domínio
 * do MDF-e. São 10 valores válidos, não 13.
 *
 * Quem gera o código por aritmética simples (eixos + n) produz XML inválido.
 * Ausência do campo quando há vale-pedágio gera a rejeição 731.
 *
 * Ver docs/05_CADASTROS_E_TABELAS_DE_DOMINIO.md, seção 3.2.
 */
enum CategoriaCombinacaoVeicular: string
{
    case DoisEixos      = '02';
    case TresEixos      = '04';
    case QuatroEixos    = '06';
    case CincoEixos     = '07';
    case SeisEixos      = '08';
    case SeteEixos      = '10';
    case OitoEixos      = '11';
    case NoveEixos      = '12';
    case DezEixos       = '13';
    case AcimaDeDez     = '14';

    public function descricao(): string
    {
        return match ($this) {
            self::DoisEixos   => 'Veículo Comercial 2 eixos',
            self::TresEixos   => 'Veículo Comercial 3 eixos',
            self::QuatroEixos => 'Veículo Comercial 4 eixos',
            self::CincoEixos  => 'Veículo Comercial 5 eixos',
            self::SeisEixos   => 'Veículo Comercial 6 eixos',
            self::SeteEixos   => 'Veículo Comercial 7 eixos',
            self::OitoEixos   => 'Veículo Comercial 8 eixos',
            self::NoveEixos   => 'Veículo Comercial 9 eixos',
            self::DezEixos    => 'Veículo Comercial 10 eixos',
            self::AcimaDeDez  => 'Veículo Comercial acima de 10 eixos',
        };
    }

    /**
     * Deriva a categoria do número TOTAL de eixos da composição — somando a
     * tração e todos os reboques, não só a tração.
     *
     * Menos de 2 eixos não existe em veículo comercial de carga; tratamos
     * como 2 em vez de lançar, porque um cadastro incompleto não pode
     * impedir a emissão — a validação de cadastro é outra camada.
     */
    public static function paraEixos(int $eixos): self
    {
        return match (true) {
            $eixos <= 2   => self::DoisEixos,
            $eixos === 3  => self::TresEixos,
            $eixos === 4  => self::QuatroEixos,
            $eixos === 5  => self::CincoEixos,
            $eixos === 6  => self::SeisEixos,
            $eixos === 7  => self::SeteEixos,
            $eixos === 8  => self::OitoEixos,
            $eixos === 9  => self::NoveEixos,
            $eixos === 10 => self::DezEixos,
            default       => self::AcimaDeDez,
        };
    }

    /** Os códigos que a SEFAZ aceita. Note a ausência de 03, 05 e 09. */
    public static function codigosValidos(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
