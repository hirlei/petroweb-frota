<?php

declare(strict_types=1);

namespace App\Livewire\Abastecimentos;

use App\Models\Abastecimento;
use App\Models\Motorista;
use App\Models\Pessoa;
use App\Models\Veiculo;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Rotina 2060 — lançamento de abastecimento.
 *
 * Ao salvar, calcula km rodado, média (km/l) e desvio contra a referência do
 * veículo — e acende o alerta quando o desvio passa do limite. A média só é
 * calculada entre dois tanques cheios (é o único intervalo confiável).
 */
class Formulario extends Component
{
    use AuthorizesRequests;

    public ?Abastecimento $abastecimento = null;

    public ?int $veiculo_id = null;
    public ?int $motorista_id = null;
    public ?int $posto_id = null;
    public string $data_hora = '';
    public string $combustivel = 'Diesel S10';
    public string $litros = '';
    public string $valor_litro = '';
    public string $odometro = '';
    public bool $tanque_cheio = true;
    public string $nota_fiscal = '';

    public function mount(?Abastecimento $abastecimento = null): void
    {
        if ($abastecimento?->exists) {
            $this->authorize('update', $abastecimento);
            $this->abastecimento = $abastecimento;
            $this->veiculo_id = $abastecimento->veiculo_id;
            $this->motorista_id = $abastecimento->motorista_id;
            $this->posto_id = $abastecimento->posto_id;
            $this->data_hora = $abastecimento->data_hora?->format('Y-m-d\TH:i') ?? '';
            $this->combustivel = (string) $abastecimento->combustivel;
            $this->litros = (string) $abastecimento->litros;
            $this->valor_litro = (string) $abastecimento->valor_litro;
            $this->odometro = (string) $abastecimento->odometro;
            $this->tanque_cheio = (bool) $abastecimento->tanque_cheio;
            $this->nota_fiscal = (string) ($abastecimento->nota_fiscal ?? '');

            return;
        }

        $this->authorize('create', Abastecimento::class);
        $this->data_hora = now()->format('Y-m-d\TH:i');
    }

    #[Computed]
    public function valorTotal(): float
    {
        return round(($this->litros !== '' ? (float) $this->litros : 0) * ($this->valor_litro !== '' ? (float) $this->valor_litro : 0), 2);
    }

    #[Computed]
    public function veiculos()
    {
        return Veiculo::query()->ativos()->orderBy('placa')->get(['id', 'placa', 'media_referencia_kml']);
    }

    #[Computed]
    public function motoristas()
    {
        return Motorista::query()->with('pessoa')->where('status', 'ativo')->get()
            ->sortBy(fn (Motorista $m) => $m->pessoa?->razao_social)->values();
    }

    #[Computed]
    public function postos()
    {
        return Pessoa::query()->comPapel('posto')->orderBy('razao_social')->get(['id', 'razao_social']);
    }

    protected function rules(): array
    {
        return [
            'veiculo_id' => ['required', 'integer', 'exists:veiculos,id'],
            'motorista_id' => ['nullable', 'integer', 'exists:motoristas,id'],
            'posto_id' => ['nullable', 'integer', 'exists:pessoas,id'],
            'data_hora' => ['required', 'date'],
            'combustivel' => ['required', 'string', 'max:20'],
            'litros' => ['required', 'numeric', 'gt:0'],
            'valor_litro' => ['required', 'numeric', 'gt:0'],
            'odometro' => ['required', 'numeric', 'min:0'],
            'nota_fiscal' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function salvar()
    {
        $this->validate();

        $veiculo = Veiculo::findOrFail($this->veiculo_id);

        // Abastecimento de tanque cheio anterior, do mesmo veículo, antes desta data.
        $anterior = Abastecimento::query()
            ->where('veiculo_id', $this->veiculo_id)
            ->where('tanque_cheio', true)
            ->where('data_hora', '<', $this->data_hora)
            ->when($this->abastecimento?->id, fn ($q) => $q->where('id', '!=', $this->abastecimento->id))
            ->orderByDesc('data_hora')
            ->first();

        $kmPercorrido = null;
        $media = null;
        $desvio = null;
        $alerta = false;

        if ($anterior !== null) {
            $kmPercorrido = (float) $this->odometro - (float) $anterior->odometro;

            if ($this->tanque_cheio && $kmPercorrido > 0 && (float) $this->litros > 0) {
                $media = round($kmPercorrido / (float) $this->litros, 3);

                $referencia = $veiculo->media_referencia_kml !== null ? (float) $veiculo->media_referencia_kml : null;

                if ($referencia !== null && $referencia > 0) {
                    $desvio = round((($media - $referencia) / $referencia) * 100, 2);
                    $alerta = abs($desvio) >= Abastecimento::LIMITE_DESVIO_PCT;
                }
            }
        }

        $dados = [
            'filial_id' => $veiculo->filial_id ?? TenantContext::filialId(),
            'veiculo_id' => $this->veiculo_id,
            'motorista_id' => $this->motorista_id,
            'posto_id' => $this->posto_id,
            'data_hora' => $this->data_hora,
            'combustivel' => trim($this->combustivel),
            'litros' => (float) $this->litros,
            'valor_litro' => (float) $this->valor_litro,
            'valor_total' => $this->valorTotal,
            'odometro' => (float) $this->odometro,
            'tanque_cheio' => $this->tanque_cheio,
            'km_percorrido' => $kmPercorrido,
            'media_calculada' => $media,
            'desvio_percentual' => $desvio,
            'alerta' => $alerta,
            'origem' => $this->abastecimento?->origem ?? 'manual',
            'nota_fiscal' => trim($this->nota_fiscal) ?: null,
        ];

        DB::transaction(function () use ($dados, $veiculo): void {
            $abastecimento = $this->abastecimento?->exists
                ? tap($this->abastecimento)->update($dados)
                : Abastecimento::create($dados);

            // Mantém o odômetro do veículo em dia, se este for o mais recente.
            if ((float) $this->odometro > (float) $veiculo->odometro_atual) {
                $veiculo->update(['odometro_atual' => (float) $this->odometro]);
            }

            $this->abastecimento = $abastecimento->fresh();
        });

        session()->flash('sucesso', 'Abastecimento lançado.');

        return $this->redirect(route('abastecimentos.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.abastecimentos.formulario')
            ->layout('layouts.app', ['title' => 'Novo abastecimento']);
    }
}
