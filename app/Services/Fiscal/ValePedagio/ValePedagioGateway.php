<?php

declare(strict_types=1);

namespace App\Services\Fiscal\ValePedagio;

use App\Models\FornecedorVpo;
use App\Models\ValePedagio;
use App\Models\Viagem;

/**
 * Porta única para a fornecedora de vale-pedágio (Sem Parar, ConectCar, Repom…).
 * Compra para a rota e a categoria da viagem; o IDVPO devolvido vai no grupo
 * valePed do MDF-e.
 */
interface ValePedagioGateway
{
    /** Quanto vai custar — mostrado antes de emitir. */
    public function cotar(Viagem $viagem, int $eixos): float;

    public function comprar(Viagem $viagem, FornecedorVpo $fornecedor, int $eixos): RespostaVale;

    public function cancelar(ValePedagio $vale, string $motivo): RespostaVale;
}
