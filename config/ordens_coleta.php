<?php

declare(strict_types=1);

/**
 * Apresentação das ordens de coleta — rótulos e cores. A lista canônica de
 * tomador e status vive em App\Models\OrdemColeta.
 */
return [
    'tomador_tipos' => [
        'remetente'    => 'Remetente',
        'destinatario' => 'Destinatário',
        'expedidor'    => 'Expedidor',
        'recebedor'    => 'Recebedor',
        'outros'       => 'Outros',
    ],

    'status' => [
        'aberta'    => 'Aberta',
        'coletada'  => 'Coletada',
        'faturada'  => 'Faturada',
        'cancelada' => 'Cancelada',
    ],

    /** Cor do x-badge por status. */
    'status_cores' => [
        'aberta'    => 'info',
        'coletada'  => 'warning',
        'faturada'  => 'success',
        'cancelada' => 'gray',
    ],

    'unidades' => ['UN', 'CX', 'PC', 'KG', 'TON', 'L', 'M3', 'PLT', 'SC', 'FD'],
];
