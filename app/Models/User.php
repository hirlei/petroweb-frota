<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory;
    use HasRoles;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'empresa_id',
        'filial_id',
        'ativo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'password'           => 'hashed',
            'ativo'              => 'boolean',
            'preferencias'       => 'array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function filial(): BelongsTo
    {
        return $this->belongsTo(Filial::class);
    }

    /**
     * Perfis com poder fiscal ou financeiro exigem 2FA. A verificação é da
     * permissão, não do papel — assim um papel novo que ganhe "cte.emitir"
     * passa a exigir 2FA sem ninguém lembrar de atualizar uma lista.
     */
    public function exige2fa(): bool
    {
        return $this->hasAnyPermission([
            'cte.emitir',
            'cte.cancelar',
            'cte.corrigir',
            'mdfe.emitir',
            'mdfe.encerrar',
            'certificado.gerenciar',
            'fatura.emitir',
            'titulo.baixar',
            'acerto.aprovar',
        ]);
    }

    public function tem2faAtivo(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }
}
