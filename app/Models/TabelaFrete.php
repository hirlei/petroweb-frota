<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Tabela de frete (rotina 1030).
 *
 * Vale para um cliente (`pessoa_id`) ou é geral (nulo). O preço é a soma dos
 * itens — cada componente com sua base de cálculo. A vigência é o que decide
 * qual tabela responde numa data; duas tabelas do mesmo cliente com vigências
 * que se sobrepõem é erro de cadastro, não do sistema.
 */
class TabelaFrete extends Model
{
    use PertenceAEmpresa;

    protected $table = 'tabelas_frete';

    protected $guarded = [];

    protected $casts = [
        'vigencia_inicio' => 'date',
        'vigencia_fim'    => 'date',
        'ativo'           => 'boolean',
    ];

    public const COMPONENTES = [
        'peso', 'valor', 'gris', 'advalorem', 'pedagio', 'tde', 'tda', 'taxa_entrega', 'outros',
    ];

    public const BASES_CALCULO = [
        'por_kg', 'por_ton', 'percentual_valor', 'fixo', 'por_km', 'por_volume',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }

    public function municipioOrigem(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'municipio_origem_id');
    }

    public function municipioDestino(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'municipio_destino_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(TabelaFreteItem::class)->orderBy('ordem');
    }

    public function ehGeral(): bool
    {
        return $this->pessoa_id === null;
    }

    public function vigenteEm(?Carbon $em = null): bool
    {
        $em = $em ?? Carbon::today();

        if (! $this->ativo || $this->vigencia_inicio->gt($em)) {
            return false;
        }

        return $this->vigencia_fim === null || $this->vigencia_fim->gte($em);
    }

    public function scopeVigentes(Builder $query, ?Carbon $em = null): Builder
    {
        $em = $em ?? Carbon::today();

        return $query->where('ativo', true)
            ->whereDate('vigencia_inicio', '<=', $em)
            ->where(function (Builder $q) use ($em): void {
                $q->whereNull('vigencia_fim')->orWhereDate('vigencia_fim', '>=', $em);
            });
    }
}
