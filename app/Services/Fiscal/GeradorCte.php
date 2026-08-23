<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\Domain\Operacao\CalculadoraFrete;
use App\Models\Cte;
use App\Models\OrdemColeta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gera um CT-e em RASCUNHO a partir de uma ordem de coleta.
 *
 * Copia participantes, trecho, carga e frete da OC — sem redigitar. Os
 * componentes vêm da tabela de frete (mesma CalculadoraFrete da OC); as NF-e dos
 * itens viram documentos do CT-e. O tomador da OC (papel) é traduzido para o
 * código do CT-e (RN-04). Nada é transmitido aqui — só o rascunho é criado.
 */
class GeradorCte
{
    private const TOMADOR_MAP = [
        'remetente' => 0, 'expedidor' => 1, 'recebedor' => 2, 'destinatario' => 3, 'outros' => 4,
    ];

    private const NOME_COMPONENTE = [
        'peso' => 'FRETE PESO', 'valor' => 'FRETE VALOR', 'gris' => 'GRIS',
        'advalorem' => 'AD VALOREM', 'pedagio' => 'PEDAGIO', 'tde' => 'TDE',
        'tda' => 'TDA', 'taxa_entrega' => 'TAXA ENTREGA', 'outros' => 'OUTROS',
    ];

    public function aPartirDaOrdem(OrdemColeta $ordem): Cte
    {
        $ordem->loadMissing(['itens', 'tabelaFrete.itens']);

        return DB::transaction(function () use ($ordem): Cte {
            $total = (float) ($ordem->valor_frete_calculado ?? 0);

            $cte = Cte::create([
                'filial_id' => $ordem->filial_id,
                'ordem_coleta_id' => $ordem->id,
                'modelo' => '57',
                'serie' => 1,
                'tipo_cte' => 0,
                'tipo_servico' => 0,
                'modal' => '01',
                'cfop' => '6353',
                'natureza_operacao' => 'Transporte de carga',
                'municipio_inicio_id' => $ordem->municipio_inicio_id,
                'municipio_fim_id' => $ordem->municipio_fim_id,
                'tomador_tipo' => self::TOMADOR_MAP[$ordem->tomador_tipo] ?? 0,
                'tomador_id' => $ordem->tomador_id,
                'remetente_id' => $ordem->remetente_id,
                'destinatario_id' => $ordem->destinatario_id,
                'expedidor_id' => $ordem->expedidor_id,
                'recebedor_id' => $ordem->recebedor_id,
                'produto_predominante' => $ordem->itens->first()?->descricao ?? 'Carga geral',
                'peso_bruto' => $ordem->peso_bruto,
                'peso_base_calculo' => $ordem->peso_bruto,
                'volumes' => $ordem->volumes,
                'valor_mercadoria' => $ordem->valor_mercadoria ?? 0,
                'valor_total_servico' => $total,
                'valor_receber' => $total,
                'icms_cst' => '00',
                'status' => 'rascunho',
                'ambiente' => (int) config('fiscal.sefaz.ambiente', 2),
                'idempotency_key' => (string) Str::uuid(),
            ]);

            foreach ($this->componentes($ordem, $total) as $ordemComp => $comp) {
                $cte->componentes()->create([
                    'nome' => $comp['nome'],
                    'valor' => $comp['valor'],
                    'ordem' => $ordemComp,
                ]);
            }

            foreach ($ordem->itens as $item) {
                if ($item->nfe_chave === null && $item->nfe_numero === null) {
                    continue;
                }
                $cte->documentos()->create([
                    'tipo' => 'nfe',
                    'chave' => $item->nfe_chave,
                    'numero' => $item->nfe_numero,
                    'serie' => $item->nfe_serie,
                    'valor' => $item->valor,
                    'peso' => $item->peso,
                ]);
            }

            return $cte->load(['componentes', 'documentos']);
        });
    }

    /** @return list<array{nome:string,valor:float}> */
    private function componentes(OrdemColeta $ordem, float $total): array
    {
        $tabela = $ordem->tabelaFrete;

        if ($tabela !== null && $tabela->itens->isNotEmpty()) {
            $resultado = CalculadoraFrete::calcular(
                $tabela->itens->map(fn ($i): array => [
                    'componente' => $i->componente, 'base_calculo' => $i->base_calculo, 'valor' => $i->valor,
                    'faixa_de' => $i->faixa_de, 'faixa_ate' => $i->faixa_ate, 'minimo' => $i->minimo, 'maximo' => $i->maximo,
                ])->all(),
                [
                    'peso_kg' => (float) ($ordem->peso_bruto ?? 0),
                    'valor_mercadoria' => (float) ($ordem->valor_mercadoria ?? 0),
                    'volumes' => (float) ($ordem->volumes ?? 0),
                ],
            );

            if ($resultado['componentes'] !== []) {
                return array_map(fn (array $c): array => [
                    'nome' => self::NOME_COMPONENTE[$c['componente']] ?? 'OUTROS',
                    'valor' => $c['valor'],
                ], $resultado['componentes']);
            }
        }

        // Sem tabela: um componente único com o total.
        return [['nome' => 'FRETE PESO', 'valor' => $total]];
    }
}
