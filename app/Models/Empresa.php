<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A transportadora. Vive no banco do tenant.
 *
 * Não usa PertenceAEmpresa — ela É a empresa. O isolamento dela é o banco
 * do tenant; o isolamento de tudo o mais é o `empresa_id` que aponta pra cá.
 */
class Empresa extends Model
{
    use SoftDeletes;

    protected $table = 'empresas';

    protected $guarded = [];

    protected $casts = [
        'parametros'   => 'array',
        'ativa'        => 'boolean',
        'vigencia_ate' => 'date',
    ];

    public function filiais(): HasMany
    {
        return $this->hasMany(Filial::class);
    }

    public function matriz(): ?Filial
    {
        return $this->filiais()->where('matriz', true)->first();
    }

    /**
     * Fator de cubagem padrão da empresa — último degrau da cascata
     * (tabela de frete → cliente → produto → empresa). Ver documento 05,
     * seção 10.3: não existe norma que fixe o fator, é livre pactuação.
     */
    public function fatorCubagem(): float
    {
        return (float) ($this->parametros['fator_cubagem_kg_m3'] ?? 300.0);
    }

    public function parametro(string $chave, mixed $padrao = null): mixed
    {
        return data_get($this->parametros, $chave, $padrao);
    }
}
