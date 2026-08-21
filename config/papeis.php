<?php

declare(strict_types=1);

/**
 * Papéis do cadastro unificado de pessoas — rótulo e cor.
 *
 * A lista canônica vive em App\Domain\Cadastro\RegrasPessoa::PAPEIS e no CHECK
 * da tabela `pessoa_papeis`. Aqui fica só a apresentação. Acrescentar papel é
 * mexer nos três lugares, de propósito: papel novo muda regra fiscal.
 */
return [
    'rotulos' => [
        'cliente'      => 'Cliente',
        'fornecedor'   => 'Fornecedor',
        'motorista'    => 'Motorista',
        'proprietario' => 'Proprietário',
        'oficina'      => 'Oficina',
        'seguradora'   => 'Seguradora',
        'posto'        => 'Posto',
    ],

    'cores' => [
        'cliente'      => 'info',
        'fornecedor'   => 'gray',
        'motorista'    => 'purple',
        'proprietario' => 'secondary',
        'oficina'      => 'warning',
        'seguradora'   => 'gray',
        'posto'        => 'warning',
    ],

    /** O que cada papel acrescenta à ficha. Usado para explicar o campo em tela. */
    'descricoes' => [
        'cliente'      => 'Contrata frete. Pode ser tomador, remetente ou destinatário no CT-e.',
        'fornecedor'   => 'Emite nota contra a transportadora — peças, serviços, combustível.',
        'motorista'    => 'Conduz veículo. Exige CNH, e RNTRC quando é TAC.',
        'proprietario' => 'Dono de veículo operado pela transportadora. Leva RNTRC ao MDF-e.',
        'oficina'      => 'Executa manutenção, interna ou de terceiro.',
        'seguradora'   => 'Emite apólice de RCTR-C, RC-DC ou RCV.',
        'posto'        => 'Ponto de abastecimento, próprio ou de rede.',
    ],
];
