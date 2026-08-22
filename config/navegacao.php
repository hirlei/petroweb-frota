<?php

declare(strict_types=1);

/**
 * Navegação da sidebar — mesma estrutura do PetroWeb: grupos colapsáveis,
 * item com ícone, rótulo e CÓDIGO DE ROTINA. O código é o que o usuário
 * digita no Ctrl+K, e é o que o suporte cita ao telefone ("abre a 2010").
 *
 * Faixas reservadas:
 *   1000  Cadastros      4000  Fiscal
 *   2000  Frota          5000  Financeiro
 *   3000  Operação       9000  Configurações
 *
 * `can` é a permission do spatie; `modulo` é checado contra a licença do
 * tenant. Item sem os dois aparece para todo mundo.
 */
return [
    [
        'solo'  => true,
        'key'   => 'inicio',
        'label' => 'Início',
        'icon'  => 'layout-dashboard',
        'route' => 'inicio',
    ],
    [
        'key'   => 'cadastros',
        'label' => 'Cadastros',
        'items' => [
            ['codigo' => '1010', 'label' => 'Pessoas',          'icon' => 'users',  'route' => 'pessoas.index',   'can' => 'pessoa.consultar'],
            ['codigo' => '1020', 'label' => 'Produtos',         'icon' => 'package','route' => 'produtos.index',  'can' => 'produto.consultar'],
            ['codigo' => '1030', 'label' => 'Tabelas de frete', 'icon' => 'tag',    'route' => 'tabelas-frete.index', 'can' => 'tabela-frete.consultar'],
        ],
    ],
    [
        'key'   => 'frota',
        'label' => 'Frota',
        'items' => [
            ['codigo' => '2010', 'label' => 'Veículos',       'icon' => 'truck',    'route' => 'veiculos.index',       'can' => 'veiculo.consultar'],
            ['codigo' => '2020', 'label' => 'Composições',    'icon' => 'link',     'route' => 'composicoes.index',    'can' => 'veiculo.consultar'],
            ['codigo' => '2030', 'label' => 'Motoristas',     'icon' => 'id-card',  'route' => 'motoristas.index',     'can' => 'motorista.consultar'],
            ['codigo' => '2040', 'label' => 'Vencimentos',    'icon' => 'calendar', 'route' => 'vencimentos.index',    'can' => 'veiculo.consultar'],
            ['codigo' => '2050', 'label' => 'Manutenção',     'icon' => 'wrench',   'route' => 'manutencao.index',     'can' => 'manutencao.consultar'],
            ['codigo' => '2060', 'label' => 'Abastecimentos', 'icon' => 'fuel',     'route' => 'abastecimentos.index', 'can' => 'abastecimento.consultar'],
        ],
    ],
    [
        'key'   => 'operacao',
        'label' => 'Operação',
        'items' => [
            ['codigo' => '3010', 'label' => 'Ordens de coleta', 'icon' => 'file-text', 'route' => 'ordens-coleta.index', 'can' => 'ordem-coleta.consultar'],
            ['codigo' => '3020', 'label' => 'Viagens',          'icon' => 'route',     'route' => 'viagens.index',       'can' => 'viagem.consultar'],
            ['codigo' => '3050', 'label' => 'Entregas',         'icon' => 'inbox',     'route' => 'entregas.index',      'can' => 'entrega.consultar'],
            ['codigo' => '3060', 'label' => 'Despesas de viagem', 'icon' => 'ticket',  'route' => 'despesas.index',      'can' => 'despesa-viagem.consultar'],
            ['codigo' => '3030', 'label' => 'Rotas',            'icon' => 'map',       'route' => 'rotas.index',         'can' => 'rota.consultar'],
            ['codigo' => '3040', 'label' => 'Ocorrências',      'icon' => 'alert-triangle', 'route' => 'ocorrencias.index', 'can' => 'ocorrencia.consultar'],
        ],
    ],
    [
        'key'   => 'fiscal',
        'label' => 'Fiscal',
        'items' => [
            ['codigo' => '4010', 'label' => 'CT-e',         'icon' => 'files',  'route' => 'cte.index',        'can' => 'cte.consultar'],
            ['codigo' => '4020', 'label' => 'MDF-e',        'icon' => 'files',  'route' => 'mdfe.index',       'can' => 'mdfe.consultar'],
            ['codigo' => '4030', 'label' => 'Vale-pedágio', 'icon' => 'ticket', 'route' => 'vale-pedagio.index', 'can' => 'mdfe.consultar'],
            ['codigo' => '4040', 'label' => 'Certificados', 'icon' => 'shield-check', 'route' => 'certificados.index', 'can' => 'certificado.gerenciar'],
        ],
    ],
    [
        'key'   => 'configuracoes',
        'label' => 'Configurações',
        'items' => [
            ['codigo' => '9010', 'label' => 'Empresa e filiais',     'icon' => 'building', 'route' => 'filiais.index',    'can' => 'filial.gerenciar'],
            ['codigo' => '9020', 'label' => 'Usuários',              'icon' => 'users',    'route' => 'usuarios.index',   'can' => 'usuario.gerenciar'],
            ['codigo' => '9030', 'label' => 'Papéis e permissões',   'icon' => 'key',      'route' => 'permissoes.index', 'can' => 'permissao.gerenciar'],
        ],
    ],
];
