@props([
    'label'    => null,
    'help'     => null,
    'error'    => null,
    'required' => false,
    'mono'     => false,
])

<div {{ $attributes->only('class')->merge(['class' => '']) }}>
    @if($label)
        <label class="block text-sm font-medium text-text-secondary mb-1.5">
            {{ $label }}@if($required)<span class="text-danger ml-0.5">*</span>@endif
        </label>
    @endif
    <input
        {{ $attributes->except(['class', 'label', 'help', 'error', 'required', 'mono'])->merge([
            'class' => 'w-full px-3 py-2 bg-white dark:bg-surface-elevated border rounded-md text-sm text-text outline-none transition-colors
                        focus:border-primary focus:ring-2 focus:ring-primary/20
                        disabled:opacity-50 disabled:cursor-not-allowed
                        ' . ($error ? 'border-danger ring-2 ring-danger/20' : 'border-border')
                        . ($mono ? ' font-mono' : '')
        ]) }}
    >
    @if($error)
        <p class="text-xs text-danger mt-1 flex items-center gap-1">
            <x-icon name="alert-circle" class="w-3 h-3" />{{ $error }}
        </p>
    @elseif($help)
        <p class="text-xs text-text-muted mt-1">{{ $help }}</p>
    @endif
</div>
