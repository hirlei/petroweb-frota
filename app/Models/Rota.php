<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rota planejada (rotina 3030). Trecho origem→destino com distância, tempo e
 * pedágio estimados, mais os pontos na ordem em que aparecem.
 */
class Rota extends Model
{
    use PertenceAEmpresa;

    protected $table = 'rotas';

    protected $guarded = [];

    protected $casts = [
        'distancia_km' => 'decimal:2',
        'tempo_estimado_min' => 'integer',
        'valor_pedagio_estimado' => 'decimal:2',
        'restricoes' => 'array',
        'geometria'  => 'array',
        'ativa' => 'boolean',
    ];

    public const TIPOS_PONTO = ['origem', 'passagem', 'pedagio', 'parada', 'destino'];

    public function municipioOrigem(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'municipio_origem_id');
    }

    public function municipioDestino(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'municipio_destino_id');
    }

    public function pontos(): HasMany
    {
        return $this->hasMany(RotaPonto::class)->orderBy('ordem');
    }

    public function tempoEstimadoHoras(): ?float
    {
        return $this->tempo_estimado_min === null ? null : round($this->tempo_estimado_min / 60, 1);
    }

    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('ativa', true);
    }
}
