<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\Viagem;
use App\Services\Operacao\CustosDaViagem;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Rotina 3080 — reconciliação dos custos denormalizados da viagem (US-079).
 * Roda toda noite (routes/console.php) e cobre o que o recálculo por evento
 * não pegou. Sem escopo de empresa de propósito: é manutenção do tenant inteiro.
 *
 *   php artisan viagens:recalcular-custos                 últimos 60 dias, todos os tenants
 *   php artisan viagens:recalcular-custos --todas         todas as viagens
 *   php artisan viagens:recalcular-custos --tenant=serraazul
 */
class RecalcularCustosViagens extends Command
{
    protected $signature = 'viagens:recalcular-custos
                            {--dias=60 : Viagens que saíram nos últimos N dias}
                            {--todas : Todas as viagens, sem limite de data}
                            {--tenant= : Só este tenant (slug)}';

    protected $description = 'Refaz custo, receita e margem das viagens a partir dos lançamentos (3080)';

    public function handle(CustosDaViagem $custos): int
    {
        $tenants = $this->option('tenant')
            ? Tenant::query()->whereKey($this->option('tenant'))->get()
            : Tenant::all()->filter(fn (Tenant $t) => $t->getAttribute('ativo') !== false);
        $desde = now()->subDays(max(1, (int) $this->option('dias')));
        $falhas = 0;

        foreach ($tenants as $tenant) {
            try {
                $this->recalcularTenant($tenant, $custos, $desde, $falhas);
            } catch (Throwable $e) {
                $falhas++;
                report($e);
                $this->error("  {$tenant->getTenantKey()}: {$e->getMessage()}");
            }
        }

        return $falhas > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function recalcularTenant(Tenant $tenant, CustosDaViagem $custos, Carbon $desde, int &$falhas): void
    {
        $tenant->run(function () use ($tenant, $custos, $desde, &$falhas): void {
            $n = 0;
            TenantContext::semEscopo(function () use ($custos, $desde, &$n, &$falhas): void {
                Viagem::query()->where('status', '!=', 'cancelada')
                    ->when(! $this->option('todas'), fn ($q) => $q->whereRaw('COALESCE(saida_real, saida_prevista, created_at) >= ?', [$desde]))
                    ->with(['motorista', 'veiculoTracao'])
                    ->chunkById(200, function ($viagens) use ($custos, &$n, &$falhas): void {
                        foreach ($viagens as $v) {
                            try {
                                $custos->recalcular($v);
                                $n++;
                            } catch (Throwable $e) {
                                $falhas++;
                                report($e);
                                $this->warn("  Viagem {$v->numero}: {$e->getMessage()}");
                            }
                        }
                    });
            });
            $this->line("  {$tenant->getTenantKey()}: {$n} viagens recalculadas");
        });
    }
}
