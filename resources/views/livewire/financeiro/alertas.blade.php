{{-- Avisos de sucesso/erro das telas financeiras --}}
@if (session('sucesso'))
    <div class="mb-4 flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800
                dark:border-green-800/50 dark:bg-green-950/50 dark:text-green-300">
        <x-icon name="check" class="h-4 w-4 flex-shrink-0" />
        {{ session('sucesso') }}
    </div>
@endif
@if (session('erro'))
    <div class="mb-4 flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800
                dark:border-red-800/50 dark:bg-red-950/50 dark:text-red-300">
        <x-icon name="alert-triangle" class="h-4 w-4 flex-shrink-0" />
        {{ session('erro') }}
    </div>
@endif
