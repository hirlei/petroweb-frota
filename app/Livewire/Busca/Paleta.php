<?php

declare(strict_types=1);

namespace App\Livewire\Busca;

use App\Models\Cte;
use App\Models\Pessoa;
use App\Models\Veiculo;
use App\Models\Viagem;
use App\Support\Navegacao;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;
use Throwable;

/**
 * Paleta Ctrl K — igual à do ERP (02/10/2026): uma caixa só para abrir rotina
 * (por código ou nome) e achar cadastro (pessoa por nome ou CPF/CNPJ, veículo
 * pela placa, viagem e CT-e pelo número). Cada grupo respeita a permissão de
 * consulta da rotina dele.
 */
class Paleta extends Component
{
    public string $termo = '';

    public string $filtro = 'tudo';

    private const POR_GRUPO = 5;

    public function usarFiltro(string $filtro): void
    {
        $this->filtro = in_array($filtro, ['tudo', 'rotinas', 'cadastros', 'operacao'], true) ? $filtro : 'tudo';
    }

    /** @return list<array{grupo:string,itens:list<array<string,string>>}> */
    public function resultados(): array
    {
        $t = trim($this->termo);
        $grupos = [];

        if ($this->filtro === 'tudo' || $this->filtro === 'rotinas') {
            $rotinas = collect(Navegacao::mapa())
                ->filter(fn ($r, $c) => $t === '' || str_starts_with((string) $c, $t) || Str::contains(Str::lower(Str::ascii($r['label'])), Str::lower(Str::ascii($t))))
                ->take($t === '' ? 8 : self::POR_GRUPO)
                ->map(fn ($r, $c) => ['k' => (string) $c, 'titulo' => $r['label'], 'sub' => $r['grupo'], 'url' => $r['url'], 'tipo' => 'Rotina'])
                ->values()->all();
            if ($rotinas !== []) {
                $grupos[] = ['grupo' => 'Rotinas', 'itens' => $rotinas];
            }
        }

        if (mb_strlen($t) < 2) {
            return $grupos;
        }

        $user = auth()->user();
        $digitos = preg_replace('/\D/', '', $t) ?? '';

        if (($this->filtro === 'tudo' || $this->filtro === 'cadastros') && $user?->can('pessoa.consultar')) {
            $this->tentar(function () use (&$grupos, $t, $digitos): void {
                $itens = Pessoa::query()
                    ->where(function ($q) use ($t, $digitos): void {
                        $q->where('razao_social', 'ilike', "%{$t}%")->orWhere('nome_fantasia', 'ilike', "%{$t}%");
                        if (strlen($digitos) >= 3) {
                            $q->orWhere('documento', 'like', "%{$digitos}%");
                        }
                    })
                    ->orderBy('razao_social')->limit(self::POR_GRUPO)->get()
                    ->map(fn (Pessoa $p) => ['k' => '1010', 'titulo' => $p->razao_social, 'sub' => $this->documento((string) $p->documento), 'url' => route('pessoas.editar', $p), 'tipo' => 'Pessoa'])
                    ->all();
                if ($itens !== []) {
                    $grupos[] = ['grupo' => 'Pessoas', 'itens' => $itens];
                }
            });
        }

        if (($this->filtro === 'tudo' || $this->filtro === 'cadastros') && $user?->can('veiculo.consultar')) {
            $this->tentar(function () use (&$grupos, $t): void {
                $placa = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $t) ?? '');
                if (strlen($placa) < 2) {
                    return;
                }
                $itens = Veiculo::query()->where('placa', 'like', "%{$placa}%")->orderBy('placa')->limit(self::POR_GRUPO)->get()
                    ->map(fn (Veiculo $v) => ['k' => '2010', 'titulo' => $v->placaFormatada(), 'sub' => Str::ucfirst((string) $v->tipo), 'url' => route('veiculos.editar', $v), 'tipo' => 'Veículo'])
                    ->all();
                if ($itens !== []) {
                    $grupos[] = ['grupo' => 'Veículos', 'itens' => $itens];
                }
            });
        }

        if (($this->filtro === 'tudo' || $this->filtro === 'operacao') && $user?->can('viagem.consultar')) {
            $this->tentar(function () use (&$grupos, $t): void {
                $itens = Viagem::query()->with(['municipioOrigem', 'municipioDestino'])->where('numero', 'ilike', "%{$t}%")
                    ->orderByDesc('id')->limit(self::POR_GRUPO)->get()
                    ->map(fn (Viagem $v) => ['k' => '3020', 'titulo' => 'Viagem ' . $v->numero,
                        'sub' => trim(($v->municipioOrigem?->nomeComUf() ?? '—') . ' → ' . ($v->municipioDestino?->nomeComUf() ?? '—')),
                        'url' => route('viagens.editar', $v), 'tipo' => 'Viagem'])
                    ->all();
                if ($itens !== []) {
                    $grupos[] = ['grupo' => 'Viagens', 'itens' => $itens];
                }
            });
        }

        if (($this->filtro === 'tudo' || $this->filtro === 'operacao') && $digitos !== '' && $user?->can('cte.consultar')) {
            $this->tentar(function () use (&$grupos, $digitos): void {
                $itens = Cte::query()->with('tomador')->where('numero', (int) $digitos)->orderByDesc('id')->limit(self::POR_GRUPO)->get()
                    ->map(fn (Cte $c) => ['k' => '4010', 'titulo' => 'CT-e ' . number_format((int) $c->numero, 0, ',', '.'),
                        'sub' => ($c->tomador?->razao_social ?? 'Sem tomador') . ' · ' . Str::ucfirst((string) $c->status),
                        'url' => route('cte.editar', $c), 'tipo' => 'CT-e'])
                    ->all();
                if ($itens !== []) {
                    $grupos[] = ['grupo' => 'CT-e', 'itens' => $itens];
                }
            });
        }

        return $grupos;
    }

    private function tentar(callable $busca): void
    {
        try {
            $busca();
        } catch (Throwable) {
            // Um grupo com problema não pode esconder os outros resultados.
        }
    }

    private function documento(string $d): string
    {
        return match (strlen($d)) {
            11 => substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9),
            14 => substr($d, 0, 2) . '.' . substr($d, 2, 3) . '.' . substr($d, 5, 3) . '/' . substr($d, 8, 4) . '-' . substr($d, 12),
            default => $d,
        };
    }

    public function render(): View
    {
        return view('livewire.busca.paleta', ['grupos' => $this->resultados()]);
    }
}
