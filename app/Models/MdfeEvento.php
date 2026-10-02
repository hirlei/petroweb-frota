<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\EmpresaViaPaiScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evento do MDF-e (§7.8): 110112 encerramento, 110114 inclusão de condutor,
 * 110118 alteração do pagamento, 110111 cancelamento. Escopo herdado do MDF-e.
 */
class MdfeEvento extends Model
{
    protected $table = 'mdfe_eventos';

    protected $guarded = [];

    protected $casts = [
        'data_evento' => 'datetime',
        'payload' => 'array',
        'sequencia' => 'integer',
    ];

    public const TIPOS = [
        '110111' => 'Cancelamento',
        '110112' => 'Encerramento',
        '110114' => 'Inclusão de condutor',
        '110115' => 'Inclusão de DF-e',
        '110118' => 'Alteração do pagamento',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new EmpresaViaPaiScope('mdfe'));
    }

    public function mdfe(): BelongsTo
    {
        return $this->belongsTo(Mdfe::class);
    }
}
