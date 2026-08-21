# PetroWeb Frota — Documento Mestre do Projeto

> Sistema SaaS multi-tenant de gestão de transporte rodoviário de cargas (TMS + gestão de frota + emissão de documentos fiscais eletrônicos).

| Campo | Valor |
|---|---|
| Nome do produto | **PetroWeb Frota** |
| Família | Produto da linha PetroWeb, ao lado do PetroWeb PDV e do PetroWeb (ERP de postos) |
| Empresa | HALC |
| Responsável técnico | Hirlei |
| Versão do documento | 1.3 |
| Data | 21/08/2026 |
| Stack | Laravel 11 + Livewire 4, PostgreSQL 16, Redis |
| Modelo | SaaS multi-tenant (isolamento por `empresa_id`) |
| Status | Documentação inicial — pré-desenvolvimento |

---

## 1. Visão do produto

### 1.1 Problema

Transportadoras de pequeno e médio porte no Brasil operam hoje com um conjunto fragmentado de ferramentas: um emissor de CT-e isolado, planilhas para controle de frota e manutenção, WhatsApp para acompanhamento de viagem, um financeiro que não conversa com a operação e nenhuma visibilidade real de custo por quilômetro. O resultado é margem desconhecida, retrabalho de digitação e risco fiscal.

### 1.2 Proposta

O PetroWeb Frota é um sistema único onde o dado nasce uma vez e percorre toda a cadeia:

```
Cotação → Ordem de coleta → CT-e → Viagem → MDF-e → Entrega (POD)
   → Custos (combustível, manutenção, pedágio, motorista)
      → Faturamento → Rentabilidade real por viagem, veículo, rota e cliente
```

O diferencial não é emitir CT-e — isso é commodity. O diferencial é **fechar o ciclo do custo**: cada litro abastecido, cada pneu, cada adiantamento e cada pedágio ficam amarrados à viagem que os consumiu, e a viagem fica amarrada ao CT-e que a faturou. A transportadora passa a saber, no dia seguinte à entrega, se aquele frete deu lucro.

### 1.3 Posição na família PetroWeb

O PetroWeb Frota não é um produto isolado: é o terceiro membro de uma linha que já tem o **PetroWeb** (ERP de postos de combustível) e o **PetroWeb PDV** (frente de caixa). Isso tem três consequências práticas de projeto:

1. **Marca e interface compartilhadas.** Identidade visual, padrões de componente, tom dos textos e convenções de UX seguem a linha PetroWeb. Um usuário que conhece o PetroWeb deve reconhecer o Frota na primeira tela.
2. **Padrões técnicos compartilhados.** Multi-tenancy, autenticação, permissões, auditoria e deploy repetem as decisões já validadas nos irmãos. Onde houver divergência, ela precisa de justificativa — não é escolha livre.
3. **Sinergia comercial e de dados.** Um posto cliente do PetroWeb e uma transportadora cliente do Frota podem trocar o abastecimento sem digitação (seção 2.4). Isso não é apenas uma integração conveniente: é o argumento de venda que nenhum concorrente de TMS puro consegue replicar.

> Onde este documento precisar distinguir os produtos, o ERP de postos aparece como **PetroWeb Postos** e este sistema como **PetroWeb Frota**.

### 1.4 Princípio norteador

O mesmo princípio que orienta o PetroWeb: **ser o sistema mais automatizado do seu mercado**. Concretamente, no contexto de transporte, isso significa:

- O CT-e nasce da ordem de coleta, não de uma tela em branco.
- O MDF-e nasce dos CT-e da viagem, não de redigitação.
- A NF-e do cliente entra no sistema pela Distribuição DF-e (download automático na SEFAZ), não por upload manual de XML.
- O abastecimento entra por integração com o posto (ou app do motorista), e o km/l é calculado sozinho.
- O vencimento de CNH, licenciamento, ANTT e revisão preventiva avisa antes de vencer, não depois da multa.

### 1.5 Público-alvo

| Perfil | Porte | Necessidade dominante |
|---|---|---|
| Transportadora rodoviária de carga (TRC) | 5 a 150 veículos | Ciclo completo: CT-e, MDF-e, viagem, custo, faturamento |
| Transportadora com frota agregada/terceirizada | qualquer | CIOT, pagamento de frete a terceiros, contratos |
| Embarcador com frota própria | 10 a 100 veículos | Frota, manutenção, custo por km, MDF-e de carga própria |
| Operador de fretamento / transporte de pessoas | — | CT-e OS (modelo 67) — **fase posterior** |

### 1.6 Fora de escopo (declarado)

