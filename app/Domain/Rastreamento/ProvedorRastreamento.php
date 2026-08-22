<?php

declare(strict_types=1);

namespace App\Domain\Rastreamento;

/**
 * Adaptador de um provedor de rastreamento (Sascar, Onixsat, Cobli…).
 *
 * O mercado brasileiro integra por "direcionamento de sinal": o provedor envia
 * as posições para o nosso webhook. Cada provedor tem seu formato de payload;
 * o adaptador traduz para a forma canônica que o sistema entende. Plugar um novo
 * provedor é escrever uma classe destas — o resto do sistema não muda.
 */
interface ProvedorRastreamento
{
    public function nome(): string;

    /**
     * Traduz o payload recebido do provedor em posições canônicas.
     *
     * @param  array<string,mixed>  $payload
     * @return list<array{placa:string,latitude:float,longitude:float,velocidade_kmh:?float,rumo:?int,ignicao:?bool,capturado_em:?string,bruto:array<string,mixed>}>
     */
    public function normalizar(array $payload): array;
}
