<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CIOT (rotina 4050) e a compra do vale-pedágio pelo sistema — 02/10/2026.
 *
 *   ciots             um por viagem remunerada; registrado na emissão do MDF-e e
 *                     reaproveitado se o MDF-e for rejeitado ou reemitido
 *   ciot_pagamentos   adiantamento e saldo pagos ao TAC (só modalidade ipef)
 *   vale_pedagios     ganha origem (informado | compra), situação e cancelamento
 *
 * Regras do BANCO:
 *  - uma viagem tem no máximo UM CIOT não cancelado (índice parcial);
 *  - o número do CIOT é único na empresa entre os não cancelados;
 *  - um veículo tem no máximo UM vale ativo por MDF-e (o índice antigo, sem
 *    filtro, impedia comprar de novo depois de cancelar).
 *
 * Valores (sem ENUM de banco, ver CLAUDE.md):
 *   ciots.modalidade   ipef | antt | informado
 *   ciots.status       recusado | registrado | quitado | cancelado
 *   ciots.forma_pagamento / ciot_pagamentos.forma   pix | transferencia | cartao_frete
 *   ciot_pagamentos.tipo     adiantamento | saldo
 *   ciot_pagamentos.status   confirmado | recusado
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ciots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('filial_id')->constrained('filiais');
            $table->foreignId('viagem_id')->constrained('viagens');
            $table->foreignId('mdfe_id')->nullable()->constrained('mdfes'); // último MDF-e que levou o número

            $table->string('modalidade', 12);
            $table->string('numero', 12)->nullable();
            $table->string('responsavel_documento', 14)->nullable(); // vai no infCIOT junto do número

            $table->string('contratante_documento', 14)->nullable();
            $table->foreignId('contratado_id')->nullable()->constrained('pessoas');
            $table->string('contratado_nome', 150)->nullable();
            $table->string('contratado_documento', 14)->nullable();
            $table->string('contratado_rntrc', 10)->nullable();

            $table->decimal('valor_frete', 15, 2)->default(0);
            $table->decimal('percentual_adiantamento', 5, 2)->default(0);
            $table->decimal('valor_adiantamento', 15, 2)->default(0);
            $table->decimal('valor_saldo', 15, 2)->default(0);
            $table->decimal('valor_pago', 15, 2)->default(0);
            $table->date('prazo_quitacao')->nullable();
            $table->string('forma_pagamento', 15)->nullable();
            $table->string('chave_pagamento', 120)->nullable(); // chave Pix, conta ou cartão do TAC

            $table->string('instituicao', 60)->nullable();
            $table->string('status', 12)->default('registrado');
            $table->string('protocolo', 40)->nullable();
            $table->string('motivo', 255)->nullable();       // recusa da instituição/ANTT
            $table->jsonb('retorno')->nullable();
            $table->unsignedSmallInteger('tentativas')->default(0);

            $table->timestamp('registrado_em')->nullable();
            $table->timestamp('quitado_em')->nullable();
            $table->timestamp('cancelado_em')->nullable();
            $table->foreignId('cancelado_por')->nullable()->constrained('users');
            $table->string('motivo_cancelamento', 255)->nullable();
            $table->foreignId('criado_por')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['empresa_id', 'status']);
            $table->index(['empresa_id', 'viagem_id']);
        });

        DB::statement("CREATE UNIQUE INDEX ciots_um_por_viagem ON ciots (viagem_id) WHERE status <> 'cancelado'");
        DB::statement("CREATE UNIQUE INDEX ciots_numero_unico ON ciots (empresa_id, numero) WHERE numero IS NOT NULL AND status <> 'cancelado'");
        DB::statement("ALTER TABLE ciots ADD CONSTRAINT ciots_modalidade_chk CHECK (modalidade IN ('ipef','antt','informado'))");
        DB::statement("ALTER TABLE ciots ADD CONSTRAINT ciots_status_chk CHECK (status IN ('recusado','registrado','quitado','cancelado'))");
        DB::statement("ALTER TABLE ciots ADD CONSTRAINT ciots_forma_chk CHECK (forma_pagamento IS NULL OR forma_pagamento IN ('pix','transferencia','cartao_frete'))");
        DB::statement("ALTER TABLE ciots ADD CONSTRAINT ciots_registrado_tem_numero CHECK (status IN ('recusado','cancelado') OR numero IS NOT NULL)");
        DB::statement('ALTER TABLE ciots ADD CONSTRAINT ciots_valores_chk CHECK (valor_frete >= 0 AND valor_pago >= 0 AND percentual_adiantamento BETWEEN 0 AND 100)');

        Schema::create('ciot_pagamentos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('ciot_id')->constrained('ciots')->cascadeOnDelete();
            $table->string('tipo', 12);
            $table->decimal('valor', 15, 2);
            $table->string('forma', 15);
            $table->date('data');
            $table->string('status', 12)->default('confirmado');
            $table->string('protocolo', 40)->nullable();
            $table->string('motivo', 255)->nullable();
            $table->jsonb('retorno')->nullable();
            $table->foreignId('criado_por')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['empresa_id', 'ciot_id']);
        });

        DB::statement("ALTER TABLE ciot_pagamentos ADD CONSTRAINT ciot_pagamentos_tipo_chk CHECK (tipo IN ('adiantamento','saldo'))");
        DB::statement("ALTER TABLE ciot_pagamentos ADD CONSTRAINT ciot_pagamentos_forma_chk CHECK (forma IN ('pix','transferencia','cartao_frete'))");
        DB::statement("ALTER TABLE ciot_pagamentos ADD CONSTRAINT ciot_pagamentos_status_chk CHECK (status IN ('confirmado','recusado'))");
        DB::statement('ALTER TABLE ciot_pagamentos ADD CONSTRAINT ciot_pagamentos_valor_chk CHECK (valor > 0)');

        Schema::table('vale_pedagios', function (Blueprint $table): void {
            $table->string('origem', 10)->default('informado');   // informado | compra
            $table->string('situacao', 10)->default('ativo');      // ativo | cancelado
            $table->string('protocolo', 40)->nullable();
            $table->jsonb('retorno')->nullable();
            $table->timestamp('cancelado_em')->nullable();
            $table->string('motivo_cancelamento', 255)->nullable();
        });

        DB::statement('ALTER TABLE vale_pedagios DROP CONSTRAINT IF EXISTS vale_pedagios_mdfe_id_veiculo_id_unique');
        DB::statement("CREATE UNIQUE INDEX vale_pedagios_um_ativo_por_mdfe ON vale_pedagios (mdfe_id, veiculo_id) WHERE situacao = 'ativo'");
        DB::statement("ALTER TABLE vale_pedagios ADD CONSTRAINT vale_pedagios_origem_chk CHECK (origem IN ('informado','compra'))");
        DB::statement("ALTER TABLE vale_pedagios ADD CONSTRAINT vale_pedagios_situacao_chk CHECK (situacao IN ('ativo','cancelado'))");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS vale_pedagios_um_ativo_por_mdfe');
        DB::statement('ALTER TABLE vale_pedagios DROP CONSTRAINT IF EXISTS vale_pedagios_origem_chk');
        DB::statement('ALTER TABLE vale_pedagios DROP CONSTRAINT IF EXISTS vale_pedagios_situacao_chk');
        Schema::table('vale_pedagios', function (Blueprint $table): void {
            $table->dropColumn(['origem', 'situacao', 'protocolo', 'retorno', 'cancelado_em', 'motivo_cancelamento']);
        });
        Schema::table('vale_pedagios', function (Blueprint $table): void {
            $table->unique(['mdfe_id', 'veiculo_id']);
        });

        Schema::dropIfExists('ciot_pagamentos');
        Schema::dropIfExists('ciots');
    }
};