Para não haver ambiguidade no que estamos construindo:

- Transporte aéreo, ferroviário, aquaviário e dutoviário (o layout do CT-e suporta; **não** implementaremos na fase 1 — apenas modal rodoviário).
- Armazenagem / WMS completo (haverá apenas movimentação de carga em filial, não gestão de endereçamento de armazém).
- Contabilidade fiscal completa (SPED Fiscal, ECD, ECF). O sistema **exporta** os XML e relatórios; a escrituração fica com o contador.
- Folha de pagamento. Haverá cálculo de comissão e diária de motorista, integrável, mas não folha.
- Roteirizador proprietário. Usaremos API de terceiros (mapas/pedágio); não construiremos motor de roteirização próprio.

---

## 2. Arquitetura

### 2.1 Stack

| Camada | Tecnologia | Observação |
|---|---|---|
| Backend | Laravel 11, PHP 8.3 | Mesma base do PetroWeb — reaproveita padrões, deploy e curva de aprendizado |
| UI | Livewire 4.3 + Alpine.js + Tailwind | SSR reativo, sem SPA separada |
| Banco | **PostgreSQL 16** | Nativo do AppStream do EL10, mesmo do PetroWeb. Single database, discriminador `empresa_id` |
| Cache / fila | Redis + Laravel Horizon | Comunicação SEFAZ **sempre** assíncrona |
| Storage | Disco local na VPS (S3-compatível quando escalar) | XML, PDF, canhotos digitalizados, fotos de ocorrência |
| PDF | Biblioteca de DACTE/DAMDFE + renderer próprio | Ver documento fiscal |
| Autenticação | Laravel Fortify/Sanctum | 2FA obrigatório para perfis com poder fiscal |
| App do motorista | PWA (fase 2) → nativo se necessário | Offline-first, sincronização por fila |

### 2.2 Multi-tenancy

**Modelo adotado: single database, shared schema, discriminador `empresa_id`.** É o mesmo modelo já validado no PetroWeb, o que permite reaproveitar decisões, middleware e o aprendizado sobre suas armadilhas.

Regras invioláveis:

1. Toda tabela de negócio carrega `empresa_id` **não nulo**, com índice composto começando por ele.
2. Todo model de negócio aplica um `TenantScope` global. Não existe consulta sem tenant — quando for realmente necessário (jobs de manutenção, superadmin), o bypass é explícito e auditado.
3. O `empresa_id` **nunca** vem do request. Vem sempre da sessão/contexto do usuário autenticado.
4. Jobs e comandos agendados recebem o `empresa_id` no payload e reidratam o contexto no `handle()`. Job sem tenant é bug.
5. Chaves únicas de negócio são únicas **por empresa**: `unique(empresa_id, placa)`, `unique(empresa_id, serie, numero, modelo)`.
6. Arquivos em storage são particionados por tenant: `empresa/{id}/cte/{ano}/{mes}/{chave}.xml`.

**Hierarquia:** `Empresa (tenant)` → `Filial` (estabelecimento com CNPJ próprio, IE, série de CT-e e certificado) → operação. Cada filial é um emitente fiscal independente.

### 2.3 Camadas da aplicação

```
app/
├─ Domain/            Regras de negócio puras, sem Laravel
│  ├─ Frota/
│  ├─ Operacao/
│  ├─ Fiscal/
│  └─ Financeiro/
├─ Services/          Orquestração (casos de uso)
│  ├─ Fiscal/CteService.php
│  ├─ Fiscal/MdfeService.php
│  └─ Fiscal/Sefaz/   Gateway — interface + implementações
├─ Jobs/              Tudo que fala com o mundo externo
├─ Livewire/          Componentes de tela
└─ Models/
```

**Regra de ouro do módulo fiscal:** a comunicação com a SEFAZ fica atrás de uma interface (`SefazGatewayInterface`). Trocar de biblioteca própria para API de terceiros — ou o inverso — deve ser trocar uma implementação, não reescrever o sistema. Ver `03_ESPECIFICACAO_FISCAL_CTE_MDFE.md`, seção 2.

### 2.4 Integrações previstas

| Integração | Fase | Finalidade |
|---|---|---|
| SEFAZ (CT-e, MDF-e, Distribuição DF-e) | 1 | Núcleo fiscal |
| Certificado digital A1 (.pfx) | 1 | Assinatura |
| Consulta de CNPJ / IBGE municípios | 1 | Cadastro |
| API de distância e pedágio (mapas) | 2 | Roteirização e custo de rota |
| CIOT / pagamento eletrônico de frete | 2 | Obrigação legal para frete a terceiros |
| Telemetria / rastreamento | 3 | Posição do veículo, cerca eletrônica |
| **PetroWeb** — abastecimento em postos | 3 | Sinergia HALC: posto fornece o abastecimento já conciliado |
| Banco (boleto, Pix, CNAB) | 3 | Financeiro |

