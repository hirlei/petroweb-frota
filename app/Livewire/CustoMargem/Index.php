<?php

declare(strict_types=1);

namespace App\Livewire\CustoMargem;

use App\Domain\Operacao\CustoViagem;
use App\Models\Filial;
use App\Models\Motorista;
use App\Models\OrdemServico;
use App\Models\Pessoa;
use App\Models\Rota;
use App\Models\Veiculo;
use App\Models\Viagem;
use App\Services\Fiscal\ExportadorXml;
use App\Services\Operacao\Acertos;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rotina 3080 — Custo e margem (mockup aprovado em 03/10/2026). Lê os custos
 * denormalizados da viagem (gravados por App\Services\Operacao\CustosDaViagem)
 * das viagens que SAÍRAM no mês, agrupados por viagem, veículo, cliente,
 * motorista ou rota. Piores margens primeiro.
 */
class Index extends Component
{
    use WithPagination;

    public const VISOES = ['viagem' => 'Viagem', 'veiculo' => 'Veículo', 'cliente' => 'Cliente', 'motorista' => 'Motorista', 'rota' => 'Rota'];

    private const COLS = ['custo_combustivel' => 'combustivel', 'custo_terceiro' => 'terceiro', 'custo_pedagio' => 'pedagio',
        'custo_motorista' => 'motorista', 'custo_manutencao' => 'manutencao', 'custo_outros' => 'outros'];

    #[Url(as: 'mes')]
    public string $mes = '';

    #[Url(as: 'filial', except: '')]
    public string $filial = '';

    #[Url(as: 'por', except: 'viagem')]
    public string $por = 'viagem';

    public function mount(): void
    {
        Gate::authorize('custo-viagem.consultar');
        $this->updatedMes();
        if (! array_key_exists($this->por, self::VISOES)) {
            $this->por = 'viagem';
        }
    }

    public function updatedMes(): void
    {
        if (! ExportadorXml::mesValido($this->mes)) {
            $this->mes = Carbon::today(ExportadorXml::fuso())->format('Y-m');
        }
        $this->resetPage();
    }

    public function updated(string $campo): void
    {
        if (in_array($campo, ['filial', 'por'], true)) {
            if (! array_key_exists($this->por, self::VISOES)) {
                $this->por = 'viagem';
            }
            $this->resetPage();
        }
    }

    /** @return array<string,string> */
    #[Computed]
    public function meses(): array
    {
        $nomes = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
        $saida = [];
        $d = Carbon::today(ExportadorXml::fuso())->startOfMonth();
        for ($i = 0; $i < 13; $i++) {
            $saida[$d->format('Y-m')] = $nomes[$d->month - 1] . '/' . $d->year;
            $d->subMonthNoOverflow();
        }

        return $saida;
    }

    /** @return Collection<int, Filial> */
    #[Computed]
    public function filiais(): Collection
    {
        return Filial::query()->orderBy('razao_social')->get(['id', 'razao_social', 'nome_fantasia']);
    }

    /** Viagens que saíram no mês (cancelada fora). */
    private function base(): Builder
    {
        // A saída é gravada como hora local (o formulário salva o que foi digitado),
        // então o corte do mês é o do calendário, sem conversão de fuso.
        $ini = $this->mes . '-01 00:00:00';
        $fim = Carbon::createFromFormat('Y-m-d', $this->mes . '-01')->addMonthNoOverflow()->format('Y-m-01 00:00:00');

        return Viagem::query()->where('status', '!=', 'cancelada')
            ->whereRaw('COALESCE(saida_real, saida_prevista) >= ? AND COALESCE(saida_real, saida_prevista) < ?', [$ini, $fim])
            ->when(ctype_digit($this->filial), fn ($q) => $q->where('filial_id', (int) $this->filial));
    }

