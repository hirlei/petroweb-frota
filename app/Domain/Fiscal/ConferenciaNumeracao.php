<?php

declare(strict_types=1);

namespace App\Domain\Fiscal;

/**
 * Conferência da numeração de um mês, por filial/modelo/série (rotina 4060).
 *
 * Desde o CT-e 4.00 não existe inutilização (Ajuste SINIEF 31/2022): número
 * pulado não exige nada, basta a sequência seguir em ordem. A lista só mostra
 * ao contador quais números do intervalo não têm documento autorizado — e por
 * quê (rejeitado, rascunho, ou simplesmente não usado).
 */
final class ConferenciaNumeracao
{
    /** Situações que contam como documento válido na SEFAZ. */
    public const VALIDOS = ['autorizado', 'cancelado', 'encerrado', 'contingencia'];

    public const MOTIVOS = [
        'rejeitado' => 'Rejeitado, não reenviado',
        'denegado' => 'Denegado',
        'rascunho' => 'Rascunho com número',
        'assinado' => 'Assinado, não transmitido',
        'enviado' => 'Enviado, sem retorno',
    ];

    /**
     * @param  array<int, string>  $documentos  número => status
     * @return array{primeiro:?int, ultimo:?int, emitidos:int, faltando:list<array{numero:int, motivo:string}>, total_faltando:int}
     */
    public static function conferir(array $documentos, int $limite = 30): array
    {
        if ($documentos === []) {
            return ['primeiro' => null, 'ultimo' => null, 'emitidos' => 0, 'faltando' => [], 'total_faltando' => 0];
        }

        ksort($documentos);
        $primeiro = (int) array_key_first($documentos);
        $ultimo = (int) array_key_last($documentos);

        $emitidos = 0;
        $faltando = [];
        $total = 0;
        for ($n = $primeiro; $n <= $ultimo; $n++) {
            $status = $documentos[$n] ?? null;
            if ($status !== null && in_array($status, self::VALIDOS, true)) {
                $emitidos++;

                continue;
            }
            $total++;
            if (count($faltando) < $limite) {
                $faltando[] = ['numero' => $n, 'motivo' => $status === null ? 'Número não usado' : (self::MOTIVOS[$status] ?? ucfirst($status))];
            }
        }

        return ['primeiro' => $primeiro, 'ultimo' => $ultimo, 'emitidos' => $emitidos, 'faltando' => $faltando, 'total_faltando' => $total];
    }
}
