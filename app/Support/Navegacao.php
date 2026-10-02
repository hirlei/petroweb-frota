<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Cte;
use App\Models\Entrega;
use App\Models\Mdfe;
use App\Models\Motorista;
use App\Models\Ocorrencia;
use App\Models\OrdemColeta;
use App\Models\User;
use App\Models\VeiculoDocumento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

/**
 * Monta o menu H6 do PetroWeb Frota — o mesmo do ERP (02/10/2026):
 * grupos com ícone, rotinas com código, rotina ativa, pendências por rotina
 * (bolinha laranja = pendência, vermelha = urgente), Recentes e Favoritos.
 *
 * Tudo é calculado uma vez por request (cache estático) e NUNCA derruba a
 * tela: se uma contagem falhar, a bolinha some e o menu continua.
 */
final class Navegacao
{
    /** Quantos códigos ficam em "Recentes" (o ERP mostra 4 numa linha). */
    private const MAX_RECENTES = 4;

    /** @var array<string,mixed>|null */
    private static ?array $cache = null;

    /**
     * @return array{
     *   inicio: array<string,mixed>|null,
     *   grupos: list<array<string,mixed>>,
     *   mapa: array<string,array{label:string,url:string,grupo:string}>,
     *   atual: array{grupo:string,item:array<string,mixed>}|null,
     *   aberto: string,
     *   alertas: list<array<string,mixed>>
     * }
     */
    public static function montar(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $user = auth()->user();
        $badges = self::badges();
        $inicio = null;
        $grupos = [];
        $mapa = [];
        $atual = null;

        foreach (config('navegacao') as $grupo) {
            if ($grupo['solo'] ?? false) {
                $inicio = [
                    'label' => $grupo['label'],
                    'url' => Route::has($grupo['route']) ? route($grupo['route']) : '#',
                    'ativo' => request()->routeIs($grupo['route']),
                ];
                continue;
            }

            $itens = [];
            foreach ($grupo['items'] as $item) {
                if (isset($item['can']) && ! $user?->can($item['can'])) {
                    continue;
                }

                $existe = Route::has($item['route']);
                $prefixo = Str::beforeLast($item['route'], '.');
                $ativo = $existe && request()->routeIs($prefixo . '.*');
                $linha = [
                    'codigo' => $item['codigo'],
                    'label' => $item['label'],
                    'route' => $item['route'],
                    'url' => $existe ? route($item['route']) : null,
                    'ativo' => $ativo,
                    'badge' => $badges[$item['codigo']] ?? null,
                ];
                $itens[] = $linha;

                if ($existe) {
                    $mapa[$item['codigo']] = ['label' => $item['label'], 'url' => $linha['url'], 'grupo' => $grupo['label']];
                }
                if ($ativo) {
                    $atual = ['grupo' => $grupo['label'], 'item' => $linha];
                }
            }

            if ($itens === []) {
                continue;
            }

            $pend = collect($itens)->pluck('badge')->filter();
            $grupos[] = [
                'key' => $grupo['key'],
                'label' => $grupo['label'],
                'icone' => $grupo['icon'] ?? 'files',
                'items' => $itens,
                'ativo' => collect($itens)->contains('ativo', true),
                'badge' => (int) $pend->sum('n'),
                'badge_nivel' => $pend->contains('nivel', 'urgente') ? 'urgente' : 'pendente',
            ];
        }

        if ($atual !== null) {
            self::registrarRecente($atual['item']['codigo']);
        }

        return self::$cache = [
            'inicio' => $inicio,
            'grupos' => $grupos,
            'mapa' => $mapa,
            'atual' => $atual,
            'aberto' => collect($grupos)->firstWhere('ativo', true)['key'] ?? '',
            'alertas' => self::alertas($badges),
        ];
    }

    /**
     * Só o mapa código → rotina (sem contagens): é o que a paleta Ctrl K e o
     * "abrir pelo código" precisam a cada tecla, sem custo de consulta.
     *
     * @return array<string,array{label:string,url:string,grupo:string}>
     */
    public static function mapa(): array
    {
        if (self::$cache !== null) {
            return self::$cache['mapa'];
        }

        $user = auth()->user();
        $mapa = [];
        foreach (config('navegacao') as $grupo) {
            foreach ($grupo['items'] ?? [] as $item) {
                if ((isset($item['can']) && ! $user?->can($item['can'])) || ! Route::has($item['route'])) {
                    continue;
                }
                $mapa[$item['codigo']] = ['label' => $item['label'], 'url' => route($item['route']), 'grupo' => $grupo['label']];
            }
        }

        return $mapa;
    }

    /* ── Recentes (sessão) e Favoritos (users.preferencias) ── */

    private static function registrarRecente(string $codigo): void
    {
        $lista = array_values(array_filter(
            (array) session('nav.recentes', []),
            fn ($c) => $c !== $codigo,
        ));
        array_unshift($lista, $codigo);
        session(['nav.recentes' => array_slice($lista, 0, self::MAX_RECENTES)]);
    }

