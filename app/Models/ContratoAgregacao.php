<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Contrato de agregação — o vínculo COMERCIAL com o TAC agregado.
 *
 * As três apólices não são burocracia: RCTR-C cobre a carga em acidente,
 * RCF-DC cobre o desaparecimento e RCV a responsabilidade civil do veículo.
 * Contrato com apólice vencida é risco que a transportadora assume sozinha.
 */
class ContratoAgregacao extends Model
{
    use PertenceAEmpresa;

    protected $table = 'contratos_agregacao';

    protected $guarded = [];

    protected $casts = [
        'inicio'        => 'date',
        'fim'           => 'date',
        'exclusividade' => 'boolean',
        'valor_base'    => 'decimal:2',
        'percentual'    => 'decimal:4',
        'validade_apolices' => 'date',
        'conta_pagamento' => 'array',
    ];

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function vigente(?Carbon $em = null): bool
    {
        $em = $em ?? Carbon::today();

        return $this->status === 'vigente'
            && $this->inicio->lte($em)
            && ($this->fim === null || $this->fim->gte($em));
    }

    public function apolicesVigentes(?Carbon $em = null): bool
    {
        return $this->validade_apolices !== null
            && $this->validade_apolices->gte($em ?? Carbon::today());
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('status', 'vigente');
    }
}
