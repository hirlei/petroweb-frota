<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Certificado A1 da filial. Uma filial tem no máximo UM ativo — garantido por
 * índice parcial no banco, não por validação de formulário.
 *
 * A senha nunca trafega nem repousa em texto puro: `senha_encriptada` guarda
 * o valor cifrado com a chave da aplicação.
 */
class CertificadoDigital extends Model
{
    use PertenceAEmpresa;

    protected $table = 'certificados_digitais';

    protected $guarded = [];

    protected $hidden = ['senha_encriptada'];

    protected $casts = [
        'valido_de'  => 'datetime',
        'valido_ate' => 'datetime',
    ];

    public function filial(): BelongsTo
    {
        return $this->belongsTo(Filial::class);
    }

    public function vigente(?Carbon $em = null): bool
    {
        $em = $em ?? Carbon::now();

        return $this->status === 'ativo'
            && $this->valido_de->lte($em)
            && $this->valido_ate->gte($em);
    }

    public function diasParaVencer(?Carbon $em = null): int
    {
        return (int) ($em ?? Carbon::now())->diffInDays($this->valido_ate, false);
    }
}
