@props(['semaforo'])

{{--
    B10 condensado — CHIP, não cartão.

    🔴 Geometria (Opção A): UMA linha horizontal de ~36px de altura, largura
    ao conteúdo. Bolinha de status + pergunta + valor em mono + delta pequeno,
    tudo lado a lado. A versão anterior empilhava pergunta sobre valor num
    bloco de largura fluida dentro de um grid de 2 colunas — isso é cartão,
    não chip, e foi o que divergiu do aprovado.

    O cartão grande (x-semaforo-card) segue existindo para a tela própria do
    B10; aqui o dashboard precisa da RESPOSTA, não da explicação.
--}}

@php
    $cores = [
        'verde'    => ['bolinha' => 'bg-green-500', 'texto' => 'text-green-700 dark:text-green-400'],
        'amarelo'  => ['bolinha' => 'bg-amber-500', 'texto' => 'text-amber-700 dark:text-amber-400'],
        'vermelho' => ['bolinha' => 'bg-red-500',   'texto' => 'text-red-700 dark:text-red-400'],
    ];
    $cor = $cores[$semaforo['cor'] ?? 'verde'] ?? $cores['verde'];

    $tendencia    = $semaforo['tendencia'] ?? '—';
    $temTendencia = $tendencia !== '—' && $tendencia !== '';

    $rota = $semaforo['rota'] ?? null;
    $href = $rota && \Illuminate\Support\Facades\Route::has($rota) ? route($rota) : null;
@endphp

<{{ $href ? 'a' : 'div' }}
    @if($href) href="{{ $href }}" @endif
    @if($href) title="{{ $semaforo['detalhe'] ?? '' }} · {{ $semaforo['rota_label'] ?? 'Abrir' }}" @endif
    {{-- `max-w-full` + `overflow-hidden` são o par obrigatório do
         `whitespace-nowrap`: sem eles, um chip de texto longo (ex.: "O caixa
         fecha? 0 de 0 turnos · maior diferença: R$ 0,00") estoura a largura
         da tela no mobile e joga overflow horizontal na página inteira —
         apareceu no screenshot de 390px. --}}
    class="inline-flex items-center gap-2 h-9 px-3 max-w-full overflow-hidden rounded-lg border border-border bg-surface whitespace-nowrap
           {{ $href ? 'hover:bg-surface-elevated hover:border-border-strong transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary' : '' }}"
>
    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $cor['bolinha'] }}" aria-hidden="true"></span>

    <span class="text-[11px] text-text-secondary flex-shrink-0">{{ $semaforo['pergunta'] }}</span>

    <span class="font-mono text-xs font-semibold truncate {{ $cor['texto'] }}">{{ $semaforo['valor'] }}</span>

    {{-- O delta é o primeiro a ser sacrificado quando falta largura: some em
         telas estreitas em vez de espremer pergunta e valor, que são o
         conteúdo essencial do chip. --}}
    @if($temTendencia)
        <span class="hidden sm:inline font-mono text-[10px] text-text-muted truncate max-w-[160px]">{{ $tendencia }}</span>
    @endif

    <span class="sr-only">{{ $semaforo['detalhe'] ?? '' }}</span>
</{{ $href ? 'a' : 'div' }}>
