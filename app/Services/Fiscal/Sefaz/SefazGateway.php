<?php

declare(strict_types=1);

namespace App\Services\Fiscal\Sefaz;

use App\Models\Cte;
use App\Models\Mdfe;

/**
 * Porta única para a SEFAZ. A assinatura do XML, a comunicação com os
 * webservices, a contingência e as retentativas ficam atrás desta interface —
 * a aplicação só conhece Cte/Mdfe e RespostaSefaz. Troca de biblioteca ou de
 * ambiente (homologação/produção) não vaza para as telas.
 *
 * REGRA: nenhuma implementação real chama a SEFAZ dentro do request — isso roda
 * em job. O gateway é o ponto onde o job entra.
 */
interface SefazGateway
{
    public function autorizarCte(Cte $cte): RespostaSefaz;

    public function cancelarCte(Cte $cte, string $justificativa): RespostaSefaz;

    /** @param array<string,mixed> $dados */
    public function registrarEventoCte(Cte $cte, string $tipoEvento, array $dados = []): RespostaSefaz;

    public function autorizarMdfe(Mdfe $mdfe): RespostaSefaz;

    public function encerrarMdfe(Mdfe $mdfe, int $municipioId): RespostaSefaz;

    /** @param array<string,mixed> $dados */
    public function registrarEventoMdfe(Mdfe $mdfe, string $tipoEvento, array $dados = []): RespostaSefaz;
}
