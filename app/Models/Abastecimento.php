<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Abastecimento (rotina 2060). A média de consumo só é confiável entre dois
 * tanques cheios; o desvio é medido contra a `media_referencia_kml` do veículo,
 * e dispara `alerta` quando passa do limite — é o sinal de bico, rota ineficiente
 * ou desvio de combustível.
 */
class Abastecimento extends Model
{
    use PertenceAEmpresa;

    protected $table = 'abastecimentos';

    protected $guarded = [];

    protected $casts = [
        'data_hora' => 'datetime',
        'litros' => 'decimal:3',
        'valor_litro' => 'decimal:2',
        'valor_total' => 'decimal:2',
        'odometro' => 'decimal:2',
        'tanque_cheio' => 'boolean',
        'km_percorrido' => 'decimal:2',
        'media_calculada' => 'decimal:3',
        'desvio_percentual' => 'decimal:2',
        'alerta' => 'boolean',
    ];

    /** Desvio acima disto (em módulo) acende o alerta. */
    public const LIMITE_DESVIO_PCT = 10.0;

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Motorista::class);
    }

    public function posto(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'posto_id');
    }

    public function scopeComAlerta(Builder $query): Builder
    {
        return $query->where('alerta', true);
    }
}
