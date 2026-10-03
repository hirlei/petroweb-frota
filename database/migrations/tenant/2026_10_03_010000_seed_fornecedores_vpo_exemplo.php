<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fornecedoras de vale-pedágio de EXEMPLO — 03/10/2026.
 *
 * Sem nenhuma fornecedora o vale-pedágio não sai e o MDF-e para nessa etapa.
 * Enquanto a emissão de vale é do emissor de teste, o catálogo nasce com quatro
 * fornecedoras de exemplo (CNPJ fictício, dígito verificador válido). Para
 * produção: cadastre a fornecedora real no 4030 → Fornecedoras (CNPJ da lista
 * da ANTT ou do contrato) e desative as de exemplo.
 *
 * Não duplica: CNPJ é único (ON CONFLICT DO NOTHING).
 */
return new class extends Migration
{
    private const EXEMPLOS = [
        ['11222333000181', 'Sem Parar (exemplo)'],
        ['22333444000181', 'ConectCar (exemplo)'],
        ['33444555000181', 'Veloe (exemplo)'],
        ['44555666000181', 'Repom (exemplo)'],
    ];

    public function up(): void
    {
        $agora = now();

        DB::table('fornecedores_vpo')->insertOrIgnore(array_map(fn (array $f) => [
            'cnpj' => $f[0],
            'razao_social' => $f[1],
            'ato_habilitacao' => 'Exemplo — confira na ANTT',
            'ativo' => true,
            'sincronizado_em' => null,
            'created_at' => $agora,
            'updated_at' => $agora,
        ], self::EXEMPLOS));
    }

    public function down(): void
    {
        // Só sai o que ninguém usou num vale.
        DB::table('fornecedores_vpo')
            ->whereIn('cnpj', array_column(self::EXEMPLOS, 0))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('vale_pedagios')->whereColumn('vale_pedagios.fornecedor_vpo_id', 'fornecedores_vpo.id'))
            ->delete();
    }
};
