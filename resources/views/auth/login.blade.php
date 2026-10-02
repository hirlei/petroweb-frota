{{--
    Acesso — igual à tela de login do ERP (02/10/2026, mockup "Frota igual ao ERP"):
    faixa azul com o letreiro "A frota inteira num sistema só.", quem atendemos e o que o
    Frota faz; formulário à direita com o botão laranja. CSS copiado do login do ERP.

    Não existe cadastro público: usuário de SaaS de transportadora é criado pelo
    administrador do tenant ou pelo painel da HALC. Por isso não há "criar conta".
    Campos (email, senha, lembrar), rota e CSRF são os mesmos de antes.
--}}
<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Entrar — PetroWeb Frota</title>
    <link rel="icon" type="image/png" href="{{ asset('img/petroweb-icone.png') }}">

    <script>
        (function () {
            var salvo = localStorage.getItem('frota-theme') || 'light';
            var t = salvo === 'auto'
                ? (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                : salvo;
            document.documentElement.setAttribute('data-theme', t);
            document.documentElement.classList.toggle('dark', t === 'dark');
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Barlow+Condensed:wght@700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root{
            --azul:#1A3DA3; --laranja:#FF6200; --laranja-claro:#FF8A3D; --laranja-texto:#FFB07A;
            --ink:#0F1A3A; --ink-soft:#4B5568; --muted:#6B7385; --line:#E3E7EF;
            --info-bg:#FFFFFF; --sol-ic-bg:#EEF2FC; --sol-ic:#1A3DA3; --inc-bg:#FFF4EC; --inc-borda:#FFD9BF;
            --form-bg:#F5F7FB; --card-border:#E3E7EF; --input-bg:#FFFFFF; --input-border:#D7DDE8;
            --foco:#1A3DA3; --anel:rgba(26,61,163,.16); --link:#1A3DA3; --danger:#dc2626;
        }
        html.dark{
            --ink:#EEF1F7; --ink-soft:#B4BBC9; --muted:#8A92A3; --line:#2A2F3A;
            --info-bg:#15181E; --sol-ic-bg:rgba(157,180,245,.12); --sol-ic:#9DB4F5; --inc-bg:rgba(255,98,0,.10); --inc-borda:rgba(255,98,0,.28);
            --form-bg:#101318; --card-border:#2A2F3A; --input-bg:#181C23; --input-border:#323846;
            --foco:#7D97EC; --anel:rgba(125,151,236,.22); --link:#9DB4F5; --danger:#f87171;
        }
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:var(--form-bg);color:var(--ink)}
        .mono{font-family:'JetBrains Mono',monospace}
        .wrap{display:grid;grid-template-columns:1fr 430px;min-height:100vh}

        .info{display:flex;flex-direction:column;background:var(--info-bg)}
        .topo{background:var(--azul);color:#fff;padding:38px 56px 34px;position:relative}
        .topo::after{content:"";position:absolute;left:0;right:0;bottom:0;height:8px;background:var(--laranja)}
        .logo-g{height:82px;width:auto;display:block}
        .letreiro{margin-top:28px;font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:92px;line-height:.92;
            letter-spacing:-.3px;text-transform:uppercase;text-wrap:balance}
        .letreiro em{font-style:normal;color:var(--laranja-claro)}
        .lead{margin-top:16px;font-size:17px;line-height:1.55;color:#D6E0FF;max-width:720px}
        .rotulo{font-size:11.5px;font-weight:700;letter-spacing:.1em;text-transform:uppercase}
        .publico{margin-top:22px;margin-bottom:6px;display:flex;gap:10px;flex-wrap:wrap;align-items:center}
        .publico .rotulo{color:var(--laranja-texto);margin-right:4px}
        .chip{display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);
            border-radius:99px;padding:7px 14px;font-size:14px;font-weight:600}
        .chip svg{width:16px;height:16px;color:var(--laranja-texto)}
        .chip.on{background:#fff;color:var(--azul);border-color:#fff} .chip.on svg{color:var(--laranja)}

        .baixo{flex:1;padding:26px 56px;display:flex;flex-direction:column;justify-content:center}
        .baixo .rotulo{color:var(--laranja)}
        .solucoes{margin-top:14px;display:grid;grid-template-columns:repeat(3,1fr);gap:20px 30px}
        .sol{display:flex;gap:12px}
        .sol-ic{flex-shrink:0;width:36px;height:36px;border-radius:10px;background:var(--sol-ic-bg);color:var(--sol-ic);
            display:flex;align-items:center;justify-content:center}
        .sol-ic svg{width:18px;height:18px}
        .sol b{display:block;font-size:15px} .sol p span{display:block;margin-top:3px;font-size:13px;line-height:1.45;color:var(--ink-soft)}
        .incluso{margin-top:30px;display:grid;grid-template-columns:auto 1fr 1fr;align-items:center;gap:14px}
        .inc{height:100%;display:flex;align-items:center;gap:10px;background:var(--inc-bg);border:1px solid var(--inc-borda);
            border-radius:12px;padding:9px 14px 9px 9px}
        .inc .sol-ic{width:30px;height:30px;border-radius:8px;background:var(--laranja);color:#fff}
        .inc .sol-ic svg{width:16px;height:16px}
        .inc b{font-size:14px} .inc p span{display:block;font-size:12px;color:var(--ink-soft);margin-top:1px}

        .auth{position:relative;display:flex;flex-direction:column;justify-content:center;padding:0 52px;
            background:var(--form-bg);border-left:1px solid var(--line)}
        .tt{position:absolute;top:18px;right:18px;width:34px;height:34px;border-radius:9px;border:1px solid var(--card-border);
            background:var(--input-bg);color:var(--muted);display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:5}
        .tt:focus-visible{outline:2px solid var(--foco);outline-offset:2px}
        html:not(.dark) .tt .icon-sun{display:none} html.dark .tt .icon-moon{display:none}
        .logo-m{display:none;margin-bottom:22px}
        .card-title{font-size:22px;font-weight:700;letter-spacing:-.3px;color:var(--ink)}
        .card-sub{font-size:13.5px;color:var(--muted);margin:4px 0 22px}
        .rot-campo{display:block;font-size:12.5px;font-weight:600;color:var(--ink-soft);margin-bottom:6px}
        .field{display:flex;align-items:center;gap:10px;height:48px;padding:0 14px;border-radius:11px;background:var(--input-bg);
            border:1.5px solid var(--input-border);transition:border-color .15s,box-shadow .15s}
        .field:focus-within{border-color:var(--foco);box-shadow:0 0 0 4px var(--anel)}
        .field.err{border-color:var(--danger)}
        .field > svg{color:var(--muted);width:19px;height:19px;flex-shrink:0}
        .field input{flex:1;min-width:0;border:none;background:transparent;font:500 14.5px 'Plus Jakarta Sans',sans-serif;color:var(--ink);outline:none;box-shadow:none;padding:0}
        .field input:focus{box-shadow:none}
        .toggle-pass{background:none;border:none;padding:4px;margin:0;display:flex;align-items:center;cursor:pointer;color:var(--muted);border-radius:6px}
        .toggle-pass svg{width:19px;height:19px}
        .toggle-pass:hover{color:var(--ink)}
        .err-msg{font-size:12px;line-height:16px;min-height:16px;color:var(--danger);margin:5px 0 12px 2px}
        .row{display:flex;align-items:center;justify-content:space-between;margin:2px 0 20px}
        .remember{display:flex;align-items:center;gap:8px;font-size:13.5px;color:var(--ink-soft);cursor:pointer}
        .remember input{width:16px;height:16px;accent-color:var(--azul)}
        .forgot{font-size:13.5px;font-weight:600;color:var(--link);text-decoration:none}
        .forgot:hover{text-decoration:underline}
        .btn{width:100%;height:52px;border:none;border-radius:12px;color:#fff;font:700 16px 'Plus Jakarta Sans',sans-serif;cursor:pointer;
            background:var(--laranja);box-shadow:0 8px 18px rgba(255,98,0,.22);transition:background .15s ease;
            display:flex;align-items:center;justify-content:center;gap:10px}
        .btn:hover{background:#EE5B00}
        .btn:focus-visible{outline:2px solid var(--ink);outline-offset:2px}
        .btn:disabled{cursor:default;opacity:.85}
        .btn .spinner{width:17px;height:17px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;
            animation:spin .7s linear infinite;display:none}
        .btn.loading .spinner{display:inline-block}
        @keyframes spin{to{transform:rotate(360deg)}}
        .note{display:flex;justify-content:center;align-items:center;gap:6px;font-size:12px;color:var(--muted);margin-top:14px}
        .note svg{color:#16A34A;width:15px;height:15px}
        .selos{display:flex;justify-content:center;gap:14px;flex-wrap:wrap;margin-top:16px;font-size:11px;color:var(--muted)}
        .selos b{color:var(--ink-soft);font-weight:600}
        .status{margin-bottom:16px;padding:11px 14px;border-radius:10px;font-size:13px;
            background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.3);color:#15803d}
        html.dark .status{color:#4ade80}
        .foot{position:absolute;bottom:20px;left:0;right:0;text-align:center;font-size:11px;color:var(--muted)}
        .fade{animation:fade .6s ease both} @keyframes fade{from{opacity:0;transform:translateY(6px)}}
        @media (prefers-reduced-motion: reduce){.fade,.btn .spinner{animation:none}}
        @media (max-width:1100px){
            .wrap{grid-template-columns:1fr}
            .auth{order:-1;border-left:0;padding:28px 20px 40px}
            .foot{position:static;margin-top:18px}
            .logo-m{display:block}
            .topo{padding:28px 20px 26px} .topo .logo-g{display:none}
            .letreiro{margin-top:0;font-size:52px} .lead{font-size:15px}
            .baixo{padding:22px 20px} .solucoes{grid-template-columns:1fr;gap:16px}
            .incluso{grid-template-columns:1fr;margin-top:22px}
        }
    </style>
</head>
<body class="h-full">
@php
    $solucoes = [
        ['route', 'Coleta e viagem', 'Ordem de coleta, viagem e entrega com comprovante'],
        ['receipt', 'CT-e e MDF-e', 'Emissão, eventos e vale-pedágio direto na SEFAZ'],
        ['truck', 'Frota', 'Veículos, composições, motoristas e vencimentos'],
        ['wrench', 'Manutenção e abastecimento', 'Plano preventivo, consumo e custo por veículo'],
        ['map', 'Mapa e rastreamento', 'Rota pela rodovia e posição do caminhão pelo GPS'],
        ['link', 'Ligado ao ERP', 'Abastecimentos do posto e financeiro no mesmo PetroWeb'],
    ];
@endphp
<div class="wrap">
    {{-- ═══════════════ ESQUERDA (apresentação) ═══════════════ --}}
    <section class="info fade" aria-label="Sobre o PetroWeb Frota">
        <div class="topo">
            <img class="logo-g" src="{{ asset('img/petroweb-frota-clara.png') }}" alt="PetroWeb Frota">
            <div class="letreiro">A frota inteira<br>num sistema <em>só.</em></div>
            <p class="lead">O PetroWeb Frota liga a coleta, a viagem, a entrega e o fiscal num sistema só. Cada frete é lançado uma vez e segue sozinho para o CT-e, o MDF-e e o custo da viagem.</p>
            <div class="publico">
                <span class="rotulo">Quem atendemos</span>
                <span class="chip"><x-icon name="fuel" class="w-4 h-4" />Postos de combustíveis</span>
                <span class="chip"><x-icon name="shopping-bag" class="w-4 h-4" />Lojas de conveniência</span>
                <span class="chip on"><x-icon name="truck" class="w-4 h-4" />Transportadoras</span>
            </div>
        </div>
        <div class="baixo">
            <p class="rotulo">O que o Frota faz</p>
            <div class="solucoes">
                @foreach ($solucoes as [$icone, $titulo, $texto])
                    <div class="sol">
                        <span class="sol-ic"><x-icon :name="$icone" class="w-4 h-4" /></span>
                        <p><b>{{ $titulo }}</b><span>{{ $texto }}</span></p>
                    </div>
                @endforeach
            </div>
            <div class="incluso">
                <span class="rotulo">Incluindo</span>
                <div class="inc">
                    <span class="sol-ic"><x-icon name="map-pin" class="w-4 h-4" /></span>
                    <p><b>Custo por viagem</b><span>Receita dos CT-e menos diesel, pedágio e diárias</span></p>
                </div>
                <div class="inc">
                    <span class="sol-ic"><x-icon name="message-square" class="w-4 h-4" /></span>
                    <p><b>IA integrada</b><span>Pergunte em português e receba a resposta com os dados da sua frota</span></p>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════ DIREITA (formulário) ═══════════════ --}}
    <main class="auth">
        <button class="tt" type="button" title="Alternar tema" aria-label="Alternar tema claro/escuro"
                onclick="(function(){var d=!document.documentElement.classList.contains('dark');document.documentElement.classList.toggle('dark',d);document.documentElement.setAttribute('data-theme',d?'dark':'light');localStorage.setItem('frota-theme',d?'dark':'light');})()">
            <x-icon name="sun" class="w-4 h-4 icon-sun" />
            <x-icon name="moon" class="w-4 h-4 icon-moon" />
        </button>

        <div class="logo-m"><x-brand-logo size="lg" /></div>
        <div class="card-title">Acesso à sua transportadora</div>
        <div class="card-sub">Entre com o e-mail e a senha cadastrados.</div>

        @if (session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}" id="form-login">
            @csrf

            <label class="rot-campo" for="email">E-mail</label>
            <div class="field @error('email') err @enderror">
                <x-icon name="mail" class="w-5 h-5" />
                <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="seu@email.com.br"
                       required autofocus autocomplete="username" aria-describedby="email-error">
            </div>
            <p class="err-msg" id="email-error">{{ $errors->first('email') }}</p>

            <label class="rot-campo" for="senha">Senha</label>
            <div class="field @error('senha') err @enderror">
                <x-icon name="lock" class="w-5 h-5" />
                <input id="senha" type="password" name="senha" placeholder="Sua senha"
                       required autocomplete="current-password" aria-describedby="senha-error">
                <button type="button" class="toggle-pass" id="ver-senha" aria-label="Mostrar senha">
                    <x-icon name="eye" class="w-5 h-5" />
                </button>
            </div>
            <p class="err-msg" id="senha-error">{{ $errors->first('senha') }}</p>

            <div class="row">
                <label class="remember"><input type="checkbox" name="lembrar" value="1"> Lembrar-me</label>
                @if (Route::has('senha.solicitar'))
                    <a href="{{ route('senha.solicitar') }}" class="forgot">Esqueci minha senha</a>
                @endif
            </div>

            <button type="submit" class="btn" id="btn-entrar">
                <span class="spinner" aria-hidden="true"></span>
                <span>Acessar sistema</span>
            </button>
            <p class="note"><x-icon name="shield-check" class="w-4 h-4" />Protegido por autenticação de 2 fatores.</p>
            <div class="selos"><span><b>Google</b> Authenticator</span><span><b>Microsoft</b> Authenticator</span><span><b>SSL</b> Conexão segura</span></div>
        </form>

        <div class="foot mono">© HALC · PetroWeb Frota v.{{ config('app.versao', '1.0.0') }}</div>
    </main>
</div>
<script>
    // Sem Alpine nesta tela (o Livewire não carrega aqui): JS puro, como no login do ERP.
    (function () {
        var senha = document.getElementById('senha');
        var ver = document.getElementById('ver-senha');
        ver.addEventListener('click', function () {
            var mostrando = senha.type === 'text';
            senha.type = mostrando ? 'password' : 'text';
            ver.setAttribute('aria-label', mostrando ? 'Mostrar senha' : 'Ocultar senha');
        });
        var btn = document.getElementById('btn-entrar');
        document.getElementById('form-login').addEventListener('submit', function () {
            btn.classList.add('loading');
            btn.disabled = true;
        });
    })();
</script>
</body>
</html>
