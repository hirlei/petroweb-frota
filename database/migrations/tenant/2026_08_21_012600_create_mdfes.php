<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MDF-e (rotina 4020) — modelo 58 — documentos, eventos e vale-pedágio.
 *
 * Um MDF-e por viagem/veículo; aberto bloqueia novo (RN-03). Percurso (UFs) e
 * condutores ficam em json nesta fase (o layout permite; relacional entra se
 * virar necessidade de consulta). Nada de UPDATE em documento autorizado —
 * mudança é por evento (encerramento 110112, cancelamento 110111…).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mdfes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('filial_id')->constrained('filiais');
            $table->foreignId('viagem_id')->constrained('viagens');
            $table->char('chave', 44)->nullable();
            $table->string('modelo', 2)->default('58');
            $table->unsignedInteger('serie')->default(1);
            $table->unsignedBigInteger('numero')->nullable();
            $table->string('modal', 2)->default('01');
            $table->unsignedTinyInteger('tipo_emitente')->default(1); // 1 prestador,2 carga própria,3 CT-e global
            $table->unsignedTinyInteger('tipo_transportador')->nullable(); // 1 ETC,2 TAC,3 CTC

            $table->char('uf_inicio', 2)->nullable();
            $table->char('uf_fim', 2)->nullable();
            $table->jsonb('percurso_ufs')->nullable();          // ["GO","MG","BA"]
            $table->jsonb('municipio_carregamento')->nullable(); // [{cod,nome}]
            $table->timestamp('emissao')->nullable();

            $table->foreignId('veiculo_tracao_id')->constrained('veiculos');
            $table->jsonb('reboques')->nullable();   // [{placa,renavam,tara,uf}]
            $table->jsonb('condutores')->nullable(); // [{nome,cpf,motorista_id}]

            $table->decimal('peso_bruto_total', 15, 2)->default(0);
            $table->decimal('valor_carga_total', 15, 2)->default(0);
            $table->unsignedTinyInteger('unidade_peso')->default(1); // 01 KG, 02 TON

            $table->string('ciot', 12)->nullable();
            $table->string('ciot_cpf_cnpj', 14)->nullable();
            $table->unsignedTinyInteger('categoria_comb_veicular')->nullable(); // derivado dos eixos
            $table->jsonb('contratante')->nullable();
            $table->boolean('produto_perigoso')->default(false);
            $table->jsonb('lacres')->nullable();
            $table->jsonb('seguro')->nullable();

            $table->string('status', 20)->default('rascunho');
            $table->string('protocolo', 20)->nullable();
            $table->timestamp('data_autorizacao')->nullable();
            $table->string('codigo_status', 10)->nullable();
            $table->string('motivo_status', 255)->nullable();
            $table->timestamp('encerrado_em')->nullable();
            $table->foreignId('municipio_encerramento_id')->nullable()->constrained('municipios');
            $table->unsignedTinyInteger('ambiente')->default(2);
            $table->string('xml_path', 255)->nullable();
            $table->string('pdf_path', 255)->nullable();
            $table->jsonb('payload')->nullable();
            $table->uuid('idempotency_key')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'chave']);
            $table->unique(['filial_id', 'modelo', 'serie', 'numero']);
            $table->index(['empresa_id', 'status']);
            $table->index(['empresa_id', 'veiculo_tracao_id', 'status']);
        });

        /*
         * RN-03 no banco: no máximo UM MDF-e em aberto por veículo de tração.
         * Índice parcial ÚNICO — o `WHERE` não passa pelo builder do Laravel,
         * então vai por SQL cru. É o backstop; a checagem amigável fica no
         * GeradorMdfe.
         */
        DB::statement(
            "CREATE UNIQUE INDEX mdfes_um_aberto_por_veiculo ON mdfes (veiculo_tracao_id) "
            . "WHERE status IN ('rascunho','autorizado','contingencia')"
        );

        Schema::create('mdfe_documentos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mdfe_id')->constrained('mdfes')->cascadeOnDelete();
            $table->foreignId('municipio_descarregamento_id')->nullable()->constrained('municipios');
            $table->string('tipo', 5)->default('cte'); // cte|nfe
            $table->char('chave', 44)->nullable();
            $table->foreignId('cte_id')->nullable()->constrained('ctes');
            $table->boolean('indicador_reentrega')->default(false);
            $table->decimal('peso', 15, 4)->nullable();
            $table->decimal('valor', 15, 2)->nullable();
            $table->timestamps();

            $table->index('mdfe_id');
        });

        Schema::create('mdfe_eventos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mdfe_id')->constrained('mdfes')->cascadeOnDelete();
            $table->string('tipo_evento', 6); // 110112 encerramento, 110114 condutor…
            $table->unsignedSmallInteger('sequencia')->default(1);
            $table->timestamp('data_evento')->nullable();
            $table->jsonb('payload')->nullable();
            $table->string('protocolo', 20)->nullable();
            $table->string('status', 20)->default('registrado');
            $table->string('xml_path', 255)->nullable();
            $table->timestamps();

            $table->unique(['mdfe_id', 'tipo_evento', 'sequencia']);
        });

        // Grupo valePed — UM registro por veículo da composição.
        Schema::create('vale_pedagios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('filial_id')->constrained('filiais');
            $table->foreignId('viagem_id')->constrained('viagens');
            $table->foreignId('mdfe_id')->nullable()->constrained('mdfes');
            $table->foreignId('veiculo_id')->constrained('veiculos');
            $table->string('papel', 10)->default('recebido'); // recebido|fornecido
            $table->foreignId('fornecedor_vpo_id')->nullable()->constrained('fornecedores_vpo');
            $table->char('cnpj_forn', 14)->nullable();
            $table->string('pagador_documento', 14)->nullable();
            $table->string('pagador_tipo', 1)->nullable(); // J|F
            $table->string('idvpo', 20)->nullable();       // nCompra = IDVPO
            $table->decimal('valor', 15, 2)->nullable();
            $table->string('tipo', 2)->nullable();          // 01 TAG, 04 leitura de placa
            $table->timestamp('data_aquisicao')->nullable();
            $table->string('comprovante_path', 255)->nullable();
            $table->boolean('dispensado')->default(false);
            $table->string('motivo_dispensa', 40)->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'viagem_id']);
            $table->index('mdfe_id');
            $table->unique(['mdfe_id', 'veiculo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vale_pedagios');
        Schema::dropIfExists('mdfe_eventos');
        Schema::dropIfExists('mdfe_documentos');
        Schema::dropIfExists('mdfes');
    }
};
