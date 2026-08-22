<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abastecimentos (rotina 2060). Doc §5.6.
 *
 * A média só é confiável entre dois tanques cheios — por isso `tanque_cheio` é
 * obrigatório. `referencia_externa` é a chave da integração com o PetroWeb
 * (posto próprio) e com cartões de frota: evita lançamento duplicado.
 *
 * `viagem_id` fica nullable SEM foreign key até o módulo de viagens existir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abastecimentos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('filial_id')->nullable()->constrained('filiais');
            $table->foreignId('veiculo_id')->constrained('veiculos');
            $table->unsignedBigInteger('viagem_id')->nullable();
            $table->foreignId('motorista_id')->nullable()->constrained('motoristas');
            $table->foreignId('posto_id')->nullable()->constrained('pessoas');

            $table->dateTime('data_hora');
            $table->string('combustivel', 20);
            $table->decimal('litros', 12, 3);
            $table->decimal('valor_litro', 15, 2);
            $table->decimal('valor_total', 15, 2);
            $table->decimal('odometro', 12, 2);
            $table->boolean('tanque_cheio')->default(true);
            $table->decimal('km_percorrido', 12, 2)->nullable();
            $table->decimal('media_calculada', 8, 3)->nullable();
            $table->decimal('desvio_percentual', 7, 2)->nullable();
            $table->boolean('alerta')->default(false);
            // manual | app_motorista | importacao | petroweb | cartao_frota
            $table->string('origem', 20)->default('manual');
            $table->string('referencia_externa', 60)->nullable();
            $table->string('nota_fiscal', 60)->nullable();
            $table->string('arquivo_path')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'veiculo_id', 'data_hora']);
            $table->index(['empresa_id', 'alerta']);
            // A integração não pode lançar o mesmo abastecimento duas vezes.
            $table->unique(['empresa_id', 'origem', 'referencia_externa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abastecimentos');
    }
};
