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
        // Situação tributária do ICMS no DACTE.
        'cst' => [
            '00' => 'Tributação normal do ICMS', '20' => 'Tributação com redução de BC', '40' => 'Isenta',
            '41' => 'Não tributada', '51' => 'Diferida', '60' => 'Cobrado por substituição tributária',
            '90' => 'Outros', 'SN' => 'Simples Nacional',
        ],
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

    // URL de consulta do QR Code do DACTE/DAMDFE quando a SEFAZ não devolver o
    // texto pronto (qrCodCTe / qrCodMDFe). Padrão: portal da SVRS.
    'qrcode' => [
        'cte' => env('QRCODE_CTE_URL', 'https://dfe-portal.svrs.rs.gov.br/cte/qrCode'),
        'mdfe' => env('QRCODE_MDFE_URL', 'https://dfe-portal.svrs.rs.gov.br/mdfe/qrCode'),
    ],
];
