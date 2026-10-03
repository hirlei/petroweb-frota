<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pagamento ao TAC de um CIOT — adiantamento (na emissão) ou saldo (depois da
 * entrega). Sempre pela instituição de pagamento, na conta do próprio TAC ou
 * em cartão frete. Recusado fica registrado: é histórico, não some.
 */
class CiotPagamento extends Model
{
    use PertenceAEmpresa;

    protected $table = 'ciot_pagamentos';

    protected $guarded = [];

    protected $casts = [
        'valor' => 'decimal:2',
        'data' => 'date',
        'retorno' => 'array',
    ];

    public function ciot(): BelongsTo
    {
        return $this->belongsTo(Ciot::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }
}
