<?php

declare(strict_types=1);

namespace App\Services\Fiscal\ValePedagio;

use App\Models\FornecedorVpo;
use App\Models\ValePedagio;
use App\Models\Viagem;

/**
 * Vale-pedágio de TESTE — sem fornecedora contratada.
 *
 * Valor = eixos × praças × tarifa, com uma praça a cada 100 km da rota (ou do
 * km padrão quando a viagem não tem rota). IDVPO fictício. Não compra nada.
 */
class FakeValePedagioGateway implements ValePedagioGateway
{
    public function cotar(Viagem $viagem, int $eixos): float
    {
        $viagem->loadMissing('rota');
        if ($viagem->rota?->valor_pedagio_estimado !== null && (float) $viagem->rota->valor_pedagio_estimado > 0) {
            return round((float) $viagem->rota->valor_pedagio_estimado, 2);
        }

        $km = (float) ($viagem->rota?->distancia_km ?: config('ciot.vale_pedagio.fake_km_padrao', 300));
        $pracas = max(1, (int) round($km / 100));

        return round(max(1, $eixos) * $pracas * (float) config('ciot.vale_pedagio.fake_tarifa_por_eixo', 8.70), 2);
    }

    public function comprar(Viagem $viagem, FornecedorVpo $fornecedor, int $eixos): RespostaVale
    {
        if (! $fornecedor->ativo) {
            return RespostaVale::recusa('Fornecedora inativa no catálogo da ANTT (rejeição 733).');
        }

        $idvpo = str_pad((string) random_int(1, 999_999_999), 9, '0', STR_PAD_LEFT);

        return RespostaVale::ok('Vale-pedágio comprado (teste)', $idvpo, $this->cotar($viagem, $eixos), 'VP' . now()->format('ymdHis'), ['ambiente' => 'teste', 'eixos' => $eixos]);
    }

    public function cancelar(ValePedagio $vale, string $motivo): RespostaVale
    {
        return RespostaVale::ok('Compra cancelada (teste)', $vale->idvpo, (float) $vale->valor, 'VP' . now()->format('ymdHis'));
    }
}
