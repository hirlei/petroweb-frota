<?php

declare(strict_types=1);

/**
 * Apresentação do cadastro de motoristas — rótulo e cor.
 *
 * A lista canônica de vínculos vive em App\Domain\Frota\RegrasMotorista e no
 * CHECK da tabela `motoristas`. RN-12: só CLT tem jornada; agregado e autônomo
 * são TAC. Aqui fica só o que aparece em tela.
 */
return [
    'vinculos' => [
        'clt'      => 'CLT',
        'agregado' => 'Agregado',
        'autonomo' => 'Autônomo',
        'terceiro' => 'Terceiro',
    ],

    'vinculos_cores' => [
        'clt'      => 'info',
        'agregado' => 'secondary',
        'autonomo' => 'warning',
        'terceiro' => 'gray',
    ],

    'vinculos_descricoes' => [
        'clt'      => 'Empregado. Tem jornada, escala e ponto. Opera sob o RNTRC da empresa.',
        'agregado' => 'TAC com contrato de exclusividade. RNTRC próprio, CIOT obrigatório, sem jornada.',
        'autonomo' => 'TAC avulso. RNTRC próprio, CIOT obrigatório, sem jornada.',
        'terceiro' => 'Motorista de outra transportadora — há subcontrato, não vínculo.',
    ],

    'status' => [
        'ativo'    => 'Ativo',
        'inativo'  => 'Inativo',
        'afastado' => 'Afastado',
        'ferias'   => 'Em férias',
    ],

    'status_cores' => [
        'ativo'    => 'success',
        'inativo'  => 'gray',
        'afastado' => 'warning',
        'ferias'   => 'info',
    ],
];
