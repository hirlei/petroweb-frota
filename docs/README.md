# PetroWeb Frota — Documentação de Projeto

Sistema SaaS multi-tenant de gestão de transporte rodoviário de cargas: frota, rotas, viagens, custos e emissão de CT-e e MDF-e.

**HALC · Produto da família PetroWeb · Versão 1.3 · 21/08/2026**

---

## Documentos

| # | Documento | Para quê serve | Leia quando |
|---|---|---|---|
| 01 | [`01_DOCUMENTO_MESTRE.md`](01_DOCUMENTO_MESTRE.md) | Visão, escopo, módulos, arquitetura, 11 regras de negócio estruturantes, roadmap e critérios de aceite | Sempre primeiro. É o contrato do projeto |
| 02 | [`02_MODELAGEM_DADOS.md`](02_MODELAGEM_DADOS.md) | ERD, dicionário de dados, ordem de migrations, máquinas de estado e índices | Antes de escrever a primeira migration |
| 03 | [`03_ESPECIFICACAO_FISCAL_CTE_MDFE.md`](03_ESPECIFICACAO_FISCAL_CTE_MDFE.md) | Layouts, fluxo SEFAZ, eventos, vale-pedágio, CIOT, contingência e roteiro de homologação | Antes de iniciar o módulo fiscal |
| 04 | [`04_BACKLOG.md`](04_BACKLOG.md) | Épicos, 163 histórias de usuário, 23 sprints, DoD e débito técnico assumido | No planejamento de cada sprint |
| 05 | [`05_CADASTROS_E_TABELAS_DE_DOMINIO.md`](05_CADASTROS_E_TABELAS_DE_DOMINIO.md) | Tipos de carga, veículos e CVC, carrocerias, motoristas (fixo/agregado/autônomo), produtos e produtos perigosos — tabelas prontas para seed | Ao construir os cadastros e os seeds |
| 06 | [`06_ARRANQUE_E_INFRA.md`](06_ARRANQUE_E_INFRA.md) | Fork do esqueleto do PetroWeb, provisionamento na VPS, DNS e primeiros passos | **Agora** — é o documento do arranque |
| — | [`provisionar-frota-el10.sh`](provisionar-frota-el10.sh) | Provisionamento do segundo app na VPS EL10 | No passo 7 do documento 06 |

---

## O produto na família PetroWeb

O PetroWeb Frota é o terceiro produto da linha, ao lado do **PetroWeb** (ERP de postos) e do **PetroWeb PDV** (frente de caixa). Isso significa marca e UX compartilhadas, padrões técnicos herdados dos irmãos, e uma sinergia que nenhum concorrente de TMS puro consegue copiar: o abastecimento feito em posto cliente do PetroWeb entra no custo da viagem sem digitação.

Onde os documentos precisam distinguir os produtos, o ERP de postos aparece como **PetroWeb Postos**.

---

## As cinco decisões já tomadas

1. **Nome:** PetroWeb Frota, produto da família PetroWeb.
2. **Stack:** Laravel 11 + Livewire 4, PostgreSQL 16, PHP 8.3 — mesma base dos irmãos.
3. **Modelo:** SaaS multi-tenant, isolamento por `empresa_id` (single database, discriminador).
4. **Escopo da Fase 1:** Cadastros + Frota, CT-e/MDF-e, Viagens e Rotas, Custos (abastecimento e manutenção) e **Vale-Pedágio Obrigatório**.
5. **Emissão fiscal:** API comercial na Fase 1, atrás de `SefazGatewayInterface`, com internalização possível depois sem reescrita.

---

## Decisões de arranque já tomadas

| Item | Escolha |
|---|---|
| Domínio | **frota.petroweb.app** |
| Base do código | **Fork do esqueleto do PetroWeb** — herda design system, tenancy, permissões, auditoria e 2FA |
| Infraestrutura | Mesma VPS — **8 GB de RAM, 2 núcleos, 100 GB SSD, AlmaLinux 10**. Já dimensionada |

## As decisões ainda pendentes

| Decisão | Prazo | Quem decide |
|---|---|---|
| Como o `stancl/tenancy` do PetroWeb realmente é usado | **Antes da primeira migration** | Técnico — ver documento 06, seção 6 |
| Ajustar o `postgresql.conf` para 8 GB e dois bancos | Janela de manutenção, sem pressa | Técnico — ver documento 06, seção 5.3 |
| Qual provedor de API fiscal | Antes da Sprint 7 | Comercial + técnico, após testar três em homologação |
| Qual fornecedora de vale-pedágio (FVPO) e instituição de pagamento (CIOT) | Antes da Sprint 9 | Comercial — de preferência **a mesma empresa** para os dois, já que a maioria das FVPO habilitadas também emite CIOT |

