<?php

declare(strict_types=1);

namespace App\Services\Fiscal\Sefaz;

use App\Models\Cte;
use App\Models\Mdfe;
use Illuminate\Support\Str;

/**
 * Gateway SEFAZ FAKE — para desenvolvimento e homologação sem certificado.
 *
 * Simula a autorização: devolve cStat 100 com um protocolo e uma chave de 44
 * dígitos plausível. Não assina nem transmite nada. É o que permite exercitar
 * todo o fluxo de tela (emitir, cancelar, encerrar) antes de a integração real
 * entrar atrás da mesma interface. NUNCA usar em produção — a env decide.
 */
class FakeSefazGateway implements SefazGateway
{
    public function autorizarCte(Cte $cte): RespostaSefaz
    {
        return RespostaSefaz::ok('100', 'Autorizado o uso do CT-e', $this->protocolo(), $this->chave('57', $cte->serie, (int) $cte->numero));
    }

    public function cancelarCte(Cte $cte, string $justificativa): RespostaSefaz
    {
        return RespostaSefaz::ok('135', 'Evento registrado e vinculado ao CT-e', $this->protocolo());
    }

    public function registrarEventoCte(Cte $cte, string $tipoEvento, array $dados = []): RespostaSefaz
    {
        return RespostaSefaz::ok('135', 'Evento registrado e vinculado ao CT-e', $this->protocolo());
    }

    public function autorizarMdfe(Mdfe $mdfe): RespostaSefaz
    {
        return RespostaSefaz::ok('100', 'Autorizado o uso do MDF-e', $this->protocolo(), $this->chave('58', $mdfe->serie, (int) $mdfe->numero));
    }

    public function encerrarMdfe(Mdfe $mdfe, int $municipioId): RespostaSefaz
    {
        return RespostaSefaz::ok('135', 'Evento registrado e vinculado ao MDF-e', $this->protocolo());
    }

    public function registrarEventoMdfe(Mdfe $mdfe, string $tipoEvento, array $dados = []): RespostaSefaz
    {
        return RespostaSefaz::ok('135', 'Evento registrado e vinculado ao MDF-e', $this->protocolo());
    }

    /** Protocolo de 15 dígitos, como o da SEFAZ. */
    private function protocolo(): string
    {
        return '135' . str_pad((string) random_int(0, 999_999_999_999), 12, '0', STR_PAD_LEFT);
    }

    /** Chave de acesso de 44 dígitos (fake, apenas plausível). */
    private function chave(string $modelo, int $serie, int $numero): string
    {
        $base = '35' // UF (SP, fake)
            . now()->format('ym')
            . str_pad((string) random_int(0, 99_999_999_999_999), 14, '0', STR_PAD_LEFT) // CNPJ fake
            . $modelo
            . str_pad((string) $serie, 3, '0', STR_PAD_LEFT)
            . str_pad((string) max($numero, 1), 9, '0', STR_PAD_LEFT);

        $base = substr($base . Str::padLeft((string) random_int(0, 999_999_999), 9, '0'), 0, 43);

        return $base . '0'; // dígito verificador fake
    }
}
