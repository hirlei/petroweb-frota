<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Numeração por filial, modelo e série. Buraco na numeração é problema fiscal
 * — resolve-se por inutilização, e por isso o próximo número sai daqui com
 * LOCK do banco, nunca de um `max(numero) + 1`.
 */
class SequenciaDocumento extends Model
{
    use PertenceAEmpresa;

    protected $table = 'sequencias_documento';

    protected $guarded = [];

    protected $casts = [
        'serie'          => 'integer',
        'proximo_numero' => 'integer',
    ];

    public const MODELO_CTE = '57';
    public const MODELO_MDFE = '58';

    public function filial(): BelongsTo
    {
        return $this->belongsTo(Filial::class);
    }

    /**
     * Reserva o próximo número atomicamente. Deve rodar DENTRO da transação
     * que grava o documento: se o XML falhar depois do commit, o número
     * queimado vira inutilização.
     */
    public static function proximo(int $filialId, string $modelo, int $serie): int
    {
        return DB::transaction(function () use ($filialId, $modelo, $serie): int {
            $sequencia = self::withoutGlobalScopes()
                ->where('filial_id', $filialId)
                ->where('modelo', $modelo)
                ->where('serie', $serie)
                ->lockForUpdate()
                ->firstOrFail();

            $numero = (int) $sequencia->proximo_numero;

            $sequencia->update(['proximo_numero' => $numero + 1]);

            return $numero;
        });
    }
}
