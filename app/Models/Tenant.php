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
