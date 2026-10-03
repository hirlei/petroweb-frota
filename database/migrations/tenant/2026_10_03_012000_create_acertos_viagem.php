<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Acerto de viagem (rotina 3070) — 03/10/2026. Só para motorista CLT (RN-12).
 *
 *   adiantamentos      dinheiro entregue ao motorista para a viagem
 *   acertos_viagem     fechamento: snapshot dos totais e do saldo; reabrir marca,
 *                      não apaga — o novo fechamento é outra linha
 *   despesas_viagem    ganha `glosada` (aceita = aprovada; glosa = glosada;
 *                      nenhum dos dois = falta conferir)
 *   (diária e comissão padrão já existem no motorista: valor_diaria e
 *    percentual_comissao, desde a criação da frota)
 *   contas_pagar       aceita a categoria/origem `acerto_viagem` (saldo a favor do
 *                      motorista vira conta no 5030)
 *
 * Regra do BANCO: uma viagem tem no máximo UM acerto fechado (índice parcial).
 *
 * Valores (sem ENUM de banco, ver CLAUDE.md):
 *   adiantamentos.forma       dinheiro | pix | cartao_frota | deposito
 *   adiantamentos.finalidade  geral | pedagio | combustivel | alimentacao
 *   acertos_viagem.status     fechado | reaberto
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adiantamentos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('viagem_id')->constrained('viagens');
            $table->foreignId('motorista_id')->constrained('motoristas');
            $table->date('data');
            $table->decimal('valor', 15, 2);
            $table->string('forma', 15);
            $table->string('finalidade', 15)->default('geral');
            $table->string('observacao', 255)->nullable();
            $table->foreignId('criado_por')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['empresa_id', 'viagem_id']);
            $table->index(['empresa_id', 'motorista_id']);
        });

        DB::statement("ALTER TABLE adiantamentos ADD CONSTRAINT adiantamentos_forma_valida CHECK (forma IN ('dinheiro','pix','cartao_frota','deposito'))");
        DB::statement("ALTER TABLE adiantamentos ADD CONSTRAINT adiantamentos_finalidade_valida CHECK (finalidade IN ('geral','pedagio','combustivel','alimentacao'))");
        DB::statement('ALTER TABLE adiantamentos ADD CONSTRAINT adiantamentos_valor_positivo CHECK (valor > 0)');

        Schema::create('acertos_viagem', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('viagem_id')->constrained('viagens');
            $table->foreignId('motorista_id')->constrained('motoristas');
            $table->string('status', 10)->default('fechado');

            // Snapshot do fechamento.
            $table->decimal('total_adiantado', 15, 2)->default(0);
            $table->decimal('gasto_adiantamento', 15, 2)->default(0);
            $table->decimal('bolso_aceito', 15, 2)->default(0);
            $table->decimal('glosado', 15, 2)->default(0);
            $table->unsignedSmallInteger('dias')->default(0);
            $table->decimal('diaria_valor', 15, 2)->default(0);
            $table->decimal('diarias_total', 15, 2)->default(0);
            $table->decimal('comissao_percentual', 5, 2)->default(0);
            $table->decimal('comissao_base', 15, 2)->default(0);
            $table->decimal('comissao_valor', 15, 2)->default(0);
            $table->decimal('saldo', 15, 2)->default(0);          // > 0 empresa paga; < 0 motorista devolve
            $table->text('observacoes')->nullable();

            $table->foreignId('conta_pagar_id')->nullable()->constrained('contas_pagar');
            $table->date('devolvido_em')->nullable();
            $table->string('devolvido_forma', 15)->nullable();

            $table->timestamp('fechado_em')->nullable();
            $table->foreignId('fechado_por')->nullable()->constrained('users');
            $table->timestamp('reaberto_em')->nullable();
            $table->foreignId('reaberto_por')->nullable()->constrained('users');
            $table->string('motivo_reabertura', 255)->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'status']);
            $table->index(['empresa_id', 'motorista_id']);
        });

        DB::statement("CREATE UNIQUE INDEX acertos_viagem_um_fechado ON acertos_viagem (empresa_id, viagem_id) WHERE status = 'fechado'");
        DB::statement("ALTER TABLE acertos_viagem ADD CONSTRAINT acertos_viagem_status_valido CHECK (status IN ('fechado','reaberto'))");

        Schema::table('despesas_viagem', function (Blueprint $table): void {
            $table->boolean('glosada')->default(false);
            $table->string('motivo_glosa', 255)->nullable();
        });
        DB::statement('ALTER TABLE despesas_viagem ADD CONSTRAINT despesas_viagem_aceita_ou_glosa CHECK (NOT (aprovada AND glosada))');

        // 5030: categoria e origem do acerto.
        DB::statement('ALTER TABLE contas_pagar DROP CONSTRAINT IF EXISTS contas_pagar_categoria_valida');
        DB::statement("ALTER TABLE contas_pagar ADD CONSTRAINT contas_pagar_categoria_valida CHECK (categoria IN ('combustivel','manutencao','frete_terceiro','acerto_viagem','pedagio','seguro','impostos','servicos','outras'))");
        DB::statement('ALTER TABLE contas_pagar DROP CONSTRAINT IF EXISTS contas_pagar_origem_valida');
        DB::statement("ALTER TABLE contas_pagar ADD CONSTRAINT contas_pagar_origem_valida CHECK (origem IN ('manual','abastecimento','ordem_servico','ciot','acerto'))");
        DB::statement('ALTER TABLE conta_pagar_origens DROP CONSTRAINT IF EXISTS conta_pagar_origens_tipo_valido');
        DB::statement("ALTER TABLE conta_pagar_origens ADD CONSTRAINT conta_pagar_origens_tipo_valido CHECK (origem_tipo IN ('abastecimento','ordem_servico','ciot','acerto'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE conta_pagar_origens DROP CONSTRAINT IF EXISTS conta_pagar_origens_tipo_valido');
        DB::statement("ALTER TABLE conta_pagar_origens ADD CONSTRAINT conta_pagar_origens_tipo_valido CHECK (origem_tipo IN ('abastecimento','ordem_servico','ciot'))");
        DB::statement('ALTER TABLE contas_pagar DROP CONSTRAINT IF EXISTS contas_pagar_origem_valida');
        DB::statement("ALTER TABLE contas_pagar ADD CONSTRAINT contas_pagar_origem_valida CHECK (origem IN ('manual','abastecimento','ordem_servico','ciot'))");
        DB::statement('ALTER TABLE contas_pagar DROP CONSTRAINT IF EXISTS contas_pagar_categoria_valida');
        DB::statement("ALTER TABLE contas_pagar ADD CONSTRAINT contas_pagar_categoria_valida CHECK (categoria IN ('combustivel','manutencao','frete_terceiro','pedagio','seguro','impostos','servicos','outras'))");

        DB::statement('ALTER TABLE despesas_viagem DROP CONSTRAINT IF EXISTS despesas_viagem_aceita_ou_glosa');
        Schema::table('despesas_viagem', fn (Blueprint $t) => $t->dropColumn(['glosada', 'motivo_glosa']));
        Schema::dropIfExists('acertos_viagem');
        Schema::dropIfExists('adiantamentos');
    }
};
