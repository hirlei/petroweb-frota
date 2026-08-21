{{--
    Acesso — fiel ao mockup aprovado.

    Não existe cadastro público: usuário de SaaS de transportadora é criado
    pelo administrador do tenant ou pelo painel da HALC. Por isso não há link
    de "criar conta" e a rota `register` não existe no projeto.
--}}
<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Acessar — PetroWeb Frota</title>

    <script>
        (function () {
            var t = localStorage.getItem('frota-theme') || 'light';
            document.documentElement.setAttribute('data-theme', t);
            if (t === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Poppins:wght@800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }</style>
</head>

<body class="h-full bg-bg text-text">
<div class="flex h-full">

    {{-- ── Formulário ────────────────────────────────────── --}}
    <div class="flex flex-1 items-center justify-center px-6">
        <div class="w-full max-w-[380px]">
            <div class="mb-8 text-center">
                <x-brand-logo size="lg" class="mx-auto" />
                <div class="mt-2">
                    <span class="inline-block rounded bg-primary-soft px-2 py-0.5 text-[11px] font-extrabold uppercase
                                 tracking-[0.14em] text-primary" style="font-family:'Poppins',sans-serif">Frota</span>
                </div>
            </div>

            <h1 class="mb-1 text-[22px] font-bold tracking-tight text-text">Acessar o sistema</h1>
            <p class="mb-6 text-sm text-text-secondary">Entre com as credenciais da sua transportadora.</p>

            @if (session('status'))
                <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            @error('email')
                <div class="mb-4 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <x-icon name="alert-triangle" class="mt-px h-4 w-4 flex-shrink-0" />
                    <span>{{ $message }}</span>
                </div>
            @enderror

            <form method="POST" action="{{ route('login') }}" x-data="{ verSenha: false }">
                @csrf

                <x-input class="mb-4" label="E-mail" type="email" name="email" required autofocus
                         autocomplete="username" value="{{ old('email') }}" />

                <div class="mb-3.5">
                    <label class="mb-1.5 block text-sm font-medium text-text-secondary">
                        Senha<span class="ml-0.5 text-danger">*</span>
                    </label>
                    <div class="relative">
                        <input :type="verSenha ? 'text' : 'password'" name="senha" required
                               autocomplete="current-password"
                               class="w-full rounded-md border border-border bg-white py-2 pl-3 pr-10 text-sm text-text
                                      outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20
                                      dark:bg-surface-elevated">
                        <button type="button" @click="verSenha = !verSenha"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-text-muted hover:text-text"
                                aria-label="Mostrar ou ocultar a senha">
                            <x-icon name="eye" class="h-4 w-4" />
                        </button>
                    </div>
                    @error('senha')
                        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-5 flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm text-text-secondary">
                        <input type="checkbox" name="lembrar" value="1"
                               class="h-4 w-4 rounded border-border text-primary focus:ring-primary/30">
                        Manter conectado
                    </label>
                    @if (Route::has('senha.solicitar'))
                        <a href="{{ route('senha.solicitar') }}" class="text-sm text-primary hover:text-primary-hover">
                            Esqueci minha senha
                        </a>
                    @endif
                </div>

                <x-button type="submit" variant="primary" size="md" icon="lock" class="w-full">
                    Entrar
                </x-button>
            </form>

            <div class="mt-5 flex items-start gap-2 rounded-r-md border-l-[3px] border-info bg-blue-50 px-3 py-2.5
                        text-xs text-blue-800 dark:bg-blue-950/40 dark:text-blue-300">
                <x-icon name="shield-check" class="mt-px h-3.5 w-3.5 flex-shrink-0" />
                <span>Perfis com poder fiscal ou financeiro exigem <b>autenticação em duas etapas</b>. Você configura no primeiro acesso.</span>
            </div>

            <p class="mt-7 text-center text-xs text-text-muted">
                PetroWeb Frota · HALC · Versão {{ config('app.versao', '1.0') }}
            </p>
        </div>
    </div>

    {{-- ── Painel de marca ───────────────────────────────── --}}
    <div class="hidden w-[560px] flex-shrink-0 flex-col justify-between p-14 text-white lg:flex"
         style="background:linear-gradient(160deg,#1C1917 0%,#292524 55%,#412402 100%)">
        <div>
            <span class="inline-block rounded px-2 py-0.5 text-[11px] font-extrabold uppercase tracking-[0.14em]"
                  style="font-family:'Poppins',sans-serif;background:rgba(250,199,117,.16);color:#FAC775">
                Família PetroWeb
            </span>

            <h2 class="mb-2.5 mt-5 text-3xl font-extrabold leading-tight tracking-tight">
                O frete que fecha<br>o ciclo do custo.
            </h2>
            <p class="max-w-[400px] text-sm leading-relaxed text-stone-400">
                Cada litro, cada pneu, cada pedágio amarrado à viagem que os consumiu — e a viagem amarrada ao CT-e que a faturou.
            </p>

            @foreach ([
                ['files', 'CT-e e MDF-e sem redigitação', 'O CT-e nasce da ordem de coleta. O MDF-e nasce dos CT-e da viagem.'],
                ['route', 'Custo por quilômetro real', 'No dia seguinte à entrega você sabe se aquele frete deu lucro.'],
                ['fuel', 'Integrado ao PetroWeb Postos', 'Abastecimento em posto integrado entra no custo da viagem sozinho.'],
            ] as [$icone, $titulo, $texto])
                <div class="mt-6 flex items-start gap-3">
                    <span class="flex h-[34px] w-[34px] flex-shrink-0 items-center justify-center rounded-lg"
                          style="background:rgba(250,199,117,.16);color:#FAC775">
                        <x-icon name="{{ $icone }}" class="h-[17px] w-[17px]" />
                    </span>
                    <div>
                        <h4 class="text-sm font-semibold text-stone-50">{{ $titulo }}</h4>
                        <p class="mt-0.5 text-[13px] leading-relaxed text-stone-400">{{ $texto }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <p class="text-xs text-stone-500">© {{ date('Y') }} HALC · Todos os direitos reservados</p>
    </div>
</div>
</body>
</html>
