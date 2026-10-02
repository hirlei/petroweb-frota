<?php

declare(strict_types=1);

namespace App\Livewire\Painel;

use App\Models\Abastecimento;
use App\Models\Cte;
use App\Models\Entrega;
use App\Models\Veiculo;
use App\Models\Viagem;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Throwable;

/**
 * Fita de indicadores da barra superior — a mesma do ERP (02/10/2026), com os
 * números da transportadora. Só no Dashboard; atualiza a cada 60 s; passar o
 * mouse pausa; clicar abre a rotina. Cada indicador falha sozinho: se uma
 * conta der erro, ele some e os outros continuam.
 */
class Fita extends Component
{
    /** @return list<array<string,mixed>> */
    public function itens(): array
    {
        if (TenantContext::empresaId() === null) {
            return [];
        }

        $user = auth()->user();
        $itens = [];
        $add = function (string $can, callable $fn) use (&$itens, $user): void {
            if (! $user?->can($can)) {
                return;
            }
            try {
                $it = $fn();
                if ($it !== null) {
                    $itens[] = $it;
                }
            } catch (Throwable) {
                // Indicador é enfeite: nunca derruba a barra.
            }
        };

        $add('viagem.consultar', function (): array {
            $transito = Viagem::query()->where('status', 'em_transito')->count();
            $carregando = Viagem::query()->where('status', 'carregando')->count();

            return ['tipo' => 'valor', 'rotulo' => 'Em trânsito', 'valor' => (string) $transito, 'bruto' => $transito,
                'delta' => ['txt' => $carregando . ' carregando', 'cls' => 'eq'], 'url' => $this->url('viagens.index'),
                'titulo' => 'Viagens em trânsito agora · 3020'];
        });

        $add('veiculo.consultar', function (): ?array {
            $total = Veiculo::query()->where('tipo', 'tracao')->where('status', 'ativo')->count();
            if ($total === 0) {
                return null;
            }
            $emUso = Viagem::query()->whereIn('status', ['carregando', 'em_transito'])->distinct()->count('veiculo_tracao_id');
            $pct = (int) round($emUso / $total * 100);

            return ['tipo' => 'meta', 'rotulo' => 'Frota em uso', 'barra' => min($pct, 100), 'valor' => "{$emUso}/{$total}", 'bruto' => $emUso,
                'delta' => ['txt' => $pct . '%', 'cls' => 'eq'], 'url' => $this->url('veiculos.index'), 'titulo' => 'Cavalos ativos em viagem · 2010'];
        });

        $add('cte.consultar', function (): array {
            $dia = fn (Carbon $d) => (float) Cte::query()->where('status', 'autorizado')->whereDate('data_autorizacao', $d)->sum('valor_total_servico');
            $hoje = $dia(Carbon::today());
            $ontem = $dia(Carbon::yesterday());
            $serie = [];
            for ($i = 6; $i >= 0; $i--) {
                $serie[] = $dia(Carbon::today()->subDays($i));
            }

            return ['tipo' => 'valor', 'rotulo' => 'Faturamento', 'valor' => $this->curto($hoje), 'bruto' => $hoje,
                'delta' => $this->variacao($hoje, $ontem), 'spark' => $serie, 'url' => $this->url('cte.index'),
                'titulo' => 'CT-e autorizados hoje × ontem · 4010'];
        });

        $add('abastecimento.consultar', function (): ?array {
            $media = fn (Carbon $de, Carbon $ate) => (float) Abastecimento::query()->where('combustivel', 'ilike', '%diesel%')
                ->whereBetween('data_hora', [$de, $ate])->avg('valor_litro');
            $agora = $media(now()->subDays(7), now());
            if ($agora <= 0) {
                return null;
            }
            $antes = $media(now()->subDays(14), now()->subDays(7));
            $d = $antes > 0 ? $agora - $antes : 0.0;
            $nome = (string) Abastecimento::query()->where('combustivel', 'ilike', '%diesel%')->latest('data_hora')->value('combustivel');

            return ['tipo' => 'combustivel', 'sigla' => str_contains(strtoupper($nome), 'S500') ? 'S500' : 'S10', 'cor' => '#475569',
                'valor' => number_format($agora, 2, ',', '.'), 'bruto' => $agora,
                // Preço caindo é bom para a transportadora: desce em verde, sobe em rosa.
                'delta' => ['txt' => ($d > 0 ? '▲ ' : ($d < 0 ? '▼ ' : '')) . number_format(abs($d), 2, ',', '.'), 'cls' => $d > 0 ? 'dn' : ($d < 0 ? 'up' : 'eq')],
                'url' => $this->url('abastecimentos.index'), 'titulo' => 'Preço médio do diesel nos últimos 7 dias · 2060'];
        });

        $add('viagem.consultar', function (): ?array {
            $ckm = function (Carbon $de, Carbon $ate): ?float {
                $q = Viagem::query()->whereBetween('saida_real', [$de, $ate]);
                $km = (float) (clone $q)->sum('km_percorrido');

                return $km > 0 ? (float) (clone $q)->sum('custo_total') / $km : null;
            };
            $mes = $ckm(now()->startOfMonth(), now());
            if ($mes === null) {
                return null;
            }
            $ant = $ckm(now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth());
            $delta = $ant ? $this->variacao($mes, $ant) : ['txt' => '', 'cls' => 'eq'];
            // Custo caindo é bom: inverte as cores.
            $delta['cls'] = ['up' => 'dn', 'dn' => 'up'][$delta['cls']] ?? 'eq';

            return ['tipo' => 'valor', 'rotulo' => 'Custo/km', 'valor' => 'R$ ' . number_format($mes, 2, ',', '.'), 'bruto' => $mes,
                'delta' => $delta, 'url' => $this->url('despesas.index'), 'titulo' => 'Custo por km das viagens do mês × mês anterior · 3060'];
        });

        $add('entrega.consultar', function (): ?array {
            $hoje = Entrega::query()->whereDate('data_hora', Carbon::today());
            $total = (clone $hoje)->count();
            if ($total === 0) {
                return null;
            }
            $ok = (clone $hoje)->comprovadas()->count();

            return ['tipo' => 'meta', 'rotulo' => 'Entregas', 'barra' => (int) round($ok / $total * 100), 'valor' => "{$ok}/{$total}", 'bruto' => $ok,
                'delta' => ['txt' => 'com comprovante', 'cls' => 'eq'], 'url' => $this->url('entregas.index'), 'titulo' => 'Entregas de hoje com comprovante · 3050'];
        });

        $add('cte.consultar', function (): array {
            $fake = config('fiscal.sefaz.driver') === 'fake';
            $amb = (int) config('fiscal.sefaz.ambiente') === 1 ? 'Produção' : 'Homologação';

            return ['tipo' => 'sefaz', 'rotulo' => 'SEFAZ', 'servicos' => [
                ['nome' => 'CT-e', 'txt' => $fake ? 'simulada' : $amb, 'cls' => $fake ? 'off' : 'ok'],
                ['nome' => 'MDF-e', 'txt' => $fake ? 'simulada' : $amb, 'cls' => $fake ? 'off' : 'ok'],
            ], 'url' => null, 'titulo' => $fake ? 'Emissão simulada (sem SEFAZ real)' : 'Ambiente da SEFAZ: ' . $amb];
        });

        return $itens;
    }

    private function url(string $rota): ?string
    {
        return Route::has($rota) ? route($rota) : null;
    }

    private function curto(float $v): string
    {
        return match (true) {
            $v >= 1_000_000 => 'R$ ' . number_format($v / 1_000_000, 1, ',', '.') . 'M',
            $v >= 1_000 => 'R$ ' . number_format($v / 1_000, 1, ',', '.') . 'k',
            default => 'R$ ' . number_format($v, 0, ',', '.'),
        };
    }

    /** @return array{txt:string,cls:string} */
    private function variacao(float $agora, float $antes): array
    {
        if ($antes <= 0) {
            return ['txt' => '', 'cls' => 'eq'];
        }
        $p = ($agora - $antes) / $antes * 100;

        return ['txt' => ($p > 0 ? '▲ ' : ($p < 0 ? '▼ ' : '')) . number_format(abs($p), 1, ',', '.') . '%', 'cls' => $p > 0 ? 'up' : ($p < 0 ? 'dn' : 'eq')];
    }

    public function render(): View
    {
        return view('livewire.painel.fita', ['itens' => $this->itens(), 'atualizado' => now()]);
    }
}
