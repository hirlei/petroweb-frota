<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fornecedor de vale-pedágio habilitado pela ANTT (§7.10).
 *
 * Tabela GLOBAL do tenant — SEM `empresa_id` e SEM PertenceAEmpresa. É catálogo
 * compartilhado, espelho da lista do SVRS, para validar CNPJForn antes de
 * transmitir (evita a rejeição 733).
 */
class FornecedorVpo extends Model
{
    protected $table = 'fornecedores_vpo';

    protected $guarded = [];

    protected $casts = [
        'ativo' => 'boolean',
        'sincronizado_em' => 'datetime',
    ];
}
