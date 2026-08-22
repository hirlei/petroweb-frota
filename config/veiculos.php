<?php

declare(strict_types=1);

/**
 * Apresentação do cadastro de veículos — rótulo e cor.
 *
 * A lista canônica de tipos e propriedades vive em
 * App\Domain\Frota\RegrasVeiculo e nos CHECKs da tabela `veiculos`. Aqui fica
 * só o que aparece em tela. Nenhum código fiscal é exibido (RN-11).
 */
return [
    'tipos' => [
        'tracao'       => 'Tração',
        'reboque'      => 'Reboque',
        'semirreboque' => 'Semirreboque',
        'dolly'        => 'Dolly',
    ],

    'tipos_cores' => [
        'tracao'       => 'primary',
        'reboque'      => 'info',
        'semirreboque' => 'info',
        'dolly'        => 'gray',
    ],

    'tipos_icones' => [
        'tracao'       => 'truck',
        'reboque'      => 'container',
        'semirreboque' => 'container',
        'dolly'        => 'link',
    ],

    'propriedades' => [
        'propria'   => 'Própria',
        'terceiro'  => 'De terceiro',
        'arrendada' => 'Arrendada',
    ],

    'propriedades_cores' => [
        'propria'   => 'secondary',
        'terceiro'  => 'warning',
        'arrendada' => 'warning',
    ],

    'status' => [
        'ativo'      => 'Ativo',
        'inativo'    => 'Inativo',
        'manutencao' => 'Em manutenção',
        'vendido'    => 'Vendido',
    ],

    'status_cores' => [
        'ativo'      => 'success',
        'inativo'    => 'gray',
        'manutencao' => 'warning',
        'vendido'    => 'gray',
    ],

    /** Rodado (tpRod) da SEFAZ — só a tração usa; não aparece em operação. */
    'rodados' => [
        '01' => 'Truck',
        '02' => 'Toco',
        '03' => 'Cavalo mecânico',
        '04' => 'VAN',
        '05' => 'Utilitário',
        '06' => 'Outros',
    ],

    /** Carroceria (tpCar) da SEFAZ — referência; a tela usa a tabela de negócio. */
    'carrocerias_fiscais' => [
        '00' => 'Não aplicável',
        '01' => 'Aberta',
        '02' => 'Fechada / Baú',
        '03' => 'Granelera',
        '04' => 'Porta-container',
        '05' => 'Sider',
    ],
];
