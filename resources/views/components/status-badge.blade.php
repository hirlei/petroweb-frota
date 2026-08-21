@props(['status', 'label' => null])

@php
    $statusNorm = strtolower(str_replace([' ', '-'], '_', (string) $status));

    $mapeamento = [
        'ativo'       => ['cor' => 'green', 'label' => 'Ativo'],
        'aberto'      => ['cor' => 'green', 'label' => 'Aberto'],
        'ok'          => ['cor' => 'green', 'label' => 'OK'],
        'normal'      => ['cor' => 'green', 'label' => 'Normal'],
        'aprovado'    => ['cor' => 'green', 'label' => 'Aprovado'],
        'aprovada'    => ['cor' => 'green', 'label' => 'Aprovada'],
        'processado'  => ['cor' => 'green', 'label' => 'Processado'],
        'liberado'    => ['cor' => 'green', 'label' => 'Liberado'],
        'fechada'     => ['cor' => 'green', 'label' => 'Fechada'],
        'nfe_emitida' => ['cor' => 'green', 'label' => 'NF-e emitida'],

        'manutencao'  => ['cor' => 'amber', 'label' => 'Manutenção'],
        'fechando'    => ['cor' => 'amber', 'label' => 'Fechando'],
        'em_analise'  => ['cor' => 'amber', 'label' => 'Em análise'],
        'pendente'    => ['cor' => 'amber', 'label' => 'Pendente'],
        'atencao'     => ['cor' => 'amber', 'label' => 'Atenção'],
        'aguardando'  => ['cor' => 'amber', 'label' => 'Aguardando'],
        'nfe_pendente' => ['cor' => 'amber', 'label' => 'NF-e pendente'],

        'critico'     => ['cor' => 'red', 'label' => 'Crítico'],
        'erro'        => ['cor' => 'red', 'label' => 'Erro'],
        'cancelada'   => ['cor' => 'red', 'label' => 'Cancelada'],
        'cancelado'   => ['cor' => 'red', 'label' => 'Cancelado'],
        'bloqueado'   => ['cor' => 'red', 'label' => 'Bloqueado'],
        'divergencia' => ['cor' => 'red', 'label' => 'Divergência'],
        'rejeitado'   => ['cor' => 'red', 'label' => 'Rejeitado'],
        'nfe_rejeitada' => ['cor' => 'red', 'label' => 'NF-e rejeitada'],

        'estornado'   => ['cor' => 'gray', 'label' => 'Estornado'],
        'inativo'     => ['cor' => 'gray', 'label' => 'Inativo'],
        'fechado'     => ['cor' => 'gray', 'label' => 'Fechado'],
        'desativado'  => ['cor' => 'gray', 'label' => 'Desativado'],
        'desativada'  => ['cor' => 'gray', 'label' => 'Desativada'],
        'sem_leitura' => ['cor' => 'gray', 'label' => 'Sem leitura'],
        'arquivado'   => ['cor' => 'gray', 'label' => 'Arquivado'],
    ];

    $cfg = $mapeamento[$statusNorm] ?? ['cor' => 'gray', 'label' => $label ?? ucfirst((string) $status)];

    $classes = [
        'green' => 'text-green-600 dark:text-green-400',
        'amber' => 'text-amber-600 dark:text-amber-400',
        'red'   => 'text-red-600 dark:text-red-400',
        'gray'  => 'text-text-muted',
    ];

    $cor        = $cfg['cor'];
    $textoFinal = $label ?? $cfg['label'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 text-sm font-medium ' . ($classes[$cor] ?? $classes['gray'])]) }}>
    <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
    {{ $textoFinal }}
</span>
