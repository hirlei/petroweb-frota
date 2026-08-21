<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enums fiscais da SEFAZ e tabelas de referência.
 *
 * São READ-ONLY: o cliente nunca edita. O código vai em VARCHAR para
 * preservar o zero à esquerda ('01', nunca 1) — ver documento 05, seção 1.
 */
return new class extends Migration
{
    /** Enums fechados da SEFAZ: tabela → comprimento do código. */
    private const ENUMS = [
        'tp_rod'          => 2,  // tipo de rodado (6 valores)
        'tp_car'          => 2,  // tipo de carroceria (6 valores)
        'tp_carga'        => 2,  // tipo de carga (12 valores)
        'categ_comb_veic' => 2,  // categoria de combinação veicular (10 valores)
        'tp_transp'       => 1,  // ETC, TAC, CTC
        'tp_unid_transp'  => 1,
        'tp_unid_carga'   => 1,
        'cunid_cte'       => 2,  // 6 valores — NÃO compartilha com o MDF-e
        'cunid_mdfe'      => 2,  // 2 valores apenas
    ];

    public function up(): void
    {
        foreach (self::ENUMS as $tabela => $tamanho) {
            Schema::create($tabela, function (Blueprint $table) use ($tamanho): void {
                $table->string('codigo', $tamanho)->primary();
                $table->string('descricao', 120);
                $table->unsignedSmallInteger('ordem')->default(0);
                $table->boolean('ativo')->default(true);
            });
        }

        Schema::create('ufs', function (Blueprint $table): void {
            $table->char('sigla', 2)->primary();
            $table->string('nome', 60);
            $table->char('codigo_ibge', 2);
        });

        Schema::create('municipios', function (Blueprint $table): void {
            $table->id();
            $table->char('codigo_ibge', 7)->unique();
            $table->string('nome', 120);
            $table->char('uf', 2);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->index(['uf', 'nome']);
        });

        Schema::create('ncm', function (Blueprint $table): void {
            $table->char('codigo', 8)->primary();
            $table->text('descricao');
            $table->date('vigencia_inicio')->nullable();
            $table->date('vigencia_fim')->nullable();
        });

        Schema::create('cfop', function (Blueprint $table): void {
            $table->char('codigo', 4)->primary();
            $table->text('descricao');
            $table->boolean('transporte')->default(false);
        });
    }

    public function down(): void
    {
        foreach (['cfop', 'ncm', 'municipios', 'ufs'] as $t) {
            Schema::dropIfExists($t);
        }

        foreach (array_keys(self::ENUMS) as $t) {
            Schema::dropIfExists($t);
        }
    }
};
