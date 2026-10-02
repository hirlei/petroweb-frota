<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ordens de coleta (rotina 3010) e seus itens.
 *
 * O documento de ENTRADA da operação — o que o cliente pede transportar. Ao ser
 * faturada, vira CT-e. Guarda os participantes (tomador, remetente, destinatário
 * e afins), o trecho, a carga e o frete calculado a partir da tabela vigente.
 *
 * As chaves de NF-e capturadas nos itens alimentam depois o grupo `infNFe` do
 * CT-e E o grupo de documentos do MDF-e — capturar na entrada evita redigitar
 * em dois lugares.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordens_coleta', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('filial_id')->constrained('filiais');
            $table->string('numero', 20);
            $table->date('data');

            // Cliente contratante e participantes do transporte.
            $table->foreignId('cliente_id')->constrained('pessoas');
            // remetente | destinatario | expedidor | recebedor | outros
            $table->string('tomador_tipo', 20)->default('remetente');
            $table->foreignId('tomador_id')->nullable()->constrained('pessoas');
            $table->foreignId('remetente_id')->nullable()->constrained('pessoas');
            $table->foreignId('destinatario_id')->nullable()->constrained('pessoas');
            $table->foreignId('expedidor_id')->nullable()->constrained('pessoas');
            $table->foreignId('recebedor_id')->nullable()->constrained('pessoas');

            // Endereços e trecho.
            $table->foreignId('endereco_coleta_id')->nullable()->constrained('enderecos');
            $table->foreignId('endereco_entrega_id')->nullable()->constrained('enderecos');
            $table->foreignId('municipio_inicio_id')->nullable()->constrained('municipios');
            $table->foreignId('municipio_fim_id')->nullable()->constrained('municipios');
            $table->timestamp('previsao_coleta')->nullable();
            $table->timestamp('previsao_entrega')->nullable();

            // Carga.
            $table->decimal('peso_bruto', 15, 4)->nullable();
            $table->decimal('peso_cubado', 15, 4)->nullable();
            $table->unsignedInteger('volumes')->nullable();
            $table->decimal('valor_mercadoria', 15, 2)->nullable();

            // Frete: qual tabela respondeu e quanto deu.
            $table->foreignId('tabela_frete_id')->nullable()->constrained('tabelas_frete');
            $table->decimal('valor_frete_calculado', 15, 2)->nullable();

            $table->text('observacoes')->nullable();
            // aberta | coletada | faturada | cancelada
            $table->string('status', 20)->default('aberta');
            $table->timestamps();

            // Número é único por empresa (RN-05: unicidade sempre composta).
            $table->unique(['empresa_id', 'numero']);
            $table->index(['empresa_id', 'status']);
            $table->index(['empresa_id', 'cliente_id']);
            $table->index(['empresa_id', 'data']);
        });

        Schema::create('oc_itens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ordem_coleta_id')->constrained('ordens_coleta')->cascadeOnDelete();
            $table->foreignId('mercadoria_id')->nullable()->constrained('mercadorias');
            $table->string('descricao', 200);
            $table->decimal('quantidade', 15, 4)->nullable();
            $table->string('unidade', 10)->nullable();
            $table->decimal('peso', 15, 4)->nullable();
            $table->decimal('volume', 15, 4)->nullable();
            $table->decimal('valor', 15, 2)->nullable();
            // Chave da NF-e: 44 dígitos. Alimenta infNFe do CT-e e docs do MDF-e.
            $table->char('nfe_chave', 44)->nullable();
            $table->string('nfe_numero', 20)->nullable();
            $table->string('nfe_serie', 5)->nullable();
            $table->timestamps();

            $table->index('ordem_coleta_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oc_itens');
        Schema::dropIfExists('ordens_coleta');
    }
};
