<?php

declare(strict_types=1);

namespace App\Domain\Operacao;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Conta do acerto de viagem com o motorista CLT (rotina 3070) — lógica pura,
 * em centavos inteiros.
 *
 *   ficou com o motorista = adiantado − gasto do adiantamento ACEITO
 *   empresa deve          = gasto do bolso ACEITO + diárias + comissão
 *   saldo                 = empresa deve − ficou com o motorista
 *                           (> 0 a empresa paga; < 0 o motorista devolve)
 *
 * Despesa paga com adiantamento e GLOSADA não é descontada de novo: ela
 * simplesmente não sai do "ficou com o motorista". Glosada do bolso não é
 * reembolsada. Paga pela empresa ou no cartão da empresa não entra.
 */
final class CalculoAcerto
{
    public const ACEITA = 'aceita';
    public const GLOSA = 'glosa';
    public const PENDENTE = 'pendente';

    /** Formas de pagamento da despesa que entram no acerto. */
    public const DO_ADIANTAMENTO = 'adiantamento';
    public const DO_BOLSO = 'reembolso';

    /**
     * @param  list<float>  $adiantamentos
     * @param  list<array{valor: float, forma: string, conferencia: string}>  $despesas
     * @return array{adiantado: float, gasto_adiantamento: float, ficou: float, bolso_aceito: float, glosado: float,
     *               diarias: float, comissao: float, credito: float, saldo: float, pendentes: int, ignoradas: int}
     */
    public static function calcular(array $adiantamentos, array $despesas, int $dias, float $diaria, float $comissaoPct, float $baseComissao): array
    {
        if ($dias < 0 || $diaria < 0 || $comissaoPct < 0 || $comissaoPct > 100 || $baseComissao < 0) {
            throw new InvalidArgumentException('Dias, diária e comissão não podem ser negativos (comissão até 100%).');
        }

        $c = fn (float $v): int => (int) round($v * 100);

        $adiantado = array_sum(array_map($c, $adiantamentos));
        $gastoAdiant = 0;
        $bolso = 0;
        $glosado = 0;
        $pendentes = 0;
        $ignoradas = 0;

        foreach ($despesas as $d) {
            $v = $c((float) $d['valor']);
            $entra = in_array($d['forma'], [self::DO_ADIANTAMENTO, self::DO_BOLSO], true);
            if (! $entra) {
                $ignoradas++;

                continue;
            }

            match ($d['conferencia']) {
                self::ACEITA => $d['forma'] === self::DO_ADIANTAMENTO ? $gastoAdiant += $v : $bolso += $v,
                self::GLOSA => $glosado += $v,
                default => $pendentes++,
            };
        }

        $diarias = $dias * $c($diaria);
        $comissao = (int) round($c($baseComissao) * $comissaoPct / 100);
        $ficou = $adiantado - $gastoAdiant;
        $credito = $bolso + $diarias + $comissao;

        $r = fn (int $centavos): float => round($centavos / 100, 2);

        return [
            'adiantado' => $r($adiantado),
            'gasto_adiantamento' => $r($gastoAdiant),
            'ficou' => $r($ficou),
            'bolso_aceito' => $r($bolso),
            'glosado' => $r($glosado),
            'diarias' => $r($diarias),
            'comissao' => $r($comissao),
            'credito' => $r($credito),
            'saldo' => $r($credito - $ficou),
            'pendentes' => $pendentes,
            'ignoradas' => $ignoradas,
        ];
    }

    /** Dias de viagem contando saída e chegada (mínimo 1). */
    public static function dias(DateTimeImmutable $saida, DateTimeImmutable $chegada): int
    {
        $a = $saida->setTime(0, 0);
        $b = $chegada->setTime(0, 0);

        return $b < $a ? 1 : (int) $a->diff($b)->days + 1;
    }
}
