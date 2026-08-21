{{-- KPI padrão do Financeiro (estilo A — clean compacto).
     Card ~62px: rótulo 9,5px maiúsculo, valor 19px peso 500 (régua Medium 15/08), sub-linha 10,5px.
     Dimensões em estilo inline (não dependem do build do Tailwind).

     Props:
       label   rótulo maiúsculo
       valor   valor formatado (string)
       cor     verde | vermelho | ambar | teal | azul | null (neutro)
       sub     sub-linha opcional (string) — ou use o slot p/ conteúdo rico
       bar     0–100 → barra de progresso
       alerta  true → borda + textos âmbar (pendências/divergências)

     Uso: <x-kpi label="Saldo atual" :valor="'R$ ' . number_format($v, 2, ',', '.')" cor="verde" /> --}}
@props(['label', 'valor', 'cor' => null, 'sub' => null, 'bar' => null, 'alerta' => false])
@php
    $corValor = $alerta ? 'text-amber-600 dark:text-amber-400' : match ($cor) {
        'verde'    => 'text-emerald-600 dark:text-emerald-400',
        'vermelho' => 'text-red-600 dark:text-red-400',
        'ambar'    => 'text-amber-600 dark:text-amber-400',
        'teal'     => 'text-teal-600 dark:text-teal-400',
        'azul'     => 'text-blue-600 dark:text-blue-400',
        default    => 'text-text',
    };
@endphp
<div {{ $attributes->merge(['class' => 'bg-surface rounded-xl border shadow-card ' . ($alerta ? 'border-amber-500' : 'border-border')]) }}
     style="padding:10px 13px;min-height:62px">
    <p class="font-bold uppercase {{ $alerta ? 'text-amber-600 dark:text-amber-400' : 'text-text-muted' }}"
       style="font-size:9.5px;letter-spacing:.6px;margin:0">{{ $label }}</p>
    <p class="tabular-nums {{ $corValor }}" style="font-size:19px;font-weight:500;font-family:inherit;margin:3px 0 0;line-height:1.15">{{ $valor }}</p>
    @if($bar !== null)
        <div class="bg-surface-elevated" style="height:4px;border-radius:2px;overflow:hidden;margin-top:4px">
            <div class="bg-teal-500" style="width:{{ max(0, min(100, (int) $bar)) }}%;height:100%"></div>
        </div>
    @endif
    @if($sub)
        <p class="text-text-muted" style="font-size:10.5px;margin:1px 0 0">{{ $sub }}</p>
    @endif
    {{ $slot }}
</div>
