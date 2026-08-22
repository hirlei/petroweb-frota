<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entrega / POD (rotina 3050) — a prova de entrega.
 *
 * Quem recebeu, quando, e o comprovante. A comprovação eletrônica é o evento
 * 110180 do CT-e; `cte_id` e `evento_cte_id` ficam reservados até o módulo
 * fiscal (4010). Por ora a entrega se amarra à viagem e à ordem de coleta.
 */
class Entrega extends Model
{
    use PertenceAEmpresa;

    protected $table = 'entregas';

    protected $guarded = [];

    protected $casts = [
        'data_hora' => 'datetime',
        'latitude'  => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public const TIPOS_COMPROVACAO = ['fisico_digitalizado', 'foto', 'evento_eletronico'];

    public function viagem(): BelongsTo
    {
        return $this->belongsTo(Viagem::class);
    }

    public function ordemColeta(): BelongsTo
    {
        return $this->belongsTo(OrdemColeta::class);
    }

    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrada_por');
    }

    public function comprovada(): bool
    {
        return $this->canhoto_path !== null
            || $this->assinatura_path !== null
            || $this->tipo_comprovacao === 'evento_eletronico';
    }

    public function scopeComprovadas(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNotNull('canhoto_path')
                ->orWhereNotNull('assinatura_path')
                ->orWhere('tipo_comprovacao', 'evento_eletronico');
        });
    }
}
