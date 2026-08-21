<?php

declare(strict_types=1);

namespace App\Domain\Cadastro;

/**
 * CPF e CNPJ — validação e formatação, sem Laravel.
 *
 * O CNPJ ALFANUMÉRICO é o ponto delicado: a IN RFB 2.229/2024 e a NT Conjunta
 * 2025.001 admitem letras nas 12 primeiras posições a partir de julho/2026.
 * Os dois dígitos verificadores continuam numéricos, e o cálculo passa a usar
 * o valor ASCII do caractere menos 48 — de modo que '0'..'9' seguem valendo
 * 0..9 e o CNPJ numérico antigo continua válido pelo mesmo código.
 *
 * Por isso `documento` é string(20) no banco e NUNCA inteiro: um CNPJ com
 * letra em coluna numérica é perda de dado silenciosa, e zero à esquerda
 * some do mesmo jeito.
 */
final class Documento
{
    public const TIPO_CPF = 'CPF';
    public const TIPO_CNPJ = 'CNPJ';

    /** Remove máscara. Mantém letras — o CNPJ alfanumérico depende delas. */
    public static function limpar(string $documento): string
    {
        return strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', $documento));
    }

    public static function tipo(string $documento): ?string
    {
        return match (strlen(self::limpar($documento))) {
            11 => self::TIPO_CPF,
            14 => self::TIPO_CNPJ,
            default => null,
        };
    }

    public static function valido(string $documento): bool
    {
        return match (self::tipo($documento)) {
            self::TIPO_CPF => self::cpfValido($documento),
            self::TIPO_CNPJ => self::cnpjValido($documento),
            default => false,
        };
    }

    public static function cpfValido(string $cpf): bool
    {
        $cpf = self::limpar($cpf);

        if (strlen($cpf) !== 11 || preg_match('/\D/', $cpf) === 1) {
            return false;
        }

        // 111.111.111-11 e companhia passam no módulo 11 — são rejeitados à mão.
        if (preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }

        foreach ([9, 10] as $posicao) {
            $soma = 0;

            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int) $cpf[$i] * ($posicao + 1 - $i);
            }

            $digito = ($soma * 10) % 11;
            $digito = $digito === 10 ? 0 : $digito;

            if ($digito !== (int) $cpf[$posicao]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Vale para o CNPJ numérico clássico e para o alfanumérico da
     * IN RFB 2.229/2024 — é o mesmo módulo 11, mudando só como o caractere
     * vira número.
     */
    public static function cnpjValido(string $cnpj): bool
    {
        $cnpj = self::limpar($cnpj);

        if (strlen($cnpj) !== 14 || preg_match('/^[0-9A-Z]{12}[0-9]{2}$/', $cnpj) !== 1) {
            return false;
        }

        if (preg_match('/^(.)\1{13}$/', $cnpj) === 1) {
            return false;
        }

        $pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $dv1 = self::digitoCnpj(substr($cnpj, 0, 12), $pesos1);

        if ($dv1 !== (int) $cnpj[12]) {
            return false;
        }

        return self::digitoCnpj(substr($cnpj, 0, 13), $pesos2) === (int) $cnpj[13];
    }

    /**
     * @param  list<int>  $pesos
     */
    private static function digitoCnpj(string $base, array $pesos): int
    {
        $soma = 0;

        foreach (str_split($base) as $i => $caractere) {
            // ASCII menos 48: '0'→0 … '9'→9, 'A'→17 … 'Z'→42.
            $soma += (ord($caractere) - 48) * $pesos[$i];
        }

        $resto = $soma % 11;

        return $resto < 2 ? 0 : 11 - $resto;
    }

    public static function formatar(string $documento): string
    {
        $d = self::limpar($documento);

        return match (strlen($d)) {
            11 => substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9),
            14 => substr($d, 0, 2) . '.' . substr($d, 2, 3) . '.' . substr($d, 5, 3)
                . '/' . substr($d, 8, 4) . '-' . substr($d, 12),
            default => $documento,
        };
    }

    /** Mascara o miolo, para listagem de pessoa física. LGPD, não estética. */
    public static function mascarar(string $documento): string
    {
        $d = self::limpar($documento);

        if (strlen($d) !== 11) {
            return self::formatar($d);
        }

        return substr($d, 0, 3) . '.***.***-' . substr($d, 9);
    }

    public static function ehAlfanumerico(string $documento): bool
    {
        return preg_match('/[A-Z]/', self::limpar($documento)) === 1;
    }
}
