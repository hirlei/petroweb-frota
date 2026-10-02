<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Linha "este CT-e está nesta fatura". `ativo` = false quando a fatura é
 * cancelada: o histórico fica e o CT-e volta a poder ser faturado. O índice
 * parcial do banco garante no máximo uma linha ativa por CT-e.
 */
class FaturaCte extends Model
{
    use PertenceAEmpresa;

    protected $table = 'fatura_ctes';

    protected $guarded = [];

    protected $casts = [
        'valor' => 'decimal:2',
        'ativo' => 'boolean',
    ];

    public function fatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class);
    }

    public function cte(): BelongsTo
    {
        return $this->belongsTo(Cte::class);
    }
}
