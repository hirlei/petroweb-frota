<?php

declare(strict_types=1);

namespace App\Services\Fiscal\ValePedagio;

/** Resposta da fornecedora de vale-pedágio (FVPO) a uma compra ou cancelamento. */
final class RespostaVale
{
    /** @param array<string,mixed> $dados */
    private function __construct(
        public readonly bool $ok,
        public readonly string $motivo,
        public readonly ?string $idvpo = null,
        public readonly ?float $valor = null,
        public readonly ?string $protocolo = null,
        public readonly array $dados = [],
    ) {
    }

    /** @param array<string,mixed> $dados */
    public static function ok(string $motivo, ?string $idvpo = null, ?float $valor = null, ?string $protocolo = null, array $dados = []): self
    {
        return new self(true, $motivo, $idvpo, $valor, $protocolo, $dados);
    }

    public static function recusa(string $motivo): self
    {
        return new self(false, $motivo);
    }

    /** @return array<string,mixed> */
    public function paraArray(): array
    {
        return ['ok' => $this->ok, 'motivo' => $this->motivo, 'idvpo' => $this->idvpo, 'valor' => $this->valor, 'protocolo' => $this->protocolo, 'dados' => $this->dados];
    }
}
