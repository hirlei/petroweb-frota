<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\Domain\Fiscal\RegrasCiot;
use App\Models\Ciot;
use App\Models\Mdfe;
use App\Models\ValePedagio;
use App\Services\Fiscal\Ciot\CiotException;
use App\Services\Fiscal\Ciot\ServicoCiot;
use App\Services\Fiscal\ValePedagio\ServicoValePedagio;
use DateTimeImmutable;
use Throwable;

/**
 * Um clique na tela do MDF-e (4020) faz as três coisas, nesta ordem:
 *
 *   1. registra o CIOT da viagem (e paga o adiantamento ao TAC)
 *   2. compra, informa ou dispensa o vale-pedágio
 *   3. envia o MDF-e à SEFAZ levando os dois números
 *
 * A ordem não muda: o manifesto carrega o CIOT e o vale, então eles existem
 * antes. Se o CIOT falha onde ele já é exigido, o MDF-e nem sai (evita a
 * rejeição 684). Se o vale falha, também para. CIOT e vale ficam presos à
 * VIAGEM — se a SEFAZ rejeitar, o reenvio usa os mesmos, sem registrar nem
 * pagar de novo.
 */
final class EmissaoMdfeCompleta
{
    public function __construct(
        private readonly ServicoCiot $ciots,
        private readonly ServicoValePedagio $vales,
        private readonly EmissorFiscal $emissor,
    ) {
    }

    /**
     * @param  array<string,mixed>  $dadosCiot  frete, percentual, prazo, forma, chave | numero, responsavel
     * @param  array<string,mixed>  $dadosVale  modo (comprar|informar|dispensar), fornecedor_id, idvpo, valor, tipo, motivo
     * @return array{autorizado: bool, etapas: list<array{chave:string,estado:string,titulo:string,detalhe:string}>}
     */
    public function emitir(Mdfe $mdfe, array $dadosCiot, array $dadosVale): array
    {
        $viagem = $mdfe->viagem;
        $etapas = [];

        if ($viagem === null) {
            return ['autorizado' => false, 'etapas' => [$this->etapa('mdfe', 'erro', 'MDF-e sem viagem', 'Gere o MDF-e a partir de uma viagem.')]];
        }

        // RN-03: reemitir um rejeitado não pode deixar o veículo com dois MDF-e abertos.
        $outroAberto = Mdfe::query()->abertos()
            ->where('veiculo_tracao_id', $mdfe->veiculo_tracao_id)->whereKeyNot($mdfe->id)->first();
        if ($outroAberto !== null) {
            return ['autorizado' => false, 'etapas' => [$this->etapa('mdfe', 'erro', 'Veículo com outro MDF-e aberto',
                'O MDF-e ' . ($outroAberto->numero ?? 'rascunho') . ' deste veículo está aberto. Use-o, ou cancele/encerre antes de reemitir este.')]];
        }

        // 1 — CIOT
        $modalidade = $viagem->modalidadeCiot();
        $ciot = null;
        if (! RegrasCiot::exige($modalidade)) {
            $etapas[] = $this->etapa('ciot', 'ok', 'CIOT não se aplica', 'Carga própria em veículo próprio.');
        } else {
            try {
                $ciot = $modalidade === RegrasCiot::INFORMADO
                    ? $this->ciots->informar($viagem, (string) ($dadosCiot['numero'] ?? ''), (string) ($dadosCiot['responsavel'] ?? ''))
                    : $this->ciots->registrar($viagem, $dadosCiot);
                $etapas[] = $this->etapaCiot($ciot);
            } catch (CiotException $e) {
                $etapas[] = $this->etapa('ciot', 'erro', 'CIOT não registrado', $e->getMessage());
            } catch (Throwable $e) {
                report($e);
                $etapas[] = $this->etapa('ciot', 'erro', 'CIOT não registrado', 'Falha de comunicação. Tente de novo em instantes.');
            }

            $temCiot = $ciot !== null && $ciot->valido();
            $trava = RegrasCiot::trava(
                $modalidade, $temCiot, (int) ($mdfe->ambiente ?: config('fiscal.sefaz.ambiente', 2)),
                new DateTimeImmutable('today'), new DateTimeImmutable((string) config('ciot.obrigatorio_desde', '2026-11-23')),
            );
            if ($trava === 'bloqueia') {
                $etapas[] = $this->etapa('vale', 'pulado', 'Vale-pedágio não processado', 'Parou no CIOT.');
                $etapas[] = $this->etapa('mdfe', 'pulado', 'MDF-e não enviado', 'Sem CIOT a SEFAZ rejeita o manifesto (684). Corrija e emita de novo.');

                return ['autorizado' => false, 'etapas' => $etapas];
            }
            if ($trava === 'avisa') {
                $etapas[] = $this->etapa('ciot', 'aviso', 'Seguindo sem CIOT', 'Ainda aceito em produção até ' . (new DateTimeImmutable((string) config('ciot.obrigatorio_desde')))->format('d/m/Y') . '.');
            }
        }

        // 2 — Vale-pedágio
        try {
            $vale = $this->vales->ativo($viagem) ?? match ((string) ($dadosVale['modo'] ?? 'comprar')) {
                'informar' => $this->vales->informar($viagem, $this->int($dadosVale['fornecedor_id'] ?? null), (string) ($dadosVale['idvpo'] ?? ''), $dadosVale['valor'] ?? null, (string) ($dadosVale['tipo'] ?? '01')),
                'dispensar' => $this->vales->dispensar($viagem, (string) ($dadosVale['motivo'] ?? '')),
                default => $this->vales->comprar($viagem, $this->int($dadosVale['fornecedor_id'] ?? null), (string) ($dadosVale['tipo'] ?? '01')),
            };
            $etapas[] = $this->etapaVale($vale);
        } catch (CiotException $e) {
            $etapas[] = $this->etapa('vale', 'erro', 'Vale-pedágio não registrado', $e->getMessage());
            $etapas[] = $this->etapa('mdfe', 'pulado', 'MDF-e não enviado', 'Parou no vale-pedágio. O CIOT, se registrado, fica guardado.');

            return ['autorizado' => false, 'etapas' => $etapas];
        } catch (Throwable $e) {
            report($e);
            $etapas[] = $this->etapa('vale', 'erro', 'Vale-pedágio não registrado', 'Falha de comunicação. Tente de novo em instantes.');
            $etapas[] = $this->etapa('mdfe', 'pulado', 'MDF-e não enviado', 'Parou no vale-pedágio.');

            return ['autorizado' => false, 'etapas' => $etapas];
        }

        // 3 — Pendura tudo no MDF-e e envia
        $valido = $ciot !== null && $ciot->valido();
        $mdfe->update([
            'ciot' => $valido ? $ciot->numero : null,
            'ciot_cpf_cnpj' => $valido ? $ciot->responsavel_documento : null,
        ]);
        if ($valido) {
            $ciot->update(['mdfe_id' => $mdfe->id]);
        }
        $this->vales->vincular($viagem, $mdfe);

        $r = $this->emissor->emitirMdfe($mdfe->fresh());
        $etapas[] = $r->autorizado
            ? $this->etapa('mdfe', 'ok', 'MDF-e autorizado', "Protocolo {$r->protocolo}")
            : $this->etapa('mdfe', 'erro', 'MDF-e rejeitado', "{$r->codigo} · {$r->motivo}. CIOT e vale ficam guardados para o reenvio.");

        return ['autorizado' => $r->autorizado, 'etapas' => $etapas];
    }