> **Nota estratégica:** a integração PetroWeb Frota ↔ PetroWeb Postos é um ativo competitivo da HALC. Uma transportadora que abastece em posto cliente do PetroWeb pode ter o abastecimento entrando no custo da viagem sem digitação. Isso deve ser considerado na modelagem desde já (ver `abastecimentos.origem` e `abastecimentos.referencia_externa` na modelagem de dados), mesmo que a integração só seja construída na fase 3.

---

## 3. Módulos

### M1 — Núcleo e Plataforma

Tenancy, filiais, usuários, papéis e permissões, parâmetros por empresa, auditoria (log de alterações em registros fiscais e financeiros — quem, quando, o quê), numeração de documentos, agenda de vencimentos e notificações.

**Regra:** todo documento fiscal e financeiro é auditável. Alteração e cancelamento gravam autor e motivo.

### M2 — Cadastros

- **Pessoas** (modelo unificado): cliente, fornecedor, motorista, proprietário de veículo, seguradora, oficina — uma pessoa pode acumular papéis. Evita o erro clássico de cadastrar o mesmo CNPJ três vezes.
- Endereços e contatos múltiplos por pessoa; endereço de coleta e de entrega distintos.
- **Tabelas fiscais:** municípios IBGE (obrigatório para CT-e), UF, países, CFOP, CST/CSOSN, NCM.
- **Mercadorias e produtos:** identificação fiscal (NCM, CEST, GTIN, unidades), dados logísticos (dimensões, densidade, fator de cubagem, empilhamento, fragilidade), natureza de carga, controle de temperatura, **produto perigoso** (número ONU, classe e subclasse de risco, risco subsidiário, número de risco, grupo de embalagem, quantidade limitada) e controles setoriais (Exército, Polícia Federal, SIF/MAPA, agrotóxicos).
- **Tabelas de domínio:** naturezas de carga, carrocerias, configurações de combinação veicular, classes de risco, Relação de Produtos Perigosos e matriz de incompatibilidade química. Detalhamento completo em `05_CADASTROS_E_TABELAS_DE_DOMINIO.md`.
- **Tabelas de frete:** por cliente, rota, faixa de peso, faixa de valor, tipo de veículo. Componentes de frete (peso, valor, GRIS, ad valorem, pedágio, taxa de entrega, TDE, TDA).

### M3 — Frota

- **Veículos:** unidade de tração, reboque, semirreboque e dolly como registros distintos, com composição (cavalo + 1..3 unidades). Identificação (placa, RENAVAM, chassi, ano, marca/modelo), configuração física (eixos, tração, tara, PBT/PBTC, capacidade em kg e m³, dimensões, compartimentos), classificação (tipo de rodado, carroceria de mercado e sua tradução fiscal, configuração de CVC) e propriedade (própria / arrendada / agregada / terceiro), com proprietário, RNTRC e tipo de transportador.
- **Motoristas:** CNH (número, categoria, validade, EAR), vínculo (**CLT / agregado / autônomo / terceiro**), RNTRC próprio para TAC, exame toxicológico, MOPP/CETPP e curso de carga indivisível, dados para o pagamento eletrônico obrigatório.
- **Contratos de agregação:** exclusividade, modalidade de remuneração, apólices próprias do TAC (RCTR-C, RC-DC, RC-V) e vigência.
- **Documentos e vencimentos:** licenciamento, seguro, ANTT/RNTRC, CIV e CIPP/CTPP para tanques e perigosos, certificação INMETRO, AET, cronotacógrafo. Painel único de vencimentos com alerta configurável (D-60/D-30/D-7).
- **Pneus:** cadastro individual por número de fogo, posição no veículo, sulco, rodízio, recapagem, vida útil e custo por km rodado.
- **Manutenção:** plano preventivo por km ou por tempo, ordem de serviço, itens e peças, oficina interna/externa, custo por veículo.
- **Abastecimento:** posto, litros, preço, odômetro/horímetro, cálculo automático de média km/l, detecção de desvio (média fora da faixa histórica do veículo dispara alerta).

### M4 — Operação (TMS)

