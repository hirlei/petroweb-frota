<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Fiscal\RegrasCiot;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CIOT da viagem (rotina 4050). Registrado na emissão do MDF-e (4020) e preso
 * à VIAGEM, não ao MDF-e: rejeição ou reemissão do manifesto reaproveita o
 * mesmo CIOT — nada é registrado nem pago duas vezes.
 *
 * Toda mudança de status e de valor passa por App\Services\Fiscal\Ciot\ServicoCiot.
 */
class Ciot extends Model
{
    use PertenceAEmpresa;

    protected $table = 'ciots';

    protected $guarded = [];

    protected $casts = [
        'valor_frete' => 'decimal:2',
        'percentual_adiantamento' => 'decimal:2',
        'valor_adiantamento' => 'decimal:2',
        'valor_saldo' => 'decimal:2',
        'valor_pago' => 'decimal:2',
        'prazo_quitacao' => 'date',
        'retorno' => 'array',
        'registrado_em' => 'datetime',
        'quitado_em' => 'datetime',
        'cancelado_em' => 'datetime',
    ];

    public const STATUSES = ['recusado', 'registrado', 'quitado', 'cancelado'];

    public const FORMAS = ['pix', 'transferencia', 'cartao_frete'];

    public function viagem(): BelongsTo
    {
        return $this->belongsTo(Viagem::class);
    }

    public function mdfe(): BelongsTo
    {
        return $this->belongsTo(Mdfe::class);
    }

    public function contratado(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'contratado_id');
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(CiotPagamento::class)->orderBy('id');
    }

    public function numeroFormatado(): string
    {
        return RegrasCiot::formatar($this->numero);
    }

    public function valido(): bool
    {
        return $this->status === 'registrado' || $this->status === 'quitado';
    }

    public function temPagamento(): bool
    {
        return RegrasCiot::temPagamento((string) $this->modalidade);
    }

    public function saldoAPagar(): float
    {
        return $this->temPagamento()
            ? max(0.0, round((float) $this->valor_frete - (float) $this->valor_pago, 2))
            : 0.0;
    }

    /**
     * Adiantamento ainda não saiu E nada foi pago ao TAC. Se o saldo (ou qualquer
     * valor) já foi pago, não há mais adiantamento a mandar — evita pagar duas vezes.
     */
    public function adiantamentoPendente(): bool
    {
        return $this->temPagamento()
            && $this->status === 'registrado'
            && (float) $this->valor_adiantamento > 0
            && (float) $this->valor_pago <= 0
            && ! $this->pagamentos->contains(fn (CiotPagamento $p) => $p->tipo === 'adiantamento' && $p->status === 'confirmado');
    }

    public function saldoVencido(): bool
    {
        return $this->status === 'registrado' && $this->saldoAPagar() > 0
            && $this->prazo_quitacao !== null && $this->prazo_quitacao->lt(today());
    }

    /**
     * Situação mostrada na tela — mais fina que o status do banco.
     *
     * @return 'recusado'|'registrado'|'adiantado'|'a_quitar_vencido'|'quitado'|'cancelado'
     */
    public function situacao(): string
    {
        if ($this->status !== 'registrado') {
            return (string) $this->status;
        }
        if ($this->saldoVencido()) {
            return 'a_quitar_vencido';
        }

        return $this->temPagamento() && (float) $this->valor_pago > 0 ? 'adiantado' : 'registrado';
    }

    /** Cancelar só com o MDF-e fora do ar e sem pagamento confirmado ao TAC. */
    public function cancelavel(): bool
    {
        if (! in_array($this->status, ['registrado', 'recusado'], true)) {
            return false;
        }
        if ($this->pagamentos()->where('status', 'confirmado')->exists()) {
            return false;
        }

        return ! Mdfe::query()->where('viagem_id', $this->viagem_id)
            ->whereIn('status', ['autorizado', 'encerrado', 'contingencia'])->exists();
    }

    public function scopeAtivos(Builder $q): Builder
    {
        return $q->where('status', '<>', 'cancelado');
    }

    public function scopeSaldoVencido(Builder $q): Builder
    {
        return $q->where('status', 'registrado')->where('modalidade', RegrasCiot::IPEF)
            ->whereColumn('valor_pago', '<', 'valor_frete')
            ->whereDate('prazo_quitacao', '<', today());
    }
}
