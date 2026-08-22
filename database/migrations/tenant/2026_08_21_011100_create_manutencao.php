<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manutenção (rotina 2050) — ordens de serviço e seus itens. Doc §5.7.
 *
 * `oficina_id` aponta para `pessoas` (papel oficina); `interna` distingue a
 * oficina própria da terceirizada. `plano_manutencao_id` e `viagem_id` ficam
 * como referência nullable SEM foreign key — planos de manutenção e viagens
 * são de sprints posteriores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordens_servico', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('filial_id')->nullable()->constrained('filiais');
            $table->foreignId('veiculo_id')->constrained('veiculos');
            $table->string('numero', 20);
            // preventiva | corretiva | sinistro | pneu | revisao
            $table->string('tipo', 20);
            $table->unsignedBigInteger('plano_manutencao_id')->nullable();
            $table->foreignId('oficina_id')->nullable()->constrained('pessoas');
            $table->boolean('interna')->default(false);
            $table->date('abertura');
            $table->date('previsao')->nullable();
            $table->date('encerramento')->nullable();
            $table->decimal('odometro', 12, 2)->nullable();
            $table->unsignedBigInteger('viagem_id')->nullable();
            // aberta | em_execucao | aguardando_peca | encerrada | cancelada
            $table->string('status', 20)->default('aberta');
            $table->decimal('valor_pecas', 15, 2)->default(0);
            $table->decimal('valor_mao_obra', 15, 2)->default(0);
            $table->decimal('valor_total', 15, 2)->default(0);
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'numero']);
            $table->index(['empresa_id', 'status']);
            $table->index(['empresa_id', 'veiculo_id']);
        });

        Schema::create('os_itens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ordem_servico_id')->constrained('ordens_servico')->cascadeOnDelete();
            // peca | servico
            $table->string('tipo', 10);
            $table->string('descricao', 150);
            $table->string('codigo', 40)->nullable();
            $table->decimal('quantidade', 12, 3)->default(1);
            $table->decimal('valor_unitario', 15, 2)->default(0);
            $table->decimal('valor_total', 15, 2)->default(0);
            $table->date('garantia_ate')->nullable();
            $table->timestamps();

            $table->index('ordem_servico_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('os_itens');
        Schema::dropIfExists('ordens_servico');
    }
};
