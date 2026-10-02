<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\Models\Cte;
use App\Models\Mdfe;
use App\Services\Fiscal\Sefaz\RespostaSefaz;
use App\Services\Fiscal\Sefaz\SefazGateway;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Orquestra a emissão fiscal: numera, chama o gateway SEFAZ e grava o resultado
 * (autorizado/rejeitado, chave, protocolo) e os eventos. Não sabe se o gateway é
 * real ou fake — só conhece a interface. Numeração por filial/modelo/série.
 */
class EmissorFiscal
{
    public function __construct(private readonly SefazGateway $sefaz)
    {
    }

    public function emitirCte(Cte $cte): RespostaSefaz
    {
        if ($cte->numero === null) {
            $cte->numero = $this->proximoNumero('ctes', $cte->filial_id, $cte->modelo, $cte->serie);
            $cte->emissao = now();
            $cte->save();
        }

        $r = $this->sefaz->autorizarCte($cte);

        $cte->update($this->aplicar($r));

        return $r;
    }

    public function cancelarCte(Cte $cte, string $justificativa): RespostaSefaz
    {
        $r = $this->sefaz->cancelarCte($cte, $justificativa);

        if ($r->autorizado) {
            DB::transaction(function () use ($cte, $justificativa, $r): void {
                $cte->update(['status' => 'cancelado', 'codigo_status' => $r->codigo, 'motivo_status' => $r->motivo]);
                $cte->eventos()->create([
                    'tipo_evento' => '110111',
                    'sequencia' => $this->proximaSequencia($cte, '110111'),
                    'data_evento' => now(),
                    'justificativa' => $justificativa,
                    'protocolo' => $r->protocolo,
                    'status' => 'registrado',
                    'codigo_status' => $r->codigo,
                    'motivo_status' => $r->motivo,
                ]);
            });
        }

        return $r;
    }

    public function emitirMdfe(Mdfe $mdfe): RespostaSefaz
    {
        if ($mdfe->numero === null) {
            $mdfe->numero = $this->proximoNumero('mdfes', $mdfe->filial_id, $mdfe->modelo, $mdfe->serie);
            $mdfe->emissao = now();
            $mdfe->save();
        }

        $r = $this->sefaz->autorizarMdfe($mdfe);

        $mdfe->update($this->aplicar($r));

        return $r;
    }

    public function encerrarMdfe(Mdfe $mdfe, int $municipioId): RespostaSefaz
    {
        $r = $this->sefaz->encerrarMdfe($mdfe, $municipioId);

        if ($r->autorizado) {
            DB::transaction(function () use ($mdfe, $municipioId, $r): void {
                $mdfe->update([
                    'status' => 'encerrado',
                    'encerrado_em' => now(),
                    'municipio_encerramento_id' => $municipioId,
                ]);
                $mdfe->eventos()->create([
                    'tipo_evento' => '110112',
                    'sequencia' => 1,
                    'data_evento' => now(),
                    'protocolo' => $r->protocolo,
                    'status' => 'registrado',
                ]);
            });
        }

        return $r;
    }

    /** @return array<string,mixed> */
    private function aplicar(RespostaSefaz $r): array
    {
        if ($r->autorizado) {
            return [
                'status' => 'autorizado',
                'chave' => $r->chave,
                'protocolo' => $r->protocolo,
                'data_autorizacao' => Carbon::now(),
                'codigo_status' => $r->codigo,
                'motivo_status' => $r->motivo,
            ];
        }

        return ['status' => 'rejeitado', 'codigo_status' => $r->codigo, 'motivo_status' => $r->motivo];
    }

    private function proximoNumero(string $tabela, int $filialId, string $modelo, int $serie): int
    {
        return DB::transaction(function () use ($tabela, $filialId, $modelo, $serie): int {
            $max = DB::table($tabela)
                ->where('filial_id', $filialId)->where('modelo', $modelo)->where('serie', $serie)
                ->lockForUpdate()->max('numero');

            return (int) $max + 1;
        });
    }

    private function proximaSequencia(Cte $cte, string $tipo): int
    {
        return (int) $cte->eventos()->where('tipo_evento', $tipo)->max('sequencia') + 1;
    }
}
