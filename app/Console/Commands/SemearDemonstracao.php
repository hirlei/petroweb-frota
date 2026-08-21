<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Database\Seeders\DemonstracaoSeeder;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

/**
 * Semeia os dados de demonstração dentro de um tenant.
 *
 * Existe para não obrigar ninguém a escrever isto num shell:
 *
 *   php artisan tenants:run db:seed --tenants=serraazul \
 *     --option="class=Database\\\\Seeders\\\\DemonstracaoSeeder"
 *
 * Além de ilegível, a contagem de barras invertidas muda entre bash, zsh e
 * PowerShell — e o erro que sai quando se erra é "Class not found", que não
 * ajuda ninguém. Um comando com nome resolve.
 *
 * Uso:
 *   php artisan demo:semear serraazul
 */
class SemearDemonstracao extends Command
{
    protected $signature = 'demo:semear
                            {tenant : Slug do tenant que vai receber os dados}
                            {--forcar : Não pede confirmação}';

    protected $description = 'Popula um tenant com a transportadora fictícia de demonstração';

    public function handle(): int
    {
        $slug = (string) $this->argument('tenant');
        $tenant = Tenant::find($slug);

        if ($tenant === null) {
            $this->error("Tenant '{$slug}' não existe.");
            $this->line("Crie antes: php artisan tenant:criar {$slug}");

            return self::FAILURE;
        }

        $this->line('');
        $this->warn('  Estes dados são FICTÍCIOS e a filial nasce em homologação da SEFAZ.');
        $this->line("  Tenant: {$slug} ({$tenant->nome})");
        $this->line('');

        if (! $this->option('forcar') && ! $this->confirm('Semear?', true)) {
            return self::SUCCESS;
        }

        try {
            $tenant->run(function () use ($slug): void {
                // Sem municípios, o seeder falha com mensagem clara — mas é
                // melhor avisar antes de começar do que no meio.
                if (DB::table('municipios')->count() === 0) {
                    throw new RuntimeException(
                        "A tabela de municípios está vazia. Rode antes:\n"
                        . "  php artisan tenants:run municipios:importar --tenants={$slug}"
                    );
                }

                $seeder = new DemonstracaoSeeder();
                $seeder->setCommand($this);
                $seeder->run();
            });
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
