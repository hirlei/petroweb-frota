<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ocorrência operacional (rotina 3040) — avaria, extravio, atraso, sinistro,
 * multa. `viagem_id`/`cte_id` são referência solta enquanto essas tabelas não
 * existem; entram como relação de verdade quando os módulos chegarem.
 */
class Ocorrencia extends Model
{
    use PertenceAEmpresa;

    protected $table = 'ocorrencias';

    protected $guarded = [];

    protected $casts = [
        'data_hora' => 'datetime',
        'valor_prejuizo' => 'decimal:2',
        'anexos' => 'array',
    ];

    public const TIPOS = [
        'avaria', 'extravio', 'atraso', 'devolucao', 'sinistro', 'multa', 'parada_nao_prevista', 'outros',
    ];

    public const RESPONSAVEIS = ['transportadora', 'cliente', 'terceiro', 'indeterminado'];

    public const STATUS = ['aberta', 'em_analise', 'resolvida'];

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function registrante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrada_por');
    }

    public function estaAberta(): bool
    {
        return $this->status !== 'resolvida';
    }

    public function scopeAbertas(Builder $query): Builder
    {
        return $query->where('status', '!=', 'resolvida');
    }
}
