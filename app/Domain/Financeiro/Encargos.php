<?php

declare(strict_types=1);

namespace App\Domain\Financeiro;

use DateTimeImmutable;

/**
 * Multa e juros de uma parcela paga em atraso (rotina 5020). Lógica pura.
 *
 * - Multa: percentual único sobre o valor em aberto, só se houver atraso.
 * - Juros: simples, pro rata dia sobre o mês comercial de 30 dias
 *   (valor × juros_mês ÷ 30 × dias de atraso) — é como o boleto calcula.
 *
 * É SUGESTÃO: a tela mostra o cálculo e o usuário pode ajustar antes de
 * confirmar (negociação com o cliente é comum no transporte).
 */
final class Encargos
{
    /** @return array{dias_atraso:int, multa:float, juros:float, total:float} */
    public static function calcular(
        float $valorEmAberto,
        DateTimeImmutable $vencimento,
        DateTimeImmutable $pagamento,
        ?float $multaPercentual,
        ?float $jurosMesPercentual,
    ): array {
        $venc = $vencimento->setTime(0, 0);
        $pag = $pagamento->setTime(0, 0);
        $dias = $pag > $venc ? (int) $venc->diff($pag)->days : 0;

        if ($dias === 0 || $valorEmAberto <= 0) {
            return ['dias_atraso' => $dias, 'multa' => 0.0, 'juros' => 0.0, 'total' => 0.0];
        }

        $multa = round($valorEmAberto * (float) $multaPercentual / 100, 2);
        $juros = round($valorEmAberto * (float) $jurosMesPercentual / 100 / 30 * $dias, 2);

        return ['dias_atraso' => $dias, 'multa' => $multa, 'juros' => $juros, 'total' => round($multa + $juros, 2)];
    }
}
