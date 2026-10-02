<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\EmpresaViaPaiScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Documento (CT-e/NF-e) vinculado ao MDF-e. Escopo herdado do MDF-e. */
class MdfeDocumento extends Model
{
    protected $table = 'mdfe_documentos';

    protected $guarded = [];

    protected $casts = [
        'indicador_reentrega' => 'boolean',
        'peso' => 'decimal:4',
        'valor' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new EmpresaViaPaiScope('mdfe'));
    }

    public function mdfe(): BelongsTo
    {
        return $this->belongsTo(Mdfe::class);
    }

    public function cte(): BelongsTo
    {
        return $this->belongsTo(Cte::class);
    }
}
