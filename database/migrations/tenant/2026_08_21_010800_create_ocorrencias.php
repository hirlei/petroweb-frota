<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ocorrências (rotina 3040).
 *
 * Registro de avaria, extravio, atraso, sinistro, multa etc. — com o
 * responsável apurado e o prejuízo, quando houver. `viagem_id` e `cte_id`
 * ficam como referência NULLABLE SEM foreign key: as tabelas `viagens` e
 * `ctes` são de sprints posteriores; a FK entra quando elas existirem, sem
 * bloquear o registro de ocorrências agora.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ocorrencias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->unsignedBigInteger('viagem_id')->nullable();
            $table->unsignedBigInteger('cte_id')->nullable();
            // avaria | extravio | atraso | devolucao | sinistro | multa | parada_nao_prevista | outros
            $table->string('tipo', 30);
            $table->dateTime('data_hora');
            $table->foreignId('municipio_id')->nullable()->constrained('municipios');
            $table->text('descricao');
            // transportadora | cliente | terceiro | indeterminado
            $table->string('responsavel', 20)->default('indeterminado');
            $table->decimal('valor_prejuizo', 15, 2)->nullable();
            $table->text('tratamento')->nullable();
            // aberta | em_analise | resolvida
            $table->string('status', 20)->default('aberta');
            $table->jsonb('anexos')->nullable();
            $table->unsignedBigInteger('registrada_por')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'status']);
            $table->index(['empresa_id', 'tipo']);
            $table->index('viagem_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocorrencias');
    }
};
