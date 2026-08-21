@props([
    'size'        => 'md',
    'showTagline' => false,
])

@php
    $heights = ['sm' => 'h-5', 'md' => 'h-7', 'lg' => 'h-9', 'xl' => 'h-12'];
    $imgH    = $heights[$size] ?? $heights['md'];

    // Logo escura → tema claro. Logo clara → tema escuro.
    // Fallback: se um dos arquivos faltar, mostra só o que existe (nunca <img> quebrado).
    $temClara  = file_exists(public_path('img/petroweb-clara.png'));
    $temEscura = file_exists(public_path('img/petroweb-escura.png'));
@endphp

<div {{ $attributes->merge(['class' => 'inline-block leading-none']) }}>
    <div>
        @if($temClara)
            <img src="{{ asset('img/petroweb-clara.png') }}" alt="PetroWeb"
                 class="{{ $imgH }} w-auto {{ $temEscura ? 'hidden dark:block' : '' }}">
        @endif
        @if($temEscura)
            <img src="{{ asset('img/petroweb-escura.png') }}" alt="PetroWeb"
                 class="{{ $imgH }} w-auto {{ $temClara ? 'block dark:hidden' : '' }}">
        @endif
    </div>
    @if($showTagline)
        <div class="text-xs text-text-muted leading-tight mt-1 font-normal">
            Gestão Inteligente<br>para Postos de Combustíveis
        </div>
    @endif
</div>
