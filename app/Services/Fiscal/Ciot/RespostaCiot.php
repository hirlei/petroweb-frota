<?php

declare(strict_types=1);

namespace App\Services\Fiscal\Ciot;

/**
 * Resposta da instituição de pagamento (ou da ANTT) a um pedido de CIOT:
 * registro, pagamento ou cancelamento. Imutável.
 */
final class RespostaCiot
{
    /** @param array<string,mixed> $dados */
    private function __construct(
        public readonly bool $ok,
        public readonly string $motivo,
        public readonly ?string $numero = null,
        public readonly ?string $protocolo = null,
        public readonly array $dados = [],
    ) {
    }

    /** @param array<string,mixed> $dados */
    public static function ok(string $motivo, ?string $numero = null, ?string $protocolo = null, array $dados = []): self
    {
        return new self(true, $motivo, $numero, $protocolo, $dados);
    }

    /** @param array<string,mixed> $dados */
    public static function recusa(string $motivo, array $dados = []): self
    {
        return new self(false, $motivo, null, null, $dados);
    }

    /** @return array<string,mixed> */
    public function paraArray(): array
    {
        return ['ok' => $this->ok, 'motivo' => $this->motivo, 'numero' => $this->numero, 'protocolo' => $this->protocolo, 'dados' => $this->dados];
    }
}
