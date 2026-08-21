<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\EmpresaOuSistemaScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RN-11 — a tabela de negócio, NÃO o enum fiscal.
 *
 * O fisco tem seis carrocerias (`tpCar` 00 a 05); a transportadora usa vinte e
 * poucas. Sider, graneleira canavieira e prancha são a MESMA `tpCar` para a
 * SEFAZ e coisas completamente diferentes na operação. `tp_car_fiscal` é a
 * tradução — e nenhuma tela de operação a exibe.
 *
 * `empresa_id` nulo = catálogo do sistema, visível a todas as empresas.
 */
#[ScopedBy([EmpresaOuSistemaScope::class])]
class Carroceria extends Model
{
    protected $table = 'carrocerias';

    protected $guarded = [];

    protected $casts = [
        'exige_temperatura_controlada' => 'boolean',
        'exige_civ_cipp'   => 'boolean',
        'exige_certificacao_inmetro' => 'boolean',
        'aceita_produto_perigoso' => 'boolean',
        'permite_conteiner' => 'boolean',
        'capacidade_m3_referencia' => 'decimal:2',
        'ativo' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function ehDoSistema(): bool
    {
        return $this->empresa_id === null;
    }

    /**
     * CIV (Certificado de Inspeção Veicular) e CIPP (de Inspeção para Produtos
     * Perigosos) valem para tanque e a granel — Res. ANTT 5.998/2022. Sem eles
     * o veículo não carrega perigoso, por mais em dia que esteja o resto.
     */
    public function exigeInspecaoPerigosos(): bool
    {
        return $this->exige_civ_cipp;
    }

    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }
}
