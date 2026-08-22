<?php

declare(strict_types=1);

namespace App\Livewire\Filiais;

use App\Models\Filial;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rotina 9010 — filiais (estabelecimentos emitentes).
 *
 * Cada filial é um emitente fiscal independente: CNPJ, IE, certificado, séries
 * e AMBIENTE SEFAZ próprios. Uma pode estar em homologação enquanto outra já
 * emite com valor fiscal. O EmpresaScope isola por empresa.
 */
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $busca = '';

    #[Url(except: 'ativas')]
    public string $situacao = 'ativas';

    public function mount(): void
    {
        $this->authorize('viewAny', Filial::class);
    }

    public function updated(string $campo): void
    {
        if (in_array($campo, ['busca', 'situacao'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function total(): int
    {
        return Filial::query()->count();
    }

    /** @return LengthAwarePaginator<Filial> */
    #[Computed]
    public function filiais(): LengthAwarePaginator
    {
        return Filial::query()
            ->with(['municipio', 'certificado'])
            ->when($this->situacao === 'ativas', fn (Builder $q) => $q->where('ativa', true))
            ->when($this->situacao === 'inativas', fn (Builder $q) => $q->where('ativa', false))
            ->when($this->busca !== '', function (Builder $q): void {
                $termo = trim($this->busca);
                $digitos = preg_replace('/\D/', '', $termo) ?? '';

                $q->where(function (Builder $sub) use ($termo, $digitos): void {
                    $sub->where('razao_social', 'ilike', "%{$termo}%")
                        ->orWhere('nome_fantasia', 'ilike', "%{$termo}%");

                    if ($digitos !== '') {
                        $sub->orWhere('cnpj', 'like', "%{$digitos}%");
                    }
                });
            })
            ->orderByDesc('matriz')
            ->orderBy('nome_fantasia')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.filiais.index')
            ->layout('layouts.app', ['title' => 'Empresa e filiais']);
    }
}
