<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Fiscal\Enums\CategoriaCombinacaoVeicular;
use App\Models\Scopes\EmpresaOuSistemaScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Combinação de Veículos de Carga — o "bitrem", o "rodotrem", o "vanderleia"
 * do vocabulário do pátio, com os números que a lei impõe.
 *
 * A categoria fiscal NÃO é armazenada: deriva dos eixos. Guardá-la abriria a
 * porta para um cadastro com 7 eixos e categoria de 9.
 */
#[ScopedBy([EmpresaOuSistemaScope::class])]
class CvcConfiguracao extends Model
{
    protected $table = 'cvc_configuracoes';

    protected $guarded = [];

    protected $casts = [
        'eixos'        => 'integer',
        'qtd_unidades' => 'integer',
        'pbtc_kg'      => 'decimal:4',
        'comprimento_max_m' => 'decimal:3',
        'capacidade_min_kg' => 'decimal:4',
        'capacidade_max_kg' => 'decimal:4',
        'exige_aet'    => 'boolean',
        'ativo'        => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function ehDoSistema(): bool
    {
        return $this->empresa_id === null;
    }

    public function categoriaCombinacaoVeicular(): CategoriaCombinacaoVeicular
    {
        return CategoriaCombinacaoVeicular::paraEixos($this->eixos);
    }

    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }
}
