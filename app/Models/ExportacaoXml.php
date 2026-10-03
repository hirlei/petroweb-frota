<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ZIP mensal de XML gerado na rotina 4060 — guardado para baixar de novo. */
class ExportacaoXml extends Model
{
    use PertenceAEmpresa;

    protected $table = 'exportacoes_xml';

    protected $guarded = [];

    protected $casts = [
        'opcoes' => 'array',
        'arquivos' => 'integer',
        'sem_xml' => 'integer',
        'tamanho' => 'integer',
    ];

    public function filial(): BelongsTo
    {
        return $this->belongsTo(Filial::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    /** "Setembro/2026". */
    public function mesRotulo(): string
    {
        [$a, $m] = explode('-', $this->mes);
        $nomes = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

        return ($nomes[(int) $m - 1] ?? $m) . '/' . $a;
    }
}