    /** @return list<array{codigo:string,label:string,url:string}> */
    public static function recentes(): array
    {
        $mapa = self::mapa();

        return collect((array) session('nav.recentes', []))
            ->filter(fn ($c) => isset($mapa[$c]))
            ->map(fn ($c) => ['codigo' => $c, 'label' => $mapa[$c]['label'], 'url' => $mapa[$c]['url']])
            ->values()->all();
    }

    /** @return list<string> */
    public static function favoritos(?User $user = null): array
    {
        $user ??= auth()->user();
        $favs = (array) (($user?->preferencias ?? [])['favoritos'] ?? []);
        $mapa = self::mapa();

        return array_values(array_filter($favs, fn ($c) => isset($mapa[$c])));
    }

    /* ── Pendências por rotina (bolinhas do menu e o sino) ── */

    /** @return array<string,array{n:int,nivel:string,hint:string}> */
    private static function badges(): array
    {
        if (TenantContext::empresaId() === null) {
            return [];
        }

        $b = [];
        $conta = function (string $codigo, string $nivel, string $hint, callable $q) use (&$b): void {
            try {
                $n = (int) $q();
                if ($n > 0) {
                    $b[$codigo] = ['n' => $n, 'nivel' => $nivel, 'hint' => str_replace('{n}', (string) $n, $hint)];
                }
            } catch (Throwable) {
                // Contagem é enfeite: nunca derruba o menu.
            }
        };

        $conta('4010', 'urgente', '{n} CT-e rejeitado(s)', fn () => Cte::query()->where('status', 'rejeitado')->count());
        $conta('4020', 'pendente', '{n} MDF-e aberto(s) há mais de 2 dias',
            fn () => Mdfe::query()->where('status', 'autorizado')->where('data_autorizacao', '<', now()->subDays(2))->count());
        $conta('3050', 'pendente', '{n} entrega(s) sem comprovante', fn () => Entrega::query()
            ->whereNull('canhoto_path')->whereNull('assinatura_path')->where('tipo_comprovacao', '!=', 'evento_eletronico')->count());
        $conta('3040', 'pendente', '{n} ocorrência(s) aberta(s)', fn () => Ocorrencia::query()->where('status', '!=', 'resolvida')->count());
        $conta('3010', 'pendente', '{n} ordem(ns) de coleta aberta(s)', fn () => OrdemColeta::query()->where('status', 'aberta')->count());
        try {
            $vencidos = self::vencidos();
            $conta('2040', $vencidos > 0 ? 'urgente' : 'pendente', '{n} documento(s) vencido(s) ou vencendo em 15 dias',
                fn () => $vencidos + self::vencendo(15));
        } catch (Throwable) {
            // Idem: sem a contagem, a rotina 2040 só fica sem bolinha.
        }

        return $b;
    }

    private static function vencidos(): int
    {
        return self::contarVencimentos(null, Carbon::yesterday());
    }

    private static function vencendo(int $dias): int
    {
        return self::contarVencimentos(Carbon::today(), Carbon::today()->addDays($dias));
    }

    private static function contarVencimentos(?Carbon $de, Carbon $ate): int
    {
        $docs = VeiculoDocumento::query()
            ->when($de, fn ($q) => $q->whereDate('vencimento', '>=', $de))
            ->whereDate('vencimento', '<=', $ate)->count();

        $mot = 0;
        foreach (['cnh_validade', 'toxicologico_validade'] as $col) {
            $mot += Motorista::query()->where('status', 'ativo')
                ->when($de, fn ($q) => $q->whereDate($col, '>=', $de))
                ->whereDate($col, '<=', $ate)->count();
        }

        return $docs + $mot;
    }

    /**
     * Alertas do sino e do cartão "Precisam de atenção": um por rotina com
     * pendência, mais urgentes primeiro.
     *
     * @param  array<string,array{n:int,nivel:string,hint:string}>  $badges
     * @return list<array{codigo:string,titulo:string,rotina:string,url:string,cor:string}>
     */
    private static function alertas(array $badges): array
    {
        $rotinas = collect(config('navegacao'))->flatMap(fn ($g) => $g['items'] ?? [])->keyBy('codigo');

        return collect($badges)
            ->map(fn ($b, $codigo) => [
                'codigo' => (string) $codigo,
                'titulo' => Str::ucfirst($b['hint']),
                'rotina' => $rotinas[$codigo]['label'] ?? '',
                'url' => Route::has($rotinas[$codigo]['route'] ?? '') ? route($rotinas[$codigo]['route']) : '#',
                'cor' => $b['nivel'] === 'urgente' ? 'urgente' : 'atencao',
            ])
            ->sortBy(fn ($a) => $a['cor'] === 'urgente' ? 0 : 1)
            ->values()->all();
    }

    /** Iniciais para o avatar laranja ("Hirlei Andrade" → "HA"). */
    public static function iniciais(?string $nome): string
    {
        $partes = preg_split('/\s+/', trim((string) $nome)) ?: [];
        $ini = mb_substr($partes[0] ?? '', 0, 1) . (count($partes) > 1 ? mb_substr(end($partes), 0, 1) : '');

        return mb_strtoupper($ini) ?: 'U';
    }

    /** Só para testes: zera o cache estático entre requisições simuladas. */
    public static function limpar(): void
    {
        self::$cache = null;
    }
}
