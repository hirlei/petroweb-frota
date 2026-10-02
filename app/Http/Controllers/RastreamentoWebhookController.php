<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Rastreamento\RegistroProvedores;
use App\Models\PosicaoVeiculo;
use App\Models\Veiculo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Throwable;

/**
 * Webhook de rastreamento — recebe as posições que os provedores de GPS enviam
 * por "direcionamento de sinal".
 *
 * URL: POST https://<tenant>.frota.petroweb.app/webhooks/rastreamento/{provedor}
 * Autenticação: header X-Rastreamento-Token = config('mapa.rastreamento.webhook_token').
 * O tenant é resolvido pelo subdomínio (grupo de middleware `tenant-api`, sem
 * sessão/CSRF). O adaptador do provedor normaliza o payload; cada posição é
 * casada ao veículo pela placa e gravada.
 */
class RastreamentoWebhookController extends Controller
{
    public function receber(Request $request, string $provedor, RegistroProvedores $registro): JsonResponse
    {
        $tokenEsperado = config('mapa.rastreamento.webhook_token');

        if (empty($tokenEsperado) || ! hash_equals((string) $tokenEsperado, (string) $request->header('X-Rastreamento-Token'))) {
            return response()->json(['erro' => 'Token inválido.'], 401);
        }

        try {
            $adaptador = $registro->resolver($provedor);
        } catch (InvalidArgumentException $e) {
            return response()->json(['erro' => $e->getMessage()], 404);
        }

        try {
            $posicoes = $adaptador->normalizar($request->all());
        } catch (Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 501);
        }

        $gravadas = 0;

        foreach ($posicoes as $p) {
            $veiculo = Veiculo::withoutGlobalScopes()->where('placa', $p['placa'])->first();

            if ($veiculo === null) {
                continue;
            }

            PosicaoVeiculo::withoutGlobalScopes()->create([
                'empresa_id' => $veiculo->empresa_id,
                'veiculo_id' => $veiculo->id,
                'latitude' => $p['latitude'],
                'longitude' => $p['longitude'],
                'velocidade_kmh' => $p['velocidade_kmh'] ?? null,
                'rumo' => $p['rumo'] ?? null,
                'ignicao' => $p['ignicao'] ?? null,
                'provedor' => $adaptador->nome(),
                'capturado_em' => $this->instante($p['capturado_em'] ?? null),
                'recebido_em' => now(),
                'bruto' => $p['bruto'] ?? null,
            ]);

            $gravadas++;
        }

        return response()->json(['recebidas' => count($posicoes), 'gravadas' => $gravadas]);
    }

    private function instante(?string $valor): Carbon
    {
        if ($valor === null || $valor === '') {
            return now();
        }

        try {
            return Carbon::parse($valor);
        } catch (Throwable) {
            return now();
        }
    }
}
