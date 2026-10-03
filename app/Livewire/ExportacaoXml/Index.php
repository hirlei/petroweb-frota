<?php

declare(strict_types=1);

namespace App\Livewire\ExportacaoXml;

use App\Models\ExportacaoXml;
use App\Models\Filial;
use App\Services\Fiscal\ExportadorXml;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

/**
 * Rotina 4060 — Exportar XML (mockup aprovado em 03/10/2026): o mês para o
 * contador num ZIP, com a conferência da numeração e as pendências antes.
 */
class Index extends Component
{
    #[Url(as: 'mes')]
    public string $mes = '';

    #[Url(as: 'filial', except: '')]
    public string $filial = '';

    public bool $cte = true;
    public bool $mdfe = true;
    public bool $eventos = true;

    public function mount(): void
    {
        Gate::authorize('xml.exportar');
        $this->updatedMes();
    }

    /** Mês fora do formato (URL ou requisição adulterada) volta para o mês passado. */
    public function updatedMes(): void
    {
        if (! ExportadorXml::mesValido($this->mes)) {
            $this->mes = Carbon::today(ExportadorXml::fuso())->subMonthNoOverflow()->format('Y-m');
        }
    }

    /** Últimos 13 meses, do atual para trás. @return array<string,string> */
    #[Computed]
    public function meses(): array
    {
        $nomes = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
        $saida = [];
        $d = Carbon::today(ExportadorXml::fuso())->startOfMonth();
        for ($i = 0; $i < 13; $i++) {
            $saida[$d->format('Y-m')] = $nomes[$d->month - 1] . '/' . $d->year . ($i === 0 ? ' (em andamento)' : '');
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

    private function filialId(): ?int
    {
        return ctype_digit($this->filial) ? (int) $this->filial : null;
    }

    /** @return array{cte:bool, mdfe:bool, eventos:bool} */
    private function opcoes(): array
    {
        return ['cte' => $this->cte, 'mdfe' => $this->mdfe, 'eventos' => $this->eventos];
    }

    /** @return array<string,mixed> */
    #[Computed]
    public function resumo(): array
    {
        return app(ExportadorXml::class)->resumo($this->mes, $this->filialId(), $this->opcoes());
    }

    /** @return Collection<int, ExportacaoXml> */
    #[Computed]
    public function anteriores(): Collection
    {
        return ExportacaoXml::query()->with(['criadoPor', 'filial'])->latest('id')->limit(12)->get();
    }

    public function gerar(ExportadorXml $exportador): mixed
    {
        Gate::authorize('xml.exportar');
        try {
            $e = $exportador->gerar($this->mes, $this->filialId(), $this->opcoes());
        } catch (RuntimeException $ex) {
            session()->flash('erro', $ex->getMessage());

            return null;
        }
        unset($this->anteriores);

        return redirect()->route('fiscal.xml.baixar', $e);
    }

    public function render(): View
    {
        return view('livewire.exportacao-xml.index')->layout('layouts.app', ['title' => 'Exportar XML']);
    }
}
