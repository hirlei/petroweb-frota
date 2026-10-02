<?php

declare(strict_types=1);

/**
 * Apresentação das viagens — rótulos e cores. A lista canônica de tipo e status
 * vive em App\Models\Viagem.
 */
return [
    'tipos' => [
        'carga_lotacao'  => 'Carga lotação',
        'fracionada'     => 'Fracionada',
        'transferencia'  => 'Transferência',
        'carga_propria'  => 'Carga própria',
        'retorno_vazio'  => 'Retorno vazio',
    ],

    'status' => [
        'planejada'   => 'Planejada',
        'carregando'  => 'Carregando',
        'em_transito' => 'Em trânsito',
        'entregue'    => 'Entregue',
        'encerrada'   => 'Encerrada',
        'cancelada'   => 'Cancelada',
    ],

    /** Cor do x-badge por status. */
    'status_cores' => [
        'planejada'   => 'info',
        'carregando'  => 'warning',
        'em_transito' => 'primary',
        'entregue'    => 'success',
        'encerrada'   => 'gray',
        'cancelada'   => 'gray',
    ],

    /** Sequência do andamento para a timeline. */
    'timeline' => ['planejada', 'carregando', 'em_transito', 'entregue', 'encerrada'],
];