- **Ordem de coleta / solicitação de transporte:** o documento de entrada. Remetente, destinatário, tomador, mercadoria, peso, volume, valor, previsão de coleta e entrega.
- **Viagem:** agrupa carga, veículo, composição, motorista e rota. É a **unidade de custo** do sistema — tudo que se gasta se pendura nela.
- **Rota:** origem, destino, pontos intermediários, distância, praças de pedágio, tempo previsto.
- **Romaneio / carregamento:** o que efetivamente subiu no veículo.
- **Ocorrências:** avaria, extravio, atraso, devolução, sinistro — com foto, responsável e tratamento.
- **Comprovante de entrega (POD/canhoto):** digitalização, data/hora, recebedor, ou evento eletrônico de comprovante de entrega do CT-e.
- **Painel de operação:** o que está em coleta, em trânsito, entregue, pendente de canhoto, pendente de faturamento.

### M5 — Fiscal

CT-e (modelo 57) e MDF-e (modelo 58) na fase 1; CT-e OS (67) e GTV-e (64) em fase posterior. Eventos, contingência, DACTE/DAMDFE, Distribuição DF-e, guarda de XML por 132 meses.

Inclui o **Vale-Pedágio Obrigatório**, que é grupo do próprio MDF-e (`infANTT/valePed`) e por isso pertence a este módulo, não ao financeiro: cadastro das fornecedoras habilitadas pela ANTT, registro do vale recebido e do vale fornecido, um `disp` por veículo da composição, cálculo de `categCombVeic` a partir dos eixos, e bloqueio dos tipos descontinuados. Detalhamento completo em `03_ESPECIFICACAO_FISCAL_CTE_MDFE.md`, seções 5.6 e 6.3.

### M6 — Financeiro

- **Contas a receber:** fatura de frete (agrupando N CT-e por cliente e período), boleto, baixa, inadimplência.
- **Contas a pagar:** frete de terceiros e agregados, despesas de viagem, oficinas, combustível.
- **Adiantamento e acerto de viagem:** o motorista recebe adiantamento, presta contas com notas, e o saldo é apurado no retorno.
- **CIOT / pagamento eletrônico de frete:** obrigatório para transporte rodoviário remunerado por conta de terceiros (Lei 11.442/2007 e regulamentação ANTT). Ver alerta de prazo na seção 5.
- **Vale-pedágio obrigatório:** o **registro e a declaração no MDF-e estão na Fase 1** (módulo M5). O que fica aqui é o lado financeiro: o título a pagar da fornecedora quando a transportadora compra o vale como embarcadora equiparada, e a compra automatizada por integração.
- **Comissões** de motorista/agregado por percentual do frete ou por km.

### M7 — Custos e BI

Custo por km (total e por componente), custo por viagem, rentabilidade por cliente/rota/veículo, ociosidade de frota, DRE gerencial por centro de custo. Dashboards e exportação.

### M8 — Portais

- **Portal do cliente:** rastreamento da carga, 2ª via de CT-e (XML/DACTE), faturas e canhoto digitalizado.
- **App do motorista (PWA):** viagem do dia, checklist de saída, registro de abastecimento e despesa com foto, captura do canhoto, registro de ocorrência, confirmação de entrega. **Offline-first** — o motorista está em estrada sem sinal.

---

## 4. Regras de negócio estruturantes

Estas são as regras que, se erradas, obrigam a refazer o sistema. Devem ser respeitadas na modelagem.

**RN-01 — A viagem é a unidade de custo.** Todo lançamento de custo (abastecimento, pedágio, diária, manutenção corretiva em rota, adiantamento) pode e deve ser vinculado a uma viagem. Custos que não pertencem a uma viagem específica (manutenção preventiva, IPVA, seguro) são rateados por km rodado no período.

**RN-02 — CT-e e viagem são coisas diferentes.** Um CT-e pode atender uma viagem; uma viagem pode carregar N CT-e (carga fracionada); e um CT-e pode ser transbordado em várias viagens (redespacho/multimodal). A relação é **N:N**, e modelar como 1:1 é o erro mais caro que se pode cometer neste domínio.

**RN-03 — MDF-e agrega CT-e, não substitui.** O MDF-e é emitido por viagem/veículo e referencia os CT-e (ou NF-e, em carga própria) que estão embarcados. Só pode ser encerrado após a conclusão do percurso, e **deve** ser encerrado — MDF-e aberto gera pendência na SEFAZ e bloqueia a emissão de novos.

**RN-04 — Tomador do serviço é campo determinante.** O tomador (remetente, destinatário, expedidor, recebedor ou terceiro) define quem paga o frete, para quem se emite a fatura e a tributação do ICMS. Erro aqui é erro fiscal e financeiro simultaneamente.

