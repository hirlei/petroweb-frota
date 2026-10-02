<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baixa de um título (rotina 5020). Estorno NÃO apaga: marca `estornado_em`,
 * guarda quem e por quê, e o saldo do título é recalculado sem ele.
 */
class Recebimento extends Model
{
    use PertenceAEmpresa;

    protected $table = 'recebimentos';

    protected $guarded = [];

    protected $casts = [
        'data'            => 'date',
        'valor_principal' => 'decimal:2',
        'juros_multa'     => 'decimal:2',
        'desconto'        => 'decimal:2',
        'valor_total'     => 'decimal:2',
        'estornado_em'    => 'datetime',
    ];

    public const FORMAS = ['pix', 'boleto', 'transferencia', 'dinheiro'];

    public function titulo(): BelongsTo
    {
        return $this->belongsTo(TituloReceber::class, 'titulo_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function estornadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estornado_por');
    }

    public function estornado(): bool
    {
        return $this->estornado_em !== null;
    }

    public function scopeValidos(Builder $q): Builder
    {
        return $q->whereNull('estornado_em');
    }
}
