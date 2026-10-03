<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Eventos do CT-e (mockup de 03/10/2026):
 *
 *   cte_eventos       ganha `criado_por` (quem transmitiu a CC-e/cancelamento)
 *   exportacoes_xml   cada ZIP mensal gerado na rotina 4060 — para baixar de novo
 *
 * Inutilização de CT-e não existe mais (CT-e 4.00, Ajuste SINIEF 31/2022):
 * nada aqui para isso — a 4060 só mostra a conferência da numeração.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cte_eventos', function (Blueprint $table): void {
            $table->foreignId('criado_por')->nullable()->constrained('users');
        });

        Schema::create('exportacoes_xml', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('filial_id')->nullable()->constrained('filiais');
            $table->char('mes', 7);                       // AAAA-MM
            $table->jsonb('opcoes');                      // {cte, mdfe, eventos}
            $table->unsignedInteger('arquivos')->default(0);
            $table->unsignedInteger('sem_xml')->default(0);
            $table->unsignedBigInteger('tamanho')->default(0);
            $table->string('caminho', 255);
            $table->foreignId('criado_por')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['empresa_id', 'mes']);
        });

        DB::statement("ALTER TABLE exportacoes_xml ADD CONSTRAINT exportacoes_xml_mes_valido CHECK (mes ~ '^[0-9]{4}-(0[1-9]|1[0-2])$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('exportacoes_xml');
        Schema::table('cte_eventos', fn (Blueprint $t) => $t->dropConstrainedForeignId('criado_por'));
    }
};
