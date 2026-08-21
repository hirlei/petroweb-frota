@props(['title', 'subtitle' => null])

<div class="mb-6">
    <div class="flex items-start justify-between gap-4">
        <div class="flex items-start gap-3">
            @isset($icon)
                <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">{{ $icon }}</div>
            @endisset
            <div>
                <h1 class="text-2xl font-bold text-text tracking-tight">{{ $title }}</h1>
                @if($subtitle)
                    <p class="text-sm text-text-secondary mt-1">{{ $subtitle }}</p>
                @endif
            </div>
        </div>
        @if(isset($actions))
            <div class="flex items-center gap-2 flex-shrink-0 mt-0.5">{{ $actions }}</div>
        @endif
    </div>
</div>
