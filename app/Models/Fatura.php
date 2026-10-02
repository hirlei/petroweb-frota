<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Financeiro\Parcelamento;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fatura (rotina 5010) — documento comercial que agrupa CT-e autorizados de um
 * tomador. Não é documento fiscal: o fiscal é o CT-e. Cada parcela vira um
 * título em Contas a receber (5020).
 *
 * "Vencida" não é status gravado: é aberta/parcial com parcela vencida.
 * Alterações de valor e status passam pelo App\Services\Financeiro\Faturamento.
 */
class Fatura extends Model
{
    use PertenceAEmpresa;

    protected $table = 'faturas';

    protected $guarded = [];

    protected $casts = [
        'emissao'        => 'date',
        'valor_ctes'     => 'decimal:2',
        'desconto'       => 'decimal:2',
        'acrescimo'      => 'decimal:2',
        'valor_total'    => 'decimal:2',
        'valor_recebido' => 'decimal:2',
        'cancelada_em'   => 'datetime',
    ];

    public const STATUSES = ['aberta', 'parcial', 'paga', 'cancelada'];

    public function tomador(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'tomador_id');
    }

    public function filial(): BelongsTo
    {
        return $this->belongsTo(Filial::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(FaturaCte::class);
    }

    /** CT-e da fatura (inclusive os de uma fatura cancelada, para o histórico). */
    public function ctes(): BelongsToMany
    {
        return $this->belongsToMany(Cte::class, 'fatura_ctes')->withPivot(['valor', 'ativo'])->withTimestamps();
    }

    public function titulos(): HasMany
    {
        return $this->hasMany(TituloReceber::class)->orderBy('parcela');
    }

    public function criadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criada_por');
    }

    public function canceladaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelada_por');
    }

    public function cancelada(): bool
    {
        return $this->status === 'cancelada';
    }

    public function saldo(): float
    {
        return $this->cancelada() ? 0.0 : max(0.0, round((float) $this->valor_total - (float) $this->valor_recebido, 2));
    }

    /** Aberta ou parcial com alguma parcela vencida e não quitada. */
    public function vencida(): bool
    {
        if (! in_array($this->status, ['aberta', 'parcial'], true)) {
            return false;
        }

        return $this->titulos->contains(fn (TituloReceber $t) => $t->vencido());
    }

    /** Situação para a tela: inclui o "vencida" derivado. */
    public function situacao(): string
    {
        return $this->vencida() ? 'vencida' : (string) $this->status;
    }

    public function rotuloCondicao(): string
    {
        try {
            return Parcelamento::rotulo(Parcelamento::prazos((string) $this->condicao));
        } catch (\InvalidArgumentException) {
            return (string) $this->condicao;
        }
    }

    /** Pode cancelar enquanto nada foi recebido (com recebimento, estorne antes). */
    public function cancelavel(): bool
    {
        return ! $this->cancelada() && (float) $this->valor_recebido <= 0.0;
    }

    public function scopeAtivas(Builder $q): Builder
    {
        return $q->where('status', '!=', 'cancelada');
    }

    public function scopeVencidas(Builder $q): Builder
    {
        return $q->whereIn('status', ['aberta', 'parcial'])
            ->whereHas('titulos', fn (Builder $t) => $t->vencidos());
    }
}
