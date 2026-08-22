<?php

declare(strict_types=1);

namespace App\Livewire\Vencimentos;

use App\Models\CertificadoDigital;
use App\Models\Motorista;
use App\Models\VeiculoDocumento;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Rotina 2040 — painel de vencimentos.
 *
 * Consolida num lugar só o que expira e bloqueia a operação: documentos de
 * veículo (CRLV, CIV, CIPP, seguro…), habilitação e exames do motorista, e o
 * certificado digital da filial. É leitura — cada item leva de volta ao seu
 * cadastro para a correção.
 *
 * Não filtra por `empresa_id`: o EmpresaScope já isola cada consulta.
 */
class Index extends Component
{
    #[Url(as: 'faixa', except: 'todos')]
    public string $faixa = 'todos';

    #[Url(as: 'cat', except: '')]
    public string $categoria = '';

    public int $horizonte = 60;

    /** Rótulos legíveis dos tipos de documento de veículo. */
    private const TIPOS_VEICULO = [
        'crlv' => 'CRLV', 'civ' => 'CIV', 'cipp' => 'CIPP',
        'tacografo' => 'Tacógrafo', 'cronotacografo' => 'Cronotacógrafo',
        'seguro' => 'Seguro', 'aet' => 'AET', 'outro' => 'Documento',
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('veiculo.consultar') ?? false, 403);
    }

    public function definirHorizonte(int $dias): void
    {
        $this->horizonte = $dias;
    }

    public function filtrarFaixa(string $faixa): void
    {
        $this->faixa = $this->faixa === $faixa ? 'todos' : $faixa;
    }

    public function filtrarCategoria(string $categoria): void
    {
        $this->categoria = $this->categoria === $categoria ? '' : $categoria;
    }

    /**
     * Lista unificada de vencimentos até o horizonte + tudo o que já venceu.
     *
     * @return Collection<int,array<string,mixed>>
     */
    #[Computed]
    public function itens(): Collection
    {
        $hoje = Carbon::today();
        $limite = $hoje->copy()->addDays($this->horizonte);
        $itens = collect();

        // 1) Documentos de veículo (polimórfico → Veiculo).
        VeiculoDocumento::query()
            ->with('documentavel')
            ->whereDate('vencimento', '<=', $limite)
            ->orderBy('vencimento')
            ->get()
            ->each(function (VeiculoDocumento $doc) use ($itens, $hoje): void {
                $itens->push($this->item(
                    categoria: 'veiculo',
                    referencia: $doc->documentavel?->placaFormatada() ?? 'Veículo',
                    documento: self::TIPOS_VEICULO[$doc->tipo] ?? ucfirst((string) $doc->tipo),
                    vencimento: $doc->vencimento,
                    bloqueia: (bool) $doc->bloqueia_operacao,
                    rota: $doc->documentavel_id ? ['veiculos.editar', $doc->documentavel_id] : null,
                    hoje: $hoje,
                ));
            });

        // 2) Habilitação, exames e RNTRC do motorista (colunas de data).
        Motorista::query()
            ->with('pessoa')
            ->get()
            ->each(function (Motorista $m) use ($itens, $hoje, $limite): void {
                $nome = $m->pessoa?->razao_social ?? 'Motorista';

                $candidatos = [
                    ['CNH', $m->cnh_validade, true],
                    ['Exame toxicológico', $m->toxicologico_validade, true],
                    ['MOPP', $m->mopp_validade, false],
                    ['Curso carga indivisível', $m->curso_carga_indivisivel_validade, false],
                ];

                if ($m->ehTac()) {
                    $candidatos[] = ['RNTRC', $m->rntrc_validade, true];
                }

                foreach ($candidatos as [$doc, $data, $bloqueia]) {
                    if ($data !== null && $data->lte($limite)) {
                        $itens->push($this->item(
                            categoria: 'motorista',
                            referencia: $nome,
                            documento: $doc,
                            vencimento: $data,
                            bloqueia: $bloqueia,
                            rota: ['motoristas.editar', $m->id],
                            hoje: $hoje,
                        ));
                    }
                }
            });

        // 3) Certificado digital da filial.
        CertificadoDigital::query()
            ->with('filial')
            ->whereDate('valido_ate', '<=', $limite)
            ->get()
            ->each(function (CertificadoDigital $c) use ($itens, $hoje): void {
                $itens->push($this->item(
                    categoria: 'certificado',
                    referencia: $c->filial?->nome_fantasia ?? $c->apelido ?? 'Certificado A1',
                    documento: 'Certificado digital A1',
                    vencimento: $c->valido_ate,
                    bloqueia: true,
                    rota: null,
                    hoje: $hoje,
                ));
            });

        return $itens->sortBy('dias')->values();
    }

    private function item(string $categoria, string $referencia, string $documento, Carbon $vencimento, bool $bloqueia, ?array $rota, Carbon $hoje): array
    {
        $dias = (int) $hoje->diffInDays($vencimento, false);

        return [
            'categoria' => $categoria,
            'referencia' => $referencia,
            'documento' => $documento,
            'vencimento' => $vencimento,
            'dias' => $dias,
            'bloqueia' => $bloqueia,
            'faixa' => $dias < 0 ? 'vencido' : ($dias <= 30 ? 'ate30' : 'ate60'),
            'rota' => $rota,
        ];
    }

    /** @return Collection<int,array<string,mixed>> */
    #[Computed]
    public function itensFiltrados(): Collection
    {
        return $this->itens
            ->when($this->faixa !== 'todos', fn (Collection $c) => $c->where('faixa', $this->faixa))
            ->when($this->categoria !== '', fn (Collection $c) => $c->where('categoria', $this->categoria))
            ->values();
    }

    /** @return array<string,int> */
    #[Computed]
    public function resumo(): array
    {
        $itens = $this->itens;

        return [
            'vencidos' => $itens->where('faixa', 'vencido')->count(),
            'ate30' => $itens->where('faixa', 'ate30')->count(),
            'ate60' => $itens->where('faixa', 'ate60')->count(),
            'bloqueantes' => $itens->where('faixa', 'vencido')->where('bloqueia', true)->count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.vencimentos.index')
            ->layout('layouts.app', ['title' => 'Vencimentos']);
    }
}