**RN-05 — Documento fiscal autorizado é imutável.** Após a autorização, o registro não é editado: corrige-se por Carta de Correção Eletrônica (limites legais) ou cancela-se e reemite (prazo legal de cancelamento). O sistema não deve, em nenhuma hipótese, permitir UPDATE em CT-e autorizado.

**RN-06 — Numeração é por filial, série e modelo, e não tem buraco.** A numeração é sequencial e controlada com bloqueio pessimista (`SELECT ... FOR UPDATE`) ou tabela dedicada de sequência. Número queimado sem autorização vira registro `inutilizado` — nunca some.

**RN-07 — Nenhuma chamada à SEFAZ acontece dentro do request HTTP.** A SEFAZ cai, fica lenta e devolve timeout. Tudo é job com retentativa, backoff exponencial e status persistido. A tela mostra o estado; não segura o usuário.

**RN-08 — Idempotência na emissão.** Duplo clique não pode gerar dois CT-e. Toda emissão carrega uma chave de idempotência; antes de transmitir, o sistema consulta a SEFAZ pela chave de acesso para verificar se o documento já foi autorizado.

**RN-09 — Vencimento vence.** Veículo com licenciamento vencido, motorista com CNH vencida ou exame toxicológico vencido **bloqueia** a alocação em viagem. Bloqueio com override registrado e justificado, não silencioso.

**RN-10 — O vale-pedágio tem duas pontas, e nosso cliente pode estar na errada.** O Vale-Pedágio Obrigatório é devido pelo embarcador, mas a Lei 10.209/2001 (art. 1º, §3º, II) **equipara a embarcador a transportadora que subcontrata transportador autônomo**. Ou seja: sempre que o cliente subcontrata um TAC, ele deixa de ser credor e vira devedor do vale, sob multa de R$ 3.000 por veículo/viagem e indenização de 2× o frete. O sistema modela as duas pontas — vale recebido e vale fornecido — desde a primeira migration. Além disso, o valor do VPO **nunca** integra o frete nem a base do ICMS, ao contrário do pedágio comum reembolsado.

**RN-11 — Enum fiscal não é tabela de negócio.** A SEFAZ define 6 tipos de carroceria; a transportadora trabalha com vinte e poucos, e dois dos mais relevantes para o mercado da HALC — **tanque e silo** — não têm código fiscal próprio. Toda classificação tem duas camadas: a tabela operacional que o cliente enxerga e edita, e o enum fiscal para onde ela é traduzida na hora de gerar o XML. **Nenhuma tela de operação mostra código fiscal ao usuário.** Vale para carroceria (`tpCar`), tipo de carga (`tpCarga`), rodado (`tpRod`) e unidades.

**RN-12 — Agregado e autônomo não têm jornada no sistema.** A Lei 11.442/2007 (art. 5º), confirmada pelo STF na ADC 48, afasta o vínculo de emprego no contrato de transporte. Mas a Justiça do Trabalho ainda reconhece vínculo quando encontra remuneração fixa desvinculada de viagem, controle de jornada e subordinação. Por isso, telas de jornada, escala e registro de ponto existem **apenas** para `vinculo = clt`. Para agregado e autônomo o sistema opera com contrato de agregação, tarifa acordada e ordem de coleta. Um espelho de ponto de agregado é prova contra o próprio cliente.

**RN-13 — O canhoto fecha o ciclo.** Sem comprovante de entrega (físico digitalizado ou evento eletrônico), a viagem não é considerada concluída e o frete não vai para faturamento automático — regra configurável por empresa.

---

## 5. Alertas regulatórios com data (situação em agosto/2026)

Este projeto nasce em um momento de transição fiscal. Ignorar isso significa nascer desatualizado.

### 5.1 Reforma Tributária no CT-e — NT 2026.002

A NT 2026.002 introduz no CT-e, CT-e OS e GTV-e os grupos de IBS/CBS e campos correlatos (`pDevTrib` para devolução/cashback, `ISUFEmit` para inscrição SUFRAMA em Área de Livre Comércio, e o grupo de pagamento antecipado). O cronograma divulgado:

| Etapa | Homologação | Produção |
|---|---|---|
| Etapa 1 — campos RTC obrigatórios | 01/07/2026 | era 03/08/2026 na v1.00; a v1.01 mudou para "implementação futura", sem nova data |
| Etapa 2 — demais alterações | 03/08/2026 | 31/08/2026 |

