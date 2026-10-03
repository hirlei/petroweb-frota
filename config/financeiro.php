<?php

declare(strict_types=1);

/**
 * Apresentação do faturamento (rotinas 5010 e 5020) — rótulos e cores.
 * As listas canônicas vivem nos models (Fatura, TituloReceber, Recebimento).
 */
return [
    'fatura_status' => [
        'aberta' => 'Em aberto', 'parcial' => 'Parcial', 'paga' => 'Paga',
        'vencida' => 'Vencida', 'cancelada' => 'Cancelada',
    ],
    'fatura_cores' => [
        'aberta' => 'gray', 'parcial' => 'info', 'paga' => 'success',
        'vencida' => 'danger', 'cancelada' => 'gray',
    ],
    'titulo_status' => [
        'aberto' => 'Em aberto', 'parcial' => 'Parcial', 'recebido' => 'Recebido', 'cancelado' => 'Cancelado',
    ],
    'formas' => [
        'pix' => 'Pix', 'boleto' => 'Boleto', 'transferencia' => 'Transferência', 'dinheiro' => 'Dinheiro',
    ],

    // Atalhos da Nova fatura (o prazo do cliente entra na frente quando existir).
    'condicoes_rapidas' => ['0' => 'À vista', '15' => '15 dias', '28' => '28 dias', '30' => '30 dias', '30/60' => '30/60 dias'],

    // Sem prazo no cadastro do cliente, a fatura sugere este.
    'condicao_padrao' => '30',

    // ── Contas a pagar (5030) ──
    'pagar' => [
        // Lançamentos esperando olham só os últimos N dias (não puxa histórico antigo).
        'sugestoes_dias' => (int) env('PAGAR_SUGESTOES_DIAS', 90),
        'categorias' => [
            'combustivel' => 'Combustível', 'manutencao' => 'Manutenção', 'frete_terceiro' => 'Frete de terceiro',
            'pedagio' => 'Pedágio', 'seguro' => 'Seguro', 'impostos' => 'Impostos', 'servicos' => 'Serviços', 'outras' => 'Outras',
        ],
        'categoria_cores' => [
            'combustivel' => 'warning', 'manutencao' => 'info', 'frete_terceiro' => 'success',
            'pedagio' => 'purple', 'seguro' => 'purple', 'impostos' => 'danger', 'servicos' => 'gray', 'outras' => 'gray',
        ],
        // Chips de filtro da lista.
        'filtros' => ['combustivel' => 'Combustível', 'manutencao' => 'Manutenção', 'frete_terceiro' => 'Frete de terceiro', 'outras' => 'Outras'],
        'formas' => [
            'pix' => 'Pix', 'boleto' => 'Boleto', 'transferencia' => 'Transferência', 'dinheiro' => 'Dinheiro', 'cartao' => 'Cartão',
        ],
        'condicoes_rapidas' => ['0' => 'À vista', '10' => '10 dias', '30' => '30 dias', '30/60/90' => '30/60/90'],
    ],
];
