@props(['variant' => 'gray'])

@php
    $variants = [
        'primary'   => 'bg-primary-soft text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 dark:border dark:border-amber-800/50',
        'secondary' => 'bg-secondary-soft text-green-700 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border dark:border-emerald-800/50',
        'success'   => 'bg-green-50 text-green-700 dark:bg-green-950/50 dark:text-green-300 dark:border dark:border-green-800/50',
        'warning'   => 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 dark:border dark:border-amber-800/50',
        'danger'    => 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300 dark:border dark:border-red-800/50',
        'info'      => 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300 dark:border dark:border-blue-800/50',
        'purple'    => 'bg-purple-50 text-purple-700 dark:bg-purple-950/50 dark:text-purple-300 dark:border dark:border-purple-800/50',
        'gray'      => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:border dark:border-gray-700',
    ];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium {$variants[$variant]}"]) }}>
    {{ $slot }}
</span>
