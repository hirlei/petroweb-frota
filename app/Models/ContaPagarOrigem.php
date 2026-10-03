<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Este abastecimento / esta OS / este CIOT virou esta conta." `ativo` = false
 * quando a conta é cancelada: o item volta para os lançamentos esperando. O
 * índice parcial do banco garante uma conta ativa por origem.
 */
class ContaPagarOrigem extends Model
{
    use PertenceAEmpresa;

    protected $table = 'conta_pagar_origens';

    protected $guarded = [];

    protected $casts = [
        'valor' => 'decimal:2',
        'ativo' => 'boolean',
    ];

    public const TIPOS = ['abastecimento', 'ordem_servico', 'ciot', 'acerto'];

    public function conta(): BelongsTo
    {
        return $this->belongsTo(ContaPagar::class, 'conta_pagar_id');
    }
}
