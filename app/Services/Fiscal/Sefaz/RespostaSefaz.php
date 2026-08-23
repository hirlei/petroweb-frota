<?php

declare(strict_types=1);

namespace App\Services\Fiscal\Sefaz;

/**
 * Resposta padronizada da SEFAZ para autorização/evento — o que a aplicação
 * grava independentemente do gateway concreto (real ou fake).
 */
final class RespostaSefaz
{
    public function __construct(
        public readonly bool $autorizado,
        public readonly string $codigo,   // cStat
        public readonly string $motivo,   // xMotivo
        public readonly ?string $protocolo = null,
        public readonly ?string $chave = null,
        public readonly ?string $xmlPath = null,
    ) {
    }

    public static function ok(string $codigo, string $motivo, ?string $protocolo = null, ?string $chave = null, ?string $xmlPath = null): self
    {
        return new self(true, $codigo, $motivo, $protocolo, $chave, $xmlPath);
    }

    public static function falha(string $codigo, string $motivo): self
    {
        return new self(false, $codigo, $motivo);
    }
}
