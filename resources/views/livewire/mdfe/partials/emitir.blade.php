{{-- Cartão "Ao clicar em emitir" da tela do MDF-e (4020): as três etapas e o botão. --}}
@php
    $fmt = fn ($v) => number_format((float) $v, 2, ',', '.');
    $mod = $this->modalidadeCiot;
    $ciot = $this->ciotAtual;
    $vale = $this->valeAtual;
    $d = $this->divisaoCiot;
    $etapas = [];

    if ($ciot?->valido()) {
        $etapas[] = ['Usa o CIOT da viagem', $ciot->numeroFormatado()];
    } elseif ($mod === 'dispensado') {
        $etapas[] = ['Sem CIOT', 'Carga própria em veículo próprio'];
    } elseif ($mod === 'informado') {
        $etapas[] = ['Guarda o CIOT informado', 'Número da transportadora terceira'];
    } else {
        $etapas[] = [$ciot?->status === 'recusado' ? 'Reenvia o CIOT' : 'Registra o CIOT',
            config('ciot.modalidades.' . $mod) . ($mod === 'ipef' && $d && $d['adiantamento'] > 0 ? ' · adianta R$ ' . $fmt($d['adiantamento']) : '')];
    }

    if ($vale) {
        $etapas[] = ['Usa o vale da viagem', $vale->dispensado ? 'Dispensado' : 'Compra ' . $vale->idvpo];
    } else {
        $etapas[] = match ($valeModo) {
            'informar' => ['Guarda o vale-pedágio', 'Comprado pelo embarcador'],
            'dispensar' => ['Dispensa o vale-pedágio', \App\Models\ValePedagio::MOTIVOS_DISPENSA[$valeMotivo] ?? ''],
            default => ['Compra o vale-pedágio', $this->cotacaoVale !== null ? 'Estimativa R$ ' . $fmt($this->cotacaoVale) : ''],
        };
    }

    $etapas[] = ['Envia o MDF-e à SEFAZ', 'Com o CIOT e o vale no manifesto'];
@endphp
<x-card padding="none" class="overflow-hidden">
    <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
        <x-icon name="upload" class="h-4 w-4 text-text-secondary" />
        <h2 class="text-sm font-semibold text-text">Ao clicar em emitir</h2>
        @if ($this->ambiente === 2)<x-badge variant="warning" class="ml-auto text-[10px]">Homologação</x-badge>@endif
    </div>
    <div class="px-5 py-4">
        <ol class="flex flex-col gap-2.5">
            @foreach ($etapas as $i => [$titulo, $detalhe])
                <li class="flex items-start gap-2.5">
                    <span class="flex h-[22px] w-[22px] flex-shrink-0 items-center justify-center rounded-full bg-[var(--h6-cod-bg)] font-mono text-[11px] font-semibold text-[var(--h6-azul-tx)]">{{ $i + 1 }}</span>
                    <div><b class="block text-[13px] font-semibold text-text">{{ $titulo }}</b><small class="text-[11.5px] text-text-secondary">{{ $detalhe }}</small></div>
                </li>
            @endforeach
        </ol>

        @if ($this->travaCiot === 'avisa')
            <p class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-[11.5px] text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                Se o CIOT falhar, ainda dá para emitir em produção até {{ \Illuminate\Support\Carbon::parse(config('ciot.obrigatorio_desde'))->format('d/m/Y') }}. Depois disso a SEFAZ rejeita (684).
            </p>
        @endif

        @can('emitir', $mdfe)
            <x-button class="mt-4 w-full justify-center" variant="primary" icon="file-text" wire:click="emitir" wire:loading.attr="disabled" wire:target="emitir">
                {{ $mdfe->status === 'rejeitado' ? 'Emitir de novo' : 'Emitir MDF-e' }}
            </x-button>
        @else
            <p class="mt-4 text-xs text-text-muted">Você não tem permissão para emitir MDF-e.</p>
        @endcan
        <p class="mt-2 text-[11.5px] leading-relaxed text-text-secondary">
            Se a SEFAZ rejeitar, CIOT e vale ficam guardados na viagem e o reenvio usa os mesmos — nada é registrado ou pago duas vezes. Se o CIOT falhar, o MDF-e nem é enviado.
        </p>
    </div>
</x-card>

{{-- Enquanto emite --}}
<div wire:loading.flex wire:target="emitir" class="fixed inset-0 z-[90] items-start justify-center bg-[rgba(15,26,58,.45)] px-4 pt-[12vh]">
    <div class="w-full max-w-md rounded-2xl bg-surface p-5 shadow-2xl" role="status" aria-live="polite">
        <h3 class="text-base font-bold text-text">Emitindo MDF-e</h3>
        <p class="mt-0.5 text-[12.5px] text-text-secondary">CIOT, vale-pedágio e SEFAZ, nesta ordem…</p>
        <div class="mt-4 flex items-center gap-3 text-sm text-text-secondary">
            <span class="h-5 w-5 animate-spin rounded-full border-2 border-[var(--h6-azul)] border-r-transparent motion-reduce:animate-none"></span>
            Aguarde — não feche a página.
        </div>
    </div>
</div>
