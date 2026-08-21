@props(['name'])

{{--
    Ícone via sprite local (public/img/icons/sprite.svg, gerado por
    scripts/gerar-sprite-icones.js a partir do lucide-static) -- zero CDN,
    zero JS de terceiro em runtime. `stroke="currentColor"` já vem embutido
    no <symbol> do sprite, então a cor segue a classe de texto (text-*)
    aplicada aqui, igual ao comportamento atual dos ícones lucide via CDN.

    Etapa 2/2 do self-host (ver docs/AUDITORIA_ATUAL.md): substitui todos os
    <i data-lucide="..."> do projeto -- o CDN do lucide foi removido.
--}}
<svg {{ $attributes->merge(['class' => 'w-5 h-5']) }} aria-hidden="true">
    <use href="{{ asset('img/icons/sprite.svg') }}#{{ $name }}"></use>
</svg>
