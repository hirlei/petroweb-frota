<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banco CENTRAL do provedor. Um tenant = um cliente, com banco próprio.
 * Estrutura do stancl/tenancy: colunas físicas mínimas + JSON `data`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            // id é o SLUG (ex.: "serraazul") — vira nome do banco e subdomínio.
            $table->string('id')->primary();
            $table->string('nome');
            $table->boolean('ativo')->default(true);
            $table->jsonb('data')->nullable();
            $table->timestamps();

            $table->index('ativo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
