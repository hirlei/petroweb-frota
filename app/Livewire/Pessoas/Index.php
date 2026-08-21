<?php

declare(strict_types=1);

namespace App\Livewire\Pessoas;

use App\Domain\Cadastro\RegrasPessoa;
use App\Models\Pessoa;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rotina 1010 — lista do cadastro unificado.
 *
 * Nenhuma query aqui filtra por `empresa_id`: quem faz isso é o EmpresaScope,
 * a partir do TenantContext. Se alguém precisar de um `where('empresa_id')`
 * nesta classe, o bug está no contexto, não aqui.
 */
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $busca = '';

    #[Url(as: 'papel', except: '')]
    public string $papel = '';

    #[Url(except: 'ativos')]
    public string $situacao = 'ativos';

    public ?int $selecionada = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Pessoa::class);
    }

    public function updated(string $campo): void
    {
        if (in_array($campo, ['busca', 'papel', 'situacao'], true)) {
            $this->resetPage();
            $this->selecionada = null;
        }
    }

    public function selecionar(int $id): void
    {
        $this->selecionada = $this->selecionada === $id ? null : $id;
    }

    public function filtrarPor(string $papel): void
    {
        $this->papel = $this->papel === $papel ? '' : $papel;
        $this->resetPage();
        $this->selecionada = null;
    }

    /**
     * Contagem por papel para os chips do topo. Uma query só, agrupada —
     * não sete `count()` separados.
     *
     * @return array<string,int>
     */
    #[Computed]
    public function totaisPorPapel(): array
    {
        $totais = Pessoa::query()
            ->join('pessoa_papeis', 'pessoa_papeis.pessoa_id', '=', 'pessoas.id')
            ->where('pessoa_papeis.ativo', true)
            ->selectRaw('pessoa_papeis.papel, count(distinct pessoas.id) as total')
            ->groupBy('pessoa_papeis.papel')
            ->pluck('total', 'papel')
            ->all();

        $saida = [];

        foreach (RegrasPessoa::PAPEIS as $papel) {
            $saida[$papel] = (int) ($totais[$papel] ?? 0);
        }

        return $saida;
    }

    #[Computed]
    public function total(): int
    {
        return Pessoa::query()->count();
    }

    #[Computed]
    public function pessoa(): ?Pessoa
    {
        if ($this->selecionada === null) {
            return null;
        }

        return Pessoa::with(['papeis', 'enderecoPrincipal.municipio', 'contatos'])
            ->find($this->selecionada);
    }

    /** @return LengthAwarePaginator<Pessoa> */
    #[Computed]
    public function pessoas(): LengthAwarePaginator
    {
        return Pessoa::query()
            ->with(['papeis', 'enderecoPrincipal.municipio'])
            ->when($this->papel !== '', fn (Builder $q) => $q->comPapel($this->papel))
            ->when($this->situacao === 'ativos', fn (Builder $q) => $q->where('ativo', true))
            ->when($this->situacao === 'inativos', fn (Builder $q) => $q->where('ativo', false))
            ->when($this->busca !== '', function (Builder $q): void {
                $termo = trim($this->busca);
                $somenteDigitos = preg_replace('/\D/', '', $termo) ?? '';

                $q->where(function (Builder $sub) use ($termo, $somenteDigitos): void {
                    $sub->where('razao_social', 'ilike', "%{$termo}%")
                        ->orWhere('nome_fantasia', 'ilike', "%{$termo}%");

                    if ($somenteDigitos !== '') {
                        $sub->orWhere('documento', 'like', "%{$somenteDigitos}%");
                    }
                });
            })
            ->orderBy('razao_social')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.pessoas.index')
            ->layout('layouts.app', ['title' => 'Pessoas']);
    }
}
