<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function autorizado(): bool
    {
        return $this->status === 'autorizado';
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
