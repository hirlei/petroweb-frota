<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permissões e papéis do tenant.
 *
 * Convenção: `<recurso>.<verbo>`, com `consultar` e `gerenciar` como verbos
 * padrão. Rotinas fiscais têm permissão própria e exigem 2FA — quem emite
 * documento usa o certificado da empresa, e isso não é um CRUD qualquer.
 */
class PermissoesSeeder extends Seeder
{
    /** Permissões que só valem com segundo fator configurado. */
    public const EXIGEM_2FA = [
        'cte.emitir', 'cte.cancelar', 'cte.corrigir', 'cte.inutilizar',
        'mdfe.emitir', 'mdfe.encerrar',
        'certificado.gerenciar', 'permissao.gerenciar',
    ];

    private const PERMISSOES = [
        // Cadastros
        'pessoa.consultar', 'pessoa.gerenciar',
        'produto.consultar', 'produto.gerenciar',
        'tabela-frete.consultar', 'tabela-frete.gerenciar',
        // Frota
        'veiculo.consultar', 'veiculo.gerenciar',
        'motorista.consultar', 'motorista.gerenciar',
        'manutencao.consultar', 'manutencao.gerenciar',
        'abastecimento.consultar', 'abastecimento.gerenciar',
        // Operação
        'ordem-coleta.consultar', 'ordem-coleta.gerenciar',
        'viagem.consultar', 'viagem.gerenciar',
        'despesa-viagem.consultar', 'despesa-viagem.gerenciar',
        'acerto.consultar', 'acerto.gerenciar', 'custo-viagem.consultar',
        'entrega.consultar', 'entrega.gerenciar',
        'rota.consultar', 'rota.gerenciar',
        'ocorrencia.consultar', 'ocorrencia.gerenciar',
        // Fiscal
        'cte.consultar', 'cte.emitir', 'cte.cancelar', 'cte.corrigir', 'cte.inutilizar',
        'mdfe.consultar', 'mdfe.emitir', 'mdfe.encerrar',
        'certificado.gerenciar', 'xml.exportar',
        'ciot.consultar', 'ciot.gerenciar',
        // Financeiro (5010 Faturas, 5020 Contas a receber, 5030 Contas a pagar)
        'fatura.consultar', 'fatura.gerenciar', 'recebimento.registrar',
        'conta-pagar.consultar', 'conta-pagar.gerenciar', 'pagamento.registrar',
        // Configuração
        'filial.gerenciar', 'usuario.gerenciar', 'permissao.gerenciar',
    ];

    private const PAPEIS = [
        'Administrador' => ['*'],
        'Fiscal' => [
            'pessoa.consultar', 'produto.consultar', 'tabela-frete.consultar',
            'veiculo.consultar', 'motorista.consultar', 'viagem.consultar',
            'cte.consultar', 'cte.emitir', 'cte.cancelar', 'cte.corrigir', 'cte.inutilizar',
            'mdfe.consultar', 'mdfe.emitir', 'mdfe.encerrar', 'certificado.gerenciar', 'xml.exportar',
            'ciot.consultar', 'ciot.gerenciar',
        ],
        'Operação' => [
            'pessoa.consultar', 'pessoa.gerenciar', 'produto.consultar',
            'tabela-frete.consultar', 'veiculo.consultar', 'veiculo.gerenciar',
            'motorista.consultar', 'motorista.gerenciar',
            'ordem-coleta.consultar', 'ordem-coleta.gerenciar',
            'viagem.consultar', 'viagem.gerenciar',
            'despesa-viagem.consultar', 'despesa-viagem.gerenciar',
            'acerto.consultar', 'acerto.gerenciar', 'custo-viagem.consultar',
            'entrega.consultar', 'entrega.gerenciar',
            'rota.consultar', 'rota.gerenciar',
            'ocorrencia.consultar', 'ocorrencia.gerenciar',
            'abastecimento.consultar', 'abastecimento.gerenciar',
            'manutencao.consultar',
            'cte.consultar', 'mdfe.consultar', 'ciot.consultar',
        ],
        'Financeiro' => [
            'pessoa.consultar', 'tabela-frete.consultar', 'tabela-frete.gerenciar',
            'viagem.consultar', 'despesa-viagem.consultar', 'despesa-viagem.gerenciar',
            'acerto.consultar', 'acerto.gerenciar', 'custo-viagem.consultar',
            'cte.consultar', 'mdfe.consultar', 'xml.exportar',
            'abastecimento.consultar', 'manutencao.consultar',
            'fatura.consultar', 'fatura.gerenciar', 'recebimento.registrar',
            'conta-pagar.consultar', 'conta-pagar.gerenciar', 'pagamento.registrar',
            'ciot.consultar', 'ciot.gerenciar',
        ],
        'Consulta' => [
            'pessoa.consultar', 'produto.consultar', 'veiculo.consultar',
            'motorista.consultar', 'viagem.consultar', 'despesa-viagem.consultar', 'acerto.consultar', 'custo-viagem.consultar',
            'entrega.consultar', 'cte.consultar', 'mdfe.consultar',
            'fatura.consultar', 'ciot.consultar', 'conta-pagar.consultar',
        ],
    ];

    public function run(): void
    {
        foreach (self::PERMISSOES as $permissao) {
            Permission::findOrCreate($permissao, 'web');
        }

        foreach (self::PAPEIS as $nome => $permissoes) {
            $papel = Role::findOrCreate($nome, 'web');

            $papel->syncPermissions(
                $permissoes === ['*'] ? self::PERMISSOES : $permissoes
            );
        }
    }
}
