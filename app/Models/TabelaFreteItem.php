<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\EmpresaViaPaiScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item (componente) de uma tabela de frete. O escopo por empresa é herdado da
 * tabela-pai — esta tabela não carrega `empresa_id`.
 */
class TabelaFreteItem extends Model
{
    protected $table = 'tabela_frete_itens';

    protected $guarded = [];

    protected $casts = [
        'faixa_de'  => 'decimal:4',
        'faixa_ate' => 'decimal:4',
        'valor'     => 'decimal:4',
        'minimo'    => 'decimal:2',
        'maximo'    => 'decimal:2',
        'ordem'     => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new EmpresaViaPaiScope('tabelaFrete'));
    }

    public function tabelaFrete(): BelongsTo
    {
        return $this->belongsTo(TabelaFrete::class);
    }
}
