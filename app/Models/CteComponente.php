<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\EmpresaViaPaiScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Componente do valor da prestação (vTPrest). Escopo herdado do CT-e. */
class CteComponente extends Model
{
    protected $table = 'cte_componentes';

    protected $guarded = [];

    protected $casts = [
        'valor' => 'decimal:2',
        'ordem' => 'integer',
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
