<?php

declare(strict_types=1);

namespace App\Domain\Operacao;

/**
 * Calcula o valor do frete a partir dos componentes de uma tabela.
 *
 * Regra de negócio pura, sem Laravel: recebe os itens da tabela já como arrays
 * (componente, base_calculo, valor, faixa_de, faixa_ate, minimo, maximo) e os
 * parâmetros da carga, e devolve o total mais o detalhamento por componente.
 *
 * A base de cálculo decide a grandeza:
 *   por_kg           → peso (kg) × valor
 *   por_ton          → (peso / 1000) × valor
 *   percentual_valor → valor da mercadoria × (valor / 100)
 *   fixo             → valor
 *   por_km           → distância (km) × valor
 *   por_volume       → volumes × valor
 *
 * A faixa (faixa_de..faixa_ate) filtra o item pela grandeza pertinente: peso
 * para componentes de peso, valor da carga para componentes sobre valor. Mínimo
 * e máximo são aplicados ao subtotal do componente, nessa ordem.
 *
 * O pedágio pode existir como componente, mas quem o separa da base do ICMS é a
 * emissão do CT-e — aqui ele apenas soma ao valor cobrado, se assim cadastrado.
 */
final class CalculadoraFrete
{
    /**
     * @param  iterable<array<string,mixed>>  $itens
     * @param  array{peso_kg?:float,valor_mercadoria?:float,volumes?:float,distancia_km?:float}  $carga
     * @return array{total:float,componentes:list<array{componente:string,base:string,valor:float}>}
     */
    public static function calcular(iterable $itens, array $carga): array
    {
        $peso = (float) ($carga['peso_kg'] ?? 0);
        $valorCarga = (float) ($carga['valor_mercadoria'] ?? 0);
        $volumes = (float) ($carga['volumes'] ?? 0);
        $km = (float) ($carga['distancia_km'] ?? 0);

        $total = 0.0;
        $componentes = [];

        foreach ($itens as $item) {
            $base = (string) ($item['base_calculo'] ?? 'fixo');
            $valorUnit = (float) ($item['valor'] ?? 0);

            $grandeza = self::grandeza($base, $peso, $valorCarga);

            if (! self::dentroDaFaixa($item, $grandeza)) {
                continue;
            }

            $subtotal = match ($base) {
                'por_kg'           => $peso * $valorUnit,
                'por_ton'          => ($peso / 1000) * $valorUnit,
                'percentual_valor' => $valorCarga * ($valorUnit / 100),
                'por_km'           => $km * $valorUnit,
                'por_volume'       => $volumes * $valorUnit,
                default            => $valorUnit, // fixo
            };

            $subtotal = self::aplicarLimites($item, $subtotal);

            if ($subtotal <= 0.0) {
                continue;
            }

            $total += $subtotal;
            $componentes[] = [
                'componente' => (string) ($item['componente'] ?? 'outros'),
                'base'       => $base,
                'valor'      => round($subtotal, 2),
            ];
        }

        return [
            'total'       => round($total, 2),
            'componentes' => $componentes,
        ];
    }

    private static function grandeza(string $base, float $peso, float $valorCarga): float
    {
        return $base === 'percentual_valor' ? $valorCarga : $peso;
    }

    /** @param array<string,mixed> $item */
    private static function dentroDaFaixa(array $item, float $grandeza): bool
    {
        $de = self::num($item['faixa_de'] ?? null);
        $ate = self::num($item['faixa_ate'] ?? null);

        if ($de !== null && $grandeza < $de) {
            return false;
        }

        if ($ate !== null && $grandeza > $ate) {
            return false;
        }

        return true;
    }

    /** @param array<string,mixed> $item */
    private static function aplicarLimites(array $item, float $subtotal): float
    {
        $minimo = self::num($item['minimo'] ?? null);
        $maximo = self::num($item['maximo'] ?? null);

        if ($minimo !== null && $subtotal < $minimo) {
            $subtotal = $minimo;
        }

        if ($maximo !== null && $subtotal > $maximo) {
            $subtotal = $maximo;
        }

        return $subtotal;
    }

    private static function num(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return (float) $valor;
    }
}
