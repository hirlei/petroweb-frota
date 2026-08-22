<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rotas (rotina 3030) e seus pontos.
 *
 * Uma rota é o trecho planejado origem→destino, com distância, tempo e pedágio
 * estimados, mais os pontos intermediários na ordem em que aparecem (passagem,
 * pedágio, parada). É referência de planejamento — a viagem real aponta para
 * uma rota, mas registra seus próprios números.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rotas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('descricao', 150);
            $table->foreignId('municipio_origem_id')->constrained('municipios');
            $table->foreignId('municipio_destino_id')->constrained('municipios');
            $table->decimal('distancia_km', 10, 2)->nullable();
            $table->unsignedInteger('tempo_estimado_min')->nullable();
            $table->decimal('valor_pedagio_estimado', 12, 2)->nullable();
            // Altura, peso, horário de circulação — restrições do trecho.
            $table->jsonb('restricoes')->nullable();
            $table->boolean('ativa')->default(true);
            $table->timestamps();

            $table->index(['empresa_id', 'ativa']);
        });

        Schema::create('rota_pontos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rota_id')->constrained('rotas')->cascadeOnDelete();
            $table->unsignedSmallInteger('ordem');
            // origem | passagem | pedagio | parada | destino
            $table->string('tipo', 20);
            $table->foreignId('municipio_id')->nullable()->constrained('municipios');
            $table->string('descricao', 150)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('distancia_acumulada_km', 10, 2)->nullable();
            $table->decimal('valor_pedagio', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['rota_id', 'ordem']);
            $table->index('rota_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rota_pontos');
        Schema::dropIfExists('rotas');
    }
};
