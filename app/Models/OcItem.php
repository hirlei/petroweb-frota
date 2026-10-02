<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\EmpresaViaPaiScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item de uma ordem de coleta. O escopo por empresa é herdado da ordem-pai —
 * esta tabela não carrega `empresa_id`. Cada item pode trazer a chave da NF-e
 * que originou a carga (44 dígitos), reaproveitada depois no CT-e e no MDF-e.
 */
class OcItem extends Model
{
    protected $table = 'oc_itens';

    protected $guarded = [];

    protected $casts = [
        'quantidade' => 'decimal:4',
        'peso'       => 'decimal:4',
        'volume'     => 'decimal:4',
        'valor'      => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new EmpresaViaPaiScope('ordemColeta'));
    }

    public function ordemColeta(): BelongsTo
    {
        return $this->belongsTo(OrdemColeta::class);
    }

    public function mercadoria(): BelongsTo
    {
        return $this->belongsTo(Mercadoria::class);
    }
}
