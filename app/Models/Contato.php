<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use App\Models\Scopes\EmpresaViaPaiScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `recebe_dfe` marca quem recebe XML e DACTE por e-mail na autorização.
 * É o campo que evita o "manda pro financeiro também" toda semana.
 */
class Contato extends Model
{
    protected $table = 'contatos';

    protected $guarded = [];

    protected $casts = [
        'recebe_dfe' => 'boolean',
    ];

    /** Escopo herdado de `Pessoa` — esta tabela não carrega `empresa_id`. */
    protected static function booted(): void
    {
        static::addGlobalScope(new EmpresaViaPaiScope('pessoa'));
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    public function scopeRecebeDfe(Builder $query): Builder
    {
        return $query->where('recebe_dfe', true)->whereNotNull('email');
    }
}
