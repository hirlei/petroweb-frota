<?php

declare(strict_types=1);

/**
 * Configuração fiscal (CT-e / MDF-e).
 *
 * `sefaz.driver` = fake enquanto a integração real não entra. `ambiente` = 2
 * (homologação) por padrão — produção só quando o cliente estiver liberado. Os
 * rótulos alimentam telas; as listas canônicas vivem nos models.
 */
return [
    'sefaz' => [
        'driver' => env('SEFAZ_DRIVER', 'fake'), // fake | real
        'ambiente' => (int) env('SEFAZ_AMBIENTE', 2), // 1 produção, 2 homologação
    ],

    'cte' => [
        'status' => [
            'rascunho' => 'Rascunho', 'assinado' => 'Assinado', 'enviado' => 'Enviado',
            'autorizado' => 'Autorizado', 'rejeitado' => 'Rejeitado', 'denegado' => 'Denegado',
            'cancelado' => 'Cancelado', 'contingencia' => 'Contingência',
        ],
        'status_cores' => [
            'rascunho' => 'gray', 'assinado' => 'info', 'enviado' => 'info',
            'autorizado' => 'success', 'rejeitado' => 'danger', 'denegado' => 'danger',
            'cancelado' => 'gray', 'contingencia' => 'warning',
        ],
        'tomadores' => [0 => 'Remetente', 1 => 'Expedidor', 2 => 'Recebedor', 3 => 'Destinatário', 4 => 'Outros'],
    ],

    'mdfe' => [
        'status' => [
            'rascunho' => 'Rascunho', 'autorizado' => 'Autorizado', 'rejeitado' => 'Rejeitado',
            'cancelado' => 'Cancelado', 'encerrado' => 'Encerrado', 'contingencia' => 'Contingência',
        ],
        'status_cores' => [
            'rascunho' => 'gray', 'autorizado' => 'info', 'rejeitado' => 'danger',
            'cancelado' => 'gray', 'encerrado' => 'success', 'contingencia' => 'warning',
        ],
    ],

    'ambientes' => [1 => 'Produção', 2 => 'Homologação'],
];
