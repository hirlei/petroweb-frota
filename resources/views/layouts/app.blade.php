<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'PetroWeb Frota')</title>

    {{-- Anti-flash: aplica o tema ANTES do render.
         Chave PRÓPRIA (frota-theme): o PetroWeb usa autopostos-theme, e os dois
         sistemas ficam abertos lado a lado o tempo todo. Compartilhar a chave
         faria um mudar o tema do outro. --}}
    <script>
        (function () {
            var t = localStorage.getItem('frota-theme') || 'light';
            document.documentElement.setAttribute('data-theme', t);
            if (t === 'dark') document.documentElement.classList.add('dark');
        })();

        function toggleTheme() {
            var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            var next = isDark ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            document.documentElement.classList.toggle('dark', !isDark);
            localStorage.setItem('frota-theme', next);
        }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Poppins:wght@800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        .nav-link {
            display: flex; align-items: center; gap: 7px;
            padding: 7px 10px; border-radius: 6px;
            font-size: 0.75rem; font-weight: 500;
            color: rgb(var(--color-text-secondary)); text-decoration: none;
            transition: background 0.12s, color 0.12s, border-color 0.12s;
            border-right: 2px solid transparent;
        }
        .nav-link:hover { background: rgb(var(--color-surface-elevated)); color: rgb(var(--color-text)); }
        .nav-link.active {
            background: rgb(var(--color-primary-soft));
            color: rgb(var(--color-primary));
            font-weight: 600;
            border-right-color: rgb(var(--color-primary));
        }
        [x-cloak] { display: none !important; }
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }
    </style>
</head>

<body class="h-full flex overflow-hidden bg-bg text-text">

@php
    $filial  = \App\Support\TenantContext::filial();
    $empresa = \App\Support\TenantContext::empresa();
    $grupos  = config('navegacao');
@endphp

{{-- ══════════════ Sidebar ══════════════ --}}
<aside class="w-60 flex-shrink-0 flex flex-col bg-sidebar border-r border-border overflow-hidden"
       style="height:100vh">

    <div class="border-b border-border flex-shrink-0 px-3 py-4">
        <div class="flex flex-col items-center text-center py-1">
            <a href="{{ route('inicio') }}" class="block">
                <x-brand-logo size="md" />
            </a>
            <span class="mt-1.5 inline-block font-extrabold text-[11px] tracking-widest uppercase
                         text-primary bg-primary-soft rounded px-2 py-0.5"
                  style="font-family:'Poppins',sans-serif">Frota</span>
            <div class="text-xs text-text-muted leading-tight mt-1">
                Gestão de transporte<br>rodoviário de cargas
            </div>
        </div>

        @if($empresa)
            <div class="mt-2 w-full flex items-center gap-2 border border-border rounded-lg px-2.5 py-2
                        bg-surface text-sm">
                <span class="w-2 h-2 rounded-full bg-primary flex-shrink-0"></span>
                <span class="flex-1 min-w-0 text-left font-medium text-text truncate">
                    {{ $filial?->nome_fantasia ?? $filial?->razao_social ?? $empresa->nome_fantasia ?? $empresa->razao_social }}
                </span>
            </div>
        @endif
    </div>

    <nav class="flex-1 py-2 px-2 overflow-y-auto overflow-x-hidden">
        @foreach($grupos as $grupo)
            @if($grupo['solo'] ?? false)
                <a href="{{ Route::has($grupo['route']) ? route($grupo['route']) : '#' }}"
                   class="nav-link mb-1 {{ request()->routeIs($grupo['route']) ? 'active' : '' }}">
                    <x-icon name="{{ $grupo['icon'] }}" class="w-4 h-4 flex-shrink-0" />
                    <span>{{ $grupo['label'] }}</span>
                </a>
                @continue
            @endif

            <div class="mt-1">
                <div class="w-full flex items-center gap-2 px-2 py-2 rounded-md text-[11px] font-bold
                            uppercase tracking-wider text-text-muted select-none">
                    <span class="flex-1 min-w-0">{{ $grupo['label'] }}</span>
                </div>

                @foreach($grupo['items'] as $item)
                    @continue(isset($item['can']) && ! auth()->user()?->can($item['can']))
                    @php $existe = Route::has($item['route']); @endphp
                    <a href="{{ $existe ? route($item['route']) : '#' }}"
                       class="nav-link {{ $existe && request()->routeIs($item['route']) ? 'active' : '' }}
                              {{ $existe ? '' : 'opacity-40 cursor-not-allowed' }}"
                       @if(! $existe) title="Ainda não implementado" @endif>
                        <x-icon name="{{ $item['icon'] }}" class="w-4 h-4 flex-shrink-0" />
                        <span class="flex-1 min-w-0 truncate">{{ $item['label'] }}</span>
                        <span class="flex-shrink-0 text-[9px] text-text-muted tabular-nums opacity-70"
                              title="Rotina {{ $item['codigo'] }}">{{ $item['codigo'] }}</span>
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>
</aside>

{{-- ══════════════ Coluna principal ══════════════ --}}
<div class="flex-1 flex flex-col min-w-0">

    <header class="h-14 bg-surface border-b border-border flex items-center justify-between px-6 flex-shrink-0">
        <div class="flex items-center gap-1.5 text-sm text-text-secondary">
            @yield('breadcrumb')
        </div>

        <div class="flex items-center gap-2">
            {{-- Ambiente da SEFAZ é atributo DA FILIAL. Homologação precisa
                 gritar: documento emitido aqui não tem valor fiscal. --}}
            @if($filial?->emHomologacao())
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2 py-1 rounded
                             bg-primary-soft text-amber-700">
                    <x-icon name="alert-triangle" class="w-3.5 h-3.5" />
                    Homologação
                </span>
            @endif

            @if($filial)
                <span class="font-mono text-xs bg-surface-elevated border border-border text-text-secondary px-2 py-1 rounded">
                    {{ $filial->codigo }}
                </span>
            @endif

            <span class="text-sm text-text-secondary hidden sm:block">{{ auth()->user()?->name }}</span>

            <button onclick="toggleTheme()" type="button"
                    class="w-9 h-9 rounded-md flex items-center justify-center text-text-secondary
                           hover:bg-surface-elevated transition-colors"
                    title="Alternar tema">
                <x-icon name="sun" class="w-4 h-4 hidden dark:block" />
                <x-icon name="moon" class="w-4 h-4 block dark:hidden" />
            </button>

            <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
                <button type="button" @click="open = !open"
                        class="w-8 h-8 rounded-full bg-primary text-white text-sm font-bold
                               flex items-center justify-center flex-shrink-0"
                        aria-label="Menu do usuário">
                    {{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}
                </button>

                <div x-show="open" x-cloak @click.outside="open = false"
                     class="absolute right-0 mt-2 w-56 bg-surface border border-border rounded-lg
                            shadow-elevated overflow-hidden z-50">
                    <div class="px-3 py-3 border-b border-border">
                        <div class="text-sm font-semibold text-text truncate">{{ auth()->user()?->name }}</div>
                        <div class="text-xs text-text-muted truncate">{{ auth()->user()?->email }}</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="w-full flex items-center gap-2 px-3 py-2.5 text-sm text-red-600
                                       hover:bg-red-50 transition-colors">
                            <x-icon name="log-out" class="w-4 h-4 flex-shrink-0" />
                            Sair do sistema
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <main class="flex-1 overflow-y-auto p-6 bg-bg">
        {{ $slot ?? '' }}
        @yield('content')
    </main>
</div>

@livewireScripts
</body>
</html>
