{{-- Paleta Ctrl K (igual ao ERP, 02/10/2026): abre com Ctrl K, pelo botão da barra ou pelo campo do menu.
     ↑ ↓ escolhem, Enter abre, Esc fecha. O item escolhido fica em laranja claro, como no ERP. --}}
<div x-data="paletaFrota()" x-on:abrir-paleta.window="abrir($event.detail?.termo ?? '')"
     x-on:keydown.window.ctrl.k.prevent="abrir('')" x-on:keydown.window.meta.k.prevent="abrir('')">
    {{-- Sem teleport: a caixa é position:fixed e o componente fica no fim do <body>, fora da barra. --}}
        <div x-show="aberta" x-cloak class="tb-modal-bg" style="align-items: flex-start; padding-top: 12vh;" @click.self="fechar()"
             @keydown.escape.window="fechar()">
            <div class="pk-caixa" role="dialog" aria-modal="true" aria-label="Buscar">
                <div class="pk-campo">
                    <x-icon name="search" class="w-5 h-5 text-text-muted" />
                    <input x-ref="campo" type="text" wire:model.live.debounce.250ms="termo" autocomplete="off" spellcheck="false"
                           placeholder="Buscar rotina, pessoa, CPF/CNPJ, placa, viagem ou CT-e…" @input="sel = 0"
                           @keydown.arrow-down.prevent="mover(1)" @keydown.arrow-up.prevent="mover(-1)" @keydown.enter.prevent="abrirSelecionado()">
                    <span class="pk-kbd">Esc</span>
                </div>
                <div class="pk-filtros">
                    @foreach (['tudo' => 'Tudo', 'rotinas' => 'Rotinas', 'cadastros' => 'Cadastros', 'operacao' => 'Viagens e CT-e'] as $k => $rotulo)
                        <button type="button" class="pk-filtro {{ $filtro === $k ? 'pk-on' : '' }}" wire:click="usarFiltro('{{ $k }}')">{{ $rotulo }}</button>
                    @endforeach
                </div>
                <div class="pk-res" x-ref="lista">
                    @php $i = 0; @endphp
                    @forelse ($grupos as $g)
                        <p class="pk-grupo">{{ $g['grupo'] }}</p>
                        @foreach ($g['itens'] as $it)
                            <a href="{{ $it['url'] }}" class="pk-it" data-pk-item :class="sel === {{ $i }} && 'pk-sel'" @mouseenter="sel = {{ $i }}">
                                <span class="pk-k">{{ $it['k'] }}</span>
                                <span class="pk-tx"><span>{{ $it['titulo'] }}</span><small>{{ $it['sub'] }}</small></span>
                                <span class="pk-tp">{{ $it['tipo'] }}</span>
                            </a>
                            @php $i++; @endphp
                        @endforeach
                    @empty
                        <p class="pk-vazio">Nada encontrado para “{{ $termo }}”.</p>
                    @endforelse
                    @if (mb_strlen(trim($termo)) === 1)
                        <p class="pk-aviso">Digite ao menos 2 letras para buscar nos cadastros.</p>
                    @endif
                </div>
                <div class="pk-rodape">
                    <span class="pk-so-teclado"><span class="pk-kbd">↑</span> <span class="pk-kbd">↓</span> Escolher</span>
                    <span class="pk-so-teclado"><span class="pk-kbd">Enter</span> Abrir</span>
                    <span><span class="pk-kbd">3020</span> + <span class="pk-kbd">Enter</span> no campo do menu abre a rotina</span>
                </div>
            </div>
        </div>
</div>
