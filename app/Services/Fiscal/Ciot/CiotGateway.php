<?php

declare(strict_types=1);

namespace App\Services\Fiscal\Ciot;

use App\Models\Ciot;

/**
 * Porta única para quem registra o CIOT: a instituição de pagamento (frete com
 * TAC) ou a ANTT (frota própria). A aplicação só conhece Ciot e RespostaCiot —
 * trocar de instituição não vaza para as telas.
 *
 * O Ciot chega preenchido (partes, frete, adiantamento, prazo, forma); a
 * implementação lê `modalidade` para saber para onde mandar.
 */
interface CiotGateway
{
    public function registrar(Ciot $ciot): RespostaCiot;

    /** @param 'adiantamento'|'saldo' $tipo */
    public function pagar(Ciot $ciot, string $tipo, float $valor, string $forma): RespostaCiot;

    public function cancelar(Ciot $ciot, string $motivo): RespostaCiot;

    /** Nome que aparece na tela ("Emissor de teste", "Repom"…). */
    public function instituicao(string $modalidade): string;
}
