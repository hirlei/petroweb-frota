<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Faturamento (rotina 5010) e contas a receber (rotina 5020) — 02/10/2026.
 *
 *   faturas          documento comercial que agrupa CT-e autorizados de UM tomador
 *   fatura_ctes      quais CT-e estão na fatura (histórico preservado ao cancelar)
 *   titulos_receber  uma linha por parcela da fatura — é o "contas a receber"
 *   recebimentos     cada baixa (total ou parcial) de um título; estorno não apaga
 *
 * Regra que é do BANCO, não da aplicação: um CT-e só pode estar em UMA fatura
 * ativa. Índice parcial em fatura_ctes(cte_id) WHERE ativo — cancelar a fatura
 * marca as linhas como inativas e o CT-e volta a poder ser faturado.
 *
 * Valores de status (sem ENUM de banco, ver CLAUDE.md):
 *   faturas.status          aberta | parcial | paga | cancelada  ("vencida" é derivado)
 *   titulos_receber.status  aberto | parcial | recebido | cancelado
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faturas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('filial_id')->nullable()->constrained('filiais');
            $table->unsignedInteger('sequencia');
            $table->string('numero', 20);
            $table->foreignId('tomador_id')->constrained('pessoas');
            $table->date('emissao');

            $table->decimal('valor_ctes', 15, 2)->default(0);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->decimal('acrescimo', 15, 2)->default(0);
            $table->decimal('valor_total', 15, 2)->default(0);
            $table->decimal('valor_recebido', 15, 2)->default(0);

            $table->string('condicao', 40);          // "28/56" — prazos em dias
            $table->string('status', 20)->default('aberta');
            $table->text('observacoes')->nullable();

            $table->foreignId('criada_por')->nullable()->constrained('users');
            $table->timestamp('cancelada_em')->nullable();
            $table->foreignId('cancelada_por')->nullable()->constrained('users');
            $table->string('motivo_cancelamento', 255)->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'sequencia']);
            $table->unique(['empresa_id', 'numero']);
            $table->index(['empresa_id', 'status']);
            $table->index(['empresa_id', 'tomador_id']);
        });

        Schema::create('fatura_ctes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('fatura_id')->constrained('faturas')->cascadeOnDelete();
            $table->foreignId('cte_id')->constrained('ctes');
            $table->decimal('valor', 15, 2);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['empresa_id', 'fatura_id']);
        });

        // Um CT-e em no máximo UMA fatura ativa.
        DB::statement('CREATE UNIQUE INDEX fatura_ctes_um_ativo_por_cte ON fatura_ctes (cte_id) WHERE ativo');

        Schema::create('titulos_receber', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('fatura_id')->constrained('faturas')->cascadeOnDelete();
            $table->foreignId('tomador_id')->constrained('pessoas');
            $table->string('numero', 30);             // 000128-1/2
            $table->unsignedSmallInteger('parcela');
            $table->unsignedSmallInteger('parcelas');
            $table->date('vencimento');
            $table->decimal('valor', 15, 2);
            $table->decimal('valor_baixado', 15, 2)->default(0);   // principal + desconto já baixados
            $table->string('status', 20)->default('aberto');
            $table->timestamps();

            $table->unique(['empresa_id', 'numero']);
            $table->index(['empresa_id', 'status', 'vencimento']);
            $table->index(['empresa_id', 'tomador_id']);
        });

        Schema::create('recebimentos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('titulo_id')->constrained('titulos_receber');
            $table->date('data');
            $table->string('forma', 20);              // pix | boleto | transferencia | dinheiro
            $table->string('conta', 80)->nullable();
            $table->decimal('valor_principal', 15, 2);
            $table->decimal('juros_multa', 15, 2)->default(0);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->decimal('valor_total', 15, 2);    // o que entrou no caixa
            $table->string('observacao', 255)->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users');
            $table->timestamp('estornado_em')->nullable();
            $table->foreignId('estornado_por')->nullable()->constrained('users');
            $table->string('motivo_estorno', 255)->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'titulo_id']);
            $table->index(['empresa_id', 'data']);
        });

        DB::statement("ALTER TABLE faturas ADD CONSTRAINT faturas_status_valido CHECK (status IN ('aberta','parcial','paga','cancelada'))");
        DB::statement("ALTER TABLE titulos_receber ADD CONSTRAINT titulos_receber_status_valido CHECK (status IN ('aberto','parcial','recebido','cancelado'))");
        DB::statement("ALTER TABLE recebimentos ADD CONSTRAINT recebimentos_forma_valida CHECK (forma IN ('pix','boleto','transferencia','dinheiro'))");
        DB::statement('ALTER TABLE titulos_receber ADD CONSTRAINT titulos_receber_valor_positivo CHECK (valor > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('recebimentos');
        Schema::dropIfExists('titulos_receber');
        Schema::dropIfExists('fatura_ctes');
        Schema::dropIfExists('faturas');
    }
};