    /** @return array<string,mixed> */
    #[Computed]
    public function resumo(): array
    {
        $sel = ['COUNT(*) AS qtd', 'COALESCE(SUM(receita_total),0) AS receita', 'COALESCE(SUM(custo_total),0) AS custo',
            'COALESCE(SUM(CASE WHEN km_percorrido > 0 THEN km_percorrido END),0) AS km',
            'COALESCE(SUM(CASE WHEN km_percorrido > 0 THEN custo_total END),0) AS custo_com_km',
            'COALESCE(SUM(CASE WHEN km_percorrido > 0 THEN receita_total END),0) AS receita_com_km'];
        foreach (self::COLS as $col => $k) {
            $sel[] = "COALESCE(SUM({$col}),0) AS {$k}";
        }
        $t = (clone $this->base())->toBase()->selectRaw(implode(', ', $sel))->first();

        $receita = (float) $t->receita;
        $custo = (float) $t->custo;
        $km = (float) $t->km;
        $comp = [];
        foreach (CustoViagem::COMPONENTES as $k => $rotulo) {
            $comp[$k] = ['rotulo' => $rotulo, 'valor' => round((float) $t->{$k}, 2), 'pct' => $custo > 0 ? round((float) $t->{$k} / $custo * 100, 1) : 0.0];
        }

        $fechadas = ['entregue', 'encerrada'];
        $acertaveis = Acertos::acertaveis(clone $this->base())->whereDoesntHave('acerto')->count();

        return [
            'qtd' => (int) $t->qtd,
            'receita' => round($receita, 2),
            'custo' => round($custo, 2),
            'margem' => round($receita - $custo, 2),
            'pct' => $receita > 0 ? round(($receita - $custo) / $receita * 100, 1) : null,
            'km' => $km,
            'custo_km' => $km > 0 ? round((float) $t->custo_com_km / $km, 2) : null,
            'receita_km' => $km > 0 ? round((float) $t->receita_com_km / $km, 2) : null,
            'componentes' => $comp,
            'pendencias' => [
                'sem_abastecimento' => (clone $this->base())->whereIn('status', $fechadas)
                    ->whereHas('veiculoTracao', fn ($v) => $v->where('propriedade', Veiculo::PROPRIEDADE_PROPRIA))
                    ->whereDoesntHave('abastecimentos')->count(),
                'acerto_aberto' => $acertaveis,
                'sem_km' => (clone $this->base())->whereIn('status', $fechadas)->whereNull('km_final')->count(),
                'sem_cte' => (clone $this->base())->whereDoesntHave('ctes')->where('receita_total', '<=', 0)->count(),
            ],
        ];
    }

    /** @return LengthAwarePaginator<Viagem> */
    #[Computed]
    public function viagens(): LengthAwarePaginator
    {
        return $this->base()
            ->with(['veiculoTracao:id,placa,propriedade', 'motorista.pessoa:id,razao_social', 'municipioOrigem:id,nome,uf', 'municipioDestino:id,nome,uf',
                'ctes' => fn ($q) => $q->select('ctes.id', 'ctes.tomador_id', 'ctes.status')->with('tomador:id,razao_social'), 'acerto:id,viagem_id'])
            ->withExists(['abastecimentos'])
            ->orderByRaw('CASE WHEN receita_total > 0 THEN margem / receita_total ELSE -999 END ASC')
            ->orderBy('id')
            ->paginate(20);
    }

    /**
     * Agrupado por veículo, cliente, motorista ou rota — piores margens primeiro.
     *
     * @return list<array{chave:string, nome:string, sub:string, viagens:int, km:float, receita:float, custo:float, margem:float, pct:?float, custo_km:?float, receita_km:?float, fora:float}>
     */
    #[Computed]
    public function grupos(): array
    {
        if ($this->por === 'viagem') {
            return [];
        }
        $linhas = $this->por === 'cliente' ? $this->porCliente() : $this->porColuna();

        usort($linhas, fn ($a, $b) => ($a['pct'] ?? -999) <=> ($b['pct'] ?? -999));

        return array_slice($linhas, 0, 200);
    }

