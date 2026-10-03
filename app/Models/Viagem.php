<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Viagem (rotina 3020) — a execução física do transporte.
 *
 * Uma composição, um ou dois motoristas, uma rota, e os CT-e que carrega (N:N,
 * RN-02). Os custos são denormalizados e recalculados por evento; margem e
 * custo por km derivam deles. A composição é congelada em `composicao_snapshot`
 * no momento da viagem — troca posterior de reboque não altera o histórico.
 */
class Viagem extends Model
{
    use PertenceAEmpresa;

    protected $table = 'viagens';

    protected $guarded = [];

    protected $casts = [
        'composicao_snapshot' => 'array',
        'saida_prevista'      => 'datetime',
        'saida_real'          => 'datetime',
        'chegada_prevista'    => 'datetime',
        'chegada_real'        => 'datetime',
        'km_inicial'          => 'decimal:2',
        'km_final'            => 'decimal:2',
        'km_percorrido'       => 'decimal:2',
        'peso_total'          => 'decimal:2',
        'valor_carga'         => 'decimal:2',
        'custo_combustivel'   => 'decimal:2',
        'custo_pedagio'       => 'decimal:2',
        'custo_motorista'     => 'decimal:2',
        'custo_manutencao'    => 'decimal:2',
        'custo_outros'        => 'decimal:2',
        'custo_terceiro'      => 'decimal:2',
        'custos_digitados'    => 'array',
        'custos_recalculados_em' => 'datetime',
        'custo_total'         => 'decimal:2',
        'receita_total'       => 'decimal:2',
        'margem'              => 'decimal:2',
        'custo_por_km'        => 'decimal:4',
    ];

    public const TIPOS = ['carga_lotacao', 'fracionada', 'transferencia', 'carga_propria', 'retorno_vazio'];

    public const STATUSES = ['planejada', 'carregando', 'em_transito', 'entregue', 'encerrada', 'cancelada'];

    public function filial(): BelongsTo
    {
        return $this->belongsTo(Filial::class);
    }

    public function veiculoTracao(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'veiculo_tracao_id');
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Motorista::class, 'motorista_id');
    }

    public function motorista2(): BelongsTo
    {
        return $this->belongsTo(Motorista::class, 'motorista_2_id');
    }

    public function rota(): BelongsTo
    {
        return $this->belongsTo(Rota::class);
    }

    public function municipioOrigem(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'municipio_origem_id');
    }

    public function municipioDestino(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'municipio_destino_id');
    }

    /** Abastecimentos lançados na viagem (custo de combustível, 3080). */
    public function abastecimentos(): HasMany
    {
        return $this->hasMany(Abastecimento::class);
    }

    public function despesas(): HasMany
    {
        return $this->hasMany(Despesa::class);
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class);
    }

    public function ciots(): HasMany
    {
        return $this->hasMany(Ciot::class);
    }

    /** O CIOT que vale para a viagem — no máximo um não cancelado (índice parcial). */
    public function ciot(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Ciot::class)->where('status', '<>', 'cancelado');
    }

    public function adiantamentos(): HasMany
    {
        return $this->hasMany(Adiantamento::class)->orderBy('data')->orderBy('id');
    }

    /** O acerto que vale para a viagem (3070) — no máximo um fechado. */
    public function acerto(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AcertoViagem::class)->where('status', 'fechado');
    }

    public function valesPedagio(): HasMany
    {
        return $this->hasMany(ValePedagio::class);
    }

    /**
     * Quem registra o CIOT desta viagem (App\Domain\Fiscal\RegrasCiot): pelo
     * motorista (TAC?), pela propriedade do veículo de tração e pelo tipo.
     */
    public function modalidadeCiot(): string
    {
        $this->loadMissing(['veiculoTracao.proprietario', 'motorista']);
        $v = $this->veiculoTracao;
        $proprio = $v === null || $v->propriedade !== Veiculo::PROPRIEDADE_TERCEIRO;
        $tp = $v?->proprietario_tp_transp ?: $v?->proprietario?->tp_transp;
        if (! $proprio && ($tp === null || $tp === '') && $v?->proprietario !== null) {
            $tp = $v->proprietario->ehPessoaFisica() ? '2' : '1';
        }

        return \App\Domain\Fiscal\RegrasCiot::modalidade(
            (string) $this->tipo,
            $proprio,
            $tp !== null && $tp !== '' ? (string) $tp : null,
            (bool) $this->motorista?->ehTac(),
        );
    }

    /** CT-e transportados nesta viagem (N:N, RN-02). */
    public function ctes(): BelongsToMany
    {
        return $this->belongsToMany(Cte::class, 'viagem_ctes')
            ->withPivot(['sequencia', 'papel', 'entregue_em'])
            ->withTimestamps()
            ->orderByPivot('sequencia');
    }

    /** Recalcula a receita a partir dos CT-e vinculados e reconsolida a margem (3080). */
    public function recalcularReceitaDosCtes(): void
    {
        app(\App\Services\Operacao\CustosDaViagem::class)->recalcular($this);
    }

    /**
     * Recompõe custo, receita e margem a partir dos lançamentos da viagem
     * (rotina 3080 — App\Services\Operacao\CustosDaViagem). O nome ficou por
     * compatibilidade: despesas, acerto e telas antigas chamam este método.
     */
    public function recalcularCustosDeDespesas(): void
    {
        app(\App\Services\Operacao\CustosDaViagem::class)->recalcular($this);
    }

    /**
     * Consolida os custos denormalizados: soma os componentes, recalcula total,
     * margem e custo por km. Chamado no save e (futuramente) pelo observer.
     */
    public function consolidarCustos(): void
    {
        $this->custo_total = (float) $this->custo_combustivel
            + (float) $this->custo_pedagio
            + (float) $this->custo_motorista
            + (float) $this->custo_manutencao
            + (float) $this->custo_outros
            + (float) $this->custo_terceiro;

        $this->margem = (float) $this->receita_total - (float) $this->custo_total;

        $km = (float) $this->km_percorrido;
        $this->custo_por_km = $km > 0 ? round((float) $this->custo_total / $km, 4) : null;
    }

    public function percentualMargem(): ?float
    {
        $receita = (float) $this->receita_total;

        return $receita > 0 ? round((float) $this->margem / $receita * 100, 1) : null;
    }

    public function emAndamento(): bool
    {
        return in_array($this->status, ['planejada', 'carregando', 'em_transito'], true);
    }

    public function scopeAbertas(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['encerrada', 'cancelada']);
    }
}