As alíquotas-teste de 2026 são CBS 0,9% e IBS 0,1%, validadas automaticamente pela SEFAZ. Há divergência entre fontes de mercado sobre qual ato normativo promoveu o adiamento da Etapa 1 — confirmar no PDF da v1.01 no portal oficial, não em blog.

**Impacto no projeto:** a modelagem do CT-e já deve prever os grupos IBS/CBS desde o primeiro dia. A produção da Etapa 2 é **31/08/2026** — dez dias após a data deste documento. Antes de escrever a primeira linha do módulo fiscal, é obrigatório baixar a versão vigente do schema XSD e do MOC no portal oficial e conferir se houve novo Ato Técnico. Notas técnicas mudam de data; o portal é a única fonte confiável.

### 5.2 Obrigatoriedade do CIOT no MDF-e — NT 2026.001

Torna obrigatório o grupo `infCIOT` no MDF-e para transporte rodoviário de cargas por conta de terceiros. Não há alteração de layout — o grupo já existia; muda a obrigatoriedade. Rejeição associada: **684**. Para `tpEmit` 1 e 3 a exigência é direta; para `tpEmit` 2 (carga própria) é condicionada ao `tpTransp`.

| Homologação | Produção |
|---|---|
| 21/09/2026 | 23/11/2026 |

**Impacto no projeto:** o módulo de CIOT sai da fase 2 "desejável" e entra como **requisito de produção até novembro/2026** para qualquer cliente que contrate frete de terceiros ou agregados. Isso muda a prioridade do backlog — ver `04_BACKLOG.md`, Sprint 12.

### 5.3 Vale-Pedágio Obrigatório — obrigação já vigente

Diferente das duas anteriores, esta não é um prazo futuro: **já está valendo**, e por isso entrou na Fase 1.

| Item | Situação em 08/2026 |
|---|---|
| Lei | Lei 10.209/2001 (com alterações até a Lei 14.229/2021) |
| Regulamento | **Resolução ANTT nº 6.024/2023**, alterada pela 6.044/2024. A Resolução 2.885/2008 está **revogada** |
| Meios aceitos | Apenas TAG eletrônica e leitura de placa desde **31/01/2025**. Cupom e cartão físico não valem mais |
| Identificador | **IDVPO** desde 23/04/2025, com validação de RNTRC do transportador e do veículo na emissão |
| Campo fiscal | Grupo `infANTT/valePed` do **MDF-e** — não existe no CT-e desde o layout 3.00 |
| Multa | R$ 3.000 por veículo/viagem ao contratante + indenização de 2× o frete ao transportador (art. 8º) |

Três consequências de projeto:

1. **O vale-pedágio saiu da Fase 2 e entrou na Sprint 9**, junto do MDF-e, porque é grupo do próprio manifesto. Ver RN-10.
2. O tipo `tpValePed` teve o enum restrito pela **NT 2025.001 do MDF-e** (em produção desde 06/10/2025) — enviar cupom ou cartão hoje quebra na validação de schema, com erro genérico e confuso.
3. A validação do IDVPO consulta o **RNTRC** do transportador e o cadastro do veículo na ANTT. Isso liga o vale-pedágio ao painel de vencimentos: RNTRC vencido não é pendência de cadastro, é bloqueio de operação.

### 5.4 Consequência para o roadmap

As duas notas técnicas juntas dizem o seguinte: o módulo fiscal precisa ser construído sobre uma camada que **absorva mudança de layout sem reescrita**, porque haverá mais mudanças nos próximos 24 meses do que houve nos últimos dez. Isso reforça a decisão arquitetural da seção 2.3 (gateway com interface) e pesa fortemente a favor de terceirizar a comunicação SEFAZ na fase 1 — ver a análise em `03_ESPECIFICACAO_FISCAL_CTE_MDFE.md`, seção 2.

---

## 6. Roadmap por fases

### Fase 0 — Fundação (3 a 4 semanas)

Projeto Laravel, tenancy, autenticação, permissões, layout base, CI, ambientes, tabelas fiscais (IBGE, UF, CFOP), cadastro de empresa e filial com certificado digital. Ao final: um sistema vazio, mas multi-tenant, autenticado e com deploy funcionando.

### Fase 1 — MVP operacional e fiscal (12 a 16 semanas)

O escopo aprovado: **Cadastros + Frota, CT-e/MDF-e, Viagens e Rotas, Custos (abastecimento e manutenção)** — mais o **Vale-Pedágio Obrigatório**, antecipado da Fase 2 por ser grupo do próprio MDF-e.

