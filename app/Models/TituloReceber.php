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
 * Título a receber (rotina 5020) — uma parcela de fatura.
 *
 * `valor_baixado` soma principal + desconto dos recebimentos não estornados;
 * é o que abate o saldo. Juros e multa entram no caixa mas não abatem saldo.
 */
class TituloReceber extends Model
{
    use PertenceAEmpresa;

    protected $table = 'titulos_receber';

    protected $guarded = [];

    protected $casts = [
        'vencimento'    => 'date',
        'valor'         => 'decimal:2',
        'valor_baixado' => 'decimal:2',
        'parcela'       => 'integer',
        'parcelas'      => 'integer',
    ];

    public const STATUSES = ['aberto', 'parcial', 'recebido', 'cancelado'];

    public const EM_ABERTO = ['aberto', 'parcial'];

    public function fatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class);
    }

    public function tomador(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'tomador_id');
    }

    public function recebimentos(): HasMany
    {
        return $this->hasMany(Recebimento::class, 'titulo_id')->orderBy('data')->orderBy('id');
    }

    public function saldo(): float
    {
        if ($this->status === 'cancelado') {
            return 0.0;
        }

        return max(0.0, round((float) $this->valor - (float) $this->valor_baixado, 2));
    }

    public function emAberto(): bool
    {
        return in_array($this->status, self::EM_ABERTO, true);
    }

    public function vencido(?Carbon $hoje = null): bool
    {
        $hoje ??= Carbon::today();

        return $this->emAberto() && $this->vencimento !== null && $this->vencimento->lt($hoje);
    }

    public function diasAtraso(?Carbon $hoje = null): int
    {
        $hoje ??= Carbon::today();

        return $this->vencido($hoje) ? (int) $this->vencimento->diffInDays($hoje) : 0;
    }

    public function scopeEmAberto(Builder $q): Builder
    {
        return $q->whereIn('status', self::EM_ABERTO);
    }

    public function scopeVencidos(Builder $q): Builder
    {
        return $q->whereIn('status', self::EM_ABERTO)->whereDate('vencimento', '<', Carbon::today());
    }
}
