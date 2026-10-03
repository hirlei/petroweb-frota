<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\EmpresaViaPaiScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evento do CT-e (§7.4): 110110 CC-e, 110111 cancelamento, 110180 comprovante
 * de entrega (110181 cancela). Escopo herdado do CT-e.
 */
class CteEvento extends Model
{
    protected $table = 'cte_eventos';

    protected $guarded = [];

    protected $casts = [
        'data_evento' => 'datetime',
        'correcoes' => 'array',
        'sequencia' => 'integer',
    ];

    public const CCE = '110110';
    public const CANCELAMENTO = '110111';

    public const TIPOS = [
        '110110' => 'Carta de Correção',
        '110111' => 'Cancelamento',
        '110113' => 'EPEC',
        '110180' => 'Comprovante de entrega',
        '110181' => 'Cancelamento do comprovante',
        '610110' => 'Prestação em desacordo',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new EmpresaViaPaiScope('cte'));
    }

    public function cte(): BelongsTo
    {
        return $this->belongsTo(Cte::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }
}
