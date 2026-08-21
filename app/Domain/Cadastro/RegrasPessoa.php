<?php

declare(strict_types=1);

namespace App\Domain\Cadastro;

/**
 * Regras do cadastro unificado, fora do Eloquent.
 *
 * A mais cara delas é o indicador de IE: ele vira `indIEDest` no CT-e e é a
 * causa de rejeição por cadastro mais frequente na prática. Aqui ele tem um
 * lugar só, checado por teste, e o banco tem o CHECK equivalente.
 */
final class RegrasPessoa
{
    public const TIPO_FISICA = 'F';
    public const TIPO_JURIDICA = 'J';
    public const TIPO_ESTRANGEIRO = 'E';

    public const IE_CONTRIBUINTE = '1';
    public const IE_ISENTO = '2';
    public const IE_NAO_CONTRIBUINTE = '9';

    public const INDICADORES_IE = [
        self::IE_CONTRIBUINTE,
        self::IE_ISENTO,
        self::IE_NAO_CONTRIBUINTE,
    ];

    public const PAPEIS = [
        'cliente', 'fornecedor', 'motorista', 'proprietario',
        'oficina', 'seguradora', 'posto',
    ];

    /** Papéis que transportam carga e portanto precisam de RNTRC. */
    public const PAPEIS_COM_RNTRC = ['motorista', 'proprietario'];

    public static function tipoValido(string $tipo): bool
    {
        return in_array($tipo, [self::TIPO_FISICA, self::TIPO_JURIDICA, self::TIPO_ESTRANGEIRO], true);
    }

    public static function papelValido(string $papel): bool
    {
        return in_array($papel, self::PAPEIS, true);
    }

    public static function indicadorIeValido(string $indicador): bool
    {
        return in_array($indicador, self::INDICADORES_IE, true);
    }

    /** Contribuinte sem inscrição estadual é rejeição garantida no CT-e. */
    public static function exigeInscricaoEstadual(string $indicadorIe): bool
    {
        return $indicadorIe === self::IE_CONTRIBUINTE;
    }

    /**
     * @param  list<string>  $papeis
     */
    public static function exigeRntrc(array $papeis): bool
    {
        return array_intersect($papeis, self::PAPEIS_COM_RNTRC) !== [];
    }

    /**
     * Sugestão de indicador a partir do que já se sabe. É SUGESTÃO — quem
     * decide é o cadastro, porque só o cliente sabe se está isento.
     * Pessoa física quase nunca é contribuinte de ICMS; sem IE, é isento ou
     * não contribuinte, nunca contribuinte.
     */
    public static function sugerirIndicadorIe(string $tipo, ?string $ie): string
    {
        if ($ie !== null && trim($ie) !== '' && strtoupper(trim($ie)) !== 'ISENTO') {
            return self::IE_CONTRIBUINTE;
        }

        return $tipo === self::TIPO_FISICA
            ? self::IE_NAO_CONTRIBUINTE
            : self::IE_ISENTO;
    }

    /**
     * Lista de inconsistências que impedem a pessoa de aparecer em um CT-e.
     * Não bloqueia o cadastro: bloqueia a emissão. Cliente que só recebe
     * boleto pode viver incompleto; tomador de frete, não.
     *
     * @param  array{tipo?:string,documento?:string,ie?:?string,ie_indicador?:string,papeis?:list<string>,rntrc?:?string}  $dados
     * @return list<string>
     */
    public static function pendenciasParaEmissao(array $dados): array
    {
        $pendencias = [];

        $tipo = $dados['tipo'] ?? '';
        $documento = $dados['documento'] ?? '';
        $indicador = $dados['ie_indicador'] ?? '';
        $ie = $dados['ie'] ?? null;
        $papeis = $dados['papeis'] ?? [];

        if (! self::tipoValido($tipo)) {
            $pendencias[] = 'Tipo de pessoa inválido';
        }

        // Estrangeiro não tem CPF nem CNPJ — o CT-e usa outro identificador.
        if ($tipo !== self::TIPO_ESTRANGEIRO && ! Documento::valido($documento)) {
            $pendencias[] = 'CPF ou CNPJ inválido';
        }

        if (! self::indicadorIeValido($indicador)) {
            $pendencias[] = 'Indicador de inscrição estadual não informado';
        } elseif (self::exigeInscricaoEstadual($indicador) && ($ie === null || trim($ie) === '')) {
            $pendencias[] = 'Contribuinte de ICMS sem inscrição estadual';
        }

        if (self::exigeRntrc($papeis) && ($dados['rntrc'] ?? null) === null) {
            $pendencias[] = 'RNTRC obrigatório para transportador';
        }

        return $pendencias;
    }

    public static function aptaParaEmissao(array $dados): bool
    {
        return self::pendenciasParaEmissao($dados) === [];
    }
}
