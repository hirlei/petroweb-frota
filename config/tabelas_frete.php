<?php

declare(strict_types=1);

/**
 * Apresentação das tabelas de frete — rótulos dos componentes e bases de
 * cálculo. A lista canônica vive em App\Models\TabelaFrete.
 */
return [
    'componentes' => [
        'peso'         => 'Peso',
        'valor'        => 'Valor da carga',
        'gris'         => 'GRIS',
        'advalorem'    => 'Ad valorem',
        'pedagio'      => 'Pedágio',
        'tde'          => 'TDE (entrega)',
        'tda'          => 'TDA (dificuldade)',
        'taxa_entrega' => 'Taxa de entrega',
        'outros'       => 'Outros',
    ],

    'componentes_ajuda' => [
        'gris'      => 'Gerenciamento de Risco — percentual sobre o valor da carga.',
        'advalorem' => 'Seguro proporcional ao valor transportado.',
        'pedagio'   => 'Componente informativo — não integra a base do ICMS nem o valor do frete no CT-e.',
        'tde'       => 'Taxa de Dificuldade de Entrega em regiões específicas.',
        'tda'       => 'Taxa de Dificuldade de Acesso.',
    ],

    'bases' => [
        'por_kg'           => 'Por kg',
        'por_ton'          => 'Por tonelada',
        'percentual_valor' => '% sobre o valor',
        'fixo'             => 'Valor fixo',
        'por_km'           => 'Por km',
        'por_volume'       => 'Por volume',
    ],
];
