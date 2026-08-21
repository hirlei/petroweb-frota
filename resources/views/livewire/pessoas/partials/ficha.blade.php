{{--
    Ficha resumida da pessoa selecionada — somente leitura.
    O que muda em relação a um "detalhe" genérico: os blocos de transportador
    só existem se o papel estiver ativo, e o indicador de IE tem destaque
    próprio porque é ele que vira `indIEDest` no CT-e.
--}}
@php
    $ehTransportador = $pessoa->temPapel('motorista') || $pessoa->temPapel('proprietario');
@endphp

<x-card padding="none" class="overflow-hidden">
    <div class="flex items-center gap-2 border-b border-border bg-primary-soft px-5 py-3.5">
        <x-icon name="users" class="h-4 w-4 text-amber-700" />
        <h2 class="flex-1 truncate text-sm font-semibold text-amber-700">{{ $pessoa->razao_social }}</h2>
        @can('update', $pessoa)
            <a href="{{ route('pessoas.editar', $pessoa) }}" wire:navigate
               class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-amber-700 hover:bg-amber-100">
                <x-icon name="pencil" class="h-3.5 w-3.5" /> Editar
            </a>
        @endcan
    </div>

    <div class="px-5 py-4">
        <p class="mb-3 text-[11px] font-bold uppercase tracking-wider text-text-muted">Papéis</p>
        <div class="mb-2 flex flex-wrap gap-1.5">
            @forelse ($pessoa->papeis->where('ativo', true) as $vinculo)
                <x-badge :variant="config('papeis.cores.' . $vinculo->papel, 'gray')" class="px-2.5 py-1"
                         :title="config('papeis.descricoes.' . $vinculo->papel)">
                    <x-icon name="check" class="h-3 w-3" />
                    {{ config('papeis.rotulos.' . $vinculo->papel) }}
                </x-badge>
            @empty
                <span class="text-sm text-text-muted">Nenhum papel atribuído — esta pessoa não aparece em nenhuma seleção.</span>
            @endforelse
        </div>

        @if ($pessoa->papeis->where('ativo', true)->count() > 1)
            <div class="mb-4 flex items-start gap-2 rounded-r-md border-l-[3px] border-info bg-blue-50 px-3 py-2.5
                        text-xs text-blue-800 dark:bg-blue-950/40 dark:text-blue-300">
                <x-icon name="info" class="mt-px h-3.5 w-3.5 flex-shrink-0" />
                <span>A mesma pessoa acumula papéis. Cadastro separado por papel duplicaria documento e endereço.</span>
            </div>
        @endif

        <p class="mb-2 mt-4 text-[11px] font-bold uppercase tracking-wider text-text-muted">Dados fiscais</p>
        <x-linha-ficha rotulo="Tipo" :valor="$pessoa->ehPessoaFisica() ? 'Física' : ($pessoa->tipo === 'E' ? 'Estrangeiro' : 'Jurídica')" :mono="false" />
        <x-linha-ficha :rotulo="$pessoa->ehPessoaFisica() ? 'CPF' : 'CNPJ'"
                       :valor="\App\Domain\Cadastro\Documento::formatar($pessoa->documento)" />
        <x-linha-ficha rotulo="Inscrição estadual" :valor="$pessoa->ie ?? '—'" />

        <div class="my-1.5 flex items-center justify-between rounded-md bg-primary-soft px-2.5 py-2">
            <span class="text-sm font-semibold text-amber-700">Indicador de IE</span>
            <x-badge variant="primary" class="text-[11px]">
                {{ $pessoa->ie_indicador }} —
                {{ match ($pessoa->ie_indicador) {
                    '1' => 'Contribuinte',
                    '2' => 'Isento',
                    default => 'Não contribuinte',
                } }}
            </x-badge>
        </div>

        @if ($pessoa->ie_indicador === '1' && ! $pessoa->ie)
            <div class="mb-3 flex items-start gap-2 rounded-r-md border-l-[3px] border-danger bg-red-50 px-3 py-2.5
                        text-xs text-red-800 dark:bg-red-950/40 dark:text-red-300">
                <x-icon name="alert-triangle" class="mt-px h-3.5 w-3.5 flex-shrink-0" />
                <span>Contribuinte sem inscrição estadual. O CT-e será rejeitado — corrija antes de usar esta pessoa em uma emissão.</span>
            </div>
        @else
            <p class="mb-3 text-xs text-text-muted">
                Vai direto para o <span class="font-mono">indIEDest</span> do CT-e.
            </p>
        @endif

        <x-linha-ficha rotulo="SUFRAMA" :valor="$pessoa->suframa ?? '—'" />
        <x-linha-ficha rotulo="CNAE principal" :valor="$pessoa->cnae ?? '—'" />

        @if ($ehTransportador)
            <div class="my-2.5 h-px bg-border"></div>
            <p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-text-muted">Como transportador</p>
            <x-linha-ficha rotulo="RNTRC" :valor="$pessoa->rntrc ?? '—'" />
            <x-linha-ficha rotulo="Validade do RNTRC" :valor="$pessoa->rntrc_validade?->format('d/m/Y') ?? '—'" />
            <x-linha-ficha rotulo="Tipo de transportador"
                           :valor="match ($pessoa->tp_transp) {
                               '1' => '1 — ETC', '2' => '2 — TAC', '3' => '3 — CTC', default => '—',
                           }" />
            <p class="mt-1.5 text-xs text-text-muted">
                Estes campos aparecem porque um papel de transporte está ativo. Para quem é só cliente, ficam ocultos.
            </p>
        @endif
    </div>
</x-card>

@if ($pessoa->enderecoPrincipal)
    <x-card padding="sm">
        <p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-text-muted">Endereço principal</p>
        <div class="text-sm leading-relaxed text-text">
            {{ $pessoa->enderecoPrincipal->linhaUnica() }}
            @if ($pessoa->enderecoPrincipal->cep)
                <br>CEP {{ substr($pessoa->enderecoPrincipal->cep, 0, 5) }}-{{ substr($pessoa->enderecoPrincipal->cep, 5) }}
            @endif
        </div>
        <div class="mt-2 flex items-center gap-1.5">
            <x-badge variant="gray" class="text-[10px]">IBGE {{ $pessoa->enderecoPrincipal->municipio?->codigo_ibge }}</x-badge>
            @if ($pessoa->enderecoPrincipal->temCoordenadas())
                <x-badge variant="secondary" class="text-[10px]">
                    <x-icon name="check" class="h-3 w-3" /> Geocodificado
                </x-badge>
            @endif
            <span class="flex-1"></span>
            @if ($pessoa->enderecos->count() > 1)
                <span class="text-xs text-text-muted">+{{ $pessoa->enderecos->count() - 1 }} outros endereços</span>
            @endif
        </div>
    </x-card>
@endif

@if ($pessoa->contatos->where('recebe_dfe', true)->isNotEmpty())
    <x-card padding="sm">
        <p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-text-muted">Recebem XML e DACTE</p>
        @foreach ($pessoa->contatos->where('recebe_dfe', true) as $contato)
            <div class="flex items-baseline gap-2 border-b border-border py-1.5 last:border-0">
                <span class="text-sm text-text">{{ $contato->nome }}</span>
                <span class="flex-1 truncate text-xs text-text-muted">{{ $contato->email }}</span>
            </div>
        @endforeach
    </x-card>
@endif
