<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * CT-e (rotina 4010) — modelo 57.
 *
 * Documento do frete, nasce da ordem de coleta. Autorizado é imutável — muda por
 * evento. O `payload` guarda a intenção; o XML autorizado é a verdade fiscal.
 */
class Cte extends Model
{
    use PertenceAEmpresa;

    protected $table = 'ctes';

    protected $guarded = [];

    protected $casts = [
        'emissao' => 'datetime',
        'data_autorizacao' => 'datetime',
        'peso_bruto' => 'decimal:4',
        'peso_base_calculo' => 'decimal:4',
        'volumes' => 'decimal:4',
        'valor_mercadoria' => 'decimal:2',
        'valor_total_servico' => 'decimal:2',
        'valor_receber' => 'decimal:2',
        'icms_base' => 'decimal:2',
        'icms_aliquota' => 'decimal:4',
        'icms_valor' => 'decimal:2',
        'cbs_valor' => 'decimal:2',
        'ibs_uf_valor' => 'decimal:2',
        'ibs_mun_valor' => 'decimal:2',
        'ibs_cbs_payload' => 'array',
        'payload' => 'array',
        'tipo_cte' => 'integer',
        'tipo_servico' => 'integer',
        'tomador_tipo' => 'integer',
        'tipo_emissao' => 'integer',
        'ambiente' => 'integer',
    ];

    public const STATUSES = ['rascunho', 'assinado', 'enviado', 'autorizado', 'rejeitado', 'denegado', 'cancelado', 'contingencia'];

    /** tomador_tipo → papel (RN-04). */
    public const TOMADORES = [0 => 'remetente', 1 => 'expedidor', 2 => 'recebedor', 3 => 'destinatario', 4 => 'outros'];

    public function filial(): BelongsTo
    {
        return $this->belongsTo(Filial::class);
    }

    public function tomador(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'tomador_id');
    }

    public function remetente(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'remetente_id');
    }

    public function destinatario(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'destinatario_id');
    }

    public function expedidor(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'expedidor_id');
    }

    public function recebedor(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'recebedor_id');
    }

    public function municipioInicio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'municipio_inicio_id');
    }

    public function municipioFim(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'municipio_fim_id');
    }

    public function ordemColeta(): BelongsTo
    {
        return $this->belongsTo(OrdemColeta::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(CteDocumento::class);
    }

    public function componentes(): HasMany
    {
        return $this->hasMany(CteComponente::class)->orderBy('ordem');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(CteEvento::class)->orderBy('data_evento');
    }

    /** Viagens que transportam este CT-e (N:N, RN-02). */
    public function viagens(): BelongsToMany
    {
        return $this->belongsToMany(Viagem::class, 'viagem_ctes')
            ->withPivot(['sequencia', 'papel', 'entregue_em'])
            ->withTimestamps();
    }

    /** Linhas de fatura deste CT-e (rotina 5010). */
    public function faturaItens(): HasMany
    {
        return $this->hasMany(FaturaCte::class);
    }

    /** A fatura ativa em que o CT-e está, se houver. */
    public function faturaAtiva(): ?Fatura
    {
        return $this->faturaItens()->where('ativo', true)->with('fatura')->first()?->fatura;
    }

    /**
     * CT-e que podem entrar numa fatura: autorizados, que cobram frete (normal,
     * complementar ou substituto — anulação não) e que não estão em fatura ativa.
     */
    public function scopeFaturaveis(Builder $q): Builder
    {
        return $q->where('status', 'autorizado')
            ->whereIn('tipo_cte', [0, 1, 3])
            ->where('valor_total_servico', '>', 0)
            ->whereDoesntHave('faturaItens', fn (Builder $f) => $f->where('ativo', true));
    }

    public function autorizado(): bool
    {
        return $this->status === 'autorizado';
    }

    /** Limite do cancelamento (null = sem prazo configurado ou sem autorização). */
    public function cancelavelAte(): ?Carbon
    {
        $horas = config('fiscal.cte.cancelamento_horas');
        if ($horas === null || $horas === '' || $this->data_autorizacao === null) {
            return null;
        }

        return $this->data_autorizacao->copy()->addHours((int) $horas);
    }

    public function noPrazoDeCancelamento(): bool
    {
        $ate = $this->cancelavelAte();

        return $ate === null || now()->lessThanOrEqualTo($ate);
    }

    /** CC-e registradas (sem ORDER BY: count/max no Postgres não aceitam). */
    public function cartasCorrecao(): HasMany
    {
        return $this->hasMany(CteEvento::class)->where('tipo_evento', CteEvento::CCE)
            ->where('status', 'registrado');
    }

    public function editavel(): bool
    {
        return in_array($this->status, ['rascunho', 'rejeitado'], true);
    }

    public function tomadorPapel(): string
    {
        return self::TOMADORES[$this->tomador_tipo] ?? 'outros';
    }

    public function scopeAutorizados(Builder $q): Builder
    {
        return $q->where('status', 'autorizado');
    }
}
