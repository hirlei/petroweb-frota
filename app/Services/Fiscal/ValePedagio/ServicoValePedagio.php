<?php

declare(strict_types=1);

namespace App\Services\Fiscal\ValePedagio;

use App\Domain\Fiscal\RegrasCiot;
use App\Models\FornecedorVpo;
use App\Models\Mdfe;
use App\Models\ValePedagio;
use App\Models\Veiculo;
use App\Models\Viagem;
use App\Services\Fiscal\Ciot\CiotException;
use Illuminate\Support\Facades\DB;

/**
 * Casos de uso do vale-pedágio na emissão do MDF-e (4020) e na consulta (4030).
 *
 * O vale é da VIAGEM, como o CIOT: rejeição ou reemissão do MDF-e reaproveita a
 * mesma compra. Um vale ativo por viagem, no veículo de tração, com a categoria
 * da composição inteira (soma dos eixos).
 *
 * Papel (RN-10): `fornecido` quando a transportadora contratou TAC (é
 * embarcadora equiparada — devedora do vale, multa de R$ 3.000/veículo); senão
 * `recebido` (o embarcador comprou e passou).
 */
final class ServicoValePedagio
{
    public function __construct(private readonly ValePedagioGateway $gateway)
    {
    }

    public function ativo(Viagem $viagem): ?ValePedagio
    {
        return ValePedagio::query()->ativos()->where('viagem_id', $viagem->id)
            ->with('fornecedorVpo')->latest('id')->first();
    }

    public function papel(Viagem $viagem): string
    {
        return $viagem->modalidadeCiot() === RegrasCiot::IPEF ? 'fornecido' : 'recebido';
    }

