<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\EmpresaViaPaiScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Documento da carga referenciado no CT-e (NF-e etc.). Escopo herdado do CT-e. */
class CteDocumento extends Model
{
    protected $table = 'cte_documentos';

    protected $guarded = [];

    protected $casts = [
        'emissao' => 'datetime',
        'valor' => 'decimal:2',
        'peso' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new EmpresaViaPaiScope('cte'));
    }

    public function cte(): BelongsTo
    {
        return $this->belongsTo(Cte::class);
    }
}
