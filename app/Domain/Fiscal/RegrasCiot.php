<?php

declare(strict_types=1);

namespace App\Domain\Fiscal;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Regras do CIOT — Código Identificador da Operação de Transporte.
 *
 * Desde 24/05/2026 ("CIOT para Todos", Res. ANTT 6.078/2026 e 6.090/2026, Lei
 * 15.485/2026) toda viagem rodoviária REMUNERADA tem CIOT, com ou sem TAC. O
 * que muda é quem registra e como:
 *
 *   ipef        contratado é TAC (agregado/autônomo, ou veículo de terceiro cujo
 *               dono é TAC) → registro pela instituição de pagamento homologada
 *               (Repom, Pamcard…); frete só na conta do TAC ou em cartão frete
 *   antt        frota própria, sem TAC → registro direto na ANTT
 *   informado   veículo de outra transportadora (ETC/CTC) → quem executa registra;
 *               aqui só se guarda o número
 *   dispensado  carga própria / transferência / retorno vazio em veículo próprio
 *               e motorista da empresa — não há frete pago a ninguém
 *
 * O número entra no grupo infCIOT do MDF-e. Sem ele, a SEFAZ rejeita (684):
 * homologação desde 21/09/2026, produção a partir de 23/11/2026 (NT 2026.001).
 *
 * Lógica pura — sem Laravel. Valores em centavos inteiros onde há divisão.
 */
final class RegrasCiot
{
    public const IPEF = 'ipef';
    public const ANTT = 'antt';
    public const INFORMADO = 'informado';
    public const DISPENSADO = 'dispensado';

    public const MODALIDADES = [self::IPEF, self::ANTT, self::INFORMADO, self::DISPENSADO];

    /** Tipos de viagem em que não há frete pago quando tudo é da casa. */
    private const SEM_FRETE = ['carga_propria', 'transferencia', 'retorno_vazio'];

    /** Prazo máximo de quitação do frete: 30 dias úteis (Lei 15.485/2026). */
    public const DIAS_UTEIS_QUITACAO = 30;

    /**
     * @param  string  $tipoViagem  viagens.tipo
     * @param  bool  $veiculoProprio  veículo de tração é da empresa (próprio ou arrendado)
     * @param  string|null  $tpTranspProprietario  '1' ETC, '2' TAC, '3' CTC — do dono do veículo de terceiro
     * @param  bool  $motoristaTac  motorista é agregado ou autônomo
     */
    public static function modalidade(string $tipoViagem, bool $veiculoProprio, ?string $tpTranspProprietario, bool $motoristaTac): string
    {
        if ($motoristaTac) {
            return self::IPEF;
        }

        if (! $veiculoProprio) {
            return $tpTranspProprietario === '2' ? self::IPEF : self::INFORMADO;
        }

        return in_array($tipoViagem, self::SEM_FRETE, true) ? self::DISPENSADO : self::ANTT;
    }

    public static function exige(string $modalidade): bool
    {
        return $modalidade !== self::DISPENSADO;
    }

    /** Só no IPEF há pagamento ao contratado controlado pelo sistema. */
    public static function temPagamento(string $modalidade): bool
    {
        return $modalidade === self::IPEF;
    }

    /** O sistema chama alguém para registrar (instituição ou ANTT)? */
    public static function registraPeloSistema(string $modalidade): bool
    {
        return in_array($modalidade, [self::IPEF, self::ANTT], true);
    }

    /**
     * O que fazer com a emissão do MDF-e sem CIOT.
     *
     * @return 'ok'|'avisa'|'bloqueia'
     */
    public static function trava(string $modalidade, bool $temCiot, int $ambiente, DateTimeImmutable $hoje, DateTimeImmutable $obrigatorioDesde): string
    {
        if (! self::exige($modalidade) || $temCiot) {
            return 'ok';
        }

        if ($ambiente === 2) {
            return 'bloqueia';
        }

        return $hoje->format('Y-m-d') >= $obrigatorioDesde->format('Y-m-d') ? 'bloqueia' : 'avisa';
    }

    /**
     * Divide o frete em adiantamento e saldo, em centavos — a soma é sempre o frete.
     *
     * @return array{adiantamento: float, saldo: float}
     */
    public static function dividir(float $frete, float $percentual): array
    {
        if ($frete < 0) {
            throw new InvalidArgumentException('Frete não pode ser negativo.');
        }
        if ($percentual < 0 || $percentual > 100) {
            throw new InvalidArgumentException('Adiantamento deve ficar entre 0% e 100%.');
        }

        $total = (int) round($frete * 100);
        $adiantamento = (int) round($total * $percentual / 100);

        return [
            'adiantamento' => round($adiantamento / 100, 2),
            'saldo' => round(($total - $adiantamento) / 100, 2),
        ];
    }

    /**
     * Último dia para quitar: N dias úteis depois da data-base (segunda a sexta;
     * feriados não entram na conta — o prazo real pode ser um pouco maior).
     */
    public static function prazoMaximo(DateTimeImmutable $base, int $diasUteis = self::DIAS_UTEIS_QUITACAO): DateTimeImmutable
    {
        $d = $base;
        $contados = 0;
        while ($contados < $diasUteis) {
            $d = $d->modify('+1 day');
            if ((int) $d->format('N') <= 5) {
                $contados++;
            }
        }

        return $d;
    }

    /** Dias úteis de $de (exclusive) até $ate (inclusive); negativo se já passou. */
    public static function diasUteisAte(DateTimeImmutable $de, DateTimeImmutable $ate): int
    {
        $de = $de->setTime(0, 0);
        $ate = $ate->setTime(0, 0);
        if ($ate == $de) {
            return 0;
        }

        $sinal = $ate > $de ? 1 : -1;
        [$a, $b] = $sinal === 1 ? [$de, $ate] : [$ate, $de];
        $n = 0;
        while ($a < $b) {
            $a = $a->modify('+1 day');
            if ((int) $a->format('N') <= 5) {
                $n++;
            }
        }

        // Prazo que venceu na sexta, visto no sábado: já passou, mesmo sem dia útil no meio.
        return $sinal === 1 ? $n : -max(1, $n);
    }

    /** Só dígitos e exatamente 12 — o tamanho do campo CIOT no MDF-e. */
    public static function numeroValido(string $numero): bool
    {
        return preg_match('/^\d{12}$/', $numero) === 1;
    }

    public static function limpar(string $numero): string
    {
        return preg_replace('/\D/', '', $numero) ?? '';
    }

    /** "482177300912" → "4821 7730 0912". */
    public static function formatar(?string $numero): string
    {
        $n = self::limpar((string) $numero);

        return strlen($n) === 12 ? implode(' ', str_split($n, 4)) : $n;
    }
}
