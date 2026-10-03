<?php

declare(strict_types=1);

namespace App\Services\Fiscal\Ciot;

use App\Domain\Fiscal\RegrasCiot;
use App\Models\Ciot;
use App\Models\CiotPagamento;
use App\Models\Pessoa;
use App\Models\Viagem;
use DateTimeImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Casos de uso do CIOT (rotinas 4020 e 4050). Toda mudança de status e de valor
 * de CIOT e de pagamento ao TAC passa por aqui — as telas só chamam.
 *
 *  - O CIOT é da VIAGEM: no máximo um não cancelado por viagem (índice parcial).
 *    Registrar de novo uma viagem que já tem CIOT válido devolve o mesmo — é o
 *    que garante que rejeição/reemissão do MDF-e não registra nem paga duas vezes.
 *  - Recusado (piso mínimo, dado faltando) fica na mesma linha; reenviar tenta de
 *    novo, com os dados corrigidos se vierem.
 *  - Adiantamento sai logo depois do registro; o saldo é pago depois da entrega.
 *  - Cancelar só sem pagamento confirmado e com o MDF-e fora do ar.
 */
final class ServicoCiot
{
    public function __construct(private readonly CiotGateway $gateway)
    {
    }

    /**
     * O que a tela de emissão já sabe da viagem: modalidade, partes, sugestões.
     *
     * @return array<string,mixed>
     */
    public function sugestao(Viagem $viagem): array
    {
        $viagem->loadMissing(['filial', 'veiculoTracao.proprietario', 'motorista.pessoa', 'motorista.contratoAgregacao']);
        $modalidade = $viagem->modalidadeCiot();
        $contratado = $this->contratado($viagem, $modalidade);
        $receita = (float) $viagem->ctes()->sum('valor_total_servico');

        $frete = match ($modalidade) {
            RegrasCiot::ANTT => $receita,
            RegrasCiot::IPEF => (float) ($viagem->motorista?->contratoAgregacao?->valor_base ?? 0),
            default => 0.0,
        };

        // Base do prazo: a saída, mas nunca no passado — saída prevista vencida
        // tornaria impossível registrar (prazo máximo já estourado).
        $base = $viagem->saida_prevista !== null && $viagem->saida_prevista->isFuture() ? $viagem->saida_prevista : Carbon::today();
        $conta = (array) ($viagem->motorista?->conta_pagamento ?? []);

        return [
            'modalidade' => $modalidade,
            'contratado' => $contratado,
            'contratante_documento' => $viagem->filial?->cnpj,
            'contratante_nome' => $viagem->filial?->razao_social ?? $viagem->filial?->nome ?? null,
            'contratante_rntrc' => $viagem->filial?->rntrc,
            'frete' => round($frete, 2),
            'receita' => round($receita, 2),
            'prazo' => RegrasCiot::prazoMaximo(new DateTimeImmutable($base->toDateString()))->format('Y-m-d'),
            'prazo_maximo' => RegrasCiot::prazoMaximo(new DateTimeImmutable($base->toDateString()))->format('Y-m-d'),
            'forma' => in_array($conta['forma'] ?? null, Ciot::FORMAS, true) ? $conta['forma'] : 'pix',
            'chave' => $conta['chave'] ?? ($contratado['documento'] ?? null),
            'responsavel_informado' => $modalidade === RegrasCiot::INFORMADO ? ($contratado['documento'] ?? null) : null,
        ];
    }

