<?php

declare(strict_types=1);

namespace App\Livewire\Permissoes;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Spatie\Permission\Models\Role;

/**
 * Rotina 9030 — papéis e permissões (leitura).
 *
 * Mostra o que cada papel pode fazer, agrupado por módulo. É intencionalmente
 * de leitura: os papéis canônicos (Administrador, Fiscal, Operação, Financeiro,
 * Consulta) são definidos no PermissoesSeeder, para que uma permissão nova
 * entre no lugar certo em todos os tenants de uma vez. Editar papel a papel na
 * tela abriria divergência entre clientes — fica para o sprint de segurança.
 */
class Index extends Component
{
    public ?int $papelSelecionado = null;

    /** Rótulos dos módulos (prefixo da permissão). */
    private const MODULOS = [
        'pessoa' => 'Pessoas', 'produto' => 'Produtos', 'tabela-frete' => 'Tabelas de frete',
        'veiculo' => 'Veículos', 'motorista' => 'Motoristas', 'ordem-coleta' => 'Ordens de coleta',
        'viagem' => 'Viagens', 'rota' => 'Rotas', 'ocorrencia' => 'Ocorrências',
        'cte' => 'CT-e', 'mdfe' => 'MDF-e', 'certificado' => 'Certificados',
        'manutencao' => 'Manutenção', 'abastecimento' => 'Abastecimentos',
        'fatura' => 'Faturas', 'titulo' => 'Títulos', 'acerto' => 'Acertos',
        'filial' => 'Filiais', 'usuario' => 'Usuários', 'permissao' => 'Permissões',
    ];

    private const ACOES = [
        'consultar' => 'Consultar', 'gerenciar' => 'Gerenciar', 'emitir' => 'Emitir',
        'cancelar' => 'Cancelar', 'encerrar' => 'Encerrar', 'baixar' => 'Baixar',
        'aprovar' => 'Aprovar',
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('permissao.gerenciar') ?? false, 403);
    }

    public function selecionar(int $id): void
    {
        $this->papelSelecionado = $id;
    }

    /** @return Collection<int,Role> */
    #[Computed]
    public function papeis(): Collection
    {
        $papeis = Role::query()->withCount(['permissions', 'users'])->orderBy('name')->get();

        if ($this->papelSelecionado === null && $papeis->isNotEmpty()) {
            $this->papelSelecionado = $papeis->first()->id;
        }

        return $papeis;
    }

    #[Computed]
    public function papel(): ?Role
    {
        if ($this->papelSelecionado === null) {
            return null;
        }

        return Role::with('permissions')->find($this->papelSelecionado);
    }

    /**
     * Permissões do papel selecionado, agrupadas por módulo e traduzidas para
     * rótulos legíveis.
     *
     * @return array<string,list<string>>
     */
    #[Computed]
    public function permissoesPorModulo(): array
    {
        $papel = $this->papel;

        if ($papel === null) {
            return [];
        }

        $grupos = [];

        foreach ($papel->permissions as $permissao) {
            [$modulo, $acao] = array_pad(explode('.', $permissao->name, 2), 2, '');
            $moduloRotulo = self::MODULOS[$modulo] ?? ucfirst($modulo);
            $grupos[$moduloRotulo][] = self::ACOES[$acao] ?? ucfirst($acao);
        }

        ksort($grupos);

        return $grupos;
    }

    public function render(): View
    {
        return view('livewire.permissoes.index')
            ->layout('layouts.app', ['title' => 'Papéis e permissões']);
    }
}