    /** @return array{chave:string,estado:string,titulo:string,detalhe:string} */
    private function etapaCiot(Ciot $ciot): array
    {
        if (! $ciot->valido()) {
            return $this->etapa('ciot', 'erro', 'CIOT recusado', (string) $ciot->motivo);
        }

        $detalhe = $ciot->numeroFormatado();
        if ($ciot->temPagamento() && (float) $ciot->valor_adiantamento > 0) {
            $detalhe .= $ciot->adiantamentoPendente()
                ? ' · adiantamento NÃO pago — reenvie no 4050'
                : ' · adiantamento de R$ ' . number_format((float) $ciot->valor_adiantamento, 2, ',', '.') . ' pago';
        }

        return $this->etapa('ciot', $ciot->adiantamentoPendente() ? 'aviso' : 'ok',
            $ciot->registrado_em !== null && $ciot->registrado_em->gt(now()->subMinutes(2)) ? 'CIOT registrado' : 'CIOT da viagem', $detalhe);
    }

    /** @return array{chave:string,estado:string,titulo:string,detalhe:string} */
    private function etapaVale(ValePedagio $vale): array
    {
        if ($vale->dispensado) {
            return $this->etapa('vale', 'ok', 'Vale-pedágio dispensado', ValePedagio::MOTIVOS_DISPENSA[$vale->motivo_dispensa] ?? (string) $vale->motivo_dispensa);
        }

        $titulo = $vale->wasRecentlyCreated
            ? ($vale->origem === 'compra' ? 'Vale-pedágio comprado' : 'Vale-pedágio informado')
            : 'Vale-pedágio da viagem';

        return $this->etapa('vale', 'ok', $titulo, trim(($vale->fornecedorVpo?->razao_social ?? '') . ' · compra ' . $vale->idvpo
            . ($vale->valor !== null ? ' · R$ ' . number_format((float) $vale->valor, 2, ',', '.') : ''), ' ·'));
    }

    /** @return array{chave:string,estado:string,titulo:string,detalhe:string} */
    private function etapa(string $chave, string $estado, string $titulo, string $detalhe): array
    {
        return compact('chave', 'estado', 'titulo', 'detalhe');
    }

    private function int(mixed $v): ?int
    {
        return is_numeric($v) ? (int) $v : null;
    }
}
