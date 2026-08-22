<?php

declare(strict_types=1);

use App\Livewire\Abastecimentos;
use App\Livewire\Composicoes;
use App\Livewire\Filiais;
use App\Livewire\Inicio;
use App\Livewire\Manutencao;
use App\Livewire\Motoristas;
use App\Livewire\Ocorrencias;
use App\Livewire\OrdensColeta;
use App\Livewire\Permissoes;
use App\Livewire\Pessoas;
use App\Livewire\Produtos;
use App\Livewire\Rotas;
use App\Livewire\Viagens;
use App\Livewire\TabelasFrete;
use App\Livewire\Usuarios;
use App\Livewire\Veiculos;
use App\Livewire\Vencimentos;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas do TENANT
|--------------------------------------------------------------------------
| Respondem no subdomínio do cliente (<slug>.frota.petroweb.app), dentro do
| banco dele. As rotas do painel do provedor ficam em routes/central.php.
|
| Regra de tenancy: nenhuma rota recebe empresa_id como parâmetro. A empresa
| vem do usuário autenticado ou da sessão — ver app/Support/TenantContext.php.
*/

Route::middleware(['tenant', 'auth', 'verified'])->group(function (): void {
    // Dashboard operacional (componente Livewire, não Route::view). Ver Inicio.
    Route::get('/', Inicio::class)->name('inicio');

    /*
     * 1010 — Pessoas. O nome da rota é o que o config/navegacao.php procura e
     * o que o Ctrl+K resolve; mudá-lo apaga o item da sidebar.
     */
    Route::get('/pessoas', Pessoas\Index::class)->name('pessoas.index');
    Route::get('/pessoas/nova', Pessoas\Formulario::class)->name('pessoas.criar');
    Route::get('/pessoas/{pessoa}', Pessoas\Formulario::class)->name('pessoas.editar');

    /*
     * 1030 — Tabelas de frete. Geral ou por cliente; preço pela soma dos itens.
     */
    /*
     * 1020 — Produtos (mercadorias). Catálogo de cargas.
     */
    Route::get('/produtos', Produtos\Index::class)->name('produtos.index');
    Route::get('/produtos/novo', Produtos\Formulario::class)->name('produtos.criar');
    Route::get('/produtos/{mercadoria}', Produtos\Formulario::class)->name('produtos.editar');

    Route::get('/tabelas-frete', TabelasFrete\Index::class)->name('tabelas-frete.index');
    Route::get('/tabelas-frete/nova', TabelasFrete\Formulario::class)->name('tabelas-frete.criar');
    Route::get('/tabelas-frete/{tabela}', TabelasFrete\Formulario::class)->name('tabelas-frete.editar');

    /*
     * 2010 — Veículos. Uma unidade por cadastro (a combinação é a 2020).
     */
    Route::get('/veiculos', Veiculos\Index::class)->name('veiculos.index');
    Route::get('/veiculos/novo', Veiculos\Formulario::class)->name('veiculos.criar');
    Route::get('/veiculos/{veiculo}', Veiculos\Formulario::class)->name('veiculos.editar');

    /*
     * 2030 — Motoristas. Papel sobre pessoas; RN-12 vive no RegrasMotorista.
     */
    Route::get('/motoristas', Motoristas\Index::class)->name('motoristas.index');
    Route::get('/motoristas/novo', Motoristas\Formulario::class)->name('motoristas.criar');
    Route::get('/motoristas/{motorista}', Motoristas\Formulario::class)->name('motoristas.editar');

    /*
     * 2020 — Composições. A combinação montada; categoria e AET são calculadas.
     */
    Route::get('/composicoes', Composicoes\Index::class)->name('composicoes.index');
    Route::get('/composicoes/nova', Composicoes\Formulario::class)->name('composicoes.criar');
    Route::get('/composicoes/{composicao}', Composicoes\Formulario::class)->name('composicoes.editar');

    /*
     * 2060 — Abastecimentos. Consumo, média e desvio por veículo.
     */
    Route::get('/abastecimentos', Abastecimentos\Index::class)->name('abastecimentos.index');
    Route::get('/abastecimentos/novo', Abastecimentos\Formulario::class)->name('abastecimentos.criar');
    Route::get('/abastecimentos/{abastecimento}', Abastecimentos\Formulario::class)->name('abastecimentos.editar');

    /*
     * 2050 — Manutenção (ordens de serviço).
     */
    Route::get('/manutencao', Manutencao\Index::class)->name('manutencao.index');
    Route::get('/manutencao/nova', Manutencao\Formulario::class)->name('manutencao.criar');
    Route::get('/manutencao/{os}', Manutencao\Formulario::class)->name('manutencao.editar');

    /*
     * 2040 — Vencimentos. Painel de leitura sobre documentos, motoristas e
     * certificados; consolida o que expira e bloqueia a operação.
     */
    Route::get('/vencimentos', Vencimentos\Index::class)->name('vencimentos.index');

    /*
     * 3010 — Ordens de coleta. Documento de entrada da operação; vira CT-e.
     */
    Route::get('/ordens-coleta', OrdensColeta\Index::class)->name('ordens-coleta.index');
    Route::get('/ordens-coleta/nova', OrdensColeta\Formulario::class)->name('ordens-coleta.criar');
    Route::get('/ordens-coleta/{ordem}', OrdensColeta\Formulario::class)->name('ordens-coleta.editar');

    /*
     * 3020 — Viagens. Execução física; carrega os CT-e (N:N, RN-02).
     */
    Route::get('/viagens', Viagens\Index::class)->name('viagens.index');
    Route::get('/viagens/nova', Viagens\Formulario::class)->name('viagens.criar');
    Route::get('/viagens/{viagem}', Viagens\Formulario::class)->name('viagens.editar');

    /*
     * 3030 — Rotas planejadas.
     */
    Route::get('/rotas', Rotas\Index::class)->name('rotas.index');
    Route::get('/rotas/nova', Rotas\Formulario::class)->name('rotas.criar');
    Route::get('/rotas/{rota}', Rotas\Formulario::class)->name('rotas.editar');

    /*
     * 3040 — Ocorrências operacionais.
     */
    Route::get('/ocorrencias', Ocorrencias\Index::class)->name('ocorrencias.index');
    Route::get('/ocorrencias/nova', Ocorrencias\Formulario::class)->name('ocorrencias.criar');
    Route::get('/ocorrencias/{ocorrencia}', Ocorrencias\Formulario::class)->name('ocorrencias.editar');

    /*
     * 9020 — Usuários. Convidados pelo gestor; papéis via spatie/permission.
     */
    Route::get('/usuarios', Usuarios\Index::class)->name('usuarios.index');
    Route::get('/usuarios/novo', Usuarios\Formulario::class)->name('usuarios.criar');
    Route::get('/usuarios/{usuario}', Usuarios\Formulario::class)->name('usuarios.editar');

    /*
     * 9010 — Empresa e filiais. Cada filial é um emitente fiscal independente.
     */
    Route::get('/filiais', Filiais\Index::class)->name('filiais.index');
    Route::get('/filiais/nova', Filiais\Formulario::class)->name('filiais.criar');
    Route::get('/filiais/{filial}', Filiais\Formulario::class)->name('filiais.editar');

    /*
     * 9030 — Papéis e permissões (leitura). O que cada papel pode fazer.
     */
    Route::get('/permissoes', Permissoes\Index::class)->name('permissoes.index');
});

require __DIR__ . '/auth.php';
