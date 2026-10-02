<?php

declare(strict_types=1);

namespace App\Services\Fiscal\Ciot;

use App\Domain\Fiscal\RegrasCiot;
use App\Models\Ciot;

/**
 * CIOT de TESTE — sem instituição de pagamento contratada.
 *
 * Gera um número de 12 dígitos e um protocolo plausíveis; não fala com
 * ninguém. Recusa frete zerado ou abaixo de `ciot.fake.piso_minimo`, para dar
 * para exercitar a recusa e o reenvio. Os números NÃO valem na fiscalização.
 */
class FakeCiotGateway implements CiotGateway
{
    public function registrar(Ciot $ciot): RespostaCiot
    {
        $frete = (float) $ciot->valor_frete;
        $piso = (float) config('ciot.fake.piso_minimo', 0);

        if ($frete <= 0) {
            return RespostaCiot::recusa('Valor do frete não informado.');
        }
        if ($piso > 0 && $frete < $piso) {
            return RespostaCiot::recusa(sprintf('Frete abaixo do piso mínimo da ANTT (R$ %s).', number_format($piso, 2, ',', '.')));
        }

        $numero = str_pad((string) random_int(1, 999_999_999_999), 12, '0', STR_PAD_LEFT);

        return RespostaCiot::ok(
            $ciot->modalidade === RegrasCiot::ANTT ? 'Operação registrada na ANTT (teste)' : 'CIOT gerado pela instituição (teste)',
            $numero,
            $this->protocolo(),
            ['ambiente' => 'teste'],
        );
    }

    public function pagar(Ciot $ciot, string $tipo, float $valor, string $forma): RespostaCiot
    {
        if ($valor <= 0) {
            return RespostaCiot::recusa('Valor do pagamento deve ser maior que zero.');
        }

        return RespostaCiot::ok(($tipo === 'saldo' ? 'Saldo' : 'Adiantamento') . ' pago (teste)', null, $this->protocolo());
    }

    public function cancelar(Ciot $ciot, string $motivo): RespostaCiot
    {
        return RespostaCiot::ok('CIOT cancelado (teste)', null, $this->protocolo());
    }

    public function instituicao(string $modalidade): string
    {
        return (string) config('ciot.instituicao', 'Emissor de teste');
    }

    private function protocolo(): string
    {
        return 'CT' . now()->format('ymdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }
}
