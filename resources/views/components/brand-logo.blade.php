@props([
    'size'        => 'md',
    'showTagline' => false,
])

@php
    /*
     * Logo do PetroWeb Frota (02/10/2026, opção C aprovada): a logo do PetroWeb — a mesma do ERP —
     * com "FROTA" espaçado embaixo, em laranja, terminando junto do "WEB".
     * A imagem é mais alta que a do ERP (918×226 contra 918×160), então as alturas abaixo deixam
     * o "PETROWEB" do mesmo tamanho que no ERP: sm ≈ 20 px, md ≈ 28 px, lg ≈ 34 px, xl ≈ 45 px.
     * Escura → tema claro. Clara → tema escuro. Sem o arquivo novo, cai na logo do PetroWeb.
     */
    $heights = ['sm' => 'h-7', 'md' => 'h-10', 'lg' => 'h-12', 'xl' => 'h-16'];
    $imgH    = $heights[$size] ?? $heights['md'];

    $arqClara  = file_exists(public_path('img/petroweb-frota-clara.png')) ? 'img/petroweb-frota-clara.png' : 'img/petroweb-clara.png';
    $arqEscura = file_exists(public_path('img/petroweb-frota-escura.png')) ? 'img/petroweb-frota-escura.png' : 'img/petroweb-escura.png';
    $temClara  = file_exists(public_path($arqClara));
    $temEscura = file_exists(public_path($arqEscura));
@endphp

<div {{ $attributes->merge(['class' => 'inline-block leading-none']) }}>
    <div>
        @if($temClara)
            <img src="{{ asset($arqClara) }}" alt="PetroWeb Frota"
                 class="{{ $imgH }} w-auto {{ $temEscura ? 'hidden dark:block' : '' }}">
        @endif
        @if($temEscura)
            <img src="{{ asset($arqEscura) }}" alt="PetroWeb Frota"
                 class="{{ $imgH }} w-auto {{ $temClara ? 'block dark:hidden' : '' }}">
        @endif
    </div>
    @if($showTagline)
        <div class="text-xs text-text-muted leading-tight mt-1 font-normal">
            Gestão inteligente<br>para transportadoras e frotas
        </div>
    @endif
</div>
