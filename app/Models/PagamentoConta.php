<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pagamento (baixa) de uma conta a pagar. Estorno marca, não apaga. Os de forma
 * `instituicao_ciot` são espelho dos pagamentos do CIOT e não se estornam aqui.
 */
class PagamentoConta extends Model
{
    use PertenceAEmpresa;

    protected $table = 'pagamentos_conta';

    protected $guarded = [];

    protected $casts = [
        'data' => 'date',
        'valor_principal' => 'decimal:2',
        'juros_multa' => 'decimal:2',
        'desconto' => 'decimal:2',
        'valor_total' => 'decimal:2',
        'estornado_em' => 'datetime',
    ];

    /** Formas que o usuário escolhe (instituicao_ciot é só do espelho do CIOT). */
    public const FORMAS = ['pix', 'boleto', 'transferencia', 'dinheiro', 'cartao'];

    /** Não se chama `conta()`: `conta` é a coluna da conta bancária. */
    public function contaPagar(): BelongsTo
    {
        return $this->belongsTo(ContaPagar::class, 'conta_pagar_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function estornado(): bool
    {
        return $this->estornado_em !== null;
    }

    public function doCiot(): bool
    {
        return $this->forma === 'instituicao_ciot';
    }

    public function scopeValidos(Builder $q): Builder
    {
        return $q->whereNull('estornado_em');
    }
}