    /** @return list<array<string,mixed>> */
    private function porColuna(): array
    {
        $col = ['veiculo' => 'veiculo_tracao_id', 'motorista' => 'motorista_id', 'rota' => 'rota_id'][$this->por];
        $rows = (clone $this->base())->toBase()->groupBy($col)
            ->selectRaw("{$col} AS chave, COUNT(*) AS viagens, COALESCE(SUM(receita_total),0) AS receita, COALESCE(SUM(custo_total),0) AS custo,
                COALESCE(SUM(CASE WHEN km_percorrido > 0 THEN km_percorrido END),0) AS km,
                COALESCE(SUM(CASE WHEN km_percorrido > 0 THEN custo_total END),0) AS custo_com_km,
                COALESCE(SUM(CASE WHEN km_percorrido > 0 THEN receita_total END),0) AS receita_com_km")
            ->get();
        $ids = $rows->pluck('chave')->filter()->all();

        $nomes = match ($this->por) {
            'veiculo' => Veiculo::query()->whereIn('id', $ids)->get()->mapWithKeys(fn (Veiculo $v) => [$v->id => [
                $v->placaFormatada(), (string) config('veiculos.propriedades.' . $v->propriedade, (string) $v->propriedade)]]),
            'motorista' => Motorista::query()->with('pessoa:id,razao_social')->whereIn('id', $ids)->get()->mapWithKeys(fn (Motorista $m) => [$m->id => [
                (string) ($m->pessoa?->razao_social ?? '—'), (string) config('motoristas.vinculos.' . $m->vinculo, ucfirst((string) $m->vinculo))]]),
            default => Rota::query()->whereIn('id', $ids)->get(['id', 'descricao'])->mapWithKeys(fn (Rota $r) => [$r->id => [$r->descricao, '']]),
        };

        // Veículo: manutenção feita fora de viagem (OS sem viagem no mês) entra no custo dele.
        $fora = collect();
        if ($this->por === 'veiculo') {
            $d = Carbon::createFromFormat('Y-m-d', $this->mes . '-01');
            $fora = OrdemServico::query()->whereNull('viagem_id')->where('status', '!=', 'cancelada')
                ->whereBetween('abertura', [$d->copy()->startOfMonth()->toDateString(), $d->copy()->endOfMonth()->toDateString()])
                ->whereIn('veiculo_id', $ids)->groupBy('veiculo_id')->selectRaw('veiculo_id, SUM(valor_total) AS total')->pluck('total', 'veiculo_id');
        }

        return $rows->map(function ($r) use ($nomes, $fora) {
            $foraV = round((float) ($fora[$r->chave] ?? 0), 2);
            $custo = round((float) $r->custo + $foraV, 2);
            $receita = round((float) $r->receita, 2);
            $km = (float) $r->km;
            [$nome, $sub] = $r->chave !== null ? ($nomes[$r->chave] ?? ['—', '']) : ['Sem ' . mb_strtolower(self::VISOES[$this->por]), ''];

            return [
                'chave' => (string) $r->chave, 'nome' => $nome, 'sub' => $sub, 'viagens' => (int) $r->viagens, 'km' => $km,
                'receita' => $receita, 'custo' => $custo, 'margem' => round($receita - $custo, 2),
                'pct' => $receita > 0 ? round(($receita - $custo) / $receita * 100, 1) : null,
                'custo_km' => $km > 0 ? round(((float) $r->custo_com_km + $foraV) / $km, 2) : null,
                'receita_km' => $km > 0 ? round((float) $r->receita_com_km / $km, 2) : null,
                'fora' => $foraV,
            ];
        })->values()->all();
    }

    /**
     * Cliente = tomador do CT-e. Viagem com CT-e de mais de um tomador divide
     * o custo na proporção da receita de cada um.
     *
     * @return list<array<string,mixed>>
     */
    private function porCliente(): array
    {
        $acc = [];
        $this->base()->select(['id', 'receita_total', 'custo_total'])
            ->with(['ctes' => fn ($q) => $q->select('ctes.id', 'ctes.tomador_id', 'ctes.valor_total_servico', 'ctes.status')])
            ->chunkById(300, function ($viagens) use (&$acc): void {
                foreach ($viagens as $v) {
                    $receitas = [];
                    foreach ($v->ctes as $c) {
                        if (in_array($c->status, ['autorizado', 'contingencia'], true) && $c->tomador_id) {
                            $receitas[$c->tomador_id] = ($receitas[$c->tomador_id] ?? 0) + (float) $c->valor_total_servico;
                        }
                    }
                    if ($receitas === []) {
                        $receitas = ['' => (float) $v->receita_total];
                    }
                    $custos = CustoViagem::ratear((float) $v->custo_total, $receitas);
                    foreach ($custos as $cliente => $custo) {
                        $k = (string) $cliente;
                        $acc[$k] ??= ['receita' => 0.0, 'custo' => 0.0, 'viagens' => 0];
                        $acc[$k]['receita'] += (float) ($receitas[$cliente] ?? 0);
                        $acc[$k]['custo'] += $custo;
                        $acc[$k]['viagens']++;
                    }
                }
            });

        $nomes = Pessoa::query()->whereIn('id', array_filter(array_keys($acc), fn ($k) => $k !== ''))->pluck('razao_social', 'id');

        return collect($acc)->map(function ($a, $k) use ($nomes) {
            $receita = round($a['receita'], 2);
            $custo = round($a['custo'], 2);

            return [
                'chave' => (string) $k, 'nome' => $k === '' ? 'Sem CT-e' : (string) ($nomes[$k] ?? '—'), 'sub' => '',
                'viagens' => $a['viagens'], 'km' => 0.0, 'receita' => $receita, 'custo' => $custo, 'margem' => round($receita - $custo, 2),
                'pct' => $receita > 0 ? round(($receita - $custo) / $receita * 100, 1) : null, 'custo_km' => null, 'receita_km' => null, 'fora' => 0.0,
            ];
        })->values()->all();
    }

    public function render(): View
    {
        return view('livewire.custo-margem.index')->layout('layouts.app', ['title' => 'Custo e margem']);
    }
}
