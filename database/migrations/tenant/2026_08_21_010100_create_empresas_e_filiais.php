<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table): void {
            $table->id();
            $table->string('razao_social', 150);
            $table->string('nome_fantasia', 150)->nullable();
            // CNPJ em string, nunca inteiro: a NT Conjunta 2025.001 torna as
            // 14 posições alfanuméricas. Ver documento 03, seção 5.2.
            $table->string('cnpj', 14)->unique();
            $table->string('timezone', 40)->default('America/Sao_Paulo');
            $table->jsonb('parametros')->nullable();
            $table->boolean('ativa')->default(true);
            $table->date('vigencia_ate')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('filiais', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('codigo', 10);
            $table->string('razao_social', 150);
            $table->string('nome_fantasia', 150)->nullable();
            $table->string('cnpj', 14);
            $table->string('ie', 20);
            $table->string('im', 20)->nullable();
            // Código de Regime Tributário: 1 Simples, 2 Simples excesso, 3 Normal.
            $table->char('crt', 1)->default('3');
            $table->string('rntrc', 10)->nullable();
            $table->date('rntrc_validade')->nullable();
            $table->char('tp_transp', 1)->nullable();
            $table->boolean('matriz')->default(false);

            $table->string('logradouro', 150);
            $table->string('numero', 20);
            $table->string('complemento', 80)->nullable();
            $table->string('bairro', 80);
            $table->foreignId('municipio_id')->constrained('municipios');
            $table->char('cep', 8);
            $table->string('telefone', 20)->nullable();
            $table->string('email', 120)->nullable();

            // Ambiente é atributo DA FILIAL: 1 produção, 2 homologação.
            $table->unsignedTinyInteger('ambiente_sefaz')->default(2);
            $table->char('uf_autorizadora', 2)->nullable();
            $table->boolean('contingencia_automatica')->default(true);
            $table->unsignedBigInteger('certificado_id')->nullable();
            $table->string('logo_path')->nullable();

            $table->boolean('ativa')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'codigo']);
            $table->unique(['empresa_id', 'cnpj']);
            $table->index(['empresa_id', 'ativa']);
        });

        // Uma matriz por empresa — índice parcial, recurso que o MySQL não tem.
        DB::statement(
            'CREATE UNIQUE INDEX filiais_matriz_unica ON filiais (empresa_id) '
            . 'WHERE matriz = true AND deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('filiais');
        Schema::dropIfExists('empresas');
    }
};
