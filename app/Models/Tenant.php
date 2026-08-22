<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Central\Licenca;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * Um TENANT = um CLIENTE do provedor. Mesmo desenho do PetroWeb:
 *
 *  - id = SLUG legível (ex.: "serraazul"), que vira o nome do banco
 *    (frota_serraazul, pelo prefix em config/tenancy.php) e o subdomínio
 *    (serraazul.frota.petroweb.app, linha na tabela domains);
 *  - banco próprio, criado e migrado pelos jobs do TenancyServiceProvider
 *    quando o tenant é criado.
 *
 * Vive no banco CENTRAL (conexão 'central'), nunca no banco de um cliente.
 *
 * Hierarquia completa:
 *   Tenant (cliente/grupo)  →  Empresa (transportadora)  →  Filial (emitente fiscal)
 *
 * A separação importa: o Tenant isola por BANCO, a Empresa isola por COLUNA
 * dentro daquele banco. Um grupo com três transportadoras é um tenant com
 * três empresas — não três tenants.
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;

    /*
     * O id é o SLUG (string), não um inteiro autoincrementável. Sem estas
     * duas linhas o Eloquent, no create(), lê o id de volta como `(int) $slug`
     * = 0 — a linha no banco fica certa, mas o objeto em memória vai com id 0,
     * e a pipeline do stancl monta o banco `frota_0`. Foi exatamente o bug que
     * derrubou a criação do primeiro tenant.
     */
    public $incrementing = false;

    protected $keyType = 'string';

    /** Colunas físicas da tabela tenants; todo o resto cai no JSON `data`. */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'nome',
            'ativo',
        ];
    }

    protected $casts = [
        'ativo' => 'boolean',
        'data'  => 'array',
    ];

    public function licenca(): HasOne
    {
        return $this->hasOne(Licenca::class);
    }

    public function dominioPrincipal(): ?string
    {
        return $this->domains->first()?->domain;
    }

    public function nomeBanco(): string
    {
        return config('tenancy.database.prefix', 'frota_') . $this->getTenantKey()
            . config('tenancy.database.suffix', '');
    }
}
