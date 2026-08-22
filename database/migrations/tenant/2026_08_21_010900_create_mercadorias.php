<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mercadorias (rotina 1020) — o catálogo de cargas.
 *
 * Colunas conforme docs/02_MODELAGEM_DADOS.md §4.5. Os campos de produto
 * perigoso alimentam o grupo `peri` DO MDF-e (não do CT-e rodoviário). O
 * `ponto_fulgor_c` é atributo de cadastro (FISPQ), não transmitido em DF-e.
 *
 * `produto_perigoso_id` fica nullable SEM foreign key: a tabela de referência
 * `produtos_perigosos` (Relação ONU) é de sincronização global, de outro
 * sprint; os campos ONU aqui já permitem operar antes dela existir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mercadorias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');

            // Identificação e fiscal
            $table->string('codigo_interno', 40);
            $table->string('descricao', 150);
            $table->string('descricao_complementar', 255)->nullable();
            $table->string('marca', 60)->nullable();
            $table->char('ncm', 8)->nullable();
            $table->char('cest', 7)->nullable();
            $table->string('gtin', 14)->nullable();
            $table->string('gtin_tributavel', 14)->nullable();
            $table->string('unidade_comercial', 6)->nullable();
            $table->string('unidade_tributavel', 6)->nullable();
            $table->char('cunid_cte', 2)->nullable();
            $table->char('cunid_mdfe', 2)->nullable();
            $table->boolean('ativo')->default(true);

            // Físico e logístico
            $table->decimal('peso_bruto_kg', 15, 4)->nullable();
            $table->decimal('peso_liquido_kg', 15, 4)->nullable();
            $table->decimal('comprimento_m', 8, 3)->nullable();
            $table->decimal('largura_m', 8, 3)->nullable();
            $table->decimal('altura_m', 8, 3)->nullable();
            $table->decimal('volume_m3', 12, 4)->nullable();
            $table->decimal('densidade_kg_m3', 12, 4)->nullable();
            $table->decimal('fator_cubagem_kg_m3', 12, 4)->nullable();
            $table->unsignedSmallInteger('empilhamento_max_camadas')->nullable();
            $table->boolean('permite_empilhar')->default(true);
            $table->decimal('carga_max_sobre_topo_kg', 15, 4)->nullable();
            $table->boolean('fragil')->default(false);
            $table->boolean('sentido_obrigatorio')->default(false);

            // Natureza
            $table->foreignId('natureza_carga_id')->nullable()->constrained('naturezas_carga');
            $table->foreignId('carroceria_recomendada_id')->nullable()->constrained('carrocerias');

            // Temperatura
            $table->boolean('exige_temp_controlada')->default(false);
            $table->decimal('temp_min_c', 6, 2)->nullable();
            $table->decimal('temp_max_c', 6, 2)->nullable();
            $table->boolean('exige_registro_continuo')->default(false);

            // Produto perigoso
            $table->boolean('eh_perigoso')->default(false);
            $table->unsignedBigInteger('produto_perigoso_id')->nullable();
            $table->char('num_onu', 4)->nullable();
            $table->string('nome_embarque', 150)->nullable();
            $table->string('classe_risco', 10)->nullable();
            $table->string('risco_subsidiario', 20)->nullable();
            $table->char('num_risco', 4)->nullable();
            $table->char('grupo_embalagem', 3)->nullable();
            $table->string('provisoes_especiais', 255)->nullable();
            $table->string('qtd_lim_veiculo', 40)->nullable();
            $table->string('qtd_lim_emb_interna', 40)->nullable();
            $table->decimal('ponto_fulgor_c', 6, 2)->nullable();
            $table->boolean('risco_ambiental')->default(false);
            $table->boolean('exige_mopp')->default(false);
            $table->boolean('exige_kit_9735')->default(false);
            $table->decimal('temp_controle_c', 6, 2)->nullable();
            $table->decimal('temp_emergencia_c', 6, 2)->nullable();

            // Controles setoriais
            $table->boolean('pce_exercito')->default(false);
            $table->boolean('exige_guia_trafego')->default(false);
            $table->boolean('controlado_pf')->default(false);
            $table->boolean('exige_mapa_siproquim')->default(false);
            $table->boolean('origem_animal')->default(false);
            $table->string('tipo_inspecao', 20)->nullable();
            $table->string('numero_registro_inspecao', 40)->nullable();
            $table->boolean('eh_agrotoxico')->default(false);
            $table->string('registro_mapa', 40)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'codigo_interno']);
            $table->index(['empresa_id', 'ativo']);
            $table->index(['empresa_id', 'eh_perigoso']);
        });

        // Perigoso sem número ONU é cadastro incompleto — mas não impede salvar
        // (a validação de emissão é outra camada). Garante ao menos coerência
        // mínima: se marcado perigoso, o campo ONU existe para ser preenchido.
        DB::statement(
            "ALTER TABLE mercadorias ADD CONSTRAINT mercadorias_temp_coerente "
            . "CHECK (temp_min_c IS NULL OR temp_max_c IS NULL OR temp_min_c <= temp_max_c)"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('mercadorias');
    }
};
