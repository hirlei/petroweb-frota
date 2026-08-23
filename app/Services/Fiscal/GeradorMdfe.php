<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\Domain\Fiscal\Enums\CategoriaCombinacaoVeicular;
use App\Models\Mdfe;
use App\Models\Veiculo;
use App\Models\Viagem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Gera um MDF-e em RASCUNHO a partir de uma viagem.
 *
 * Traz veículo de tração, reboques (do snapshot), condutores (motoristas),
 * percurso (UFs) e a carga. `categoria_comb_veicular` é DERIVADA da soma dos
 * eixos da composição — nunca digitada. RN-03: não gera se o veículo já tem
 * MDF-e aberto.
 */
class GeradorMdfe
{
    public function aPartirDaViagem(Viagem $viagem): Mdfe
    {
        $viagem->loadMissing(['veiculoTracao', 'motorista.pessoa', 'motorista2.pessoa', 'municipioOrigem', 'municipioDestino']);

        // RN-03 — um MDF-e aberto por veículo.
        $aberto = Mdfe::query()->abertos()->where('veiculo_tracao_id', $viagem->veiculo_tracao_id)->exists();
        if ($aberto) {
            throw new RuntimeException('Este veículo já tem um MDF-e em aberto. Encerre-o antes de emitir outro (RN-03).');
        }

        return DB::transaction(function () use ($viagem): Mdfe {
            $snapshot = is_array($viagem->composicao_snapshot) ? $viagem->composicao_snapshot : [];
            $reboques = $this->reboques($snapshot);
            $eixos = (int) ($viagem->veiculoTracao?->eixos ?? 0) + array_sum(array_column($reboques, 'eixos'));

            $condutores = [];
            foreach ([$viagem->motorista, $viagem->motorista2] as $m) {
                if ($m?->pessoa !== null) {
                    $condutores[] = ['nome' => $m->pessoa->razao_social, 'cpf' => $m->pessoa->documento, 'motorista_id' => $m->id];
                }
            }

            $ufIni = $viagem->municipioOrigem?->uf;
            $ufFim = $viagem->municipioDestino?->uf;

            return Mdfe::create([
                'filial_id' => $viagem->filial_id,
                'viagem_id' => $viagem->id,
                'modelo' => '58',
                'serie' => 1,
                'modal' => '01',
                'tipo_emitente' => 1,
                'uf_inicio' => $ufIni,
                'uf_fim' => $ufFim,
                'percurso_ufs' => array_values(array_unique(array_filter([$ufIni, $ufFim]))),
                'municipio_carregamento' => $viagem->municipioOrigem
                    ? [['cod' => $viagem->municipioOrigem->codigo_ibge, 'nome' => $viagem->municipioOrigem->nome]]
                    : null,
                'veiculo_tracao_id' => $viagem->veiculo_tracao_id,
                'reboques' => $reboques ?: null,
                'condutores' => $condutores ?: null,
                'peso_bruto_total' => $viagem->peso_total ?? 0,
                'valor_carga_total' => $viagem->valor_carga ?? 0,
                'unidade_peso' => 1,
                'categoria_comb_veicular' => $eixos > 0 ? (int) CategoriaCombinacaoVeicular::paraEixos($eixos)->value : null,
                'status' => 'rascunho',
                'ambiente' => (int) config('fiscal.sefaz.ambiente', 2),
                'idempotency_key' => (string) Str::uuid(),
            ]);
        });
    }

    /**
     * @param  list<string>  $placas
     * @return list<array{placa:string,eixos:int}>
     */
    private function reboques(array $placas): array
    {
        $reboques = [];

        foreach ($placas as $placa) {
            $limpa = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $placa));
            $veiculo = Veiculo::query()->where('placa', $limpa)->first(['placa', 'eixos']);
            $reboques[] = ['placa' => $limpa, 'eixos' => (int) ($veiculo?->eixos ?? 0)];
        }

        return $reboques;
    }
}