    /**
     * Registra (ou reaproveita) o CIOT da viagem e, no IPEF, paga o adiantamento.
     *
     * @param  array{frete?: float|string|null, percentual?: float|string|null, prazo?: string|null, forma?: string|null, chave?: string|null}  $dados
     *
     * @throws CiotException quando os dados não fecham — nada é enviado
     */
    public function registrar(Viagem $viagem, array $dados = []): Ciot
    {
        $atual = $viagem->ciot()->first();
        if ($atual !== null && $atual->valido()) {
            if ($atual->adiantamentoPendente()) {
                $this->pagarAdiantamento($atual);
            }

            return $atual->fresh('pagamentos');
        }

        $modalidade = $viagem->modalidadeCiot();
        if (! RegrasCiot::registraPeloSistema($modalidade)) {
            throw new CiotException($modalidade === RegrasCiot::INFORMADO
                ? 'O caminhão é de outra transportadora: informe o número do CIOT que ela registrou.'
                : 'Esta viagem não precisa de CIOT.');
        }

        $campos = $this->camposValidados($viagem, $modalidade, $dados);
        $ciot = $atual ?? new Ciot(['viagem_id' => $viagem->id, 'filial_id' => $viagem->filial_id, 'criado_por' => Auth::id()]);
        // Grava antes de enviar (o banco não aceita "registrado" sem número): se a
        // resposta nunca chegar, a linha fica recusada e o reenvio resolve.
        $ciot->fill($campos + [
            'modalidade' => $modalidade,
            'instituicao' => $this->gateway->instituicao($modalidade),
            'status' => 'recusado',
            'motivo' => 'Sem resposta da instituição — reenvie.',
        ]);
        $this->salvar($ciot);

        $this->enviar($ciot);

        if ($ciot->valido()) {
            $this->lembrarConta($viagem, $campos);
            if ($ciot->adiantamentoPendente()) {
                $this->pagarAdiantamento($ciot);
            }
        }

        return $ciot->fresh('pagamentos');
    }

    /**
     * CIOT registrado por outra transportadora (veículo de terceiro ETC/CTC):
     * só se guarda o número e quem registrou.
     *
     * @throws CiotException
     */
    public function informar(Viagem $viagem, string $numero, ?string $responsavelDocumento): Ciot
    {
        $numero = RegrasCiot::limpar($numero);

        // Reemissão: a tela já mostra o CIOT guardado e não manda o número de novo.
        $atual = $viagem->ciot()->first();
        if ($atual !== null && $atual->valido() && ($numero === '' || $numero === $atual->numero || $atual->modalidade !== RegrasCiot::INFORMADO)) {
            return $atual;
        }

        if (! RegrasCiot::numeroValido($numero)) {
            throw new CiotException('O CIOT tem 12 dígitos. Confira o número que a transportadora passou.');
        }
        $doc = preg_replace('/\D/', '', (string) $responsavelDocumento) ?? '';
        if (! in_array(strlen($doc), [11, 14], true)) {
            throw new CiotException('Informe o CPF ou CNPJ de quem registrou o CIOT.');
        }

        $viagem->loadMissing('veiculoTracao.proprietario');
        $dono = $viagem->veiculoTracao?->proprietario;

        $ciot = $atual ?? new Ciot(['viagem_id' => $viagem->id, 'filial_id' => $viagem->filial_id, 'criado_por' => Auth::id()]);
        $ciot->fill([
            'modalidade' => RegrasCiot::INFORMADO,
            'numero' => $numero,
            'responsavel_documento' => $doc,
            'contratado_id' => $dono?->id,
            'contratado_nome' => $dono?->razao_social,
            'contratado_documento' => $dono?->documento,
            'contratado_rntrc' => $viagem->veiculoTracao?->proprietario_rntrc ?: $dono?->rntrc,
            'status' => 'registrado',
            'motivo' => null,
            'registrado_em' => now(),
            'instituicao' => 'Informado pela transportadora',
        ]);
        $this->salvar($ciot);

        return $ciot;
    }

    /** Tenta de novo o que ficou pela metade: registro recusado ou adiantamento pendente. */
    public function reenviar(Ciot $ciot): Ciot
    {
        if ($ciot->status === 'recusado') {
            $this->enviar($ciot);
        }
        $ciot->refresh();
        if ($ciot->valido() && $ciot->adiantamentoPendente()) {
            $this->pagarAdiantamento($ciot);
        }

        return $ciot->fresh('pagamentos');
    }

