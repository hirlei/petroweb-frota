<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\EmpresaViaPaiScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O papel que uma pessoa exerce. `dados` guarda o que só faz sentido naquele
 * papel — limite de crédito do cliente, especialidade da oficina, ramo do
 * fornecedor — sem poluir `pessoas` com dezenas de colunas quase sempre nulas.
 *
 * Escopo vem por herança de `pessoas` (EmpresaViaPaiScope), não daqui.
 */
class PessoaPapel extends Model
{
    protected $table = 'pessoa_papeis';

    protected $guarded = [];

    protected $casts = [
        'dados' => 'array',
        'ativo' => 'boolean',
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
}
