<?php

declare(strict_types=1);

/**
 * Apresentação das ordens de serviço. Listas canônicas em App\Models\OrdemServico.
 */
return [
    'tipos' => [
        'preventiva' => 'Preventiva', 'corretiva' => 'Corretiva', 'sinistro' => 'Sinistro',
        'pneu' => 'Pneu', 'revisao' => 'Revisão',
    ],

    'tipos_cores' => [
        'preventiva' => 'info', 'corretiva' => 'warning', 'sinistro' => 'danger',
        'pneu' => 'gray', 'revisao' => 'secondary',
    ],

    'status' => [
        'aberta' => 'Aberta', 'em_execucao' => 'Em execução', 'aguardando_peca' => 'Aguardando peça',
        'encerrada' => 'Encerrada', 'cancelada' => 'Cancelada',
    ],

    'status_cores' => [
        'aberta' => 'info', 'em_execucao' => 'warning', 'aguardando_peca' => 'warning',
        'encerrada' => 'success', 'cancelada' => 'gray',
    ],
];
