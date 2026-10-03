<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Adiantamento ao motorista para a viagem (rotina 3070). Entra no acerto; só
 * muda enquanto a viagem não tem acerto fechado.
 */
class Adiantamento extends Model
{
    use PertenceAEmpresa;

    protected $table = 'adiantamentos';

    protected $guarded = [];

    protected $casts = [
        'data' => 'date',
        'valor' => 'decimal:2',
    ];

    public const FORMAS = ['dinheiro', 'pix', 'cartao_frota', 'deposito'];

    public const FINALIDADES = ['geral', 'pedagio', 'combustivel', 'alimentacao'];

    public function viagem(): BelongsTo
    {
        return $this->belongsTo(Viagem::class);
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Motorista::class);
    }
}
