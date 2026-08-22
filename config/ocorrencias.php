<?php

declare(strict_types=1);

/**
 * Apresentação das ocorrências. Lista canônica em App\Models\Ocorrencia.
 */
return [
    'tipos' => [
        'avaria' => 'Avaria', 'extravio' => 'Extravio', 'atraso' => 'Atraso',
        'devolucao' => 'Devolução', 'sinistro' => 'Sinistro', 'multa' => 'Multa',
        'parada_nao_prevista' => 'Parada não prevista', 'outros' => 'Outros',
    ],

    'tipos_cores' => [
        'avaria' => 'warning', 'extravio' => 'danger', 'atraso' => 'info',
        'devolucao' => 'warning', 'sinistro' => 'danger', 'multa' => 'danger',
        'parada_nao_prevista' => 'gray', 'outros' => 'gray',
    ],

    'responsaveis' => [
        'transportadora' => 'Transportadora', 'cliente' => 'Cliente',
        'terceiro' => 'Terceiro', 'indeterminado' => 'Indeterminado',
    ],

    'status' => [
        'aberta' => 'Aberta', 'em_analise' => 'Em análise', 'resolvida' => 'Resolvida',
    ],

    'status_cores' => [
        'aberta' => 'danger', 'em_analise' => 'warning', 'resolvida' => 'success',
    ],
];
