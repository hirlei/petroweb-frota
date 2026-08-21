@props([
    'rotulo',
    'valor' => '—',
    'mono'  => true,
])

{{--
    Par rótulo/valor das fichas laterais. Existe como componente porque a
    ficha de pessoa, de veículo e de motorista repetem o mesmo padrão — e
    porque alinhamento de número em ficha lateral é onde a densidade do
    PetroWeb se ganha ou se perde.
--}}
<div {{ $attributes->merge(['class' => 'flex items-baseline justify-between gap-3 py-1']) }}>
    <span class="flex-shrink-0 text-sm text-text-secondary">{{ $rotulo }}</span>
    <span class="text-right text-sm font-medium text-text {{ $mono ? 'font-mono tabular-nums' : '' }}">
        {{ $valor }}
    </span>
</div>
