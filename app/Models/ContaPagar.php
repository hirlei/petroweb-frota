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
 * Conta a pagar (rotina 5030) — uma parcela.
 *
 * `valor_baixado` soma principal + desconto dos pagamentos não estornados; é o
 * que abate o saldo. Juros e multa saem do caixa mas não abatem saldo. Conta de
 * frete de TAC (origem `ciot`) só é paga pela instituição, no 4050 — aqui ela
 * espelha os pagamentos do CIOT.
 *
 * Toda mudança de valor/status passa por App\Services\Financeiro\ContasPagar.
 */
class ContaPagar extends Model
{
    use PertenceAEmpresa;

    protected $table = 'contas_pagar';

    protected $guarded = [];

    protected $casts = [
        'emissao' => 'date',
        'vencimento' => 'date',
        'valor' => 'decimal:2',
        'valor_baixado' => 'decimal:2',
        'parcela' => 'integer',
        'parcelas' => 'integer',
        'cancelado_em' => 'datetime',
    ];

    public const STATUSES = ['aberto', 'parcial', 'pago', 'cancelado'];

    public const EM_ABERTO = ['aberto', 'parcial'];

    public const CATEGORIAS = ['combustivel', 'manutencao', 'frete_terceiro', 'acerto_viagem', 'pedagio', 'seguro', 'impostos', 'servicos', 'outras'];

    public function favorecido(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'favorecido_id');
    }

    public function filial(): BelongsTo
    {
        return $this->belongsTo(Filial::class);
    }

    public function origens(): HasMany
    {
        return $this->hasMany(ContaPagarOrigem::class);
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(PagamentoConta::class)->orderBy('data')->orderBy('id');
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

    public function vencida(?Carbon $hoje = null): bool
    {
        $hoje ??= Carbon::today();

        return $this->emAberto() && $this->vencimento !== null && $this->vencimento->lt($hoje);
    }

    public function diasAtraso(?Carbon $hoje = null): int
    {
        $hoje ??= Carbon::today();

        return $this->vencida($hoje) ? (int) $this->vencimento->diffInDays($hoje) : 0;
    }

    /** Frete de TAC: paga-se no 4050, pela instituição do CIOT. */
    public function pagaNoCiot(): bool
    {
        return $this->origem === 'ciot';
    }

    /** O CIOT de onde a conta nasceu (só origem ciot). */
    public function ciotId(): ?int
    {
        if (! $this->pagaNoCiot()) {
            return null;
        }
        $o = $this->origens->first(fn (ContaPagarOrigem $o) => $o->origem_tipo === 'ciot');

        return $o?->origem_id;
    }

    public function scopeEmAberto(Builder $q): Builder
    {
        return $q->whereIn('status', self::EM_ABERTO);
    }

    public function scopeVencidas(Builder $q): Builder
    {
        return $q->whereIn('status', self::EM_ABERTO)->whereDate('vencimento', '<', Carbon::today());
    }
}
