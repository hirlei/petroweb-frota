<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas OPERACIONAIS de domínio — o que o cliente enxerga e edita.
 *
 * Cada uma aponta para o enum fechado da SEFAZ correspondente. O operador
 * escolhe "Sider"; o sistema envia tpCar 05. Nenhuma tela de operação mostra
 * código fiscal (RN-11).
 *
 * `empresa_id` NULO = registro padrão do sistema, visível a todas as empresas
 * do tenant. Preenchido = criado pelo cliente. Por isso estas três NÃO usam
 * PertenceAEmpresa: o escopo delas é "meu ou do sistema", não "só meu".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('naturezas_carga', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas');
            $table->string('codigo', 40);
            $table->string('nome', 80);
            $table->string('tp_carga_base', 2);
            $table->boolean('permite_perigosa')->default(true);
            $table->boolean('exige_temperatura')->default(false);
            $table->boolean('exige_aet')->default(false);
            $table->boolean('exige_gta')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'codigo']);
        });

        Schema::create('carrocerias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas');
            $table->string('codigo', 40);
            $table->string('nome', 80);
            // tpCar da SEFAZ: 00 não aplicável, 01 aberta, 02 fechada/baú,
            // 03 granelera, 04 porta-container, 05 sider.
            $table->char('tp_car_fiscal', 2);
            $table->string('natureza_padrao', 40)->nullable();
            $table->boolean('exige_temperatura_controlada')->default(false);
            $table->boolean('exige_civ_cipp')->default(false);
            $table->boolean('exige_certificacao_inmetro')->default(false);
            $table->boolean('aceita_produto_perigoso')->default(true);
            $table->boolean('permite_conteiner')->default(false);
            $table->decimal('capacidade_m3_referencia', 10, 2)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'codigo']);
        });

        Schema::create('cvc_configuracoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas');
            $table->string('nome_popular', 60);
            $table->string('slug', 60);
            $table->unsignedTinyInteger('eixos');
            $table->unsignedTinyInteger('qtd_unidades')->default(1);
            $table->string('tracao', 5)->nullable();
            $table->decimal('pbtc_kg', 15, 4)->nullable();
            $table->decimal('comprimento_max_m', 8, 3)->nullable();
            $table->decimal('capacidade_min_kg', 15, 4)->nullable();
            $table->decimal('capacidade_max_kg', 15, 4)->nullable();
            $table->boolean('exige_aet')->default(false);
            $table->char('tp_rod_default', 2)->nullable();
            $table->char('cnh_minima', 1)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'slug']);
        });

        /*
         * ARMADILHA DO POSTGRES: em `UNIQUE (empresa_id, codigo)`, dois
         * registros com `empresa_id IS NULL` NÃO colidem — NULL nunca é igual
         * a NULL. Sem os índices parciais abaixo, o seed do sistema poderia
         * inserir "Sider" duas vezes e ninguém perceberia até a tela duplicar.
         */
        DB::statement(
            'CREATE UNIQUE INDEX naturezas_carga_sistema_unica '
            . 'ON naturezas_carga (codigo) WHERE empresa_id IS NULL'
        );
        DB::statement(
            'CREATE UNIQUE INDEX carrocerias_sistema_unica '
            . 'ON carrocerias (codigo) WHERE empresa_id IS NULL'
        );
        DB::statement(
            'CREATE UNIQUE INDEX cvc_configuracoes_sistema_unica '
            . 'ON cvc_configuracoes (slug) WHERE empresa_id IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('cvc_configuracoes');
        Schema::dropIfExists('carrocerias');
        Schema::dropIfExists('naturezas_carga');
    }
};
