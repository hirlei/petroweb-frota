<?php

declare(strict_types=1);

use App\Http\Controllers\DocumentoAuxiliarController;
use App\Http\Controllers\ExportacaoXmlController;
use App\Http\Controllers\FaturaImpressaoController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\RastreamentoWebhookController;
use App\Http\Controllers\ReciboAcertoController;
use App\Livewire\Abastecimentos;
use App\Livewire\Acertos;
use App\Livewire\Ciots;
use App\Livewire\Composicoes;
use App\Livewire\ContasPagar;
use App\Livewire\ContasReceber;
use App\Livewire\Cte;
use App\Livewire\Despesas;
use App\Livewire\Entregas;
use App\Livewire\ExportacaoXml;
use App\Livewire\Faturas;
use App\Livewire\Filiais;
use App\Livewire\Inicio;
use App\Livewire\Manutencao;
use App\Livewire\Mdfe;
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
use App\Livewire\ValesPedagio;
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

    // Menu H6 (igual ao ERP): abrir rotina pelo código e fixar nos Favoritos.
    Route::get('/ir/{codigo}', [MenuController::class, 'ir'])->where('codigo', '[0-9]{4}')->name('menu.ir');
    Route::post('/menu/fixar/{codigo}', [MenuController::class, 'fixar'])->where('codigo', '[0-9]{4}')->name('menu.fixar');

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
     * 3050 — Entregas (POD). Prova de entrega; base do evento 110180 do CT-e.
     */
    Route::get('/entregas', Entregas\Index::class)->name('entregas.index');
    Route::get('/entregas/nova', Entregas\Formulario::class)->name('entregas.criar');
    Route::get('/entregas/{entrega}', Entregas\Formulario::class)->name('entregas.editar');

    /*
     * 3060 — Despesas de viagem. Aprovadas, entram no custo da viagem.
     */
    Route::get('/despesas', Despesas\Index::class)->name('despesas.index');
    Route::get('/despesas/nova', Despesas\Formulario::class)->name('despesas.criar');
    Route::get('/despesas/{despesa}', Despesas\Formulario::class)->name('despesas.editar');

    /*
     * 3070 — Acerto de viagem (motorista CLT): adiantamentos, conferência,
     * diárias e comissão. Saldo a favor do motorista vira conta no 5030.
     */
    Route::get('/acertos', Acertos\Index::class)->name('acertos.index');
    Route::get('/acertos/{viagem}', Acertos\Acerto::class)->name('acertos.ver');
    Route::get('/acertos/{viagem}/recibo', ReciboAcertoController::class)->name('acertos.recibo');

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
     * 4010 — CT-e (modelo 57). Nasce da ordem de coleta.
     */
    Route::get('/cte', Cte\Index::class)->name('cte.index');
    Route::get('/cte/novo', Cte\Formulario::class)->name('cte.criar');
    Route::get('/cte/{cte}', Cte\Formulario::class)->name('cte.editar');
    Route::get('/cte/{cte}/dacte', [DocumentoAuxiliarController::class, 'dacte'])->name('cte.dacte');
    Route::get('/cte/{cte}/cce/{evento}', [DocumentoAuxiliarController::class, 'cce'])->name('cte.cce');

    /*
     * 4020 — MDF-e (modelo 58). Nasce da viagem; RN-03.
     */
    Route::get('/mdfe', Mdfe\Index::class)->name('mdfe.index');
    Route::get('/mdfe/novo', Mdfe\Formulario::class)->name('mdfe.criar');
    Route::get('/mdfe/{mdfe}', Mdfe\Formulario::class)->name('mdfe.editar');
    Route::get('/mdfe/{mdfe}/damdfe', [DocumentoAuxiliarController::class, 'damdfe'])->name('mdfe.damdfe');

    /*
     * 4030 — Vale-pedágio (grupo valePed do MDF-e). Um por veículo.
     */
    Route::get('/vale-pedagio', ValesPedagio\Index::class)->name('vale-pedagio.index');
    Route::get('/vale-pedagio/novo', ValesPedagio\Formulario::class)->name('vale-pedagio.criar');
    Route::get('/vale-pedagio/{vale}', ValesPedagio\Formulario::class)->name('vale-pedagio.editar');

    /*
     * 4050 — CIOT. Registrado na emissão do MDF-e (4020); aqui é consulta,
     * pagamento do saldo ao TAC, cancelamento e reenvio.
     */
    Route::get('/ciot', Ciots\Index::class)->name('ciot.index');
    Route::get('/ciot/{ciot}', Ciots\Detalhe::class)->name('ciot.ver');

    /*
     * 4060 — Exportar XML: o mês para o contador (CT-e, MDF-e e eventos) num ZIP.
     */
    Route::get('/fiscal/exportar-xml', ExportacaoXml\Index::class)->name('fiscal.xml.index');
    Route::get('/fiscal/exportar-xml/{exportacao}/baixar', ExportacaoXmlController::class)->name('fiscal.xml.baixar');

    /*
     * 5010 — Faturas. Agrupa CT-e autorizados de um tomador; cada parcela vira
     * um título em Contas a receber (5020). Regras em Services\Financeiro\Faturamento.
     */
    Route::get('/faturas', Faturas\Index::class)->name('faturas.index');
    Route::get('/faturas/nova', Faturas\Formulario::class)->name('faturas.criar');
    Route::get('/faturas/{fatura}', Faturas\Detalhe::class)->name('faturas.ver');
    Route::get('/faturas/{fatura}/imprimir', FaturaImpressaoController::class)->name('faturas.imprimir');

    /*
     * 5020 — Contas a receber. Parcelas das faturas, recebimento e estorno.
     */
    Route::get('/contas-receber', ContasReceber\Index::class)->name('contas-receber.index');

    /*
     * 5030 — Contas a pagar. Lançamentos esperando (abastecimento, OS, CIOT),
     * conta manual, pagamento e estorno. Regras em Services\Financeiro\ContasPagar.
     */
    Route::get('/contas-pagar', ContasPagar\Index::class)->name('contas-pagar.index');
    Route::get('/contas-pagar/lancar', ContasPagar\Lancar::class)->name('contas-pagar.lancar');
    Route::get('/contas-pagar/nova', ContasPagar\Formulario::class)->name('contas-pagar.criar');

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

/*
 * Webhook de rastreamento (máquina-a-máquina): tenancy pelo subdomínio, sem
 * sessão nem CSRF (grupo `tenant-api`). Autenticação por token no header. É por
 * aqui que os provedores de GPS enviam as posições ("direcionamento de sinal").
 */
Route::middleware('tenant-api')->group(function (): void {
    Route::post('/webhooks/rastreamento/{provedor}', [RastreamentoWebhookController::class, 'receber'])
        ->name('webhooks.rastreamento');
});

require __DIR__ . '/auth.php';
