<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Mercadoria (rotina 1020) — o catálogo de cargas.
 *
 * Os campos de produto perigoso alimentam o grupo `peri` DO MDF-e — o CT-e
 * rodoviário não tem `peri`. O fator de cubagem não tem norma: é parâmetro em
 * cascata (tabela de frete → cliente → produto → empresa), e aqui fica o nível
 * do produto.
 */
class Mercadoria extends Model
{
    use PertenceAEmpresa;
    use SoftDeletes;

    protected $table = 'mercadorias';

    protected $guarded = [];

    protected $casts = [
        'ativo' => 'boolean',
        'peso_bruto_kg' => 'decimal:4',
        'peso_liquido_kg' => 'decimal:4',
        'volume_m3' => 'decimal:4',
        'densidade_kg_m3' => 'decimal:4',
        'fator_cubagem_kg_m3' => 'decimal:4',
        'permite_empilhar' => 'boolean',
        'fragil' => 'boolean',
        'sentido_obrigatorio' => 'boolean',
        'exige_temp_controlada' => 'boolean',
        'temp_min_c' => 'decimal:2',
        'temp_max_c' => 'decimal:2',
        'exige_registro_continuo' => 'boolean',
        'eh_perigoso' => 'boolean',
        'ponto_fulgor_c' => 'decimal:2',
        'risco_ambiental' => 'boolean',
        'exige_mopp' => 'boolean',
        'exige_kit_9735' => 'boolean',
        'pce_exercito' => 'boolean',
        'exige_guia_trafego' => 'boolean',
        'controlado_pf' => 'boolean',
        'exige_mapa_siproquim' => 'boolean',
        'origem_animal' => 'boolean',
        'eh_agrotoxico' => 'boolean',
    ];

    public function naturezaCarga(): BelongsTo
    {
        return $this->belongsTo(NaturezaCargaTabela::class, 'natureza_carga_id');
    }

    public function carroceriaRecomendada(): BelongsTo
    {
        return $this->belongsTo(Carroceria::class, 'carroceria_recomendada_id');
    }

    /** Volume calculado das dimensões, quando não informado direto. */
    public function volumeCalculado(): ?float
    {
        if ($this->comprimento_m === null || $this->largura_m === null || $this->altura_m === null) {
            return null;
        }

        return round((float) $this->comprimento_m * (float) $this->largura_m * (float) $this->altura_m, 4);
    }

    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function scopePerigosas(Builder $query): Builder
    {
        return $query->where('eh_perigoso', true);
    }
}
