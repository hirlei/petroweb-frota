<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Fiscal\Enums\CategoriaCombinacaoVeicular;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A combinação veicular montada — cavalo + reboques, na ORDEM em que roda.
 *
 * A ordem não é enfeite: define qual unidade é a primeira e qual a segunda no
 * `veicReboque` do MDF-e, e é ela que determina a categoria (bitrem, rodotrem,
 * treminhão) e portanto a AET.
 */
class Composicao extends Model
{
    use PertenceAEmpresa;

    protected $table = 'composicoes';

    protected $guarded = [];

    protected $casts = [
        'eixos_total'   => 'integer',
        'tara_total_kg' => 'decimal:4',
        'pbtc_kg'       => 'decimal:4',
        'capacidade_kg' => 'decimal:4',
        'exige_aet'     => 'boolean',
        'ativa'         => 'boolean',
    ];

    public function tracao(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'veiculo_tracao_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ComposicaoItem::class)->orderBy('ordem');
    }

    public function veiculos(): BelongsToMany
    {
        return $this->belongsToMany(Veiculo::class, 'composicao_itens')
            ->withPivot('ordem')
            ->orderByPivot('ordem');
    }

    /**
     * Soma os eixos das unidades e traduz para o código da SEFAZ. É calculado,
     * nunca digitado: o operador troca um reboque e a categoria muda sozinha.
     */
    public function categoriaCombinacaoVeicular(): CategoriaCombinacaoVeicular
    {
        return CategoriaCombinacaoVeicular::paraEixos(
            $this->eixos_total ?? $this->veiculos->sum('eixos')
        );
    }

    /** Acima de 57 t ou 19,80 m a combinação só roda com AET (Res. CONTRAN). */
    public function precisaAet(): bool
    {
        return $this->exige_aet
            || ($this->pbtc_kg !== null && (float) $this->pbtc_kg > 57_000);
    }

    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('ativa', true);
    }
}