    /** @throws CiotException */
    public function pagarSaldo(Ciot $ciot, string $forma, ?Carbon $data = null): CiotPagamento
    {
        if (! in_array($forma, Ciot::FORMAS, true)) {
            throw new CiotException('Forma de pagamento inválida.');
        }

        $resultado = DB::transaction(function () use ($ciot, $forma, $data): array {
            // Trava a linha: duas abas pagando ao mesmo tempo não pagam duas vezes.
            $c = Ciot::query()->with('pagamentos')->lockForUpdate()->findOrFail($ciot->id);

            if (! $c->temPagamento()) {
                throw new CiotException('Só há pagamento ao contratado em CIOT de TAC.');
            }
            if ($c->status !== 'registrado') {
                throw new CiotException('Este CIOT não está em aberto.');
            }
            $saldo = $c->saldoAPagar();
            if ($saldo <= 0) {
                throw new CiotException('Não há saldo a pagar.');
            }

            $r = $this->gateway->pagar($c, 'saldo', $saldo, $forma);

            return [$this->gravarPagamento($c, 'saldo', $saldo, $forma, $r, $data), $r];
        });

        [$pagamento, $r] = $resultado;
        if (! $r->ok) {
            throw new CiotException('A instituição recusou o pagamento: ' . $r->motivo);
        }

        return $pagamento;
    }

    /** @throws CiotException */
    public function cancelar(Ciot $ciot, string $motivo): Ciot
    {
        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 10) {
            throw new CiotException('Explique o motivo do cancelamento (mínimo de 10 caracteres).');
        }
        if (! $ciot->cancelavel()) {
            throw new CiotException('Só dá para cancelar com o MDF-e cancelado ou não emitido e sem pagamento ao TAC.');
        }

        if ($ciot->status === 'registrado' && RegrasCiot::registraPeloSistema((string) $ciot->modalidade)) {
            $r = $this->gateway->cancelar($ciot, $motivo);
            if (! $r->ok) {
                throw new CiotException('A instituição recusou o cancelamento: ' . $r->motivo);
            }
        }

        $ciot->update([
            'status' => 'cancelado',
            'cancelado_em' => now(),
            'cancelado_por' => Auth::id(),
            'motivo_cancelamento' => $motivo,
        ]);

