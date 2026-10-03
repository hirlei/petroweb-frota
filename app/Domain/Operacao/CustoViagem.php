<?php

declare(strict_types=1);

namespace App\Domain\Operacao;

/**
 * Custo e margem da viagem (rotina 3080) — lógica pura, em centavos.
 *
 * Cada componente soma os LANÇAMENTOS ligados à viagem. Componente sem nenhum
 * lançamento usa o valor que foi digitado na viagem antes da 3080 existir
 * (`custos_digitados`), para a viagem antiga não zerar.
 */
final class CustoViagem
{
    public const COMPONENTES = [
        'combustivel' => 'Combustível',
        'terceiro' => 'Terceiros (CIOT)',
        'pedagio' => 'Pedágio',
        'motorista' => 'Motorista',
        'manutencao' => 'Manutenção',
        'outros' => 'Outros',
    ];

    /**
     * @param  array<string, list<float>|null>  $lancamentos  componente => valores (null/[] = nenhum lançamento)
     * @param  array<string, float|int|string|null>  $digitados  componente => valor digitado (legado)
     * @return array{componentes: array<string, array{valor: float, digitado: bool}>, total: float}
     */
    public static function somar(array $lancamentos, array $digitados = []): array
    {
        $c = fn (float $v): int => (int) round($v * 100);
        $total = 0;
        $saida = [];
        foreach (array_keys(self::COMPONENTES) as $comp) {
            $valores = $lancamentos[$comp] ?? [];
            if ($valores !== []) {
                $cent = array_sum(array_map($c, $valores));
                $saida[$comp] = ['valor' => round($cent / 100, 2), 'digitado' => false];
            } else {
                $cent = $c((float) ($digitados[$comp] ?? 0));
                $saida[$comp] = ['valor' => round($cent / 100, 2), 'digitado' => $cent > 0];
            }
            $total += $cent;
        }

        return ['componentes' => $saida, 'total' => round($total / 100, 2)];
    }

    /** @return array{margem: float, percentual: ?float, custo_km: ?float, receita_km: ?float} */
    public static function resultado(float $receita, float $custo, ?float $km): array
    {
        $margem = round($receita - $custo, 2);

        return [
            'margem' => $margem,
            'percentual' => $receita > 0 ? round($margem / $receita * 100, 1) : null,
            'custo_km' => $km !== null && $km > 0 ? round($custo / $km, 4) : null,
            'receita_km' => $km !== null && $km > 0 ? round($receita / $km, 4) : null,
        ];
    }

    /**
     * Divide um valor entre clientes na proporção da receita de cada um; o
     * resto de centavos vai para o de maior receita (a soma fecha no centavo).
     * Sem receita, tudo fica em `null` (sem cliente).
     *
     * @param  array<int|string, float>  $receitas  cliente => receita
     * @return array<int|string, float>
     */
    public static function ratear(float $valor, array $receitas): array
    {
        $alvo = (int) round($valor * 100);
        $receitas = array_filter($receitas, fn ($r) => $r > 0);
        $soma = array_sum($receitas);
        if ($soma <= 0) {
            return ['' => round($alvo / 100, 2)];
        }

        $partes = [];
        $distribuido = 0;
        foreach ($receitas as $cliente => $r) {
            $partes[$cliente] = (int) floor($alvo * $r / $soma);
            $distribuido += $partes[$cliente];
        }
        arsort($receitas);
        $maior = array_key_first($receitas);
        $partes[$maior] += $alvo - $distribuido;

        return array_map(fn (int $p) => round($p / 100, 2), $partes);
    }
}
