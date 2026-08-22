<?php

declare(strict_types=1);

namespace App\Livewire\Usuarios;

use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rotina 9020 — usuários da empresa.
 *
 * User não é model de negócio (não tem EmpresaScope), então o filtro por
 * empresa é explícito aqui, a partir do TenantContext. Usuário é convidado
 * pelo gestor — não existe auto-cadastro.
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

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updated(string $campo): void
    {
        if (in_array($campo, ['busca', 'papel', 'situacao'], true)) {
            $this->resetPage();
        }
    }

    private function baseQuery(): Builder
    {
        $empresaId = TenantContext::empresaId();

        return User::query()
            ->with('roles')
            ->when($empresaId !== null, fn (Builder $q) => $q->where('empresa_id', $empresaId));
    }

    #[Computed]
    public function total(): int
    {
        return $this->baseQuery()->count();
    }

    /** @return LengthAwarePaginator<User> */
    #[Computed]
    public function usuarios(): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->when($this->papel !== '', fn (Builder $q) => $q->whereHas('roles', fn (Builder $r) => $r->where('name', $this->papel)))
            ->when($this->situacao === 'ativos', fn (Builder $q) => $q->where('ativo', true))
            ->when($this->situacao === 'inativos', fn (Builder $q) => $q->where('ativo', false))
            ->when($this->busca !== '', function (Builder $q): void {
                $termo = trim($this->busca);
                $q->where(function (Builder $sub) use ($termo): void {
                    $sub->where('name', 'ilike', "%{$termo}%")
                        ->orWhere('email', 'ilike', "%{$termo}%");
                });
            })
            ->orderBy('name')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.usuarios.index')
            ->layout('layouts.app', ['title' => 'Usuários']);
    }
}
