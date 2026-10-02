<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fornecedores de vale-pedágio habilitados pela ANTT (§7.10).
 *
 * Tabela GLOBAL do tenant — SEM `empresa_id`. É o espelho local da "Relação de
 * CNPJ de Fornecedores de Vale Pedágio" do SVRS, usado para validar `CNPJForn`
 * antes de transmitir e evitar a rejeição 733. Alimentada por rotina agendada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fornecedores_vpo', function (Blueprint $table): void {
            $table->id();
            $table->char('cnpj', 14)->unique();
            $table->string('razao_social', 150)->nullable();
            $table->string('ato_habilitacao', 60)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamp('sincronizado_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fornecedores_vpo');
    }
};
