@props([
    'variant'      => 'primary',
    'size'         => 'md',
    'icon'         => null,
    'iconPosition' => 'left',
    'loading'      => false,
    'disabled'     => false,
    'href'         => null,
    'iconOnly'     => false,
    'ariaLabel'    => null,
])

@php
    $variants = [
        // ── Vocabulário de variantes ─────────────────────────────────────────
        // Cor encoda DOMÍNIO (fiscal/negócio), NÃO CRUD. Regras:
        // primary  = âmbar B: UMA CTA âmbar por tela (ação principal da tela).
        //            ✓ Emitir NF-e, Salvar configuração, Nova entidade, Confirmar modal.
        //            ✗ Buscar/Filtrar, Adicionar inline, ações de linha, secundárias.
        //            âmbar = CTA primária OU acento de marca. NUNCA botão secundário.
        // neutral  = CRUD genérico: Salvar inline, Adicionar linha, Buscar, Cancelar,
        //            Abrir/Fechar caixa (ação de gestão, não de domínio colorido).
        // success  = ação positiva/entrada: confirmar recebimento, baixa CP/CR, reativar,
        //            Suprimento (↓ entra — par verde-entra / vermelho-sai).
        // danger   = ação destrutiva (Remover, Cancelar NF-e) OU saída financeira:
        //            Sangria (↑ sai — par verde-entra / vermelho-sai).
        // warning  = ação reversível de atenção: Reabrir turno, Ajustar.
        // info     = ação técnica/informativa: Ver comprovante, histórico de doc.
        // ver/historico/corrigir = ações de linha com semântica própria (acorde com domínio).
        // ghost/outline = navegação, fechar modal, voltar.
        // Peso médio (fill-100 / text-900) como padrão de domínio — âmbar B ainda domina.
        'primary'  => 'bg-amber-b hover:bg-amber-b-hover text-amber-b-fg dark:bg-amber-500/15 dark:text-amber-400 dark:hover:bg-amber-500/25 focus-visible:ring-amber-500',
        'danger'         => 'bg-red-100 text-red-900 hover:bg-red-200 dark:bg-red-500/25 dark:text-red-300 dark:hover:bg-red-500/40 focus-visible:ring-red-500',
        'danger-outline' => 'bg-transparent border border-red-300 text-red-700 hover:bg-red-50 dark:border-red-500/40 dark:text-red-400 dark:hover:bg-red-500/10 focus-visible:ring-red-500',
        'success'  => 'bg-green-100 text-green-900 hover:bg-green-200 dark:bg-green-500/25 dark:text-green-300 dark:hover:bg-green-500/40 focus-visible:ring-green-500',
        'info'     => 'bg-blue-100 text-blue-900 hover:bg-blue-200 dark:bg-blue-500/25 dark:text-blue-300 dark:hover:bg-blue-500/40 focus-visible:ring-blue-500',
        'warning'  => 'bg-yellow-100 text-yellow-900 hover:bg-yellow-200 dark:bg-yellow-500/25 dark:text-yellow-300 dark:hover:bg-yellow-500/40 focus-visible:ring-yellow-500',
        'neutral'  => 'bg-gray-500/10 text-gray-700 hover:bg-gray-500/20 dark:bg-gray-400/15 dark:text-gray-300 dark:hover:bg-gray-400/25 focus-visible:ring-gray-400',
        // ── Ações semânticas de linha/contexto ────────────────────────────────
        // Regra: só para botões de ação com significado próprio (Ver, Histórico,
        // CC-e). NÃO usar em salvar/adicionar genérico. Âmbar reservado ao acento
        // de marca (não aparece aqui).
        'ver'       => 'bg-teal-100 text-teal-900 hover:bg-teal-200 dark:bg-teal-500/25 dark:text-teal-200 dark:hover:bg-teal-500/40 focus-visible:ring-teal-500',
        'historico' => 'bg-violet-100 text-violet-900 hover:bg-violet-200 dark:bg-violet-500/25 dark:text-violet-200 dark:hover:bg-violet-500/40 focus-visible:ring-violet-500',
        'corrigir'  => 'bg-orange-100 text-orange-900 hover:bg-orange-200 dark:bg-orange-500/25 dark:text-orange-200 dark:hover:bg-orange-500/40 focus-visible:ring-orange-500',
        // ── Legado (preservados) ─────────────────────────────────────────────
        'ghost'    => 'bg-transparent text-text-secondary hover:bg-surface-elevated hover:text-text focus-visible:ring-border-strong',
        'outline'  => 'bg-transparent border border-border-strong text-text hover:bg-surface-elevated focus-visible:ring-border-strong',
        'secondary'=> 'bg-gray-500/10 text-gray-700 hover:bg-gray-500/20 dark:bg-gray-400/15 dark:text-gray-300 dark:hover:bg-gray-400/25 focus-visible:ring-gray-400',
    ];

    // Tamanhos — normal e icon-only (quadrado)
    $sizes = [
        'xs' => $iconOnly ? 'h-6 w-6 text-xs'          : 'px-2 py-0.5 text-xs gap-1',
        'sm' => $iconOnly ? 'h-8 w-8 text-sm'           : 'px-3 py-1.5 text-sm gap-1.5 h-8',
        'md' => $iconOnly ? 'h-10 w-10 text-sm'         : 'px-4 py-2 text-sm gap-2 h-10',
        'lg' => $iconOnly ? 'h-12 w-12 text-base'       : 'px-6 py-3 text-base gap-2 h-12',
    ];

    $base    = 'inline-flex items-center justify-center font-medium rounded-md transition-all duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-bg disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap';
    $classes = "{$base} {$variants[$variant]} {$sizes[$size]}";
    $tag     = $href ? 'a' : 'button';

    // Aria-label para icon-only: usa o prop, depois o nome do ícone, depois aviso
    $effectiveAriaLabel = $ariaLabel;
    if ($iconOnly && !$effectiveAriaLabel) {
        $effectiveAriaLabel = $icon ?? 'button';
        // Dev warning — só no ambiente de desenvolvimento
        if (app()->isLocal()) {
            trigger_error("[x-button] iconOnly sem ariaLabel definido (ícone: {$effectiveAriaLabel}). Defina :aria-label ou ariaLabel=.", E_USER_WARNING);
        }
    }
@endphp

<{{ $tag }} {{ $attributes->merge([
    'class'      => $classes,
    'href'       => $href,
    'type'       => $tag === 'button' ? 'button' : null,
    'aria-label' => $effectiveAriaLabel,
]) }} @disabled($disabled || $loading)>
    @if($loading)
        <x-icon name="loader-2" class="{{ $iconOnly ? 'w-4 h-4' : 'w-4 h-4' }} animate-spin" />
    @elseif($icon && ($iconOnly || $iconPosition === 'left'))
        <x-icon name="{{ $icon }}" class="w-4 h-4 flex-shrink-0" />
    @endif
    @unless($iconOnly)
        {{ $slot }}
    @endunless
    @if(!$iconOnly && $icon && $iconPosition === 'right' && !$loading)
        <x-icon name="{{ $icon }}" class="w-4 h-4 flex-shrink-0" />
    @endif
</{{ $tag }}>
