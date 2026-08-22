<?php

declare(strict_types=1);

/**
 * Apresentação das despesas de viagem — rótulos e cores. A lista canônica de
 * tipo, forma e origem vive em App\Models\Despesa.
 */
return [
    'tipos' => [
        'pedagio'        => 'Pedágio',
        'alimentacao'    => 'Alimentação',
        'hospedagem'     => 'Hospedagem',
        'estacionamento' => 'Estacionamento',
        'lavagem'        => 'Lavagem',
        'chapa'          => 'Chapa (carga/descarga)',
        'balanca'        => 'Balança',
        'multa'          => 'Multa',
        'outros'         => 'Outros',
    ],

    'formas_pagamento' => [
        'adiantamento' => 'Adiantamento',
        'cartao'       => 'Cartão',
        'reembolso'    => 'Reembolso',
        'empresa'      => 'Empresa',
    ],

    'origens' => [
        'manual'        => 'Manual',
        'app_motorista' => 'App do motorista',
    ],

    /** Cor do x-badge por forma de pagamento. */
    'forma_cores' => [
        'adiantamento' => 'info',
        'cartao'       => 'gray',
        'reembolso'    => 'gray',
        'empresa'      => 'secondary',
    ],
];
