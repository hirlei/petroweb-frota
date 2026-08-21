<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Central\Licenca;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cria um tenant no banco CENTRAL — o ato administrativo que dá a um cliente
 * banco próprio e subdomínio próprio.
 *
 * A criação dispara os jobs do TenancyServiceProvider: cria o banco
 * `frota_<slug>`, roda as migrations do tenant e o DatabaseSeeder (permissões
 * e catálogo padrão). Não é enfileirado — falha aqui aparece na tela.
 *
 * Uso:
 *   php artisan tenant:criar serraazul --nome="Transportes Serra Azul"
 */
class CriarTenant extends Command
{
    protected $signature = 'tenant:criar
                            {slug : Identificador do cliente, minúsculo e sem acento (vira o banco e o subdomínio)}
                            {--nome= : Razão social ou nome do grupo}
                            {--dominio= : Domínio completo; padrão <slug>.frota.petroweb.app}
                            {--plano=starter : Plano da licença}
                            {--veiculos=25 : Limite de veículos da licença}';

    protected $description = 'Cria um tenant: banco próprio, migrations, seed e subdomínio';

    public function handle(): int
    {
        $slug = Str::slug((string) $this->argument('slug'));

        if ($slug === '' || ! preg_match('/^[a-z][a-z0-9]{2,29}$/', $slug)) {
            $this->error('O slug precisa começar com letra e ter de 3 a 30 caracteres, só letras e números.');
            $this->line('Ele vira nome de banco e de subdomínio — hífen e acento causam dor depois.');

            return self::FAILURE;
        }

        if (Tenant::find($slug) !== null) {
            $this->error("O tenant '{$slug}' já existe.");

            return self::FAILURE;
        }

        $nome = (string) ($this->option('nome') ?: $slug);
        $dominio = (string) ($this->option('dominio')
            ?: $slug . '.' . str_replace('admin.', '', config('tenancy.central_domains.0', 'frota.petroweb.app')));

        // Um domínio central nunca pode virar domínio de tenant: o painel do
        // provedor deixaria de responder e o cliente veria o banco central.
        if (in_array($dominio, config('tenancy.central_domains', []), true)) {
            $this->error("'{$dominio}' é um domínio central. Escolha outro com --dominio.");

            return self::FAILURE;
        }

        $this->line('');
        $this->line("  Cliente : {$nome}");
        $this->line("  Slug    : {$slug}");
        $this->line("  Banco   : " . config('tenancy.database.prefix') . $slug);
        $this->line("  Domínio : https://{$dominio}");
        $this->line('');

        if (! $this->option('no-interaction') && ! $this->confirm('Criar?', true)) {
            return self::SUCCESS;
        }

        try {
            $this->info('Criando banco, rodando migrations e semeando…');

            $tenant = Tenant::create([
                'id' => $slug,
                'nome' => $nome,
                'ativo' => true,
            ]);

            $tenant->domains()->create(['domain' => $dominio]);

            Licenca::create([
                'tenant_id' => $tenant->id,
                'plano' => (string) $this->option('plano'),
                'veiculos_max' => (int) $this->option('veiculos'),
                'ativa' => true,
            ]);
        } catch (Throwable $e) {
            $this->error('Falhou: ' . $e->getMessage());
            $this->line('');
            $this->line('Se o banco chegou a ser criado, remova antes de tentar de novo:');
            $this->line('  sudo -u postgres dropdb ' . config('tenancy.database.prefix') . $slug);

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Tenant '{$slug}' criado.");
        $this->line("Acesse: https://{$dominio}");
        $this->newLine();
        $this->line('Próximos passos dentro do tenant:');
        $this->line("  php artisan tenants:run municipios:importar --tenants={$slug}");
        $this->line("  php artisan demo:semear {$slug}   # só para demonstração");

        return self::SUCCESS;
    }
}
