<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\EmpresaViaPaiScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O município NÃO é texto — é FK para a tabela do IBGE. O CT-e exige
 * `cMunIni`/`cMunFim` e o MDF-e o município de carregamento e descarga; se o
 * endereço guardasse o nome digitado, cada emissão viraria um "de/para".
 */
class Endereco extends Model
{
    protected $table = 'enderecos';

    protected $guarded = [];

    protected $casts = [
        'latitude'  => 'float',
        'longitude' => 'float',
        'principal' => 'boolean',
    ];

    /** Escopo herdado de `Pessoa` — esta tabela não carrega `empresa_id`. */
    protected static function booted(): void
    {
        static::addGlobalScope(new EmpresaViaPaiScope('pessoa'));
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function temCoordenadas(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function linhaUnica(): string
    {
        $partes = array_filter([
            $this->logradouro,
            $this->numero,
            $this->complemento,
            $this->bairro,
            $this->municipio?->nome,
            $this->municipio?->uf,
        ]);

        return implode(', ', $partes);
    }
}
