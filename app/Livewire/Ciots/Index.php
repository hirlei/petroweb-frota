<?php

declare(strict_types=1);

namespace App\Livewire\Ciots;

use App\Domain\Fiscal\RegrasCiot;
use App\Models\Ciot;
use App\Models\Mdfe;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rotina 4050 — CIOT (mockup aprovado em 02/10/2026). O registro acontece na
 * emissão do MDF-e (4020); aqui é consulta, pagamento do saldo, cancelamento
 * e reenvio.
 */
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $busca = '';

    #[Url(as: 'situacao', except: '')]
    public string $situacao = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Ciot::class);
    }

    public function updated(string $campo): void
    {
        if (in_array($campo, ['busca', 'situacao'], true)) {
            $this->resetPage();
        }
    }

    /**
     * MDF-e em rascunho ou rejeitado cuja viagem ainda não tem CIOT válido —
     * o CIOT sai junto com a emissão de cada um.
     *
     * @return Collection<int, Mdfe>
     */
    #[Computed]
    public function aguardando(): Collection
    {
        return Mdfe::query()
            ->with(['viagem.veiculoTracao.proprietario', 'viagem.motorista.pessoa', 'viagem.municipioOrigem', 'viagem.municipioDestino', 'viagem.ciot'])
            ->whereIn('status', ['rascunho', 'rejeitado'])
            ->whereNotNull('viagem_id')
            ->latest('id')->limit(50)->get()
            ->filter(fn (Mdfe $m) => $m->viagem !== null
                && ! ($m->viagem->ciot?->valido() ?? false)
                && RegrasCiot::exige($m->viagem->modalidadeCiot()))
            ->values();
    }

    /** @return array<string,mixed> */
    #[Computed]
    public function resumo(): array
    {
        $mes = now()->startOfMonth();
        $aQuitar = Ciot::query()->where('status', 'registrado')->where('modalidade', RegrasCiot::IPEF)
            ->whereColumn('valor_pago', '<', 'valor_frete');

        $porModalidade = Ciot::query()->where('registrado_em', '>=', $mes)->whereIn('status', ['registrado', 'quitado'])
            ->selectRaw('modalidade, count(*) as n')->groupBy('modalidade')->pluck('n', 'modalidade');

        return [
            'aguardando' => $this->aguardando->count(),
            'registrados_mes' => (int) $porModalidade->sum(),
            'mes_antt' => (int) ($porModalidade['antt'] ?? 0),
            'mes_ipef' => (int) ($porModalidade['ipef'] ?? 0),
            'mes_informado' => (int) ($porModalidade['informado'] ?? 0),
            'saldo' => (float) (clone $aQuitar)->sum(DB::raw('valor_frete - valor_pago')),
            'saldo_qtd' => (clone $aQuitar)->count(),
            'vencendo' => (clone $aQuitar)->whereDate('prazo_quitacao', '<=', today()->addDays(5))->count(),
            'vencidos' => Ciot::query()->saldoVencido()->count(),
            'contagem' => [
                '' => Ciot::query()->count(),
                'recusados' => Ciot::query()->where('status', 'recusado')->count(),
                'a_quitar' => (clone $aQuitar)->count(),
                'quitados' => Ciot::query()->where('status', 'quitado')->count(),
                'cancelados' => Ciot::query()->where('status', 'cancelado')->count(),
            ],
        ];
    }

    /** @return LengthAwarePaginator<Ciot> */
    #[Computed]
    public function ciots(): LengthAwarePaginator
    {
        return Ciot::query()
            ->with(['viagem.veiculoTracao', 'viagem.municipioOrigem', 'viagem.municipioDestino', 'pagamentos'])
            ->when($this->situacao === 'recusados', fn (Builder $q) => $q->where('status', 'recusado'))
            ->when($this->situacao === 'a_quitar', fn (Builder $q) => $q->where('status', 'registrado')
                ->where('modalidade', RegrasCiot::IPEF)->whereColumn('valor_pago', '<', 'valor_frete'))
            ->when($this->situacao === 'quitados', fn (Builder $q) => $q->where('status', 'quitado'))
            ->when($this->situacao === 'cancelados', fn (Builder $q) => $q->where('status', 'cancelado'))
            ->when(trim($this->busca) !== '', function (Builder $q): void {
                $termo = trim($this->busca);
                $digitos = preg_replace('/\D/', '', $termo) ?? '';
                $placa = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $termo) ?? '');
                $q->where(function (Builder $s) use ($termo, $digitos, $placa): void {
                    $s->where('contratado_nome', 'ilike', "%{$termo}%")
                        ->orWhereHas('viagem', fn (Builder $v) => $v->where('numero', 'ilike', "%{$termo}%")
                            ->orWhereHas('veiculoTracao', fn (Builder $ve) => $ve->where('placa', 'like', "%{$placa}%")));
                    if (strlen($digitos) >= 3) {
                        $s->orWhere('numero', 'like', "%{$digitos}%")->orWhere('contratado_documento', 'like', "%{$digitos}%");
                    }
                });
            })
            ->orderByRaw("CASE WHEN status = 'recusado' THEN 0 ELSE 1 END")
            ->latest('id')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.ciots.index')->layout('layouts.app', ['title' => 'CIOT']);
    }
}
