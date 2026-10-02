<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vale-pedágio (grupo valePed do MDF-e) — UM registro por veículo da composição.
 *
 * `papel` distingue quem recebeu (embarcador pagou) de quem forneceu (a
 * transportadora virou embarcadora equiparada ao subcontratar TAC — devedora,
 * sujeita à multa de R$ 3.000/veículo). `tipo` só aceita 01 (TAG) e 04 (leitura
 * de placa); 02 e 03 estão descontinuados.
 */
class ValePedagio extends Model
{
    use PertenceAEmpresa;

    protected $table = 'vale_pedagios';

    protected $guarded = [];

    protected $casts = [
        'valor' => 'decimal:2',
        'data_aquisicao' => 'datetime',
        'dispensado' => 'boolean',
        'retorno' => 'array',
        'cancelado_em' => 'datetime',
    ];

    public const PAPEIS = ['recebido', 'fornecido'];

    public const TIPOS_VALIDOS = ['01', '04']; // 02 e 03 descontinuados

    /** Motivos de dispensa aceitos na emissão do MDF-e. */
    public const MOTIVOS_DISPENSA = [
        'sem_pracas' => 'Rota sem praça de pedágio',
        'isento' => 'Veículo ou operação isenta',
    ];

    public function viagem(): BelongsTo
    {
        return $this->belongsTo(Viagem::class);
    }

    public function mdfe(): BelongsTo
    {
        return $this->belongsTo(Mdfe::class);
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function fornecedorVpo(): BelongsTo
    {
        return $this->belongsTo(FornecedorVpo::class);
    }

    public function ativo(): bool
    {
        return $this->situacao === 'ativo';
    }

    /** Ativo e ainda não usado num MDF-e autorizado (inclui dispensa feita por engano). */
    public function cancelavel(): bool
    {
        return $this->ativo() && ! $this->usadoEmMdfeAutorizado();
    }

    /** Lançamento manual que ainda pode ser corrigido no 4030. */
    public function editavel(): bool
    {
        return $this->ativo() && $this->origem !== 'compra' && ! $this->dispensado && ! $this->usadoEmMdfeAutorizado();
    }

    public function usadoEmMdfeAutorizado(): bool
    {
        return $this->mdfe !== null && in_array($this->mdfe->status, ['autorizado', 'encerrado', 'contingencia'], true);
    }

    public function scopeAtivos(\Illuminate\Database\Eloquent\Builder $q): \Illuminate\Database\Eloquent\Builder
    {
        return $q->where('situacao', 'ativo');
    }
}
