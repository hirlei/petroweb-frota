<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Condições de faturamento do cliente (02/10/2026, rotinas 5010/5020).
 *
 * - `prazo_faturamento`: prazos em dias separados por barra ("28", "28/56",
 *   "0" para à vista). É o padrão que a Nova fatura já traz marcado.
 * - `multa_percentual` e `juros_mes_percentual`: encargos sugeridos ao receber
 *   uma parcela em atraso (multa uma vez; juros simples pro rata dia).
 *
 * Tudo opcional: sem prazo, a fatura sugere 30 dias; sem encargos, nada é
 * cobrado a mais — e em qualquer caso dá para ajustar na hora.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pessoas', function (Blueprint $table): void {
            $table->string('prazo_faturamento', 40)->nullable()->after('observacoes');
            $table->decimal('multa_percentual', 5, 2)->nullable()->after('prazo_faturamento');
            $table->decimal('juros_mes_percentual', 5, 2)->nullable()->after('multa_percentual');
        });
    }

    public function down(): void
    {
        Schema::table('pessoas', function (Blueprint $table): void {
            $table->dropColumn(['prazo_faturamento', 'multa_percentual', 'juros_mes_percentual']);
        });
    }
};