Critério de encerramento da fase: uma transportadora real consegue, sem sair do sistema, cadastrar sua frota, receber uma solicitação de transporte, emitir CT-e autorizado, emitir e encerrar MDF-e, registrar a viagem com abastecimentos e despesas, anexar o canhoto e ver o custo daquela viagem.

### Fase 2 — Financeiro e conformidade (8 a 10 semanas)

Contas a receber e a pagar, fatura de frete agrupada, adiantamento e acerto de viagem, **CIOT** (prazo legal de novembro/2026), vale-pedágio, comissões, Distribuição DF-e, portal do cliente.

### Fase 3 — Inteligência e mobilidade (8 a 12 semanas)

App do motorista (PWA offline-first), roteirização com pedágio, telemetria, BI de custo por km e rentabilidade, integração PetroWeb, pneus com custo por km.

### Fase 4 — Expansão (contínuo)

CT-e OS (modelo 67) para fretamento, GTV-e, outros modais, marketplace de cargas, integração com embarcadores (EDI), auditoria fiscal automatizada.

---

## 7. Critérios de aceite da Fase 1

Cada item abaixo é verificável de forma binária. A fase não é dada por concluída com item pendente.

| # | Critério |
|---|---|
| CA-01 | Duas empresas distintas no mesmo banco não enxergam dado uma da outra — verificado por teste automatizado que tenta acessar registro de outro tenant por ID direto |
| CA-02 | Cadastro completo de veículo de tração, reboque e composição, com validação de placa e RENAVAM |
| CA-03 | Painel de vencimentos exibe CNH, licenciamento e ANTT com alerta em D-30, e bloqueia alocação de veículo/motorista vencido |
| CA-04 | CT-e emitido em ambiente de homologação e autorizado pela SEFAZ, com XML assinado e DACTE gerado em PDF |
| CA-05 | Cancelamento de CT-e e Carta de Correção Eletrônica funcionando, com XML de evento armazenado |
| CA-06 | MDF-e emitido referenciando N CT-e da mesma viagem, autorizado e **encerrado** pelo evento de encerramento |
| CA-07 | Contingência: com a SEFAZ indisponível, o documento é emitido em contingência e transmitido automaticamente no retorno |
| CA-08 | Nenhuma comunicação SEFAZ ocorre no ciclo do request — verificado por ausência de chamada HTTP externa nos controllers/componentes |
| CA-09 | Duplo clique em "Emitir" não gera dois documentos (teste de idempotência) |
| CA-10 | Viagem consolida abastecimentos, despesas e pedágios, e exibe custo total e custo por km |
| CA-11 | Abastecimento calcula média km/l automaticamente e sinaliza desvio superior a X% da média histórica do veículo |
| CA-12 | Ordem de serviço de manutenção fecha com custo de peças e mão de obra apropriado ao veículo |
| CA-13 | XML de todos os documentos armazenados com particionamento por tenant e política de retenção parametrizável (padrão 132 meses) |
| CA-14 | Numeração sem buraco: teste de concorrência com 50 emissões simultâneas não gera número duplicado nem salto |
| CA-15 | MDF-e emitido com grupo `valePed` completo: um `disp` por veículo da composição, `CNPJForn` validado contra a lista da ANTT, `nCompra` com IDVPO e `categCombVeic` calculado dos eixos |
| CA-16 | Tentativa de transmitir com `tpValePed` descontinuado (`02` ou `03`) é bloqueada **antes** do envio, com mensagem explicativa |
| CA-17 | Vale-pedágio fornecido pela transportadora como embarcadora equiparada é registrado com prova de repasse e não é lançado como receita |
| CA-18 | Cadastro de veículo com carroceria de mercado, configuração de CVC e eixos, gerando `tpCar`, `tpRod` e `categCombVeic` corretos sem o operador ver código fiscal |
| CA-19 | Sistema recusa alocar motorista com CNH categoria C em combinação com acoplado de PBT ≥ 6.000 kg |
| CA-20 | Motorista agregado e autônomo não têm nenhuma tela de jornada, escala ou ponto disponível |
| CA-21 | Produto perigoso cadastrado gera o grupo `peri` do MDF-e e a descrição fiscal no padrão do capítulo 5.4, sem digitação livre |
| CA-22 | `tpCarga` é derivado de natureza + periculosidade: marcar "carga geral" com produto perigoso produz `11`, não `05` |
| CA-23 | Toda tela nova passou por mockup aprovado antes da implementação (convenção de trabalho HALC) |

---

## 8. Convenções de trabalho

Herdadas da prática já estabelecida no PetroWeb:

