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
        Schema::create('certificados_digitais', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('filial_id')->constrained('filiais');
            $table->string('apelido', 80);
            // Caminho no storage privado, FORA do webroot. A senha é
            // criptografada com a chave da aplicação — nunca em texto puro.
            $table->string('arquivo_path');
            $table->text('senha_encriptada');
            $table->string('cnpj_titular', 14);
            $table->timestamp('valido_de');
            $table->timestamp('valido_ate');
            $table->string('status', 20)->default('ativo');
            $table->timestamps();

            $table->index(['empresa_id', 'valido_ate']);
            $table->index(['filial_id', 'status']);
        });

        // Um certificado ativo por filial.
        DB::statement(
            'CREATE UNIQUE INDEX certificado_ativo_unico ON certificados_digitais (filial_id) '
            . "WHERE status = 'ativo'"
        );

        Schema::create('sequencias_documento', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('filial_id')->constrained('filiais');
            // 57 CT-e, 58 MDF-e, 67 CT-e OS, 64 GTV-e.
            $table->char('modelo', 2);
            $table->unsignedInteger('serie');
            $table->unsignedBigInteger('proximo_numero')->default(1);
            $table->timestamps();

            $table->unique(['filial_id', 'modelo', 'serie']);
        });

        Schema::create('inutilizacoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('filial_id')->constrained('filiais');
            $table->char('modelo', 2);
            $table->unsignedInteger('serie');
            $table->unsignedBigInteger('numero_inicial');
            $table->unsignedBigInteger('numero_final');
            $table->unsignedSmallInteger('ano');
            $table->string('justificativa', 255);
            $table->string('protocolo', 20)->nullable();
            $table->string('status', 20)->default('pendente');
            $table->string('xml_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['empresa_id', 'modelo', 'serie']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inutilizacoes');
        Schema::dropIfExists('sequencias_documento');
        Schema::dropIfExists('certificados_digitais');
    }
};
