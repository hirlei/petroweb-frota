<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Licença de um cliente. Vive no banco CENTRAL — o tenant não pode editar a
 * própria licença, que é exatamente o ponto.
 */
class Licenca extends Model
{
    protected $connection = 'central';

    protected $table = 'licencas';

    protected $guarded = [];

    protected $casts = [
        'modulos'      => 'array',
        'vigencia_ate' => 'date',
        'ativa'        => 'boolean',
        'veiculos_max' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function temModulo(string $modulo): bool
    {
        return in_array($modulo, $this->modulos ?? [], true);
    }

    public function vigente(): bool
    {
        return $this->ativa
            && ($this->vigencia_ate === null || $this->vigencia_ate->isFuture());
    }
}
