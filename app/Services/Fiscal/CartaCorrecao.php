<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\Domain\Fiscal\RegrasCce;
use App\Models\Cte;
use App\Models\CteEvento;
use App\Services\Fiscal\Sefaz\RespostaSefaz;
use App\Services\Fiscal\Sefaz\SefazGateway;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Carta de Correção Eletrônica do CT-e (evento 110110). As regras puras
 * (vedações, limite de 20, consolidação) estão em RegrasCce; aqui ficam a trava
 * por CT-e, a sequência e o registro do evento.
 */
final class CartaCorrecao
{
    public function __construct(private readonly SefazGateway $sefaz)
    {
    }

    /**
     * Correções que valem hoje (as da última CC-e registrada).
     *
     * @return list<array{grupo:string,campo:string,valor:string,item:int|string|null}>
     */
    public function vigentes(Cte $cte): array
    {
        $ultima = $cte->cartasCorrecao()->orderByDesc('sequencia')->first();

        return array_values(array_map(fn (array $c) => [
            'grupo' => (string) ($c['grupo'] ?? ''), 'campo' => (string) ($c['campo'] ?? ''),
            'valor' => (string) ($c['valor'] ?? ''), 'item' => $c['item'] ?? null,
        ], (array) ($ultima?->correcoes ?? [])));
    }

    /**
     * Transmite a CC-e com a lista COMPLETA de correções (as anteriores que
     * continuam valendo + as novas — a tela já monta assim).
     *
     * @param  list<array{grupo:string,campo:string,valor:string,item?:int|string|null}>  $correcoes
     *
     * @throws CartaCorrecaoException  regra de negócio (nada foi enviado)
     */
    public function transmitir(Cte $cte, array $correcoes): RespostaSefaz
    {
        return DB::transaction(function () use ($cte, $correcoes): RespostaSefaz {
            $cte = Cte::query()->lockForUpdate()->findOrFail($cte->id);

            if (! $cte->autorizado()) {
                throw new CartaCorrecaoException('Só CT-e autorizado aceita carta de correção.');
            }

            $emitidas = $cte->cartasCorrecao()->count();
            $erros = RegrasCce::validar($correcoes, $emitidas);
            if ($erros !== []) {
                throw new CartaCorrecaoException($erros['_'] ?? 'Corrija as linhas marcadas antes de transmitir.', $erros);
            }

            $lista = RegrasCce::consolidar([], $correcoes);
            // Recusada pela SEFAZ não consome a sequência: a próxima tentativa reaproveita o número.
            $sequencia = (int) CteEvento::query()->where('cte_id', $cte->id)->where('tipo_evento', CteEvento::CCE)
                ->where('status', 'registrado')->max('sequencia') + 1;
            if ($sequencia > RegrasCce::MAXIMO) {
                throw new CartaCorrecaoException('Este CT-e já usou as ' . RegrasCce::MAXIMO . ' sequências de carta de correção.');
            }

            $r = $this->sefaz->registrarEventoCte($cte, CteEvento::CCE, [
                'sequencia' => $sequencia,
                'correcoes' => $lista,
                'condicao_uso' => RegrasCce::CONDICAO_USO,
            ]);

            // Guarda também a recusa (a tela mostra o motivo); uma recusa anterior
            // com a mesma sequência dá lugar a esta tentativa.
            CteEvento::query()->where('cte_id', $cte->id)->where('tipo_evento', CteEvento::CCE)
                ->where('sequencia', $sequencia)->where('status', '!=', 'registrado')->delete();
            $cte->eventos()->create([
                'tipo_evento' => CteEvento::CCE,
                'sequencia' => $sequencia,
                'data_evento' => now(),
                'correcoes' => $lista,
                'protocolo' => $r->protocolo,
                'status' => $r->autorizado ? 'registrado' : 'rejeitado',
                'codigo_status' => $r->codigo,
                'motivo_status' => mb_substr($r->motivo, 0, 255),
                'xml_path' => $r->xmlPath,
                'criado_por' => Auth::id(),
            ]);

            return $r;
        });
    }
}