    public function eixos(Viagem $viagem): int
    {
        $viagem->loadMissing('veiculoTracao');
        $eixos = (int) ($viagem->veiculoTracao?->eixos ?? 0);
        foreach ((array) ($viagem->composicao_snapshot ?? []) as $placa) {
            $limpa = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $placa) ?? '');
            $eixos += (int) (Veiculo::query()->where('placa', $limpa)->value('eixos') ?? 0);
        }

        return max(1, $eixos);
    }

    public function cotar(Viagem $viagem): float
    {
        return $this->gateway->cotar($viagem, $this->eixos($viagem));
    }

    /** @throws CiotException */
    public function comprar(Viagem $viagem, ?int $fornecedorId, string $tipo = '01'): ValePedagio
    {
        if ($existente = $this->ativo($viagem)) {
            return $existente;
        }

        $fornecedor = $this->fornecedor($fornecedorId);
        $eixos = $this->eixos($viagem);
        $r = $this->gateway->comprar($viagem, $fornecedor, $eixos);
        if (! $r->ok) {
            throw new CiotException('A fornecedora recusou a compra do vale-pedágio: ' . $r->motivo);
        }

        return $this->criar($viagem, $fornecedor, [
            'origem' => 'compra',
            'idvpo' => $r->idvpo,
            'valor' => $r->valor,
            'tipo' => in_array($tipo, ValePedagio::TIPOS_VALIDOS, true) ? $tipo : '01',
            'protocolo' => $r->protocolo,
            'retorno' => $r->paraArray(),
            'data_aquisicao' => now(),
        ]);
    }

    /** Vale comprado fora do sistema (normalmente pelo embarcador). @throws CiotException */
    public function informar(Viagem $viagem, ?int $fornecedorId, string $idvpo, mixed $valor, string $tipo = '01'): ValePedagio
    {
        if ($existente = $this->ativo($viagem)) {
            return $existente;
        }

        $idvpo = trim($idvpo);
        if ($idvpo === '' || mb_strlen($idvpo) > 20) {
            throw new CiotException('Informe o número da compra do vale-pedágio (até 20 caracteres).');
        }
        if (! in_array($tipo, ValePedagio::TIPOS_VALIDOS, true)) {
            throw new CiotException('Tipo de vale inválido: só TAG ou leitura de placa.');
        }

        return $this->criar($viagem, $this->fornecedor($fornecedorId), [
            'origem' => 'informado',
            'idvpo' => $idvpo,
            'valor' => is_numeric($valor) ? round((float) $valor, 2) : null,
            'tipo' => $tipo,
            'data_aquisicao' => now(),
        ]);
    }

    /** @throws CiotException */
    public function dispensar(Viagem $viagem, string $motivo): ValePedagio
    {
        if ($existente = $this->ativo($viagem)) {
            return $existente;
        }
        if (! array_key_exists($motivo, ValePedagio::MOTIVOS_DISPENSA)) {
            throw new CiotException('Escolha o motivo da dispensa do vale-pedágio.');
        }

        return ValePedagio::create([
            'viagem_id' => $viagem->id,
            'filial_id' => $viagem->filial_id,
            'veiculo_id' => $viagem->veiculo_tracao_id,
            'papel' => $this->papel($viagem),
            'dispensado' => true,
            'motivo_dispensa' => $motivo,
            'origem' => 'informado',
            'situacao' => 'ativo',
        ]);
    }

    /** @throws CiotException */
    public function cancelar(ValePedagio $vale, string $motivo): ValePedagio
    {
        $vale->loadMissing('mdfe');
        if (! $vale->cancelavel()) {
            throw new CiotException('Vale já usado num MDF-e autorizado não pode ser cancelado aqui.');
        }

        if ($vale->origem === 'compra') {
            $r = $this->gateway->cancelar($vale, $motivo);
            if (! $r->ok) {
                throw new CiotException('A fornecedora recusou o cancelamento: ' . $r->motivo);
            }
        }

        $vale->update(['situacao' => 'cancelado', 'cancelado_em' => now(), 'motivo_cancelamento' => mb_substr(trim($motivo), 0, 255) ?: null]);

        return $vale;
    }

    /** Pendura os vales ativos da viagem no MDF-e que vai ser enviado. */
    public function vincular(Viagem $viagem, Mdfe $mdfe): void
    {
        DB::transaction(function () use ($viagem, $mdfe): void {
            ValePedagio::query()->ativos()->where('viagem_id', $viagem->id)
                ->where(fn ($q) => $q->whereNull('mdfe_id')->orWhere('mdfe_id', '<>', $mdfe->id))
                ->update(['mdfe_id' => $mdfe->id]);
        });
    }

    /** @param array<string,mixed> $campos */
    private function criar(Viagem $viagem, FornecedorVpo $fornecedor, array $campos): ValePedagio
    {
        $viagem->loadMissing('filial');
        $papel = $this->papel($viagem);
        $tomador = $papel === 'recebido' ? $viagem->ctes()->with('tomador')->first()?->tomador : null;
        $pagador = $papel === 'fornecido' ? $viagem->filial?->cnpj : $tomador?->documento;

        return ValePedagio::create($campos + [
            'viagem_id' => $viagem->id,
            'filial_id' => $viagem->filial_id,
            'veiculo_id' => $viagem->veiculo_tracao_id,
            'papel' => $papel,
            'fornecedor_vpo_id' => $fornecedor->id,
            'cnpj_forn' => $fornecedor->cnpj,
            'pagador_documento' => $pagador,
            'pagador_tipo' => $pagador !== null && strlen((string) $pagador) === 11 ? 'F' : 'J',
            'situacao' => 'ativo',
            'dispensado' => false,
        ]);
    }

    /** @throws CiotException */
    private function fornecedor(?int $id): FornecedorVpo
    {
        $f = $id !== null ? FornecedorVpo::query()->find($id) : null;
        if ($f === null) {
            throw new CiotException('Escolha a fornecedora do vale-pedágio.');
        }
        if (! $f->ativo) {
            throw new CiotException('Fornecedora inativa no catálogo da ANTT (rejeição 733).');
        }

        return $f;
    }
}
