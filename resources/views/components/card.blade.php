@props([
    'padding'  => 'md',
    'elevated' => false,
])

@php
    $paddings = [
        'none' => '',
        'sm'   => 'p-4',
        'md'   => 'p-6',
        'lg'   => 'p-8',
    ];
    $shadow = $elevated ? 'shadow-elevated' : 'shadow-card';
@endphp

<div {{ $attributes->merge(['class' => "bg-surface rounded-lg border border-border {$shadow} {$paddings[$padding]}"]) }}>
    {{ $slot }}
</div>
