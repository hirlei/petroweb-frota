<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CT-e (rotina 4010) — modelo 57 — e seus grupos.
 *
 * Colunas para o que aparece em listagem/filtro/relatório; o resto do XML mora
 * no `payload` (fonte de verdade da intenção; o XML autorizado é a fonte fiscal).
 * Documento autorizado é imutável — correção por evento, nunca UPDATE. Por isso
 * NÃO usamos soft delete em tabela fiscal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ctes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('filial_id')->constrained('filiais');
            $table->char('chave', 44)->nullable();
            $table->string('modelo', 2)->default('57');
            $table->unsignedInteger('serie')->default(1);
            $table->unsignedBigInteger('numero')->nullable();
            $table->unsignedTinyInteger('tipo_cte')->default(0);   // 0 normal,1 compl,2 anul,3 subst
            $table->foreignId('cte_referenciado_id')->nullable()->constrained('ctes');
            $table->unsignedTinyInteger('tipo_servico')->default(0);
            $table->string('modal', 2)->default('01');
            $table->string('cfop', 4)->nullable();
            $table->string('natureza_operacao', 60)->nullable();
            $table->timestamp('emissao')->nullable();

            // Participantes — RN-04 (tomador).
            $table->foreignId('municipio_inicio_id')->nullable()->constrained('municipios');
            $table->foreignId('municipio_fim_id')->nullable()->constrained('municipios');
            $table->unsignedTinyInteger('tomador_tipo')->default(0); // 0 rem,1 exp,2 rec,3 dest,4 outros
            $table->foreignId('tomador_id')->nullable()->constrained('pessoas');
            $table->foreignId('remetente_id')->nullable()->constrained('pessoas');
            $table->foreignId('destinatario_id')->nullable()->constrained('pessoas');
            $table->foreignId('expedidor_id')->nullable()->constrained('pessoas');
            $table->foreignId('recebedor_id')->nullable()->constrained('pessoas');
            $table->foreignId('ordem_coleta_id')->nullable()->constrained('ordens_coleta');

            // Carga e valores.
            $table->string('produto_predominante', 60)->nullable();
            $table->decimal('peso_bruto', 15, 4)->nullable();
            $table->decimal('peso_base_calculo', 15, 4)->nullable();
            $table->decimal('volumes', 15, 4)->nullable();
            $table->decimal('valor_mercadoria', 15, 2)->default(0);
            $table->decimal('valor_total_servico', 15, 2)->default(0); // vTPrest
            $table->decimal('valor_receber', 15, 2)->default(0);

            // ICMS.
            $table->string('icms_cst', 2)->nullable();
            $table->decimal('icms_base', 15, 2)->nullable();
            $table->decimal('icms_aliquota', 7, 4)->nullable();
            $table->decimal('icms_valor', 15, 2)->nullable();

            // Reforma tributária (NT 2026.002) — IBS/CBS.
            $table->jsonb('ibs_cbs_payload')->nullable();
            $table->decimal('cbs_valor', 15, 2)->nullable();
            $table->decimal('ibs_uf_valor', 15, 2)->nullable();
            $table->decimal('ibs_mun_valor', 15, 2)->nullable();

            // Transmissão.
            $table->string('status', 20)->default('rascunho');
            $table->string('protocolo', 20)->nullable();
            $table->timestamp('data_autorizacao')->nullable();
            $table->string('codigo_status', 10)->nullable();  // cStat
            $table->string('motivo_status', 255)->nullable(); // xMotivo
            $table->unsignedTinyInteger('tipo_emissao')->default(1);
            $table->unsignedTinyInteger('ambiente')->default(2); // 1 prod, 2 homolog
            $table->string('xml_path', 255)->nullable();
            $table->string('pdf_path', 255)->nullable();
            $table->jsonb('payload')->nullable();
            $table->uuid('idempotency_key')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'chave']);
            $table->unique(['filial_id', 'modelo', 'serie', 'numero']);
            $table->unique(['empresa_id', 'idempotency_key']);
            $table->index(['empresa_id', 'status']);
            $table->index(['empresa_id', 'emissao']);
        });

        Schema::create('cte_documentos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cte_id')->constrained('ctes')->cascadeOnDelete();
            $table->string('tipo', 15)->default('nfe'); // nfe|nf|outros|cte_anterior
            $table->char('chave', 44)->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('serie', 5)->nullable();
            $table->timestamp('emissao')->nullable();
            $table->decimal('valor', 15, 2)->nullable();
            $table->decimal('peso', 15, 4)->nullable();
            $table->timestamps();

            $table->index('cte_id');
        });

        Schema::create('cte_componentes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cte_id')->constrained('ctes')->cascadeOnDelete();
            $table->string('nome', 30); // FRETE PESO, GRIS, PEDAGIO…
            $table->decimal('valor', 15, 2);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();

            $table->index('cte_id');
        });

        Schema::create('cte_eventos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cte_id')->constrained('ctes')->cascadeOnDelete();
            $table->string('tipo_evento', 6); // 110110,110111,110180…
            $table->unsignedSmallInteger('sequencia')->default(1);
            $table->timestamp('data_evento')->nullable();
            $table->string('justificativa', 255)->nullable();
            $table->jsonb('correcoes')->nullable();
            $table->string('protocolo', 20)->nullable();
            $table->string('status', 20)->default('registrado');
            $table->string('codigo_status', 10)->nullable();
            $table->string('motivo_status', 255)->nullable();
            $table->string('xml_path', 255)->nullable();
            $table->timestamps();

            $table->unique(['cte_id', 'tipo_evento', 'sequencia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cte_eventos');
        Schema::dropIfExists('cte_componentes');
        Schema::dropIfExists('cte_documentos');
        Schema::dropIfExists('ctes');
    }
};
