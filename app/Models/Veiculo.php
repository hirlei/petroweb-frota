<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Fiscal\Enums\CategoriaCombinacaoVeicular;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Uma UNIDADE — cavalo, reboque, semirreboque ou dolly. A combinação em si é
 * `Composicao`, não este model: o mesmo semirreboque roda hoje atrás de um
 * cavalo e amanhã de outro, e `veicReboque` do MDF-e é lista, não campo.
 *
 * `eixos` é NOT NULL porque é a base do `categCombVeic`. Sem ele o MDF-e não
 * fecha e a SEFAZ devolve rejeição por combinação veicular inválida.
 */
class Veiculo extends Model
{
    use PertenceAEmpresa;
    use SoftDeletes;

    protected $table = 'veiculos';

    protected $guarded = [];

    protected $casts = [
        'ano_fabricacao'  => 'integer',
        'ano_modelo'      => 'integer',
        'eixos'           => 'integer',
        'qtd_compartimentos' => 'integer',
        'compartimentos'  => 'array',
        'exige_aet'       => 'boolean',
        'tara_kg'         => 'decimal:4',
        'pbt_kg'          => 'decimal:4',
        'pbtc_kg'         => 'decimal:4',
        'capacidade_kg'   => 'decimal:4',
        'capacidade_m3'   => 'decimal:4',
        'odometro_atual'  => 'decimal:2',
        'horimetro_atual' => 'decimal:2',
    ];

    public const TIPO_TRACAO = 'tracao';
    public const TIPO_REBOQUE = 'reboque';
    public const TIPO_SEMIRREBOQUE = 'semirreboque';
    public const TIPO_DOLLY = 'dolly';

    public const PROPRIEDADE_PROPRIA = 'propria';
    public const PROPRIEDADE_TERCEIRO = 'terceiro';
    public const PROPRIEDADE_ARRENDADA = 'arrendada';

    public function filial(): BelongsTo
    {
        return $this->belongsTo(Filial::class);
    }

    public function proprietario(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'proprietario_id');
    }

    public function carroceria(): BelongsTo
    {
        return $this->belongsTo(Carroceria::class);
    }

    public function cvcConfiguracao(): BelongsTo
    {
        return $this->belongsTo(CvcConfiguracao::class, 'cvc_configuracao_id');
    }

    public function municipioLicenciamento(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'municipio_licenciamento_id');
    }

    public function composicoes(): BelongsToMany
    {
        return $this->belongsToMany(Composicao::class, 'composicao_itens')
            ->withPivot('ordem')
            ->orderByPivot('ordem');
    }

    public function documentos(): MorphMany
    {
        return $this->morphMany(VeiculoDocumento::class, 'documentavel');
    }

    public function ehTracao(): bool
    {
        return $this->tipo === self::TIPO_TRACAO;
    }

    public function ehProprio(): bool
    {
        return $this->propriedade === self::PROPRIEDADE_PROPRIA;
    }

    /**
     * Veículo de terceiro leva o RNTRC e o tipo de transportador DO
     * PROPRIETÁRIO ao MDF-e — nunca o da transportadora. Errar isso é rejeição
     * e, no caso do TAC, é o gatilho da obrigação de vale-pedágio (RN-10).
     */
    public function rntrcParaMdfe(): ?string
    {
        return $this->ehProprio()
            ? null
            : ($this->proprietario_rntrc ?? $this->proprietario?->rntrc);
    }

    /** Carga útil real: o que sobra do PBTC depois da tara. */
    public function cargaUtilKg(): ?float
    {
        if ($this->pbtc_kg === null) {
            return null;
        }

        return (float) $this->pbtc_kg - (float) $this->tara_kg;
    }

    /**
     * Só faz sentido para veículo isolado. Para combinação, quem manda é
     * `Composicao::categoriaCombinacaoVeicular()` — a soma dos eixos.
     */
    public function categoriaCombinacaoVeicular(): CategoriaCombinacaoVeicular
    {
        return CategoriaCombinacaoVeicular::paraEixos($this->eixos);
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('status', 'ativo');
    }

    public function scopeTracao(Builder $query): Builder
    {
        return $query->where('tipo', self::TIPO_TRACAO);
    }

    public function placaFormatada(): string
    {
        $p = (string) $this->placa;

        return strlen($p) === 7 ? substr($p, 0, 3) . '-' . substr($p, 3) : $p;
    }
}
