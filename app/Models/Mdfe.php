<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MDF-e (rotina 4020) — modelo 58.
 *
 * Manifesto da viagem; amarra os CT-e ao veículo. Um por viagem/veículo; aberto
 * bloqueia novo (RN-03, também garantido por índice parcial). categCombVeic é
 * derivado dos eixos da composição, nunca digitado.
 */
class Mdfe extends Model
{
    use PertenceAEmpresa;

    protected $table = 'mdfes';

    protected $guarded = [];

    protected $casts = [
        'emissao' => 'datetime',
        'data_autorizacao' => 'datetime',
        'encerrado_em' => 'datetime',
        'percurso_ufs' => 'array',
        'municipio_carregamento' => 'array',
        'reboques' => 'array',
        'condutores' => 'array',
        'contratante' => 'array',
        'lacres' => 'array',
        'seguro' => 'array',
        'payload' => 'array',
        'produto_perigoso' => 'boolean',
        'peso_bruto_total' => 'decimal:2',
        'valor_carga_total' => 'decimal:2',
        'tipo_emitente' => 'integer',
        'categoria_comb_veicular' => 'integer',
        'ambiente' => 'integer',
    ];

    public const STATUSES = ['rascunho', 'autorizado', 'rejeitado', 'cancelado', 'encerrado', 'contingencia'];

    /** Status que ocupam o veículo — usados na RN-03. */
    public const ABERTOS = ['rascunho', 'autorizado', 'contingencia'];

    public function filial(): BelongsTo
    {
        return $this->belongsTo(Filial::class);
    }

    public function viagem(): BelongsTo
    {
        return $this->belongsTo(Viagem::class);
    }

    public function veiculoTracao(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'veiculo_tracao_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(MdfeDocumento::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(MdfeEvento::class)->orderBy('data_evento');
    }

    public function valesPedagio(): HasMany
    {
        return $this->hasMany(ValePedagio::class);
    }

    public function autorizado(): bool
    {
        return $this->status === 'autorizado';
    }

    public function encerravel(): bool
    {
        return $this->status === 'autorizado' && $this->encerrado_em === null;
    }

    public function scopeAbertos(Builder $q): Builder
    {
        return $q->whereIn('status', self::ABERTOS);
    }
}
