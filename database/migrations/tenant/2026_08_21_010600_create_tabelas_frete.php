<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas de frete (rotina 1030) e seus itens.
 *
 * Uma tabela vale para um cliente (`pessoa_id`) ou é geral (`pessoa_id` nulo),
 * numa vigência e, opcionalmente, num trecho (UF/município origem→destino) e
 * tipo de veículo. O preço em si é a soma dos ITENS — cada um um componente
 * (peso, valor, gris, ad valorem, pedágio, TDE, TDA…) com sua base de cálculo.
 *
 * O vale-pedágio NÃO integra o valor do frete nem a base do ICMS (RN fiscal),
 * mas pode existir como componente informativo da tabela; a separação é feita
 * na geração do CT-e, não aqui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabelas_frete', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            // Nulo = tabela geral, vale para qualquer cliente sem tabela própria.
            $table->foreignId('pessoa_id')->nullable()->constrained('pessoas');
            $table->string('descricao', 150);
            $table->date('vigencia_inicio');
            $table->date('vigencia_fim')->nullable();
            $table->char('uf_origem', 2)->nullable();
            $table->char('uf_destino', 2)->nullable();
            $table->foreignId('municipio_origem_id')->nullable()->constrained('municipios');
            $table->foreignId('municipio_destino_id')->nullable()->constrained('municipios');
            $table->string('tipo_veiculo', 30)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['empresa_id', 'ativo']);
            $table->index(['empresa_id', 'pessoa_id']);
        });

        Schema::create('tabela_frete_itens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tabela_frete_id')->constrained('tabelas_frete')->cascadeOnDelete();
            // peso | valor | gris | advalorem | pedagio | tde | tda | taxa_entrega | outros
            $table->string('componente', 20);
            // por_kg | por_ton | percentual_valor | fixo | por_km | por_volume
            $table->string('base_calculo', 20);
            $table->decimal('faixa_de', 15, 4)->nullable();
            $table->decimal('faixa_ate', 15, 4)->nullable();
            $table->decimal('valor', 15, 4);
            $table->decimal('minimo', 15, 2)->nullable();
            $table->decimal('maximo', 15, 2)->nullable();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();

            $table->index('tabela_frete_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabela_frete_itens');
        Schema::dropIfExists('tabelas_frete');
    }
};
