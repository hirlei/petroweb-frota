<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Despesa de viagem (rotina 3060).
 *
 * Um gasto de estrada amarrado à viagem e ao motorista. Só entra no custo da
 * viagem quando APROVADA — e no componente certo, conforme o mapa abaixo. O
 * recálculo em si mora na Viagem (recalcularCustosDeDespesas), disparado pelo
 * formulário; o mapa aqui é a regra de para onde cada tipo vai.
 */
class Despesa extends Model
{
    use PertenceAEmpresa;

    protected $table = 'despesas_viagem';

    protected $guarded = [];

    protected $casts = [
        'data'        => 'date',
        'valor'       => 'decimal:2',
        'aprovada'    => 'boolean',
        'aprovada_em' => 'datetime',
    ];

    public const TIPOS = [
        'pedagio', 'alimentacao', 'hospedagem', 'estacionamento',
        'lavagem', 'chapa', 'balanca', 'multa', 'outros',
    ];

    public const FORMAS_PAGAMENTO = ['adiantamento', 'cartao', 'reembolso', 'empresa'];

    public const ORIGENS = ['manual', 'app_motorista'];

    /**
     * Para qual coluna de custo da viagem cada tipo soma. Pedágio é próprio;
     * gastos de estrada do condutor vão para o custo do motorista; o resto cai
     * em "outros".
     */
    public const COMPONENTE_CUSTO = [
        'pedagio'        => 'custo_pedagio',
        'alimentacao'    => 'custo_motorista',
        'hospedagem'     => 'custo_motorista',
        'estacionamento' => 'custo_motorista',
        'lavagem'        => 'custo_motorista',
        'chapa'          => 'custo_motorista',
        'balanca'        => 'custo_motorista',
        'multa'          => 'custo_outros',
        'outros'         => 'custo_outros',
    ];

    public function componenteCusto(): string
    {
        return self::COMPONENTE_CUSTO[$this->tipo] ?? 'custo_outros';
    }

    public function viagem(): BelongsTo
    {
        return $this->belongsTo(Viagem::class);
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Motorista::class);
    }

    public function aprovadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprovada_por');
    }

    public function scopeAprovadas(Builder $query): Builder
    {
        return $query->where('aprovada', true);
    }

    public function scopePendentes(Builder $query): Builder
    {
        return $query->where('aprovada', false);
    }
}
