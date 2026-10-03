<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Frota\RegrasMotorista;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * O motorista é um PAPEL sobre `pessoas`, não uma pessoa paralela.
 *
 * RN-12 — agregado e autônomo NÃO têm jornada no sistema. Lei 11.442/2007
 * art. 5º e a ADC 48 do STF põem o TAC fora do vínculo empregatício; registrar
 * jornada dele é fabricar prova contra o próprio cliente. Por isso
 * `controlaJornada()` existe: é a porta única que as telas consultam.
 */
class Motorista extends Model
{
    use PertenceAEmpresa;
    use SoftDeletes;

    protected $table = 'motoristas';

    protected $guarded = [];

    protected $casts = [
        'cnh_validade'  => 'date',
        'cnh_primeira_habilitacao' => 'date',
        'cnh_ear'       => 'boolean',
        'rntrc_validade' => 'date',
        'admissao'      => 'date',
        'demissao'      => 'date',
        'toxicologico_data' => 'date',
        'toxicologico_validade' => 'date',
        'mopp_validade' => 'date',
        'curso_carga_indivisivel_validade' => 'date',
        'valor_diaria'  => 'decimal:2',
        'percentual_comissao' => 'decimal:4',
        'valor_por_km'  => 'decimal:4',
        'conta_pagamento' => 'array',
    ];

    public const VINCULO_CLT = RegrasMotorista::VINCULO_CLT;
    public const VINCULO_AGREGADO = RegrasMotorista::VINCULO_AGREGADO;
    public const VINCULO_AUTONOMO = RegrasMotorista::VINCULO_AUTONOMO;
    public const VINCULO_TERCEIRO = RegrasMotorista::VINCULO_TERCEIRO;

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    public function contratoAgregacao(): BelongsTo
    {
        return $this->belongsTo(ContratoAgregacao::class, 'contrato_agregacao_id');
    }

    /** RN-12. Única fonte da verdade para telas de jornada, escala e ponto. */
    public function controlaJornada(): bool
    {
        return RegrasMotorista::controlaJornada((string) $this->vinculo);
    }

    /** Agregado e autônomo são TAC: RNTRC próprio e CIOT pela instituição de pagamento. */
    public function ehTac(): bool
    {
        return RegrasMotorista::ehTac((string) $this->vinculo);
    }

    public function exigeCiot(): bool
    {
        return RegrasMotorista::exigeCiot((string) $this->vinculo);
    }

    /**
     * Documentos que impedem a viagem. O toxicológico é exigência da
     * Lei 13.103/2015 para as categorias C, D e E — vencido, não roda.
     */
    public function pendenciasBloqueantes(?Carbon $em = null): array
    {
        $em = $em ?? Carbon::today();
        $pendencias = [];

        if ($this->cnh_validade !== null && $this->cnh_validade->lt($em)) {
            $pendencias[] = 'CNH vencida';
        }

        if (! $this->cnh_ear) {
            $pendencias[] = 'CNH sem observação EAR';
        }

        $exigeToxicologico = RegrasMotorista::exigeToxicologico((string) $this->cnh_categoria);

        if ($exigeToxicologico
            && ($this->toxicologico_validade === null || $this->toxicologico_validade->lt($em))) {
            $pendencias[] = 'Exame toxicológico vencido ou ausente';
        }

        if ($this->ehTac() && $this->rntrc_validade !== null && $this->rntrc_validade->lt($em)) {
            $pendencias[] = 'RNTRC vencido';
        }

        return $pendencias;
    }

    public function podeViajar(?Carbon $em = null): bool
    {
        return $this->status === 'ativo' && $this->pendenciasBloqueantes($em) === [];
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('status', 'ativo');
    }

    public function scopeComJornada(Builder $query): Builder
    {
        return $query->where('vinculo', self::VINCULO_CLT);
    }
}
