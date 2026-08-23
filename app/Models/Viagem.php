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

    public function despesas(): HasMany
    {
        return $this->hasMany(Despesa::class);
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class);
    }

    /** CT-e transportados nesta viagem (N:N, RN-02). */
    public function ctes(): BelongsToMany
    {
        return $this->belongsToMany(Cte::class, 'viagem_ctes')
            ->withPivot(['sequencia', 'papel', 'entregue_em'])
            ->withTimestamps()
            ->orderByPivot('sequencia');
    }

    /** Recalcula a receita a partir dos CT-e vinculados e reconsolida a margem. */
    public function recalcularReceitaDosCtes(): void
    {
        $this->receita_total = (float) $this->ctes()->sum('valor_total_servico');
        $this->consolidarCustos();
        $this->save();
    }

    /**
     * Recompõe os custos que vêm das despesas aprovadas (pedágio, motorista,
     * outros) a partir do que está lançado, deixando combustível e manutenção
     * como estão (esses vêm de abastecimento e OS). Em seguida consolida total,
     * margem e custo por km. É o observer de despesa, disparado pelo formulário.
     */
    public function recalcularCustosDeDespesas(): void
    {
        $porComponente = $this->despesas()
            ->where('aprovada', true)
            ->get(['tipo', 'valor'])
            ->groupBy(fn (Despesa $d): string => $d->componenteCusto())
            ->map(fn ($grupo) => (float) $grupo->sum('valor'));

        $this->custo_pedagio = $porComponente->get('custo_pedagio', 0.0);
        $this->custo_motorista = $porComponente->get('custo_motorista', 0.0);
        $this->custo_outros = $porComponente->get('custo_outros', 0.0);

        $this->consolidarCustos();
        $this->save();
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
            + (float) $this->custo_outros;

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
