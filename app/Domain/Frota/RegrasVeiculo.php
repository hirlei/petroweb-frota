<?php

declare(strict_types=1);

namespace App\Domain\Frota;

use App\Domain\Fiscal\Enums\CategoriaCombinacaoVeicular;

/**
 * Regras do veículo SEM Eloquent — testáveis isoladas e com um único lugar
 * para as verdades fiscais que decidem o cadastro.
 *
 * O model `App\Models\Veiculo` e a tela delegam para cá. Nenhuma tela deriva
 * `categCombVeic` por conta própria (é a armadilha dos códigos 03/05/09, ver
 * CategoriaCombinacaoVeicular).
 */
final class RegrasVeiculo
{
    public const TIPO_TRACAO = 'tracao';
    public const TIPO_REBOQUE = 'reboque';
    public const TIPO_SEMIRREBOQUE = 'semirreboque';
    public const TIPO_DOLLY = 'dolly';

    public const TIPOS = [
        self::TIPO_TRACAO,
        self::TIPO_REBOQUE,
        self::TIPO_SEMIRREBOQUE,
        self::TIPO_DOLLY,
    ];

    public const PROPRIEDADE_PROPRIA = 'propria';
    public const PROPRIEDADE_TERCEIRO = 'terceiro';
    public const PROPRIEDADE_ARRENDADA = 'arrendada';

    public const PROPRIEDADES = [
        self::PROPRIEDADE_PROPRIA,
        self::PROPRIEDADE_TERCEIRO,
        self::PROPRIEDADE_ARRENDADA,
    ];

    public const STATUS = ['ativo', 'inativo', 'manutencao', 'vendido'];

    /**
     * Só a tração leva rodado (tpRod) — é o que o CHECK do banco também exige.
     * Reboque, semirreboque e dolly não têm.
     */
    public static function exigeRodado(string $tipo): bool
    {
        return $tipo === self::TIPO_TRACAO;
    }

    /**
     * Veículo de terceiro (ou arrendado) precisa de proprietário: é dele o
     * RNTRC e o tipo de transportador que vão ao MDF-e, nunca o da empresa.
     */
    public static function exigeProprietario(string $propriedade): bool
    {
        return $propriedade !== self::PROPRIEDADE_PROPRIA;
    }

    public static function ehProprio(string $propriedade): bool
    {
        return $propriedade === self::PROPRIEDADE_PROPRIA;
    }

    public static function tipoValido(string $tipo): bool
    {
        return in_array($tipo, self::TIPOS, true);
    }

    public static function propriedadeValida(string $propriedade): bool
    {
        return in_array($propriedade, self::PROPRIEDADES, true);
    }

    /**
     * Placa Mercosul OU padrão antigo. Sete posições, alfanumérica — nunca
     * inteiro. A validação é de forma; a autoridade sobre a placa é o Denatran.
     */
    public static function placaValida(string $placa): bool
    {
        $placa = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $placa) ?? '');

        // ABC1234 (antiga) ou ABC1D23 (Mercosul).
        return preg_match('/^[A-Z]{3}[0-9][0-9A-Z][0-9]{2}$/', $placa) === 1;
    }

    public static function limparPlaca(string $placa): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $placa) ?? '');
    }

    /**
     * Carga útil real: o que sobra do PBTC depois da tara. Nunca acima disso —
     * é o que o CHECK `veiculos_capacidade_coerente` do banco garante.
     */
    public static function cargaUtilKg(?float $pbtcKg, ?float $taraKg): ?float
    {
        if ($pbtcKg === null) {
            return null;
        }

        return $pbtcKg - (float) $taraKg;
    }

    /**
     * Categoria de combinação a partir dos eixos do veículo isolado. Para
     * combinação real quem manda é a soma dos eixos da composição.
     */
    public static function categoriaPorEixos(int $eixos): CategoriaCombinacaoVeicular
    {
        return CategoriaCombinacaoVeicular::paraEixos($eixos);
    }

    /**
     * Pendências que NÃO impedem salvar o cadastro, mas bloqueiam a emissão de
     * MDF-e. A tela mostra; o cadastro é salvo mesmo assim — o operador cadastra
     * agora e completa depois.
     *
     * @param  array{tipo:string,propriedade:string,eixos:int,tp_rod:?string,proprietario_id:?int,proprietario_rntrc:?string,renavam:?string}  $dados
     * @return list<string>
     */
    public static function pendenciasParaEmissao(array $dados): array
    {
        $pendencias = [];

        if (self::exigeRodado($dados['tipo']) && empty($dados['tp_rod'])) {
            $pendencias[] = 'Tração sem tipo de rodado (tpRod) — o MDF-e não fecha sem ele.';
        }

        if (($dados['eixos'] ?? 0) < 1) {
            $pendencias[] = 'Eixos não informados — é a base do categCombVeic (rejeição 731).';
        }

        if (self::exigeProprietario($dados['propriedade'])) {
            if (empty($dados['proprietario_id'])) {
                $pendencias[] = 'Veículo de terceiro sem proprietário cadastrado — o RNTRC dele vai ao MDF-e.';
            } elseif (empty($dados['proprietario_rntrc'])) {
                $pendencias[] = 'Proprietário sem RNTRC — obrigatório no MDF-e de veículo de terceiro.';
            }
        }

        if (empty($dados['renavam'])) {
            $pendencias[] = 'RENAVAM ausente — exigido no grupo veicTração/veicReboque do MDF-e.';
        }

        return $pendencias;
    }
}
