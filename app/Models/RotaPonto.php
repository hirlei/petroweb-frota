<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\EmpresaViaPaiScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ponto de uma rota, na ordem em que aparece. Escopo por empresa herdado da
 * rota-pai — esta tabela não carrega `empresa_id`.
 */
class RotaPonto extends Model
{
    protected $table = 'rota_pontos';

    protected $guarded = [];

    protected $casts = [
        'ordem' => 'integer',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'distancia_acumulada_km' => 'decimal:2',
        'valor_pedagio' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new EmpresaViaPaiScope('rota'));
    }

    public function rota(): BelongsTo
    {
        return $this->belongsTo(Rota::class);
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }
}
