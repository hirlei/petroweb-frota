<?php

declare(strict_types=1);

namespace App\Domain\Financeiro;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Divide o total de uma fatura em parcelas (rotina 5010). Lógica pura, sem
 * Laravel — testada em tests/Unit/Domain/ParcelamentoTest.php.
 *
 * A condição é escrita como os prazos em dias separados por barra, do jeito
 * que o financeiro fala: "28/56" são duas parcelas, vencendo 28 e 56 dias
 * depois da emissão. "0" é à vista.
 *
 * As contas são feitas em CENTAVOS inteiros: a soma das parcelas é sempre
 * exatamente o total. O centavo que sobra da divisão vai para a primeira
 * parcela (é a que o cliente paga primeiro e a conferência bate na hora).
 */
final class Parcelamento
{
    public const MAX_PARCELAS = 12;

    public const MAX_PRAZO_DIAS = 365;

    /**
     * "28/56" → [28, 56]. Aceita espaço, vírgula, ponto e vírgula ou barra.
     *
     * @return list<int>
     */
    public static function prazos(string $condicao): array
    {
        $partes = preg_split('/[\s\/,;]+/', trim($condicao), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($partes === []) {
            throw new InvalidArgumentException('Informe os prazos em dias, por exemplo 30 ou 28/56.');
        }

        $prazos = [];
        foreach ($partes as $p) {
            if (! ctype_digit($p)) {
                throw new InvalidArgumentException("Prazo inválido: “{$p}”. Use só números de dias, como 30 ou 28/56.");
            }
            $prazos[] = (int) $p;
        }

        self::validar($prazos);

        return $prazos;
    }

    /** [28, 56] → "28/56". */
    public static function condicao(array $prazos): string
    {
        self::validar($prazos);

        return implode('/', $prazos);
    }

    /** [0] → "À vista"; [28] → "28 dias"; [28, 56] → "28/56 dias". */
    public static function rotulo(array $prazos): string
    {
        if ($prazos === [0]) {
            return 'À vista';
        }

        return implode('/', $prazos) . ' dias';
    }

    /**
     * @param  list<int>  $prazos
     * @return list<array{parcela:int, parcelas:int, vencimento:DateTimeImmutable, valor:float}>
     */
    public static function dividir(float $total, array $prazos, DateTimeImmutable $emissao): array
    {
        self::validar($prazos);

        $centavos = (int) round($total * 100);
        if ($centavos <= 0) {
            throw new InvalidArgumentException('O total da fatura precisa ser maior que zero.');
        }

        $n = count($prazos);
        $base = intdiv($centavos, $n);
        $sobra = $centavos - $base * $n;

        $parcelas = [];
        foreach ($prazos as $i => $dias) {
            $valor = $base + ($i === 0 ? $sobra : 0);
            $parcelas[] = [
                'parcela' => $i + 1,
                'parcelas' => $n,
                'vencimento' => $emissao->modify("+{$dias} days"),
                'valor' => round($valor / 100, 2),
            ];
        }

        return $parcelas;
    }

    /** @param list<int> $prazos */
    private static function validar(array $prazos): void
    {
        if ($prazos === []) {
            throw new InvalidArgumentException('Informe ao menos um prazo.');
        }

        if (count($prazos) > self::MAX_PARCELAS) {
            throw new InvalidArgumentException('No máximo ' . self::MAX_PARCELAS . ' parcelas por fatura.');
        }

        $anterior = -1;
        foreach ($prazos as $dias) {
            if (! is_int($dias) || $dias < 0 || $dias > self::MAX_PRAZO_DIAS) {
                throw new InvalidArgumentException('Cada prazo precisa ficar entre 0 e ' . self::MAX_PRAZO_DIAS . ' dias.');
            }
            if ($dias <= $anterior) {
                throw new InvalidArgumentException('Os prazos precisam ser crescentes, como 30/60/90.');
            }
            $anterior = $dias;
        }
    }
}
