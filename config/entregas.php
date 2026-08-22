<?php

declare(strict_types=1);

/**
 * Apresentação das entregas (POD) — rótulos e cores. A lista canônica de tipo
 * de comprovação vive em App\Models\Entrega.
 */
return [
    'tipos_comprovacao' => [
        'fisico_digitalizado' => 'Canhoto digitalizado',
        'foto'                => 'Foto',
        'evento_eletronico'   => 'Evento eletrônico',
    ],

    /** Cor do x-badge por tipo de comprovação. */
    'comprovacao_cores' => [
        'fisico_digitalizado' => 'success',
        'foto'                => 'success',
        'evento_eletronico'   => 'purple',
    ],

    'comprovacao_ajuda' => [
        'evento_eletronico' => 'Evento 110180 do CT-e (cancelamento pelo 110181) — liberado com o módulo fiscal (4010).',
    ],
];
