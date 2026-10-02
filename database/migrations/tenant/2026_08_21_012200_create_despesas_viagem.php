<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Despesas de viagem (rotina 3060).
 *
 * Os gastos de estrada de uma viagem — pedágio, alimentação, hospedagem, chapa,
 * balança, multa. Cada despesa APROVADA alimenta os custos denormalizados da
 * viagem no componente certo (o mapa vive em App\Models\Despesa), e a margem é
 * recalculada. Enquanto pendente, não entra no custo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('despesas_viagem', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('viagem_id')->constrained('viagens')->cascadeOnDelete();
            $table->foreignId('motorista_id')->nullable()->constrained('motoristas');
            // pedagio | alimentacao | hospedagem | estacionamento | lavagem | chapa | balanca | multa | outros
            $table->string('tipo', 20);
            $table->date('data');
            $table->string('descricao', 200)->nullable();
            $table->decimal('valor', 15, 2);
            // adiantamento | cartao | reembolso | empresa
            $table->string('forma_pagamento', 20)->default('adiantamento');
            $table->string('comprovante_path', 255)->nullable();
            $table->boolean('aprovada')->default(false);
            $table->foreignId('aprovada_por')->nullable()->constrained('users');
            $table->timestamp('aprovada_em')->nullable();
            // manual | app_motorista
            $table->string('origem', 20)->default('manual');
            $table->timestamps();

            $table->index(['empresa_id', 'viagem_id']);
            $table->index(['empresa_id', 'aprovada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('despesas_viagem');
    }
};
