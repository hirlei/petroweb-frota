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
    ];

    public const PAPEIS = ['recebido', 'fornecido'];

    public const TIPOS_VALIDOS = ['01', '04']; // 02 e 03 descontinuados

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
}
