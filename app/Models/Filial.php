<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Estabelecimento com CNPJ, IE, certificado e séries próprias.
 *
 * CADA FILIAL É UM EMITENTE FISCAL INDEPENDENTE. O ambiente da SEFAZ é
 * atributo dela, não da aplicação: uma filial pode estar em homologação
 * enquanto outra já emite com valor fiscal.
 */
class Filial extends Model
{
    use PertenceAEmpresa;
    use SoftDeletes;

    protected $table = 'filiais';

    protected $guarded = [];

    protected $casts = [
        'matriz'         => 'boolean',
        'ambiente_sefaz' => 'integer',
        'contingencia_automatica' => 'boolean',
        'ativa'          => 'boolean',
    ];

    public const AMBIENTE_PRODUCAO = 1;
    public const AMBIENTE_HOMOLOGACAO = 2;

    /** Código de Regime Tributário — determina os grupos de IBS/CBS no CT-e. */
    public const CRT_SIMPLES = '1';
    public const CRT_SIMPLES_EXCESSO = '2';
    public const CRT_REGIME_NORMAL = '3';

    public function certificado(): BelongsTo
    {
        return $this->belongsTo(CertificadoDigital::class, 'certificado_id');
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function sequencias(): HasMany
    {
        return $this->hasMany(SequenciaDocumento::class);
    }

    public function emHomologacao(): bool
    {
        return $this->ambiente_sefaz === self::AMBIENTE_HOMOLOGACAO;
    }

    /**
     * Regime normal exige os grupos de IBS e CBS no CT-e desde a produção da
     * Etapa 2 da NT 2026.002 (31/08/2026). Ver documento 03, seção 4.1.
     */
    public function exigeIbsCbs(): bool
    {
        return $this->crt === self::CRT_REGIME_NORMAL;
    }

    public function podeEmitir(): bool
    {
        return $this->ativa
            && $this->certificado !== null
            && $this->certificado->vigente();
    }
}
