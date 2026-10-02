<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entregas / POD (rotina 3050).
 *
 * A prova de entrega: quem recebeu, quando, e o comprovante (canhoto
 * digitalizado, foto ou evento eletrônico). A comprovação eletrônica é o evento
 * 110180 do CT-e (cancelamento pelo 110181) — a FK para `ctes` e o
 * `evento_cte_id` ficam reservados até o módulo fiscal (4010) existir. Por ora a
 * entrega se amarra à viagem e à ordem de coleta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entregas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('viagem_id')->nullable()->constrained('viagens')->nullOnDelete();
            $table->foreignId('ordem_coleta_id')->nullable()->constrained('ordens_coleta')->nullOnDelete();
            // Reservado para o módulo 4010; sem FK enquanto `ctes` não existir.
            $table->unsignedBigInteger('cte_id')->nullable();
            $table->timestamp('data_hora');
            $table->string('recebedor_nome', 150)->nullable();
            $table->string('recebedor_documento', 20)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('canhoto_path', 255)->nullable();
            $table->string('assinatura_path', 255)->nullable();
            // fisico_digitalizado | foto | evento_eletronico
            $table->string('tipo_comprovacao', 20)->default('foto');
            // Reservado: aponta para o evento 110180 do CT-e quando eletrônico.
            $table->unsignedBigInteger('evento_cte_id')->nullable();
            $table->text('observacoes')->nullable();
            $table->foreignId('registrada_por')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['empresa_id', 'viagem_id']);
            $table->index(['empresa_id', 'data_hora']);
            $table->index('cte_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entregas');
    }
};