        return $ciot;
    }

    /* ── internos ── */

    private function enviar(Ciot $ciot): void
    {
        $r = $this->gateway->registrar($ciot);

        $ciot->forceFill([
            'tentativas' => (int) $ciot->tentativas + 1,
            'retorno' => $r->paraArray(),
        ]);

        if ($r->ok && RegrasCiot::numeroValido((string) $r->numero)) {
            $ciot->forceFill([
                'status' => 'registrado',
                'numero' => $r->numero,
                'protocolo' => $r->protocolo,
                'motivo' => null,
                'registrado_em' => now(),
            ]);
        } else {
            $motivo = $r->ok ? 'A instituição respondeu sem um número de CIOT válido — reenvie.' : $r->motivo;
            $ciot->forceFill(['status' => 'recusado', 'motivo' => mb_substr($motivo, 0, 255)]);
        }

        $ciot->save();
    }

    private function pagarAdiantamento(Ciot $ciot): void
    {
        DB::transaction(function () use ($ciot): void {
            $c = Ciot::query()->with('pagamentos')->lockForUpdate()->find($ciot->id);
            if ($c === null || ! $c->adiantamentoPendente()) {
                return;
            }

            $valor = min((float) $c->valor_adiantamento, $c->saldoAPagar());
            if ($valor <= 0) {
                return;
            }

            $r = $this->gateway->pagar($c, 'adiantamento', $valor, (string) $c->forma_pagamento);
            $this->gravarPagamento($c, 'adiantamento', $valor, (string) $c->forma_pagamento, $r);
        });
    }

    /** @throws CiotException */
    private function salvar(Ciot $ciot): void
    {
        try {
            $ciot->save();
        } catch (UniqueConstraintViolationException) {
            throw new CiotException($ciot->numero !== null && $ciot->modalidade === RegrasCiot::INFORMADO
                ? 'Este número de CIOT já está em outra viagem. Confira com a transportadora.'
                : 'Outra pessoa está registrando o CIOT desta viagem agora. Atualize a tela.');
        }
    }

    private function gravarPagamento(Ciot $ciot, string $tipo, float $valor, string $forma, RespostaCiot $r, ?Carbon $data = null): CiotPagamento
    {
        return DB::transaction(function () use ($ciot, $tipo, $valor, $forma, $r, $data): CiotPagamento {
            $pagamento = CiotPagamento::create([
                'empresa_id' => $ciot->empresa_id,
                'ciot_id' => $ciot->id,
                'tipo' => $tipo,
                'valor' => $valor,
                'forma' => $forma,
                'data' => ($data ?? Carbon::today())->toDateString(),
                'status' => $r->ok ? 'confirmado' : 'recusado',
                'protocolo' => $r->protocolo,
                'motivo' => $r->ok ? null : mb_substr($r->motivo, 0, 255),
                'retorno' => $r->paraArray(),
                'criado_por' => Auth::id(),
            ]);

            $pago = round((float) $ciot->pagamentos()->where('status', 'confirmado')->sum('valor'), 2);
            $quitado = $pago >= round((float) $ciot->valor_frete, 2);
            $ciot->update([
                'valor_pago' => $pago,
                'status' => $quitado ? 'quitado' : $ciot->status,
                'quitado_em' => $quitado ? now() : null,
            ]);

            return $pagamento;
        });
    }

    /**
     * @param  array<string,mixed>  $dados
     * @return array<string,mixed>
     *
     * @throws CiotException
     */
    private function camposValidados(Viagem $viagem, string $modalidade, array $dados): array
    {
        $sug = $this->sugestao($viagem);
        $contratado = $sug['contratado'];

        if (empty($sug['contratante_documento'])) {
            throw new CiotException('A filial da viagem está sem CNPJ — complete em Empresa e filiais (9010).');
        }

        $campos = [
            'contratante_documento' => $sug['contratante_documento'],
            'responsavel_documento' => $sug['contratante_documento'],
            'contratado_id' => $contratado['id'] ?? null,
            'contratado_nome' => $contratado['nome'] ?? null,
            'contratado_documento' => $contratado['documento'] ?? null,
            'contratado_rntrc' => $contratado['rntrc'] ?? null,
        ];

        if ($modalidade === RegrasCiot::ANTT) {
            $frete = $this->numero($dados['frete'] ?? null) ?? (float) $sug['frete'];

            return $campos + [
                'valor_frete' => round($frete, 2),
                'percentual_adiantamento' => 0, 'valor_adiantamento' => 0, 'valor_saldo' => 0,
                'prazo_quitacao' => null, 'forma_pagamento' => null, 'chave_pagamento' => null,
            ];
        }

        // IPEF — contrato com TAC.
        if (($contratado['id'] ?? null) === null) {
            throw new CiotException('Não achei o TAC desta viagem: confira o motorista ou o proprietário do veículo.');
        }
        if (empty($contratado['rntrc'])) {
            throw new CiotException("{$contratado['nome']} está sem RNTRC no cadastro — sem ele a instituição recusa o CIOT.");
        }

        $frete = $this->numero($dados['frete'] ?? null);
        if ($frete === null || $frete <= 0) {
            throw new CiotException('Informe o frete contratado com o TAC.');
        }
        $pct = $this->numero($dados['percentual'] ?? null);
        if ($pct === null) {
            throw new CiotException('Informe o adiantamento (0% para sem adiantamento).');
        }

        try {
            $div = RegrasCiot::dividir($frete, $pct);
        } catch (InvalidArgumentException $e) {
            throw new CiotException($e->getMessage());
        }

        $forma = (string) ($dados['forma'] ?? 'pix');
        if (! in_array($forma, Ciot::FORMAS, true)) {
            throw new CiotException('Escolha como o TAC vai receber: Pix, transferência ou cartão frete.');
        }
        $chave = trim((string) ($dados['chave'] ?? ''));
        if ($chave === '') {
            throw new CiotException('Informe a chave Pix, a conta ou o cartão frete do TAC.');
        }

        $prazo = trim((string) ($dados['prazo'] ?? '')) ?: $sug['prazo'];
        try {
            $prazoData = new DateTimeImmutable($prazo);
        } catch (\Exception) {
            throw new CiotException('Data de quitação inválida.');
        }
        if ($prazoData->format('Y-m-d') > $sug['prazo_maximo']) {
            throw new CiotException('O saldo tem de ser quitado em até 30 dias úteis: até ' . (new DateTimeImmutable($sug['prazo_maximo']))->format('d/m/Y') . '.');
        }
        if ($prazoData->format('Y-m-d') < Carbon::today()->toDateString()) {
            throw new CiotException('A data de quitação já passou.');
        }

        return $campos + [
            'valor_frete' => round($frete, 2),
            'percentual_adiantamento' => $pct,
            'valor_adiantamento' => $div['adiantamento'],
            'valor_saldo' => $div['saldo'],
            'prazo_quitacao' => $prazoData->format('Y-m-d'),
            'forma_pagamento' => $forma,
            'chave_pagamento' => mb_substr($chave, 0, 120),
        ];
    }

    /** @return array{id:int|null,nome:string|null,documento:string|null,rntrc:string|null,origem:string}|array{} */
    private function contratado(Viagem $viagem, string $modalidade): array
    {
        if ($modalidade === RegrasCiot::ANTT || $modalidade === RegrasCiot::DISPENSADO) {
            return [];
        }

        $motorista = $viagem->motorista;
        /** @var Pessoa|null $pessoa */
        $pessoa = null;
        $rntrc = null;
        $origem = 'proprietario';

        if ($motorista !== null && $motorista->ehTac()) {
            $pessoa = $motorista->pessoa;
            $rntrc = $motorista->rntrc ?? $motorista->contratoAgregacao?->rntrc ?? $pessoa?->rntrc;
            $origem = 'motorista';
        } else {
            $pessoa = $viagem->veiculoTracao?->proprietario;
            $rntrc = $viagem->veiculoTracao?->proprietario_rntrc ?: $pessoa?->rntrc;
        }

        if ($pessoa === null) {
            return [];
        }

        return [
            'id' => $pessoa->id,
            'nome' => $pessoa->razao_social,
            'documento' => $pessoa->documento,
            'rntrc' => $rntrc ?: null,
            'origem' => $origem,
            'vinculo' => $origem === 'motorista' ? (string) $motorista?->vinculo : null,
        ];
    }

    /** Guarda forma e chave no motorista TAC — na próxima viagem já vem preenchido. */
    private function lembrarConta(Viagem $viagem, array $campos): void
    {
        $motorista = $viagem->motorista;
        if ($motorista === null || ! $motorista->ehTac() || empty($campos['forma_pagamento'])) {
            return;
        }

        $conta = (array) ($motorista->conta_pagamento ?? []);
        $conta['forma'] = $campos['forma_pagamento'];
        $conta['chave'] = $campos['chave_pagamento'];
        $motorista->forceFill(['conta_pagamento' => $conta])->saveQuietly();
    }

    private function numero(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_string($v)) {
            $v = str_contains($v, ',') ? str_replace(['.', ','], ['', '.'], $v) : $v;
        }

        return is_numeric($v) ? (float) $v : null;
    }
}
