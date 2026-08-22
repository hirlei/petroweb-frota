<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ordem de serviço de manutenção (rotina 2050). O total é a soma dos itens
 * (peças + mão de obra); recalculado a cada gravação.
 */
class OrdemServico extends Model
{
    use PertenceAEmpresa;

    protected $table = 'ordens_servico';

    protected $guarded = [];

    protected $casts = [
        'interna' => 'boolean',
        'abertura' => 'date',
        'previsao' => 'date',
        'encerramento' => 'date',
        'odometro' => 'decimal:2',
        'valor_pecas' => 'decimal:2',
        'valor_mao_obra' => 'decimal:2',
        'valor_total' => 'decimal:2',
    ];

    public const TIPOS = ['preventiva', 'corretiva', 'sinistro', 'pneu', 'revisao'];

    public const STATUS = ['aberta', 'em_execucao', 'aguardando_peca', 'encerrada', 'cancelada'];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function oficina(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'oficina_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(OrdemServicoItem::class);
    }

    public function estaAberta(): bool
    {
        return ! in_array($this->status, ['encerrada', 'cancelada'], true);
    }

    public function scopeAbertas(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['encerrada', 'cancelada']);
    }
}
