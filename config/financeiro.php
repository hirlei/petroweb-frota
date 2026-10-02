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
];
