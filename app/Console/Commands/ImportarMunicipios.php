<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Importa a tabela de municípios direto do IBGE.
 *
 * Por que comando e não seeder com dados embutidos: o CT-e exige `cMunIni` e
 * `cMunFim`, e o MDF-e o município de carregamento e de descarga. Um código
 * IBGE errado é rejeição na SEFAZ. Digitar 5.570 códigos à mão — ou pior,
 * de memória — é criar erro em silêncio. A fonte é o IBGE, e ponto.
 *
 * Roda no tenant (a tabela `municipios` vive no banco do cliente):
 *   php artisan tenants:run municipios:importar
 *
 * Ou, dentro de um tenant específico:
 *   php artisan tenants:run municipios:importar --tenants=serraazul
 */
class ImportarMunicipios extends Command
{
    protected $signature = 'municipios:importar
                            {--forcar : Reimporta mesmo que a tabela já tenha dados}';

    protected $description = 'Importa municípios e códigos IBGE da API oficial do IBGE';

    private const URL = 'https://servicodados.ibge.gov.br/api/v1/localidades/municipios?view=nivelado';

    /** Abaixo disso, a resposta veio truncada — não vale gravar. */
    private const MINIMO_ESPERADO = 5_000;

    public function handle(): int
    {
        $existentes = DB::table('municipios')->count();

        if ($existentes > 0 && ! $this->option('forcar')) {
            $this->info("Tabela já tem {$existentes} municípios. Use --forcar para reimportar.");

            return self::SUCCESS;
        }

        $this->info('Buscando a lista no IBGE…');

        try {
            $resposta = Http::timeout(90)->retry(3, 2_000)->get(self::URL);
        } catch (Throwable $e) {
            $this->error('Não foi possível falar com o IBGE: ' . $e->getMessage());
            $this->line('A VPS precisa de saída HTTPS para servicodados.ibge.gov.br.');

            return self::FAILURE;
        }

        if (! $resposta->successful()) {
            $this->error('IBGE respondeu ' . $resposta->status() . '.');

            return self::FAILURE;
        }

        $itens = $resposta->json();

        if (! is_array($itens) || count($itens) < self::MINIMO_ESPERADO) {
            $this->error('Resposta com ' . (is_array($itens) ? count($itens) : 0)
                . ' itens — esperava pelo menos ' . self::MINIMO_ESPERADO . '. Nada foi gravado.');

            return self::FAILURE;
        }

        $linhas = [];

        foreach ($itens as $item) {
            $codigo = (string) ($item['municipio-id'] ?? '');
            $nome = (string) ($item['municipio-nome'] ?? '');
            $uf = (string) ($item['UF-sigla'] ?? '');

            if (strlen($codigo) !== 7 || $nome === '' || strlen($uf) !== 2) {
                continue;
            }

            $linhas[] = ['codigo_ibge' => $codigo, 'nome' => $nome, 'uf' => $uf];
        }

        if (count($linhas) < self::MINIMO_ESPERADO) {
            $this->error('Só ' . count($linhas) . ' registros passaram na validação. Nada foi gravado.');

            return self::FAILURE;
        }

        $barra = $this->output->createProgressBar(count($linhas));
        $barra->start();

        DB::transaction(function () use ($linhas, $barra): void {
            // upsert em lote: reimportar não duplica nem perde a FK de quem
            // já aponta para o município (endereços, filiais, viagens).
            foreach (array_chunk($linhas, 500) as $lote) {
                DB::table('municipios')->upsert($lote, ['codigo_ibge'], ['nome', 'uf']);
                $barra->advance(count($lote));
            }
        });

        $barra->finish();
        $this->newLine(2);

        $total = DB::table('municipios')->count();
        $this->info("Pronto: {$total} municípios na tabela.");

        return self::SUCCESS;
    }
}
