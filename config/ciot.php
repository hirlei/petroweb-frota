<?php

declare(strict_types=1);

/**
 * CIOT (rotina 4050) e compra de vale-pedágio — 02/10/2026.
 *
 * `driver` = fake enquanto não há instituição de pagamento contratada: gera
 * números fictícios, que NÃO valem na fiscalização. Para valer, o frete com TAC
 * precisa de uma instituição homologada pela ANTT (Repom, Pamcard, e-Frete…) e a
 * frota própria registra direto na ANTT ("CIOT para Todos"). A troca é só aqui —
 * as telas não mudam.
 */
return [
    'driver' => env('CIOT_DRIVER', 'fake'),               // fake | (instituição, quando contratada)
    'instituicao' => env('CIOT_INSTITUICAO', 'Emissor de teste'),

    // NT 2026.001: rejeição 684 em produção a partir desta data (homologação já exige).
    'obrigatorio_desde' => env('CIOT_OBRIGATORIO_DESDE', '2026-11-23'),

    'adiantamentos' => [0, 30, 50, 70],                  // atalhos de % na tela
    'adiantamento_padrao' => 50,

    'modalidades' => [
        'ipef' => 'Instituição de pagamento',
        'antt' => 'Direto na ANTT',
        'informado' => 'Informado pela terceira',
        'dispensado' => 'Não se aplica',
    ],
    'modalidade_cores' => ['ipef' => 'info', 'antt' => 'success', 'informado' => 'warning', 'dispensado' => 'gray'],

    'formas' => ['pix' => 'Pix', 'transferencia' => 'Transferência', 'cartao_frete' => 'Cartão frete'],

    'situacoes' => [
        'recusado' => 'Recusado', 'registrado' => 'Registrado', 'adiantado' => 'Adiantado',
        'a_quitar_vencido' => 'Saldo vencido', 'quitado' => 'Quitado', 'cancelado' => 'Cancelado',
    ],
    'situacao_cores' => [
        'recusado' => 'danger', 'registrado' => 'gray', 'adiantado' => 'info',
        'a_quitar_vencido' => 'danger', 'quitado' => 'success', 'cancelado' => 'gray',
    ],

    'fake' => [
        // Frete abaixo disto é recusado, como a ANTT faz com o piso mínimo — serve
        // para exercitar a recusa e o reenvio. 0 = só recusa frete zerado.
        'piso_minimo' => (float) env('CIOT_FAKE_PISO_MINIMO', 0),
    ],

    'vale_pedagio' => [
        'driver' => env('VALE_PEDAGIO_DRIVER', 'fake'),
        // Emissor de teste: valor = eixos × praças × tarifa. Praças ≈ 1 a cada 100 km.
        'fake_tarifa_por_eixo' => (float) env('VALE_PEDAGIO_FAKE_TARIFA', 8.70),
        'fake_km_padrao' => 300,
    ],
];
