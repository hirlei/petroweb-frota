<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Geometria da rota (traçado pela estrada) e posições de GPS.
 *
 * `rotas.geometria` guarda o traçado calculado pelo motor de rotas
 * (OpenRouteService) — lista de [lat,lng] — para não recalcular a cada abertura.
 * `posicoes_veiculo` é a base da camada de rastreamento: cada posição recebida
 * de um provedor (Sascar, Onixsat, Cobli…) via webhook/pull, com o veículo, a
 * coordenada e o instante. A última posição de cada veículo alimenta o mapa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rotas', function (Blueprint $table): void {
            // { pontos: [[lat,lng],...], distancia_km, duracao_min, calculado_em }
            $table->jsonb('geometria')->nullable()->after('restricoes');
        });

        Schema::create('posicoes_veiculo', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('veiculo_id')->constrained('veiculos')->cascadeOnDelete();
            $table->foreignId('viagem_id')->nullable()->constrained('viagens')->nullOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('velocidade_kmh', 6, 2)->nullable();
            $table->unsignedSmallInteger('rumo')->nullable(); // 0–359 graus
            $table->boolean('ignicao')->nullable();
            $table->string('provedor', 30)->nullable(); // sascar | onixsat | cobli | manual…
            $table->timestamp('capturado_em');           // instante do GPS
            $table->timestamp('recebido_em')->nullable(); // quando chegou no sistema
            $table->jsonb('bruto')->nullable();           // payload original do provedor
            $table->timestamps();

            $table->index(['empresa_id', 'veiculo_id', 'capturado_em']);
            $table->index(['veiculo_id', 'capturado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posicoes_veiculo');
        Schema::table('rotas', function (Blueprint $table): void {
            $table->dropColumn('geometria');
        });
    }
};
