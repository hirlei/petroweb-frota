<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veiculos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('filial_id')->nullable()->constrained('filiais');

            // Identificação
            $table->string('placa', 7);
            $table->string('renavam', 11)->nullable();
            $table->string('chassi', 17)->nullable();
            $table->string('tipo', 20);            // tracao | reboque | semirreboque | dolly
            $table->string('marca', 60)->nullable();
            $table->string('modelo', 60)->nullable();
            $table->unsignedSmallInteger('ano_fabricacao')->nullable();
            $table->unsignedSmallInteger('ano_modelo')->nullable();
            $table->string('cor', 20)->nullable();

            // Propriedade
            $table->string('propriedade', 20)->default('propria');
            $table->foreignId('proprietario_id')->nullable()->constrained('pessoas');
            $table->string('proprietario_rntrc', 10)->nullable();
            $table->char('proprietario_tp_transp', 1)->nullable();
            $table->foreignId('contrato_agregacao_id')->nullable();

            // Configuração física
            $table->char('tp_rod', 2)->nullable();   // só para tração
            $table->char('tp_car', 2)->nullable();
            $table->foreignId('carroceria_id')->nullable()->constrained('carrocerias');
            $table->foreignId('cvc_configuracao_id')->nullable()->constrained('cvc_configuracoes');
            // Obrigatório: base do categCombVeic. Sem isso, rejeição 731.
            $table->unsignedTinyInteger('eixos');
            $table->string('tracao', 5)->nullable();
            $table->decimal('tara_kg', 15, 4);
            $table->decimal('pbt_kg', 15, 4)->nullable();
            $table->decimal('pbtc_kg', 15, 4)->nullable();
            $table->decimal('capacidade_kg', 15, 4)->nullable();
            $table->decimal('capacidade_m3', 15, 4)->nullable();
            $table->decimal('comprimento_m', 8, 3)->nullable();
            $table->decimal('largura_m', 8, 3)->nullable();
            $table->decimal('altura_m', 8, 3)->nullable();
            $table->unsignedTinyInteger('qtd_compartimentos')->nullable();
            $table->jsonb('compartimentos')->nullable();
            $table->boolean('exige_aet')->default(false);

            $table->char('uf_licenciamento', 2);
            $table->foreignId('municipio_licenciamento_id')->nullable()->constrained('municipios');

            // Operação e custo
            $table->string('combustivel', 20)->nullable();
            $table->decimal('capacidade_tanque_l', 10, 2)->nullable();
            $table->decimal('media_referencia_kml', 8, 3)->nullable();
            $table->decimal('odometro_atual', 12, 2)->default(0);
            $table->decimal('horimetro_atual', 12, 2)->nullable();
            $table->decimal('custo_km_alvo', 12, 4)->nullable();
            $table->string('rastreador_id', 50)->nullable();

            $table->string('status', 20)->default('ativo');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'placa']);
            $table->index(['empresa_id', 'status', 'tipo']);
        });

        // Invariantes que o banco garante — não dependem de ninguém lembrar
        // de chamar uma validação.
        DB::statement("ALTER TABLE veiculos ADD CONSTRAINT veiculos_eixos_min CHECK (eixos >= 1)");
        DB::statement(
            "ALTER TABLE veiculos ADD CONSTRAINT veiculos_tracao_tem_rodado "
            . "CHECK (tipo <> 'tracao' OR tp_rod IS NOT NULL)"
        );
        DB::statement(
            "ALTER TABLE veiculos ADD CONSTRAINT veiculos_terceiro_tem_proprietario "
            . "CHECK (propriedade = 'propria' OR proprietario_id IS NOT NULL)"
        );
        // Capacidade nunca acima do que a física permite.
        DB::statement(
            "ALTER TABLE veiculos ADD CONSTRAINT veiculos_capacidade_coerente "
            . "CHECK (capacidade_kg IS NULL OR pbtc_kg IS NULL "
            . "OR capacidade_kg <= pbtc_kg - tara_kg)"
        );

        Schema::create('composicoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('descricao', 120);
            $table->foreignId('veiculo_tracao_id')->constrained('veiculos');
            $table->unsignedTinyInteger('eixos_total')->nullable();
            $table->decimal('tara_total_kg', 15, 4)->nullable();
            $table->decimal('pbtc_kg', 15, 4)->nullable();
            $table->decimal('capacidade_kg', 15, 4)->nullable();
            $table->char('categ_comb_veic', 2)->nullable();
            $table->boolean('exige_aet')->default(false);
            $table->boolean('ativa')->default(true);
            $table->timestamps();

            $table->index(['empresa_id', 'ativa']);
        });

        Schema::create('composicao_itens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('composicao_id')->constrained('composicoes')->cascadeOnDelete();
            $table->foreignId('veiculo_id')->constrained('veiculos');
            $table->unsignedTinyInteger('ordem');

            $table->unique(['composicao_id', 'veiculo_id']);
            $table->unique(['composicao_id', 'ordem']);
        });

        Schema::create('motoristas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('pessoa_id')->constrained('pessoas');

            $table->string('cnh_numero', 11);
            $table->string('cnh_categoria', 5);
            $table->date('cnh_validade');
            $table->date('cnh_primeira_habilitacao')->nullable();
            $table->boolean('cnh_ear')->default(true);

            // clt | agregado | autonomo | terceiro
            $table->string('vinculo', 20);
            $table->char('tp_transp', 1)->nullable();
            $table->string('rntrc', 10)->nullable();
            $table->date('rntrc_validade')->nullable();
            $table->foreignId('contrato_agregacao_id')->nullable();
            $table->date('admissao')->nullable();
            $table->date('demissao')->nullable();

            $table->date('toxicologico_data')->nullable();
            $table->date('toxicologico_validade')->nullable();
            $table->date('mopp_validade')->nullable();
            $table->date('curso_carga_indivisivel_validade')->nullable();

            $table->decimal('valor_diaria', 15, 2)->nullable();
            $table->decimal('percentual_comissao', 7, 4)->nullable();
            $table->decimal('valor_por_km', 12, 4)->nullable();
            $table->jsonb('conta_pagamento')->nullable();

            $table->string('status', 20)->default('ativo');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'pessoa_id']);
            $table->index(['empresa_id', 'status', 'vinculo']);
            $table->index(['empresa_id', 'cnh_validade']);
        });

        // Agregado e autônomo são TAC e têm RNTRC próprio. CLT não tem —
        // opera sob o RNTRC da empresa.
        DB::statement(
            "ALTER TABLE motoristas ADD CONSTRAINT motoristas_vinculo_valido "
            . "CHECK (vinculo IN ('clt','agregado','autonomo','terceiro'))"
        );

        Schema::create('contratos_agregacao', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('pessoa_id')->constrained('pessoas');
            $table->foreignId('veiculo_id')->nullable()->constrained('veiculos');
            $table->string('numero', 30);
            $table->date('inicio');
            $table->date('fim')->nullable();
            $table->boolean('exclusividade')->default(true);
            $table->string('modalidade_remuneracao', 30);
            $table->decimal('valor_base', 15, 2)->nullable();
            $table->decimal('percentual', 7, 4)->nullable();
            $table->string('rntrc', 10)->nullable();
            $table->string('apolice_rctrc', 40)->nullable();
            $table->string('apolice_rcdc', 40)->nullable();
            $table->string('apolice_rcv', 40)->nullable();
            $table->date('validade_apolices')->nullable();
            $table->jsonb('conta_pagamento')->nullable();
            $table->string('arquivo_contrato_path')->nullable();
            $table->string('status', 20)->default('vigente');
            $table->timestamps();

            $table->unique(['empresa_id', 'numero']);
            $table->index(['empresa_id', 'status']);
        });

        Schema::create('veiculo_documentos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('documentavel_type');
            $table->unsignedBigInteger('documentavel_id');
            $table->string('tipo', 40);
            $table->string('numero', 40)->nullable();
            $table->date('emissao')->nullable();
            $table->date('vencimento');
            $table->decimal('valor', 15, 2)->nullable();
            $table->string('arquivo_path')->nullable();
            $table->boolean('bloqueia_operacao')->default(true);
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['documentavel_type', 'documentavel_id']);
            // O índice do painel de vencimentos — a consulta mais frequente.
            $table->index(['empresa_id', 'vencimento', 'bloqueia_operacao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veiculo_documentos');
        Schema::dropIfExists('contratos_agregacao');
        Schema::dropIfExists('motoristas');
        Schema::dropIfExists('composicao_itens');
        Schema::dropIfExists('composicoes');
        Schema::dropIfExists('veiculos');
    }
};
