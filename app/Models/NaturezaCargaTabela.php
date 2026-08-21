<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Fiscal\Enums\NaturezaCarga as NaturezaCargaEnum;
use App\Domain\Fiscal\Enums\TipoCarga;
use App\Models\Scopes\EmpresaOuSistemaScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Natureza da carga como o CLIENTE a nomeia. O sufixo `Tabela` evita colisão
 * com o enum `App\Domain\Fiscal\Enums\NaturezaCarga`, que é o vocabulário
 * fechado por trás dela.
 *
 * `codigo` casa com o valor do enum quando é registro do sistema; o cliente
 * pode criar naturezas próprias ("cana picada", "bobina de aço") que continuam
 * apontando para um `tp_carga_base` válido.
 */
#[ScopedBy([EmpresaOuSistemaScope::class])]
class NaturezaCargaTabela extends Model
{
    protected $table = 'naturezas_carga';

    protected $guarded = [];

    protected $casts = [
        'permite_perigosa'  => 'boolean',
        'exige_temperatura' => 'boolean',
        'exige_aet'         => 'boolean',
        'exige_gta'         => 'boolean',
        'ativo'             => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function ehDoSistema(): bool
    {
        return $this->empresa_id === null;
    }

    /** O enum por trás do registro, quando o código pertence ao vocabulário. */
    public function enum(): ?NaturezaCargaEnum
    {
        return NaturezaCargaEnum::tryFrom((string) $this->codigo);
    }

    /**
     * `tpCarga` do MDF-e. Carga perigosa muda o código: granel sólido comum é
     * 01, perigoso é 07. Quem decide é a mercadoria, não a natureza — por isso
     * o parâmetro.
     */
    public function tipoCargaFiscal(bool $perigosa = false): TipoCarga
    {
        $enum = $this->enum();

        if ($enum !== null) {
            return TipoCarga::derivar($enum, $perigosa);
        }

        // Natureza criada pelo cliente: respeita a base declarada no cadastro.
        return TipoCarga::from((string) $this->tp_carga_base);
    }

    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }
}
