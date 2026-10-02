<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Posição de GPS de um veículo (camada de rastreamento).
 *
 * Cada registro é uma posição recebida de um provedor (Sascar, Onixsat, Cobli…)
 * pelo webhook, ou lançada manualmente. A última posição de cada veículo é o que
 * alimenta o caminhão no mapa. `bruto` guarda o payload original do provedor,
 * útil para depurar integração.
 */
class PosicaoVeiculo extends Model
{
    use PertenceAEmpresa;

    protected $table = 'posicoes_veiculo';

    protected $guarded = [];

    protected $casts = [
        'latitude'       => 'decimal:7',
        'longitude'      => 'decimal:7',
        'velocidade_kmh' => 'decimal:2',
        'rumo'           => 'integer',
        'ignicao'        => 'boolean',
        'capturado_em'   => 'datetime',
        'recebido_em'    => 'datetime',
        'bruto'          => 'array',
    ];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function viagem(): BelongsTo
    {
        return $this->belongsTo(Viagem::class);
    }
}
