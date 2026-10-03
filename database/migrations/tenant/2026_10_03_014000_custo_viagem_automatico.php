<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Custo e margem (rotina 3080, mockup de 03/10/2026). O custo da viagem passa
 * a ser montado dos lançamentos ligados a ela (abastecimento, despesa, OS,
 * CIOT, vale-pedágio, acerto) por App\Services\Operacao\CustosDaViagem.
 *
 *   custo_terceiro          frete pago a agregado/autônomo/ETC (CIOT)
 *   custos_digitados        o que estava digitado antes — vale só para o
 *                           componente que não tem lançamento
 *   custos_recalculados_em  último recálculo (por evento ou da rotina noturna)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viagens', function (Blueprint $table): void {
            $table->decimal('custo_terceiro', 15, 2)->default(0);
            $table->jsonb('custos_digitados')->nullable();
            $table->timestamp('custos_recalculados_em')->nullable();
        });

        // Pedágio, motorista e outros já vinham das despesas aprovadas: só são
        // "digitados" na viagem que não tem despesa nenhuma.
        DB::statement("UPDATE viagens v SET custos_digitados = jsonb_build_object(
                'combustivel', v.custo_combustivel,
                'manutencao', v.custo_manutencao,
                'pedagio', CASE WHEN d.id IS NULL THEN v.custo_pedagio ELSE 0 END,
                'motorista', CASE WHEN d.id IS NULL THEN v.custo_motorista ELSE 0 END,
                'outros', CASE WHEN d.id IS NULL THEN v.custo_outros ELSE 0 END)
            FROM viagens v2
            LEFT JOIN LATERAL (SELECT id FROM despesas_viagem dv WHERE dv.viagem_id = v2.id LIMIT 1) d ON true
            WHERE v2.id = v.id
              AND (v.custo_combustivel > 0 OR v.custo_pedagio > 0 OR v.custo_motorista > 0 OR v.custo_manutencao > 0 OR v.custo_outros > 0)");
    }

    public function down(): void
    {
        Schema::table('viagens', fn (Blueprint $t) => $t->dropColumn(['custo_terceiro', 'custos_digitados', 'custos_recalculados_em']));
    }
};
