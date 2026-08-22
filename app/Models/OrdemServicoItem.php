<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\EmpresaViaPaiScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item (peça ou serviço) de uma ordem de serviço. Escopo por empresa herdado
 * da OS-pai — esta tabela não carrega `empresa_id`.
 */
class OrdemServicoItem extends Model
{
    protected $table = 'os_itens';

    protected $guarded = [];

    protected $casts = [
        'quantidade' => 'decimal:3',
        'valor_unitario' => 'decimal:2',
        'valor_total' => 'decimal:2',
        'garantia_ate' => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new EmpresaViaPaiScope('ordemServico'));
    }

    public function ordemServico(): BelongsTo
    {
        return $this->belongsTo(OrdemServico::class);
    }
}
