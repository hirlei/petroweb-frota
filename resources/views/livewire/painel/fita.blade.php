{{-- Fita de indicadores (igual à do ERP, 02/10/2026). Só no Dashboard. Atualiza a cada 60 s;
     passe o mouse para pausar; clique abre a rotina. Estilos em partials/moldura-estilos. --}}
@php
    $spark = function (array $pts): string {
        if (count($pts) < 2) {
            return '';
        }
        $w = 42; $h = 14; $mx = max($pts); $mn = min($pts); $n = count($pts) - 1;
        $d = [];
        foreach (array_values($pts) as $i => $v) {
            $x = $i / $n * $w;
            $y = $h - 1 - ($mx > $mn ? ($v - $mn) / ($mx - $mn) : 0.5) * ($h - 2);
            $d[] = ($i ? 'L' : 'M') . number_format($x, 1, '.', '') . ' ' . number_format($y, 1, '.', '');
        }

        return implode(' ', $d);
    };
@endphp
<div class="fita-raiz" wire:poll.60s>
    <div class="fita" role="region" aria-label="Indicadores da frota">
        <span class="fita-vivo" title="Ao vivo · atualizado {{ $atualizado->format('H:i:s') }}"><i></i><span class="fita-vivo-tx">Ao vivo</span></span>

        <div class="fita-jan">
            @if ($itens === [])
                <span class="fita-vazia">Sem indicadores para mostrar ainda.</span>
            @else
                <div class="fita-trilho">
                    @foreach ([0, 1] as $copia)
                        @foreach ($itens as $n => $it)
                            @php $tag = ! empty($it['url']) ? 'a' : 'span'; @endphp
                            <{{ $tag }} wire:key="fita-{{ $copia }}-{{ $n }}" class="fita-q"
                                @if ($tag === 'a') href="{{ $it['url'] }}" @endif
                                @if ($copia === 1) aria-hidden="true" tabindex="-1" @endif
                                title="{{ $it['titulo'] ?? '' }}">
                                @switch($it['tipo'])
                                    @case('combustivel')
                                        <span class="fita-pd" style="background: {{ $it['cor'] }}">{{ $it['sigla'] }}</span>
                                        <span class="fita-v">{{ $it['valor'] }}</span>
                                        <span class="fita-d fita-{{ $it['delta']['cls'] }}">{{ $it['delta']['txt'] }}</span>
                                        @break
                                    @case('meta')
                                        <span class="fita-sg">{{ $it['rotulo'] }}</span>
                                        <span class="fita-meta"><i style="width: {{ $it['barra'] }}%"></i></span>
                                        <span class="fita-v">{{ $it['valor'] }}</span>
                                        <span class="fita-d fita-{{ $it['delta']['cls'] }}">{{ $it['delta']['txt'] }}</span>
                                        @break
                                    @case('sefaz')
                                        <span class="fita-sg">{{ $it['rotulo'] }}</span>
                                        @foreach ($it['servicos'] as $sv)
                                            <span class="fita-st"><i class="fita-{{ $sv['cls'] }}"></i>{{ $sv['nome'] }} {{ $sv['txt'] }}</span>
                                        @endforeach
                                        @break
                                    @default
                                        <span class="fita-sg">{{ $it['rotulo'] }}</span>
                                        <span class="fita-v">{{ $it['valor'] }}</span>
                                        <span class="fita-d fita-{{ $it['delta']['cls'] }}">{{ $it['delta']['txt'] }}</span>
                                @endswitch
                                @if (! empty($it['spark']) && ($d = $spark($it['spark'])) !== '')
                                    @php $corSpark = end($it['spark']) >= reset($it['spark']) ? '#4ade80' : '#fda4af'; @endphp
                                    <svg class="fita-sp" viewBox="0 0 42 14" aria-hidden="true"><path d="{{ $d }}" fill="none" stroke="{{ $corSpark }}" stroke-width="1.6" /></svg>
                                @endif
                            </{{ $tag }}>
                        @endforeach
                    @endforeach
                </div>
            @endif
        </div>

        <span class="fita-fim">Atualizado <b>{{ $atualizado->format('H:i:s') }}</b></span>
    </div>
</div>
