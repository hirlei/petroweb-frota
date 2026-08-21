<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Tabela do IBGE — global no tenant, sem `empresa_id`. O CT-e exige
 * `cMunIni`/`cMunFim` e o MDF-e o município de carregamento e de descarga;
 * todos referenciam esta tabela, nunca texto digitado.
 */
class Municipio extends Model
{
    protected $table = 'municipios';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'latitude'  => 'float',
        'longitude' => 'float',
    ];

    public function scopeDaUf(Builder $query, string $uf): Builder
    {
        return $query->where('uf', strtoupper($uf));
    }

    public function nomeComUf(): string
    {
        return $this->nome . '/' . $this->uf;
    }
}
