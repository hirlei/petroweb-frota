<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preferências do usuário (02/10/2026, menu H6 igual ao ERP): por enquanto só
 * os Favoritos do menu — {"favoritos": ["3020", "4010"]}. Fica no usuário, não
 * no navegador, para acompanhar a pessoa em qualquer computador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->jsonb('preferencias')->nullable()->after('ativo');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('preferencias');
        });
    }
};