1. **Mockup antes de implementar.** Nenhuma tela nova é codificada sem mockup aprovado. Vale para o PetroWeb Frota integralmente.
2. **Textos de interface começam com letra maiúscula** — rótulos, chips, sublegendas e placeholders. Exceções: slugs, e-mails de exemplo e fragmentos no meio de frase.
3. **Documentos versionados são entregues como arquivo** com UTF-8 validado, nunca como copia-e-cola em várias mensagens.
4. **Catálogo de débitos técnicos** (`DEBITOS_TECNICOS.md`) mantido desde o primeiro commit, como no PetroWeb.
5. **Commits assinados como `hirlei`**, mensagens em português, escopo por módulo.

---

## 9. Riscos e mitigações

| Risco | Impacto | Mitigação |
|---|---|---|
| Layout fiscal muda durante o desenvolvimento (RTC em curso) | Alto | Gateway com interface; schemas versionados em diretório separado; monitorar portal oficial semanalmente |
| Biblioteca open source de CT-e não acompanhar a Reforma Tributária | Alto | Avaliar API comercial na fase 1 (seção 2 do documento fiscal); manter a possibilidade de troca por design |
| Homologação SEFAZ instável por UF | Médio | Testar cedo nas UFs dos primeiros clientes; ter fallback SVRS/SVSP |
| Certificado digital vencido em produção | Alto | Alerta automático em D-30 e bloqueio preventivo com aviso |
| Modelagem CT-e↔viagem feita como 1:1 | Alto | RN-02 é vinculante na modelagem — validar antes da primeira migration |
| Escopo da fase 1 crescer (4 módulos já é ambicioso) | Médio | Critérios de aceite da seção 7 são o contrato; nada entra sem sair |
| CIOT não pronto até 23/11/2026 | Alto | Antecipado para Sprint 12 no backlog, ainda dentro da fase 2 |
| CNPJ alfanumérico (NT Conjunta 2025.001) exigir refatoração ampla | Médio | Tratar CNPJ como `string(14)` desde a primeira migration e implementar o DV suportando letras já na Sprint 7 |
| Códigos de evento errados por copiar documentação de terceiros | Médio | Conferir contra o MOC oficial; a especificação fiscal marca as armadilhas conhecidas |
| Cliente tomar multa de vale-pedágio por operar como embarcador equiparado sem saber | Alto | RN-10 e Sprint 9; alerta de viagem com pedágio sem VPO registrado nem dispensa |
| Lista de fornecedoras de vale-pedágio desatualizada gerar rejeição 733 | Médio | Sincronização agendada da relação publicada no SVRS, com validação local antes de transmitir |
| Enum fiscal usado como tabela de negócio, travando o cadastro de frota | Médio | RN-11; tabelas operacionais separadas desde a Sprint 3 |
| Modelar jornada de agregado e gerar prova de vínculo empregatício para o cliente | Alto | RN-12; telas de jornada só para CLT |
| NCM desatualizada — muda por Resolução Gecex várias vezes ao ano | Médio | Rotina de sincronização com vigência, não seed estático |

---

## 10. Documentos relacionados

| Documento | Conteúdo |
|---|---|
| `02_MODELAGEM_DADOS.md` | ERD, dicionário de dados, migrations sugeridas |
| `03_ESPECIFICACAO_FISCAL_CTE_MDFE.md` | Layouts, fluxo SEFAZ, eventos, contingência, decisão de biblioteca |
| `04_BACKLOG.md` | Épicos, histórias de usuário e sprints |
| `05_CADASTROS_E_TABELAS_DE_DOMINIO.md` | Tipos de carga, veículos e CVC, carrocerias, motoristas, produtos e produtos perigosos — tabelas prontas para seed |

---

## 11. Fontes consultadas

- [NT 2026.002 — CT-e, CTe-OS e GTV-e: evoluções para a Reforma Tributária do Consumo (TecnoSpeed)](https://blog.tecnospeed.com.br/nt-2026-002-reforma-tributaria-ct-e/)
- [NT 2026.001 — Exigência do CIOT no MDF-e (TecnoSpeed)](https://blog.tecnospeed.com.br/nota-tecnica-2026-001-exigencia-do-ciot-no-mdfe/)
- [Portal Nacional do CT-e — Notas técnicas e schemas](https://www.cte.fazenda.gov.br/portal/listaConteudo.aspx?tipoConteudo=0xlG1bdBass%3D)
- [Portal DF-e SVRS — MDF-e](https://dfe-portal.svrs.rs.gov.br/mdfe/Documentos)
- [Portal DF-e SVRS — CT-e](https://dfe-portal.svrs.rs.gov.br/Cte/Documentos)
