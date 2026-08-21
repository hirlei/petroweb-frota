<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Vencimentos — polimórfico de propósito: CIV e CIPP são do veículo, MOPP e
 * toxicológico do motorista, apólice do contrato. Um painel só, uma consulta
 * só, um alerta só.
 *
 * `bloqueia_operacao` separa o que impede a viagem do que apenas avisa.
 */
class VeiculoDocumento extends Model
{
    use PertenceAEmpresa;

    protected $table = 'veiculo_documentos';

    protected $guarded = [];

    protected $casts = [
        'emissao'    => 'date',
        'vencimento' => 'date',
        'valor'      => 'decimal:2',
        'bloqueia_operacao' => 'boolean',
    ];

    public function documentavel(): MorphTo
    {
        return $this->morphTo();
    }

    public function vencido(?Carbon $em = null): bool
    {
        return $this->vencimento->lt($em ?? Carbon::today());
    }

    public function diasParaVencer(?Carbon $em = null): int
    {
        return (int) ($em ?? Carbon::today())->diffInDays($this->vencimento, false);
    }

    public function scopeVencendoAte(Builder $query, int $dias): Builder
    {
        return $query->whereDate('vencimento', '<=', Carbon::today()->addDays($dias));
    }

    public function scopeBloqueantes(Builder $query): Builder
    {
        return $query->where('bloqueia_operacao', true);
    }
}
