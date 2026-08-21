@props(['icon' => 'inbox', 'title', 'description' => null])

<div class="flex flex-col items-center justify-center py-12 px-4 text-center">
    <div class="w-14 h-14 rounded-full bg-surface-elevated border border-border flex items-center justify-center mb-4">
        <x-icon name="{{ $icon }}" class="w-7 h-7 text-text-muted" />
    </div>
    <h3 class="text-sm font-semibold text-text">{{ $title }}</h3>
    @if($description)
        <p class="text-sm text-text-muted mt-1 max-w-xs">{{ $description }}</p>
    @endif
    @if(isset($action))
        <div class="mt-4">{{ $action }}</div>
    @endif
</div>
