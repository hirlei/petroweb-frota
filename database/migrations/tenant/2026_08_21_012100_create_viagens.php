<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Viagens (rotina 3020) e a pivot com CT-e (RN-02).
 *
 * A viagem é a execução física do transporte: uma composição, um (ou dois)
 * motoristas, uma rota, e os CT-e que ela carrega. O vínculo viagem↔CT-e é
 * N:N — uma viagem leva vários CT-e (carga fracionada) e um CT-e pode ser
 * transbordado em várias viagens. Modelar como 1:1 obrigaria a refazer o
 * sistema, então a pivot já nasce aqui.
 *
 * Os custos são DENORMALIZADOS de propósito: o painel de operação é a tela mais
 * acessada e somar em tempo real a cada consulta é caro. São recalculados por
 * observer quando abastecimento, despesa, OS ou CT-e da viagem mudam, e há
 * reconciliação noturna. Débito técnico consciente, registrado no doc mestre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viagens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('filial_id')->constrained('filiais');
            $table->string('numero', 20);
            // carga_lotacao | fracionada | transferencia | carga_propria | retorno_vazio
            $table->string('tipo', 20)->default('carga_lotacao');

            // Composição no momento da viagem.
            $table->foreignId('veiculo_tracao_id')->constrained('veiculos');
            // Placas dos reboques no momento da viagem — congelado, não segue troca posterior.
            $table->jsonb('composicao_snapshot')->nullable();
            $table->foreignId('motorista_id')->constrained('motoristas');
            $table->foreignId('motorista_2_id')->nullable()->constrained('motoristas');
            $table->foreignId('rota_id')->nullable()->constrained('rotas');
            $table->foreignId('municipio_origem_id')->nullable()->constrained('municipios');
            $table->foreignId('municipio_destino_id')->nullable()->constrained('municipios');

            $table->timestamp('saida_prevista')->nullable();
            $table->timestamp('saida_real')->nullable();
            $table->timestamp('chegada_prevista')->nullable();
            $table->timestamp('chegada_real')->nullable();

            $table->decimal('km_inicial', 12, 2)->nullable();
            $table->decimal('km_final', 12, 2)->nullable();
            $table->decimal('km_percorrido', 12, 2)->nullable();

            // Consolidados dos CT-e.
            $table->decimal('peso_total', 15, 2)->nullable();
            $table->decimal('valor_carga', 15, 2)->nullable();

            // Custos denormalizados — recalculados por evento.
            $table->decimal('custo_combustivel', 15, 2)->default(0);
            $table->decimal('custo_pedagio', 15, 2)->default(0);
            $table->decimal('custo_motorista', 15, 2)->default(0);
            $table->decimal('custo_manutencao', 15, 2)->default(0);
            $table->decimal('custo_outros', 15, 2)->default(0);
            $table->decimal('custo_total', 15, 2)->default(0);

            $table->decimal('receita_total', 15, 2)->default(0);
            $table->decimal('margem', 15, 2)->default(0);
            $table->decimal('custo_por_km', 12, 4)->nullable();

            // planejada | carregando | em_transito | entregue | encerrada | cancelada
            $table->string('status', 20)->default('planejada');
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'numero']);
            $table->index(['empresa_id', 'status']);
            $table->index(['empresa_id', 'veiculo_tracao_id']);
            $table->index(['empresa_id', 'motorista_id']);
        });

        // Pivot N:N com CT-e (RN-02). A FK para `ctes` é adicionada quando a
        // tabela de CT-e existir (módulo 4010) — aqui fica o vínculo estrutural.
        Schema::create('viagem_ctes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('viagem_id')->constrained('viagens')->cascadeOnDelete();
            $table->unsignedBigInteger('cte_id');
            $table->unsignedSmallInteger('sequencia')->default(0);
            // principal | redespacho | subcontratacao | transbordo
            $table->string('papel', 20)->default('principal');
            $table->timestamp('entregue_em')->nullable();
            $table->timestamps();

            $table->unique(['viagem_id', 'cte_id']);
            $table->index('cte_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viagem_ctes');
        Schema::dropIfExists('viagens');
    }
};
