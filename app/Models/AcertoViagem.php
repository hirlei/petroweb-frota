<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Acerto de viagem fechado (rotina 3070) — snapshot dos totais no fechamento.
 * `saldo` > 0: a empresa paga (vira conta no 5030); < 0: o motorista devolve.
 * Reabrir marca `reaberto`; o novo fechamento é outra linha (índice parcial:
 * um fechado por viagem). Toda mudança passa por App\Services\Operacao\Acertos.
 */
class AcertoViagem extends Model
{
    use PertenceAEmpresa;

    protected $table = 'acertos_viagem';

    protected $guarded = [];

    protected $casts = [
        'total_adiantado' => 'decimal:2',
        'gasto_adiantamento' => 'decimal:2',
        'bolso_aceito' => 'decimal:2',
        'glosado' => 'decimal:2',
        'dias' => 'integer',
        'diaria_valor' => 'decimal:2',
        'diarias_total' => 'decimal:2',
        'comissao_percentual' => 'decimal:2',
        'comissao_base' => 'decimal:2',
        'comissao_valor' => 'decimal:2',
        'saldo' => 'decimal:2',
        'devolvido_em' => 'date',
        'fechado_em' => 'datetime',
        'reaberto_em' => 'datetime',
    ];

    public function viagem(): BelongsTo
    {
        return $this->belongsTo(Viagem::class);
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Motorista::class);
    }

    public function contaPagar(): BelongsTo
    {
        return $this->belongsTo(ContaPagar::class);
    }

    public function fechadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fechado_por');
    }

    public function empresaPaga(): bool
    {
        return (float) $this->saldo > 0;
    }

    public function motoristaDevolve(): bool
    {
        return (float) $this->saldo < 0;
    }

    public function scopeFechados(Builder $q): Builder
    {
        return $q->where('status', 'fechado');
    }
}
