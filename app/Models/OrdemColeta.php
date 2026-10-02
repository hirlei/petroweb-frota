<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ordem de coleta (rotina 3010) — documento de entrada da operação.
 *
 * O cliente pede o transporte; a ordem guarda participantes, trecho, carga e o
 * frete calculado a partir da tabela vigente. Ao ser faturada vira CT-e — por
 * isso os participantes já seguem o vocabulário fiscal (tomador, remetente,
 * destinatário, expedidor, recebedor), mas NENHUM código fiscal aparece aqui.
 */
class OrdemColeta extends Model
{
    use PertenceAEmpresa;

    protected $table = 'ordens_coleta';

    protected $guarded = [];

    protected $casts = [
        'data'                  => 'date',
        'previsao_coleta'       => 'datetime',
        'previsao_entrega'      => 'datetime',
        'peso_bruto'            => 'decimal:4',
        'peso_cubado'           => 'decimal:4',
        'volumes'               => 'integer',
        'valor_mercadoria'      => 'decimal:2',
        'valor_frete_calculado' => 'decimal:2',
    ];

    /** Papel do contratante do frete — vira `toma3`/`toma4` na emissão. */
    public const TOMADOR_TIPOS = ['remetente', 'destinatario', 'expedidor', 'recebedor', 'outros'];

    public const STATUSES = ['aberta', 'coletada', 'faturada', 'cancelada'];

    public function filial(): BelongsTo
    {
        return $this->belongsTo(Filial::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'cliente_id');
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

    public function enderecoColeta(): BelongsTo
    {
        return $this->belongsTo(Endereco::class, 'endereco_coleta_id');
    }

    public function enderecoEntrega(): BelongsTo
    {
        return $this->belongsTo(Endereco::class, 'endereco_entrega_id');
    }

    public function municipioInicio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'municipio_inicio_id');
    }

    public function municipioFim(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'municipio_fim_id');
    }

    public function tabelaFrete(): BelongsTo
    {
        return $this->belongsTo(TabelaFrete::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(OcItem::class);
    }

    /** Documento autorizado é imutável; aqui, ordem faturada ou cancelada trava a edição. */
    public function podeEditar(): bool
    {
        return in_array($this->status, ['aberta', 'coletada'], true);
    }

    public function scopeAbertas(Builder $query): Builder
    {
        return $query->where('status', 'aberta');
    }
}
