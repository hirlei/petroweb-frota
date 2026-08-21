<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\EmpresaViaPaiScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unidade dentro da combinação, com a posição. Sem timestamps: é elo, não
 * cadastro — a auditoria de quem montou fica na composição.
 */
class ComposicaoItem extends Model
{
    protected $table = 'composicao_itens';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'ordem' => 'integer',
    ];

    /** Escopo herdado de `Composicao` — esta tabela não carrega `empresa_id`. */
    protected static function booted(): void
    {
        static::addGlobalScope(new EmpresaViaPaiScope('composicao'));
    }

    public function composicao(): BelongsTo
    {
        return $this->belongsTo(Composicao::class);
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }
}
