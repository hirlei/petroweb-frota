<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title . ' — PetroWeb Frota' : trim($__env->yieldContent('title', 'PetroWeb Frota')) }}</title>

    {{-- Anti-flash: aplica o tema ANTES do render. Claro, Escuro ou Auto (segue o sistema), como no ERP.
         Chave PRÓPRIA (frota-theme): o ERP usa autopostos-theme e os dois sistemas ficam abertos
         lado a lado; compartilhar a chave faria um mudar o tema do outro. --}}
    <script>
        (function () {
            var salvo = localStorage.getItem('frota-theme') || 'light';
            var t = salvo === 'auto'
                ? (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                : salvo;
            document.documentElement.setAttribute('data-theme', t);
            document.documentElement.classList.toggle('dark', t === 'dark');
        })();

        function aplicarTema(salvo) {
            var t = salvo === 'auto'
                ? (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                : salvo;
            document.documentElement.setAttribute('data-theme', t);
            document.documentElement.classList.toggle('dark', t === 'dark');
            localStorage.setItem('frota-theme', salvo);
        }

        function toggleTheme() {
            aplicarTema(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
        }
    </script>

    <link rel="icon" type="image/png" href="{{ asset('img/petroweb-icone.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @include('partials.moldura-estilos')
</head>

@php
    /*
     * Moldura igual à do ERP (02/10/2026 · mockup "Frota igual ao ERP"):
     * menu lateral H6, barra superior com caminho e código, busca Ctrl K, sino,
     * tema e usuário com avatar laranja; fita de indicadores só no Dashboard.
     */
    $nav       = \App\Support\Navegacao::montar();
    $navAtual  = $nav['atual'];
    $navInicio = $nav['inicio'];
    $navGroups = $nav['grupos'];
    $navRecentes = \App\Support\Navegacao::recentes();
    $navFavoritos = \App\Support\Navegacao::favoritos();
    $navMapa   = $nav['mapa'];
    $alertas   = $nav['alertas'];
    $usuario   = auth()->user();
    $navNome   = $usuario?->name ?? 'Usuário';
    $navIniciais = \App\Support\Navegacao::iniciais($usuario?->name);
    $perfil    = $usuario && method_exists($usuario, 'getRoleNames') ? \Illuminate\Support\Str::ucfirst((string) $usuario->getRoleNames()->first()) : '';
    $filial    = \App\Support\TenantContext::filial();
    $empresa   = \App\Support\TenantContext::empresa();
    $nomeEmpresa = $empresa ? ($empresa->nome_fantasia ?: $empresa->razao_social) : null;
    $nomeFilial  = $filial ? ($filial->nome_fantasia ?: $filial->razao_social) : null;
    $temFita   = request()->routeIs('inicio');
    $versao    = config('app.versao', '1.0.0');
@endphp

<body class="h-full flex overflow-hidden">

{{-- Fundo do menu no celular --}}
<div x-data x-show="$store.menu.aberto" x-cloak @click="$store.menu.aberto = false"
     class="fixed inset-0 z-30 bg-black/40 md:hidden"></div>

{{-- ══════════════ Menu lateral H6 ══════════════ --}}
<aside x-data class="h6-menu fixed inset-y-0 left-0 z-40 w-60 flex flex-col bg-sidebar border-r border-border overflow-hidden
              transition-transform duration-200 md:static md:z-auto md:flex-shrink-0 md:translate-x-0"
       :class="$store.menu.aberto ? 'translate-x-0' : '-translate-x-full'"
       @keydown.escape.window="$store.menu.aberto = false" style="height:100vh;">

    {{-- Topo: logo, sublegenda, empresa/filial, campo e recentes --}}
    <div class="flex-shrink-0 px-3 pt-4 pb-3 border-b border-border">
        <a href="{{ route('inicio') }}" class="block" aria-label="PetroWeb Frota — Início">
            <x-brand-logo size="md" />
        </a>
        <p class="h6-sub">Gestão inteligente para transportadoras e frotas</p>

        @if ($nomeEmpresa)
            <div class="emp" title="{{ $nomeEmpresa }}{{ $nomeFilial ? ' · ' . $nomeFilial : '' }}">
                <span>{{ \Illuminate\Support\Str::upper($nomeEmpresa) }}{{ $nomeFilial && $nomeFilial !== $nomeEmpresa ? ' · ' . $nomeFilial : '' }}</span>
            </div>
        @endif
        {{-- Ambiente da SEFAZ é atributo DA FILIAL. Homologação precisa gritar: documento daqui não tem valor fiscal. --}}
        @if ($filial?->emHomologacao())
            <div class="emp-homolog"><x-icon name="alert-triangle" class="w-3.5 h-3.5" />Homologação · sem valor fiscal</div>
        @endif

        {{-- Campo "Código ou nome": código + Enter abre a rotina; texto + Enter abre a busca com o termo --}}
        <div x-data="campoCodigoH6(@js(array_map('strval', array_keys($navMapa))), @js(route('menu.ir', ['codigo' => '0000'])))" class="mt-2.5">
            <label class="h6-campo" :class="{ 'h6-campo-foco': foco }">
                <span class="h6-prompt" aria-hidden="true">›</span>
                <span class="h6-cursor" x-show="termo === '' && !foco" aria-hidden="true"></span>
                <input x-ref="campo" x-model="termo" type="text" autocomplete="off" spellcheck="false"
                       @focus="foco = true" @blur="foco = false"
                       @keydown.enter.prevent="enter()" @keydown.escape="termo = ''; $refs.campo.blur()"
                       placeholder="Código ou nome" aria-label="Código ou nome da rotina" class="h6-campo-input">
                <button type="button" class="h6-kbd" @click="window.dispatchEvent(new CustomEvent('abrir-paleta'))"
                        title="Abrir a busca (Ctrl K)">Ctrl K</button>
            </label>
            <p class="h6-dica" x-show="dica !== ''" x-cloak :class="{ 'h6-dica-erro': dicaErro }" x-text="dica"></p>
        </div>

        @if ($navRecentes !== [])
            <div class="mt-3">
                <p class="h6-secao">Recentes</p>
                <div class="h6-recentes">
                    @foreach ($navRecentes as $r)
                        <a href="{{ $r['url'] }}" class="h6-rec" title="{{ $r['codigo'] }} · {{ $r['label'] }}">
                            <b>{{ $r['codigo'] }}</b>
                            <span>{{ \Illuminate\Support\Str::before($r['label'], ' ') ?: $r['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Lista de rotinas --}}
    <nav class="flex-1 py-2 px-2 sidebar-scroll" style="overflow-y:auto; overflow-x:hidden;"
         x-data="navH6(@js($nav['aberto']), @js($navFavoritos), @js($navMapa), @js(route('menu.fixar', ['codigo' => '0000'])), @js($navAtual['item']['codigo'] ?? ''))">

        {{-- Favoritos: só aparece com alguma rotina fixada --}}
        <template x-if="favs.length > 0">
            <div>
                <p class="h6-secao h6-secao-lista">Favoritos</p>
                <template x-for="c in favs" :key="c">
                    <a :href="mapa[c] ? mapa[c].url : '#'" class="h6-rot" :class="{ 'h6-on': c === atual }" :title="'Rotina ' + c">
                        <span class="h6-cod" x-text="c"></span>
                        <span class="h6-nome" x-text="mapa[c] ? mapa[c].label : c"></span>
                        <span class="h6-fixar h6-fixado" role="button" title="Tirar dos favoritos" @click.prevent.stop="alternar(c)">★</span>
                    </a>
                </template>
                <div class="h6-divisor"></div>
            </div>
        </template>

        <p class="h6-secao h6-secao-lista">Rotinas</p>
        @if ($navInicio)
            {{-- Dashboard: só o ícone, sem código (é o início; não entra nos Recentes) --}}
            <a href="{{ $navInicio['url'] }}" class="h6-rot h6-rot-topo {{ $navInicio['ativo'] ? 'h6-on' : '' }}">
                <span class="h6-cod h6-cod-ic"><x-icon name="layout-dashboard" class="w-3 h-3" /></span>
                <span class="h6-nome">{{ $navInicio['label'] }}</span>
            </a>
        @endif

        @foreach ($navGroups as $group)
            @php $gKey = $group['key']; @endphp
            <button type="button" class="h6-grupo" @click="aberto = (aberto === @js($gKey) ? '' : @js($gKey))"
                    :aria-expanded="aberto === @js($gKey) ? 'true' : 'false'">
                <span class="h6-gic {{ $group['ativo'] ? 'h6-gic-ativo' : '' }}"><x-icon :name="$group['icone']" class="w-4 h-4" /></span>
                <span class="h6-nome">{{ $group['label'] }}</span>
                @if ($group['badge'] > 0)
                    <span class="h6-badge {{ $group['badge_nivel'] === 'urgente' ? 'h6-badge-urg' : 'h6-badge-pend' }}"
                          x-show="aberto !== @js($gKey)" title="{{ $group['badge'] }} pendência(s) neste grupo">{{ $group['badge'] > 9 ? '9+' : $group['badge'] }}</span>
                @endif
                <span class="h6-seta" :class="{ 'h6-seta-aberta': aberto === @js($gKey) }" aria-hidden="true">▸</span>
            </button>
            <div x-show="aberto === @js($gKey)" @if ($nav['aberto'] !== $gKey) x-cloak @endif class="h6-itens">
                @foreach ($group['items'] as $item)
                    <a href="{{ $item['url'] ?? '#' }}" class="h6-rot {{ $item['ativo'] ? 'h6-on' : '' }} {{ $item['url'] === null ? 'h6-bloq' : '' }}"
                       title="{{ $item['url'] === null ? 'Ainda não implementado' : 'Rotina ' . $item['codigo'] . ' — ' . $item['label'] }}">
                        <span class="h6-cod">{{ $item['codigo'] }}</span>
                        <span class="h6-nome">{{ $item['label'] }}</span>
                        @if ($item['badge'] !== null)
                            <span class="h6-badge {{ $item['badge']['nivel'] === 'urgente' ? 'h6-badge-urg' : 'h6-badge-pend' }}"
                                  title="{{ $item['badge']['hint'] }}">{{ $item['badge']['n'] > 9 ? '9+' : $item['badge']['n'] }}</span>
                        @endif
                        @if ($item['url'] !== null)
                            <span class="h6-fixar" role="button" :class="{ 'h6-fixado': favs.includes(@js($item['codigo'])) }"
                                  :title="favs.includes(@js($item['codigo'])) ? 'Tirar dos favoritos' : 'Fixar nos favoritos'"
                                  @click.prevent.stop="alternar(@js($item['codigo']))"
                                  x-text="favs.includes(@js($item['codigo'])) ? '★' : '☆'">{{ in_array($item['codigo'], $navFavoritos, true) ? '★' : '☆' }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>

    {{-- Rodapé: usuário e versão --}}
    <div class="flex-shrink-0 border-t border-border px-3 py-2.5 flex items-center gap-2">
        <span class="h6-avatar" title="{{ $navNome }}">{{ $navIniciais }}</span>
        <div class="min-w-0 flex-1">
            <p class="text-[12px] font-semibold text-text truncate">{{ $navNome }}</p>
            <p class="font-mono text-[10px] text-text-muted leading-tight">PetroWeb Frota v.{{ $versao }}</p>
        </div>
    </div>
</aside>

{{-- ══════════════ Coluna principal ══════════════ --}}
<div class="flex-1 flex flex-col min-w-0">

    <header class="tb flex-shrink-0">
        <div class="tb-esq">
            <button x-data @click="$store.menu.aberto = true" type="button" class="tb-ib tb-menu-mob" aria-label="Abrir menu">
                <x-icon name="menu" class="w-5 h-5" />
            </button>
            <nav class="tb-cam" aria-label="Caminho da rotina">
                @if ($navAtual !== null && request()->routeIs($navAtual['item']['route']))
                    <span class="tb-grupo">{{ $navAtual['grupo'] }}</span>
                    <span class="tb-sep" aria-hidden="true">›</span>
                    <span class="tb-cod">{{ $navAtual['item']['codigo'] }}</span>
                    <b class="tb-nome">{{ $navAtual['item']['label'] }}</b>
                @elseif ($navAtual !== null)
                    {{-- Dentro da rotina (novo/editar): o código volta para a lista --}}
                    <a href="{{ $navAtual['item']['url'] }}" class="tb-cod" title="{{ $navAtual['item']['codigo'] }} · {{ $navAtual['item']['label'] }}">{{ $navAtual['item']['codigo'] }}</a>
                    <a href="{{ $navAtual['item']['url'] }}" class="tb-grupo hover:underline">{{ $navAtual['item']['label'] }}</a>
                    @if (! empty($title) && $title !== $navAtual['item']['label'])
                        <span class="tb-sep" aria-hidden="true">›</span>
                        <b class="tb-nome">{{ $title }}</b>
                    @endif
                @elseif (request()->routeIs('inicio'))
                    <b class="tb-nome">Dashboard</b>
                @else
                    <span class="tb-nome">@yield('breadcrumb'){{ $title ?? '' }}</span>
                @endif
            </nav>
        </div>

        {{-- Fita de indicadores: só no Dashboard, como no ERP. Abaixo de 1280 px desce para a faixa sob a barra. --}}
        @if ($temFita)
            <div class="tb-fita">
                <livewire:painel.fita />
            </div>
        @endif

        <div class="tb-dir">
            <button x-data type="button" class="tb-busca" @click="window.dispatchEvent(new CustomEvent('abrir-paleta'))" title="Buscar (Ctrl K)">
                <x-icon name="search" class="w-4 h-4" />
                <span>Buscar rotina, cadastro, CPF/CNPJ, placa…</span>
                <kbd>Ctrl K</kbd>
            </button>

            <div class="tb-band hidden md:flex">
                <x-brand-logo size="lg" />
            </div>

            {{-- Ajuda --}}
            <div class="relative" x-data="{ aberto: false }" @keydown.escape.window="aberto = false">
                <button type="button" class="tb-ib" :class="aberto && 'tb-on'" @click="aberto = !aberto" title="Ajuda" aria-label="Ajuda">
                    <x-icon name="info" class="w-5 h-5" />
                </button>
                <div x-show="aberto" x-cloak @click.outside="aberto = false" class="tb-dd tb-ajuda">
                    <div class="tb-dd-t">Ajuda</div>
                    <p class="tb-at"><kbd>Ctrl K</kbd> Buscar qualquer coisa: rotina, pessoa, CPF/CNPJ, placa, viagem ou CT-e</p>
                    <p class="tb-at"><kbd>3020</kbd> + <kbd>Enter</kbd> no campo do menu abre a rotina pelo código</p>
                    <p class="tb-at"><kbd>☆</kbd> Fixa a rotina nos favoritos do menu</p>
                </div>
            </div>

            {{-- Sino: as mesmas pendências das bolinhas do menu --}}
            <div class="relative" x-data="{ aberto: false }" @keydown.escape.window="aberto = false">
                <button type="button" class="tb-ib" :class="aberto && 'tb-on'" @click="aberto = !aberto" title="Notificações" aria-label="Notificações">
                    <x-icon name="bell" class="w-5 h-5" />
                    @if (count($alertas) > 0)
                        <span class="tb-bd">{{ count($alertas) > 9 ? '9+' : count($alertas) }}</span>
                    @endif
                </button>
                <div x-show="aberto" x-cloak @click.outside="aberto = false" class="tb-dd tb-notif">
                    <div class="tb-dd-t">Notificações @if (count($alertas) > 0)<span>{{ count($alertas) }} {{ count($alertas) === 1 ? 'pendência' : 'pendências' }}</span>@endif</div>
                    @forelse ($alertas as $a)
                        <a href="{{ $a['url'] }}" class="tb-nt">
                            <i class="tb-dot tb-{{ $a['cor'] }}"></i>
                            <span class="tb-nt-tx"><b>{{ $a['titulo'] }}</b><small>{{ $a['rotina'] }} · {{ $a['codigo'] }}</small></span>
                        </a>
                    @empty
                        <p class="tb-vazio">Nenhuma pendência aberta.</p>
                    @endforelse
                </div>
            </div>

            {{-- Tema (atalho) --}}
            <button onclick="toggleTheme()" type="button" class="tb-ib hidden sm:flex" title="Alternar tema" aria-label="Alternar tema">
                <x-icon name="sun" class="w-4 h-4 icon-sun" />
                <x-icon name="moon" class="w-4 h-4 icon-moon" />
            </button>

            {{-- Usuário --}}
            <div class="relative" x-data="usuarioTopo()" @keydown.escape.window="aberto = false">
                <button type="button" class="tb-us" :class="aberto && 'tb-on'" @click="aberto = !aberto" aria-label="Menu do usuário">
                    <span class="tb-av">{{ $navIniciais }}</span>
                    <span class="tb-us-nm hidden sm:flex"><b>{{ $navNome }}</b>@if ($perfil)<small>{{ $perfil }}</small>@endif</span>
                    <span class="tb-car hidden sm:inline" aria-hidden="true">▾</span>
                </button>
                <div x-show="aberto" x-cloak @click.outside="aberto = false" class="tb-dd tb-user">
                    <div class="tb-u-cab">
                        <span class="tb-av">{{ $navIniciais }}</span>
                        <div class="min-w-0"><b>{{ $navNome }}</b><small class="truncate">{{ $usuario?->email }}</small>@if ($perfil)<small class="tb-pf">{{ $perfil }}</small>@endif</div>
                    </div>
                    @if ($nomeEmpresa)
                        <div class="tb-ui tb-ui-fixo">
                            <span class="tb-mini"><x-icon name="building" class="w-4 h-4" /></span>
                            <span>{{ $nomeEmpresa }}@if ($filial)<small>Filial {{ $filial->codigo }}{{ $nomeFilial && $nomeFilial !== $nomeEmpresa ? ' · ' . $nomeFilial : '' }}</small>@endif</span>
                        </div>
                    @endif
                    <div class="tb-ui tb-ui-fixo tb-fx">
                        <span class="tb-mini"><x-icon name="star" class="w-4 h-4" /></span>
                        <span>Rotinas fixadas
                            @if ($navFavoritos !== [])
                                <span class="tb-fxs">
                                    @foreach ($navFavoritos as $c)
                                        <a href="{{ $navMapa[$c]['url'] }}" title="{{ $navMapa[$c]['label'] }}">{{ $c }}</a>
                                    @endforeach
                                </span>
                            @else
                                <small>Nenhuma ainda: clique na estrela ao lado da rotina.</small>
                            @endif
                        </span>
                    </div>
                    <div class="tb-tema">
                        <small>Tema</small>
                        <div class="tb-seg" role="group" aria-label="Tema">
                            <button type="button" :class="tema === 'light' && 'tb-seg-on'" @click="usar('light')">Claro</button>
                            <button type="button" :class="tema === 'dark' && 'tb-seg-on'" @click="usar('dark')">Escuro</button>
                            <button type="button" :class="tema === 'auto' && 'tb-seg-on'" @click="usar('auto')">Auto</button>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="tb-ui tb-sair"><span class="tb-mini"><x-icon name="log-out" class="w-4 h-4" /></span>Sair do sistema</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <main class="flex-1 overflow-y-auto p-6 bg-bg sidebar-scroll {{ $temFita ? 'tem-fita' : '' }}">
        @if (session('aviso'))
            <p class="tb-aviso">{{ session('aviso') }}</p>
        @endif
        {{ $slot ?? '' }}
        @yield('content')
    </main>
</div>

<livewire:busca.paleta />

@livewireScripts
<script>
    document.addEventListener('alpine:init', function () {
        Alpine.store('menu', { aberto: false });

        // Campo do menu: "3020" + Enter abre a rotina; texto + Enter abre a busca com o termo.
        Alpine.data('campoCodigoH6', function (codigos, urlIr) {
            return {
                termo: '', foco: false, dica: '', dicaErro: false,
                enter() {
                    var t = this.termo.trim();
                    if (t === '') return;
                    if (/^\d{4}$/.test(t)) {
                        if (codigos.indexOf(t) === -1) { this.dica = 'Rotina ' + t + ' não existe ou você não tem acesso.'; this.dicaErro = true; return; }
                        this.dica = 'Abrindo a ' + t + '…'; this.dicaErro = false;
                        window.location.href = urlIr.replace('0000', t);
                        return;
                    }
                    window.dispatchEvent(new CustomEvent('abrir-paleta', { detail: { termo: t } }));
                    this.termo = '';
                },
            };
        });

        // Lista do menu: grupo aberto e Favoritos (★) salvos no usuário.
        Alpine.data('navH6', function (aberto, favs, mapa, urlFixar, atual) {
            return {
                aberto: aberto, favs: favs, mapa: mapa, atual: atual,
                alternar(c) {
                    var antes = this.favs.slice();
                    this.favs = this.favs.indexOf(c) === -1 ? this.favs.concat([c]) : this.favs.filter(function (x) { return x !== c; });
                    fetch(urlFixar.replace('0000', c), {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                    }).then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                      .then((j) => { this.favs = j.favoritos; })
                      .catch(() => { this.favs = antes; });
                },
            };
        });

        // Paleta Ctrl K.
        Alpine.data('paletaFrota', function () {
            return {
                aberta: false, sel: 0,
                abrir(termo) {
                    this.aberta = true; this.sel = 0;
                    if (termo !== undefined) this.$wire.set('termo', termo);
                    this.$nextTick(() => this.$refs.campo.focus());
                },
                fechar() { this.aberta = false; },
                itens() { return this.$refs.lista ? this.$refs.lista.querySelectorAll('[data-pk-item]') : []; },
                mover(d) { var n = this.itens().length; if (n) this.sel = (this.sel + d + n) % n; },
                abrirSelecionado() { var it = this.itens()[this.sel]; if (it) window.location.href = it.getAttribute('href'); },
            };
        });

        // Menu do usuário: tema Claro / Escuro / Auto.
        Alpine.data('usuarioTopo', function () {
            return {
                aberto: false, tema: localStorage.getItem('frota-theme') || 'light',
                usar(t) { this.tema = t; aplicarTema(t); },
            };
        });
    });
</script>
</body>
</html>