---

## Três marcos regulatórios que o cronograma não pode ignorar

| Quando | O que | Onde impacta |
|---|---|---|
| **Já vigente** | **Vale-Pedágio Obrigatório** — Res. ANTT 6.024/2023, só TAG e leitura de placa desde 31/01/2025, IDVPO desde 23/04/2025. MDF-e NT 2025.001 em produção desde 06/10/2025 | Sprint 9 — é a linha de base, não um prazo futuro |
| **31/08/2026** | Produção da Etapa 2 da NT 2026.002 — grupos IBS/CBS no CT-e, CT-e OS e GTV-e | Modelagem do CT-e (doc. 02, seção 7.1) e Sprint 7 |
| **23/11/2026** | Produção da NT 2026.001 — CIOT obrigatório no MDF-e, rejeição 684 | Sprint 12 e contratação da instituição de pagamento |

> Notas técnicas mudam de prazo: a Etapa 1 da NT 2026.002 tinha produção em 03/08/2026 na v1.00 e passou a "implementação futura" na v1.01. Confirme sempre no portal oficial antes de programar.

---

## Por onde começar

1. Criar o registro A de `frota.petroweb.app` apontando para a VPS — é a única espera real.
2. Enquanto propaga: criar o repositório `hirlei/petroweb-frota` e fazer a cirurgia do fork (documento 06, seção 4).
3. Rodar o `provisionar-frota-el10.sh` quando o domínio resolver.
4. Resolver a pendência do `stancl/tenancy` antes da primeira migration.
5. Puxar a Sprint 1 do documento 04 — boa parte dela vem pronta no fork.

---

## Nota sobre verificação

Os dados fiscais e regulatórios deste conjunto foram conferidos contra fontes independentes em 21/08/2026, e a checagem corrigiu erros que circulam em documentação de terceiros:

- Comprovante de entrega eletrônico do CT-e é o evento **110180** (e o cancelamento, **110181**) — não 110160/110161, que correspondem a "Registros do Multimodal".
- Alteração do pagamento do serviço de transporte no MDF-e é o evento **110118** — 110116 é "Pagamento da Operação de Transporte".
- A multa de vale-pedágio ao contratante é de **R$ 3.000** por veículo/viagem (Res. 6.024/2023). O valor de R$ 550 que circula em blogs é do regime **revogado**, ou é multa dirigida a concessionárias.
- O grupo `valePed` **não existe no CT-e** desde o layout 3.00 — só no MDF-e. E o VPO não integra o frete nem a base do ICMS, ao contrário do pedágio reembolsado.
- O prazo de guarda de XML tem divergência entre fontes (5 anos para o contribuinte × 132 meses do Ajuste SINIEF nº 2/2025). O produto adota 132 meses.
- A **NT Conjunta 2025.001 (CNPJ alfanumérico)** afeta o cálculo do DV da chave de acesso e a validação de CNPJ em todo o sistema.
- O **modal rodoviário do CT-e não tem grupo `peri`** — o grupo existe no CT-e apenas no modal aéreo. No rodoviário, produto perigoso vai estruturado no **MDF-e** e como descrição textual no CT-e.
- `categCombVeic` tem **10 valores, não 13** — os códigos `03`, `05` e `09` não existem. Derivar por aritmética simples produz XML inválido.
- **Ficha de emergência e envelope não são mais de porte obrigatório** desde a Res. ANTT 5.848/2019, dispensa mantida pela 5.998/2022. Muito TMS ainda bloqueia viagem por isso.

Pontos que a verificação **não** conseguiu confirmar e que precisam de checagem antes de virar código estão listados em cada documento. Os principais: quais dispensas de vale-pedágio permanecem válidas sob a Res. 6.024/2023 (as fontes disponíveis citam a resolução revogada); o número do ato que adiou a Etapa 1 da NT 2026.002; a convenção de `tpCar` para tanque e silo, que não têm código próprio; a revogação expressa da Res. CONTRAN 789/2020 pela 1.020/2025, que decide a validade do MOPP; e as cotas da NBR 7500 na edição de 2026.

Ainda assim, **nenhum documento aqui substitui o MOC e os schemas do portal oficial.** Baixe-os antes da Sprint 7.

---

## Convenções de trabalho

Herdadas da prática do PetroWeb:

- Mockup aprovado antes de implementar qualquer tela.
- Textos de interface começam com letra maiúscula.
- `DEBITOS_TECNICOS.md` mantido desde o primeiro commit.
- Documentos versionados entregues como arquivo, com UTF-8 validado.
