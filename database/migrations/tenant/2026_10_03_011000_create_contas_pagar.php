<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contas a pagar (rotina 5030) — 03/10/2026.
 *
 *   contas_pagar         uma linha por parcela a pagar (o "título")
 *   conta_pagar_origens  de onde a conta nasceu: abastecimento, ordem de serviço
 *                        ou CIOT — várias origens podem virar uma conta (vários
 *                        abastecimentos do mesmo posto)
 *   pagamentos_conta     cada baixa, total ou parcial; estorno não apaga
 *
 * Regra do BANCO: uma origem em no máximo UMA conta ativa — índice parcial em
 * conta_pagar_origens(origem_tipo, origem_id) WHERE ativo. Cancelar a conta
 * desativa a linha e o item volta para "Lançamentos esperando".
 *
 * Valores (sem ENUM de banco, ver CLAUDE.md):
 *   contas_pagar.categoria  combustivel | manutencao | frete_terceiro | pedagio | seguro | impostos | servicos | outras
 *   contas_pagar.status     aberto | parcial | pago | cancelado  ("vencida" é derivado)
 *   conta_pagar_origens.origem_tipo  abastecimento | ordem_servico | ciot
 *   pagamentos_conta.forma  pix | boleto | transferencia | dinheiro | cartao | instituicao_ciot
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contas_pagar', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('filial_id')->nullable()->constrained('filiais');
            $table->foreignId('favorecido_id')->constrained('pessoas');
            $table->string('categoria', 20);
            $table->string('documento', 60)->nullable();
            $table->string('descricao', 200)->nullable();
            $table->uuid('grupo');                       // parcelas do mesmo lançamento
            $table->unsignedSmallInteger('parcela')->default(1);
            $table->unsignedSmallInteger('parcelas')->default(1);
            $table->date('emissao');
            $table->date('vencimento');
            $table->decimal('valor', 15, 2);
            $table->decimal('valor_baixado', 15, 2)->default(0);   // principal + desconto já baixados
            $table->string('status', 20)->default('aberto');
            $table->string('origem', 20)->default('manual');     // manual | abastecimento | ordem_servico | ciot
            $table->text('observacoes')->nullable();

            $table->foreignId('criado_por')->nullable()->constrained('users');
            $table->timestamp('cancelado_em')->nullable();
            $table->foreignId('cancelado_por')->nullable()->constrained('users');
            $table->string('motivo_cancelamento', 255)->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'status', 'vencimento']);
            $table->index(['empresa_id', 'favorecido_id']);
            $table->index(['empresa_id', 'grupo']);
        });

        Schema::create('conta_pagar_origens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('conta_pagar_id')->constrained('contas_pagar')->cascadeOnDelete();
            $table->string('origem_tipo', 20);
            $table->unsignedBigInteger('origem_id');
            $table->decimal('valor', 15, 2);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['empresa_id', 'conta_pagar_id']);
        });

        DB::statement('CREATE UNIQUE INDEX conta_pagar_origens_uma_ativa ON conta_pagar_origens (empresa_id, origem_tipo, origem_id) WHERE ativo');

        Schema::create('pagamentos_conta', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('conta_pagar_id')->constrained('contas_pagar');
            $table->date('data');
            $table->string('forma', 20);
            $table->string('conta', 80)->nullable();
            $table->decimal('valor_principal', 15, 2);
            $table->decimal('juros_multa', 15, 2)->default(0);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->decimal('valor_total', 15, 2);       // o que saiu do caixa
            $table->string('observacao', 255)->nullable();
            $table->foreignId('ciot_pagamento_id')->nullable()->constrained('ciot_pagamentos');
            $table->foreignId('registrado_por')->nullable()->constrained('users');
            $table->timestamp('estornado_em')->nullable();
            $table->foreignId('estornado_por')->nullable()->constrained('users');
            $table->string('motivo_estorno', 255)->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'conta_pagar_id']);
            $table->index(['empresa_id', 'data']);
        });

        // Um pagamento do CIOT espelhado no máximo uma vez.
        DB::statement('CREATE UNIQUE INDEX pagamentos_conta_ciot_unico ON pagamentos_conta (ciot_pagamento_id) WHERE ciot_pagamento_id IS NOT NULL');

        DB::statement("ALTER TABLE contas_pagar ADD CONSTRAINT contas_pagar_status_valido CHECK (status IN ('aberto','parcial','pago','cancelado'))");
        DB::statement("ALTER TABLE contas_pagar ADD CONSTRAINT contas_pagar_categoria_valida CHECK (categoria IN ('combustivel','manutencao','frete_terceiro','pedagio','seguro','impostos','servicos','outras'))");
        DB::statement("ALTER TABLE contas_pagar ADD CONSTRAINT contas_pagar_origem_valida CHECK (origem IN ('manual','abastecimento','ordem_servico','ciot'))");
        DB::statement('ALTER TABLE contas_pagar ADD CONSTRAINT contas_pagar_valor_positivo CHECK (valor > 0 AND valor_baixado >= 0)');
        DB::statement("ALTER TABLE conta_pagar_origens ADD CONSTRAINT conta_pagar_origens_tipo_valido CHECK (origem_tipo IN ('abastecimento','ordem_servico','ciot'))");
        DB::statement("ALTER TABLE pagamentos_conta ADD CONSTRAINT pagamentos_conta_forma_valida CHECK (forma IN ('pix','boleto','transferencia','dinheiro','cartao','instituicao_ciot'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('pagamentos_conta');
        Schema::dropIfExists('conta_pagar_origens');
        Schema::dropIfExists('contas_pagar');
    }
};
