<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Empresa;
use App\Models\Filial;
use Illuminate\Support\Facades\Auth;

/**
 * Resolve a EMPRESA e a FILIAL do ator atual.
 *
 * Duas camadas de isolamento, herdadas do PetroWeb:
 *
 *   1. TENANT (stancl/tenancy) — um cliente do provedor, com banco próprio
 *      (frota_<slug>) e subdomínio próprio. Resolvido pelo middleware do
 *      stancl, antes de qualquer coisa daqui.
 *   2. EMPRESA — dentro do banco do tenant, o discriminador `empresa_id`.
 *      É desta camada que este contexto trata.
 *
 * Cascata de resolução da empresa:
 *   filial da sessão  →  users.empresa_id  →  sessão (troca de empresa)  →  null
 *
 * `null` significa "sem empresa derivável" — o EmpresaScope então não filtra
 * nada. Isso é deliberado e vale para dois casos: comandos de console e o
 * suporte da HALC operando dentro do tenant sem ter escolhido empresa.
 * Toda leitura sem empresa é, por definição, uma leitura privilegiada.
 *
 * NUNCA lê do request. O `empresa_id` vem do usuário autenticado ou da
 * sessão — nunca de um parâmetro que o cliente controla (RN de tenancy).
 */
final class TenantContext
{
    private const CHAVE_EMPRESA = 'contexto.empresa_id';
    private const CHAVE_FILIAL = 'contexto.filial_id';

    /** Bypass explícito, para jobs e comandos que precisam varrer o tenant inteiro. */
    private static bool $semEscopo = false;

    public static function empresaId(): ?int
    {
        if (self::$semEscopo) {
            return null;
        }

        if ($filial = self::filial()) {
            return $filial->empresa_id;
        }

        $usuario = Auth::user();

        if ($usuario?->empresa_id !== null) {
            return (int) $usuario->empresa_id;
        }

        if (session()->has(self::CHAVE_EMPRESA)) {
            return (int) session(self::CHAVE_EMPRESA);
        }

        return null;
    }

    public static function empresa(): ?Empresa
    {
        $id = self::empresaId();

        return $id === null ? null : Empresa::withoutGlobalScopes()->find($id);
    }

    public static function filialId(): ?int
    {
        if (self::$semEscopo) {
            return null;
        }

        $usuario = Auth::user();

        if ($usuario?->filial_id !== null) {
            return (int) $usuario->filial_id;
        }

        return session()->has(self::CHAVE_FILIAL)
            ? (int) session(self::CHAVE_FILIAL)
            : null;
    }

    public static function filial(): ?Filial
    {
        $id = self::filialId();

        return $id === null ? null : Filial::withoutGlobalScopes()->find($id);
    }

    /**
     * Troca a empresa/filial ativa da sessão. Só deve ser chamado depois de
     * verificar que o usuário TEM acesso — este método não autoriza nada.
     */
    public static function trocarPara(int $empresaId, ?int $filialId = null): void
    {
        session([self::CHAVE_EMPRESA => $empresaId]);

        $filialId === null
            ? session()->forget(self::CHAVE_FILIAL)
            : session([self::CHAVE_FILIAL => $filialId]);
    }

    public static function limpar(): void
    {
        session()->forget([self::CHAVE_EMPRESA, self::CHAVE_FILIAL]);
    }

    /**
     * Roda um callback SEM escopo de empresa. Use para jobs de manutenção e
     * relatórios consolidados — e registre o motivo, porque toda chamada aqui
     * é uma exceção à regra de isolamento.
     *
     * @template T
     * @param  callable():T  $callback
     * @return T
     */
    public static function semEscopo(callable $callback): mixed
    {
        $anterior = self::$semEscopo;
        self::$semEscopo = true;

        try {
            return $callback();
        } finally {
            self::$semEscopo = $anterior;
        }
    }

    public static function estaSemEscopo(): bool
    {
        return self::$semEscopo;
    }
}
