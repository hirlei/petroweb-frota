# PetroWeb Frota — Backlog de Desenvolvimento

> Épicos, histórias de usuário e organização em sprints.
> Documento complementar a `01_DOCUMENTO_MESTRE.md`. Versão 1.3 — 21/08/2026.

---

## 1. Como ler este backlog

| Convenção | Significado |
|---|---|
| Sprint | 2 semanas |
| ID | `EP-XX` épico, `US-XXX` história |
| Prioridade | 🔴 Bloqueante · 🟠 Alta · 🟡 Média · 🟢 Baixa |
| Estimativa | P (≤1 dia) · M (2-3 dias) · G (4-6 dias) · GG (quebrar antes de puxar) |
| DoD | *Definition of Done* — ver seção 8 |

**Regra de entrada:** nenhuma história vai para "em desenvolvimento" sem mockup aprovado quando envolve tela (convenção HALC).

**Regra de saída:** nenhuma história é dada como concluída sem atender à DoD da seção 8, integralmente.

> As histórias de cadastro (US-141 a US-163) apoiam-se nas tabelas de `05_CADASTROS_E_TABELAS_DE_DOMINIO.md`. Leia a seção correspondente antes de puxar a história — os valores de seed já estão prontos ali.

---

## 2. Épicos

| ID | Épico | Fase | Sprints |
|---|---|---|---|
| EP-01 | Fundação e multi-tenancy | 0 | 1-2 |
| EP-02 | Cadastros base | 1 | 3 |
| EP-03 | Gestão de frota | 1 | 4-5 |
| EP-04 | Operação: ordens, viagens e rotas | 1 | 6 |
| EP-05 | Fiscal: CT-e | 1 | 7-8 |
| EP-06 | Fiscal: MDF-e + vale-pedágio | 1 | 9 |
| EP-07 | Custos: abastecimento e manutenção | 1 | 5, 10 |
| EP-08 | Financeiro: receber, pagar, faturamento | 2 | 11 |
| EP-09 | CIOT e financeiro do vale-pedágio | 2 | 12 |
| EP-10 | Acerto de viagem e comissões | 2 | 13 |
| EP-11 | Distribuição DF-e e portal do cliente | 2 | 14-15 |
| EP-12 | App do motorista (PWA) | 3 | 16-18 |
| EP-13 | Roteirização e telemetria | 3 | 19-20 |
| EP-14 | BI e custo por km | 3 | 21 |
| EP-15 | Pneus com custo por km | 3 | 22 |
| EP-16 | Integração PetroWeb | 3 | 23 |

---

## 3. Fase 0 — Fundação

### Sprint 1 — Esqueleto e tenancy

| ID | História | Pri | Est |
|---|---|---|---|
| US-001 | Como desenvolvedor, quero o projeto Laravel criado com estrutura de pastas por domínio, padrão de código e CI configurado, para que todo commit passe por lint e testes | 🔴 | M |
| US-002 | Como desenvolvedor, quero o `TenantScope` global aplicado automaticamente a todo model de negócio, para que nenhuma consulta vaze dado entre empresas | 🔴 | G |
| US-003 | Como desenvolvedor, quero middleware que resolva o `empresa_id` a partir do usuário autenticado (nunca do request), para que o tenant não seja falsificável | 🔴 | M |
| US-004 | Como desenvolvedor, quero jobs e comandos agendados cientes de tenant, para que processamento assíncrono não misture empresas | 🔴 | M |
| US-005 | Como administrador, quero cadastrar empresas (tenants) com plano e status, para gerenciar as assinaturas | 🟠 | M |
| US-006 | Como usuário, quero fazer login com e-mail e senha e 2FA opcional, para acessar o sistema com segurança | 🔴 | M |

> **Teste de aceite crítico da sprint:** teste automatizado que autentica como empresa A, tenta acessar por ID direto um registro da empresa B e recebe 404. Sem esse teste verde, a sprint não fecha.

### Sprint 2 — Perfis, filiais e tabelas fiscais

| ID | História | Pri | Est |
|---|---|---|---|
| US-007 | Como administrador da transportadora, quero cadastrar filiais com CNPJ, IE, CRT, RNTRC e endereço, para emitir documentos por estabelecimento | 🔴 | G |
| US-008 | Como administrador, quero fazer upload do certificado A1 com senha criptografada e validação de CNPJ e validade, para habilitar a emissão fiscal | 🔴 | G |
| US-009 | Como administrador, quero receber alerta em D-30, D-15 e D-7 do vencimento do certificado, para não parar a operação | 🟠 | M |
| US-010 | Como administrador, quero definir papéis e permissões por usuário, para restringir acesso ao módulo fiscal e financeiro | 🟠 | G |
| US-011 | Como sistema, quero as tabelas de municípios IBGE, UF, países, CFOP e NCM carregadas por seed, para viabilizar a emissão fiscal | 🔴 | M |
| US-012 | Como auditor, quero registro de auditoria de toda alteração em documento fiscal e financeiro, com autor, momento, valores e motivo | 🟠 | M |
| US-013 | Como usuário, quero o layout base com navegação por módulo, seguindo o padrão visual HALC | 🟠 | G |

---

## 4. Fase 1 — MVP operacional e fiscal

### Sprint 3 — Cadastros (EP-02)

| ID | História | Pri | Est |
|---|---|---|---|
| US-014 | Como operador, quero cadastrar pessoas (física/jurídica) com múltiplos papéis, para não duplicar o mesmo CNPJ em cadastros diferentes | 🔴 | G |
| US-015 | Como operador, quero que o cadastro consulte o CNPJ e preencha razão social e endereço automaticamente, para reduzir digitação | 🟡 | M |
| US-016 | Como operador, quero cadastrar múltiplos endereços por pessoa (principal, coleta, entrega, cobrança), para atender clientes com várias unidades | 🟠 | M |
| US-017 | Como operador fiscal, quero informar o indicador de IE (contribuinte/isento/não contribuinte), para evitar rejeição da SEFAZ | 🔴 | P |
| US-018 | Como operador, quero cadastrar mercadorias com NCM e dados de produto perigoso (ONU, classe de risco, grupo de embalagem), para alimentar o grupo `peri` do **MDF-e** | 🟠 | M |
| US-019 | Como comercial, quero cadastrar tabelas de frete por cliente, rota e faixa de peso, com componentes (peso, valor, GRIS, pedágio, TDE, TDA) | 🟠 | G |
| US-020 | Como comercial, quero simular o frete a partir de origem, destino, peso e valor da carga, para cotar rapidamente | 🟠 | M |
| US-141 | Como desenvolvedor, quero os enums fiscais semeados como tabelas read-only (`tpRod`, `tpCar`, `tpCarga`, `categCombVeic`, `tpTransp`, `tpUnidTransp`, `tpUnidCarga`, `cUnid` do CT-e e do MDF-e), com código em string preservando o zero à esquerda | 🔴 | M |
| US-142 | Como operador, quero cadastrar **naturezas de carga** (granel sólido, líquido, frigorificada, neogranel, carga viva, indivisível, conteinerizada, geral) e que o sistema **derive** o `tpCarga` cruzando natureza com o indicador de periculosidade | 🔴 | M |
| US-143 | Como operador, quero classificar a mercadoria por natureza de carga e regime (lotação ou fracionada), para que o sistema sugira a carroceria adequada | 🟠 | M |
| US-144 | Como operador, quero informar dados logísticos do produto (dimensões, densidade, fator de cubagem, empilhamento, fragilidade), para calcular peso cubado e taxável | 🟠 | M |
| US-145 | Como sistema, quero o fator de cubagem em cascata — tabela de frete → cliente → produto → padrão da empresa — e nunca como constante de código | 🔴 | M |
| US-146 | Como operador, quero registrar controles setoriais do produto (Exército, Polícia Federal, SIF/MAPA, agrotóxico, temperatura controlada), com os documentos que cada um exige | 🟡 | M |
| US-147 | Como sistema, quero bloquear romaneio que junte agrotóxico com alimento, medicamento, ração ou bebida | 🟠 | M |

### Sprint 4 — Frota: veículos e motoristas (EP-03)

| ID | História | Pri | Est |
|---|---|---|---|
| US-021 | Como gestor de frota, quero cadastrar veículos de tração e reboque com placa, RENAVAM, tara, capacidade, tipo de rodado e carroceria | 🔴 | G |
| US-022 | Como gestor de frota, quero registrar a propriedade do veículo (própria, arrendada, agregada, terceiro) com proprietário e RNTRC | 🔴 | M |
| US-023 | Como gestor de frota, quero montar composições (cavalo + até 3 reboques), fixas ou por viagem | 🟠 | M |
| US-024 | Como gestor de RH, quero cadastrar motoristas com CNH, categoria, validade, vínculo, toxicológico e MOPP | 🔴 | G |
| US-025 | Como gestor, quero um painel único de vencimentos (CNH, licenciamento, ANTT, seguro, CIV/CIPP, toxicológico) com alerta configurável | 🟠 | G |
| US-026 | Como sistema, quero bloquear a alocação de veículo ou motorista com documento vencido, permitindo override justificado e registrado | 🔴 | M |
| US-148 | Como gestor de frota, quero escolher a **carroceria de mercado** (baú, sider, tanque, silo, prancha, cegonha, boiadeiro…) e que o sistema traduza para o `tpCar` fiscal, sem me mostrar código | 🔴 | G |
| US-149 | Como gestor de frota, quero selecionar a **configuração de CVC** (toco, truck, bitruck, carreta, vanderleia, bitrem, rodotrem, tritrem…), com PBTC, eixos e comprimento pré-preenchidos | 🔴 | G |
| US-150 | Como sistema, quero calcular `capacidade_kg` como `pbtc_kg − tara_kg` e impedir capacidade cadastrada acima do limite legal da configuração | 🟠 | M |
| US-151 | Como sistema, quero derivar `categCombVeic` do total de eixos da composição, respeitando que os códigos `03`, `05` e `09` não existem | 🔴 | M |
| US-152 | Como sistema, quero marcar o veículo como `exige_aet` quando a CVC passar de 57 t de PBTC ou 19,80 m, e alertar na viagem que não tiver AET válida | 🟠 | M |
| US-153 | Como gestor de frota, quero cadastrar tanque multicompartimentado com capacidade e produto por compartimento | 🟡 | M |
| US-154 | Como sistema, quero exigir CIV e CIPP/CTPP com arquivo anexado — não só data — para veículo que transporta perigoso a granel | 🟠 | M |
| US-155 | Como sistema, quero validar que a categoria da CNH é compatível com o veículo alocado, exigindo **E** para combinação com acoplado de PBT igual ou superior a 6.000 kg | 🔴 | M |
| US-156 | Como gestor, quero cadastrar **contrato de agregação** do TAC-agregado com exclusividade, modalidade de remuneração, RNTRC, apólices próprias e conta para pagamento eletrônico | 🔴 | G |
| US-157 | Como sistema, quero que telas de jornada, escala e ponto apareçam **apenas** para motorista CLT, nunca para agregado ou autônomo | 🔴 | M |
| US-158 | Como sistema, quero alertar quando um proprietário cadastrado como TAC atingir 3 veículos, porque a partir daí ele precisa migrar para ETC | 🟡 | P |
| US-159 | Como administrador, quero que o bloqueio por MOPP vencido seja configurável, dado que a Res. CONTRAN 1.020/2025 deixou a validade do CETPP em aberto | 🟠 | P |

### Sprint 5 — Abastecimento e consumo (EP-07 parte 1)

| ID | História | Pri | Est |
|---|---|---|---|
| US-027 | Como operador, quero registrar abastecimento com posto, litros, valor, odômetro e indicador de tanque cheio | 🟠 | M |
| US-028 | Como sistema, quero calcular km/l automaticamente entre dois abastecimentos de tanque cheio | 🟠 | M |
| US-029 | Como gestor, quero ser alertado quando a média de um abastecimento desviar mais de X% da média histórica do veículo | 🟠 | M |
| US-030 | Como gestor, quero o relatório de consumo por veículo, motorista e período | 🟡 | M |
| US-031 | Como operador, quero importar abastecimentos por planilha (cartão frota), para não digitar centenas de registros | 🟡 | G |
| US-032 | Como desenvolvedor, quero o campo `origem` e `referencia_externa` no abastecimento desde já, para viabilizar a integração PetroWeb na fase 3 | 🟡 | P |

### Sprint 6 — Operação: ordens, viagens e rotas (EP-04)

| ID | História | Pri | Est |
|---|---|---|---|
| US-033 | Como operador, quero registrar ordem de coleta com tomador, remetente, destinatário, mercadoria, peso, volume e valor | 🔴 | G |
| US-034 | Como operador, quero informar as chaves de NF-e da carga na ordem de coleta, para alimentar CT-e e MDF-e sem redigitar | 🔴 | M |
| US-035 | Como operador, quero cadastrar rotas com origem, destino, pontos de passagem, pedágios, distância e tempo estimado | 🟠 | G |
| US-036 | Como operador, quero criar a viagem alocando veículo, composição, motorista e rota, com validação de vencimentos | 🔴 | G |
| US-037 | Como operador, quero vincular N CT-e a uma viagem e um CT-e a várias viagens (transbordo), respeitando a relação N:N | 🔴 | M |
| US-038 | Como operador, quero um painel de operação com viagens por status (planejada, carregando, em trânsito, entregue, encerrada) | 🟠 | G |
| US-039 | Como operador, quero registrar ocorrências (avaria, atraso, extravio, sinistro) com foto e responsável | 🟠 | M |

### Sprint 7 — CT-e: emissão (EP-05 parte 1)

| ID | História | Pri | Est |
|---|---|---|---|
| US-040 | Como arquiteto, quero a `SefazGatewayInterface` com implementações comercial e fake, para que a aplicação não dependa de fornecedor | 🔴 | G |
| US-041 | Como desenvolvedor, quero o gerador e validador da chave de acesso de 44 dígitos com teste unitário, antes de qualquer transmissão | 🔴 | M |
| US-042 | Como sistema, quero reservar número de CT-e com bloqueio pessimista, para não gerar duplicidade nem salto sob concorrência | 🔴 | M |
| US-043 | Como operador fiscal, quero emitir CT-e a partir da ordem de coleta, com os dados já preenchidos | 🔴 | GG → quebrar |
| US-044 | Como operador fiscal, quero que a transmissão à SEFAZ ocorra em job assíncrono com retentativa e backoff, sem travar a tela | 🔴 | G |
| US-045 | Como sistema, quero consultar a chave na SEFAZ antes de transmitir e usar chave de idempotência, para que duplo clique não gere dois CT-e | 🔴 | M |
| US-046 | Como operador fiscal, quero ver mensagens de rejeição traduzidas em português com ação sugerida, não o retorno bruto da SEFAZ | 🟠 | M |
| US-047 | Como operador fiscal, quero o cálculo dos componentes do frete com `vTPrest` igual à soma exata, sem divergência de centavo | 🔴 | M |
| US-048 | Como operador fiscal, quero os grupos IBS/CBS preenchidos conforme a NT 2026.002 quando a filial for de Regime Normal | 🔴 | G |

### Sprint 8 — CT-e: eventos, DACTE e contingência (EP-05 parte 2)

| ID | História | Pri | Est |
|---|---|---|---|
| US-049 | Como operador fiscal, quero cancelar CT-e dentro do prazo legal, com justificativa validada | 🔴 | M |
| US-050 | Como operador fiscal, quero emitir Carta de Correção Eletrônica, com bloqueio dos campos não corrigíveis antes da transmissão | 🟠 | M |
| US-051 | Como operador fiscal, quero inutilizar faixa de numeração não utilizada | 🟠 | M |
| US-052 | Como operador, quero gerar e imprimir o DACTE em PDF com código de barras e marca d'água em homologação | 🔴 | G |
| US-053 | Como cliente, quero receber o DACTE e o XML por e-mail automaticamente na autorização | 🟠 | M |
| US-054 | Como sistema, quero monitorar o status do serviço SEFAZ por UF a cada 5 minutos, com resultado em cache | 🟠 | M |
| US-055 | Como operador fiscal, quero que a contingência (EPEC ou SVC) seja acionada automaticamente quando a SEFAZ estiver indisponível | 🔴 | G |
| US-056 | Como operador fiscal, quero um painel de documentos em contingência com contador de tempo, para não esquecer pendências | 🟠 | M |
| US-057 | Como operador fiscal, quero emitir CT-e complementar, de anulação e substituto | 🟡 | G |
| US-058 | Como operador fiscal, quero emitir CT-e de subcontratação e redespacho com o grupo `docAnt` | 🟡 | G |
| US-059 | Como contador, quero exportar em lote os XML de um período (documentos e eventos) | 🟠 | M |

### Sprint 9 — MDF-e e vale-pedágio (EP-06)

| ID | História | Pri | Est |
|---|---|---|---|
| US-060 | Como operador fiscal, quero que o MDF-e seja montado automaticamente a partir da viagem e dos CT-e vinculados | 🔴 | GG → quebrar |
| US-061 | Como operador fiscal, quero emitir MDF-e com composição (tração + reboques), condutores e percurso interestadual | 🔴 | G |
| US-062 | Como operador fiscal, quero emitir MDF-e de carga própria referenciando NF-e (`tpEmit = 2`) | 🟠 | M |
| US-063 | Como operador fiscal, quero encerrar o MDF-e informando data, UF e município de encerramento | 🔴 | M |
| US-064 | Como gestor, quero um painel de MDF-e em aberto com tempo decorrido, para não acumular pendência na SEFAZ | 🔴 | M |
| US-065 | Como sistema, quero alertar ao emitir novo MDF-e quando houver manifesto anterior em aberto para o mesmo veículo | 🟠 | M |
| US-066 | Como operador fiscal, quero incluir condutor em MDF-e autorizado (troca de motorista em rota) | 🟠 | M |
| US-067 | Como operador fiscal, quero incluir DF-e em MDF-e com carregamento posterior | 🟡 | M |
| US-068 | Como operador fiscal, quero cancelar MDF-e antes de registro de passagem | 🟠 | P |
| US-069 | Como operador, quero gerar o DAMDFE em PDF com QR Code | 🔴 | M |
| US-070 | Como operador fiscal, quero que o grupo `peri` do MDF-e seja montado a partir do cadastro da mercadoria, dentro de `infCTe`/`infNFe`, com `nONU` de 4 posições e `xClaRisco` incluindo o risco subsidiário | 🟠 | G |
| US-160 | Como operador, quero que o sistema monte automaticamente a descrição fiscal do produto perigoso no padrão `ONU nº, NOME, Subclasse, (risco subsidiário), GE, quantidade` | 🟠 | M |
| US-161 | Como sistema, quero identificar a carga em **quantidade limitada** a partir das colunas 8 e 9 da Relação de Produtos Perigosos, dispensar MOPP e sinalização, e incluir "QUANT. LTDA" no documento | 🟡 | G |
| US-162 | Como sistema, quero verificar **incompatibilidade química** (NBR 14619) ao montar o romaneio, por classe e por número ONU, bloqueando carregamento conjunto proibido | 🟠 | G |
| US-163 | Como operador, quero informar unidades de transporte e de carga (`infUnidTransp`/`infUnidCarga`) com lacres, para carga conteinerizada e paletizada | 🟡 | M |
| US-132 | Como sistema, quero sincronizar a lista de CNPJ de fornecedoras de vale-pedágio publicada pelo SVRS, para validar `CNPJForn` localmente e evitar a rejeição 733 | 🔴 | M |
| US-133 | Como operador, quero registrar o vale-pedágio **recebido** do embarcador na viagem, com fornecedora, IDVPO, valor e tipo, um registro por veículo da composição | 🔴 | G |
| US-134 | Como operador, quero registrar o vale-pedágio **fornecido** ao subcontratado quando a transportadora for embarcadora equiparada, guardando a prova do repasse | 🔴 | G |
| US-135 | Como operador fiscal, quero que os dados do vale-pedágio alimentem o grupo `valePed` do MDF-e com um `disp` por veículo, sem redigitação | 🔴 | G |
| US-136 | Como sistema, quero calcular `categCombVeic` a partir do número de eixos da composição, para não tomar a rejeição 731 | 🔴 | M |
| US-137 | Como sistema, quero bloquear a transmissão com `tpValePed` descontinuado (`02` cupom, `03` cartão), explicando ao operador que só TAG e leitura de placa são aceitos desde 31/01/2025 | 🔴 | M |
| US-138 | Como operador, quero registrar a dispensa de vale-pedágio (carga própria ou percurso sem pedágio) com motivo, para que o alerta não incomode indevidamente | 🟠 | M |
| US-139 | Como gestor, quero ser alertado quando a viagem percorrer rota com pedágio e não houver vale-pedágio registrado nem dispensa, dado que a multa é de R$ 3.000 por veículo/viagem mais indenização de 2× o frete | 🟠 | M |

> **Por que na Sprint 9 e não na 12:** o `valePed` é grupo do próprio MDF-e. Construir o MDF-e agora e o vale-pedágio três sprints depois significa emitir manifesto incompleto no intervalo e refazer a tela de viagem. Além disso, a transportadora que subcontrata autônomo é **embarcadora equiparada** — ela é a devedora do vale, não a credora.

### Sprint 10 — Manutenção, entregas e fechamento de custo (EP-07 parte 2)

| ID | História | Pri | Est |
|---|---|---|---|
| US-071 | Como gestor de manutenção, quero cadastrar planos preventivos por km ou tempo, com antecedência de alerta | 🟠 | M |
| US-072 | Como gestor de manutenção, quero abrir ordem de serviço com itens de peça e mão de obra, oficina interna ou externa | 🟠 | G |
| US-073 | Como gestor, quero ser alertado quando um veículo atingir o gatilho de manutenção preventiva | 🟠 | M |
| US-074 | Como operador, quero registrar despesas de viagem (pedágio, alimentação, hospedagem, chapa) com comprovante | 🟠 | M |
| US-075 | Como operador, quero registrar a entrega com canhoto digitalizado, recebedor e data/hora | 🔴 | M |
| US-076 | Como operador fiscal, quero registrar o comprovante de entrega eletrônico do CT-e (evento 110180) | 🟡 | M |
| US-077 | Como gestor, quero ver o custo consolidado da viagem (combustível, pedágio, motorista, manutenção, outros) e o custo por km | 🔴 | G |
| US-078 | Como gestor, quero ver a margem da viagem (receita dos CT-e menos custo total) | 🟠 | M |
| US-079 | Como desenvolvedor, quero um comando de reconciliação noturna dos custos denormalizados da viagem, para corrigir divergências | 🟠 | M |
| US-080 | Como gestor, quero que a viagem só seja encerrada com MDF-e encerrado e canhoto registrado (regra configurável) | 🟠 | M |

> **Marco:** ao fim da Sprint 10, os 15 critérios de aceite da Fase 1 (documento mestre, seção 7) devem estar verdes. Sprint 10 inclui uma semana reservada para o roteiro de homologação de 40 casos (documento fiscal, seção 12).

---

## 5. Fase 2 — Financeiro e conformidade

### Sprint 11 — Financeiro base (EP-08)

| ID | História | Pri | Est |
|---|---|---|---|
| US-081 | Como financeiro, quero gerar fatura de frete agrupando N CT-e por cliente e período | 🔴 | G |
| US-082 | Como financeiro, quero que o destinatário da fatura seja o tomador do CT-e, sem escolha manual | 🔴 | M |
| US-083 | Como financeiro, quero gerar títulos a receber com parcelas e vencimentos | 🟠 | G |
| US-084 | Como financeiro, quero registrar títulos a pagar (frete de terceiros, oficinas, combustível, despesas) | 🟠 | G |
| US-085 | Como financeiro, quero baixar títulos com data, valor e forma de pagamento | 🟠 | M |
| US-086 | Como financeiro, quero centros de custo por veículo, filial e rota, aplicados aos movimentos | 🟠 | M |
| US-087 | Como gestor, quero o relatório de contas a receber e a pagar por vencimento e por cliente | 🟠 | M |
| US-088 | Como financeiro, quero emitir boleto ou cobrança Pix a partir da fatura | 🟡 | G |

### Sprint 12 — CIOT e financeiro do vale-pedágio (EP-09) — **prazo legal 23/11/2026**

| ID | História | Pri | Est |
|---|---|---|---|
| US-089 | Como arquiteto, quero a integração com instituição de pagamento de frete homologada pela ANTT, atrás de interface própria | 🔴 | G |
| US-090 | Como operador, quero solicitar a geração do CIOT ao criar viagem com frete de terceiros | 🔴 | G |
| US-091 | Como operador fiscal, quero que o número do CIOT seja levado ao grupo `infCIOT` do MDF-e automaticamente | 🔴 | M |
| US-092 | Como sistema, quero bloquear a emissão de MDF-e sem CIOT quando `tpEmit` for 1, 2 ou 3 em rodoviário remunerado por terceiros, evitando a rejeição 684 | 🔴 | M |
| US-093 | Como financeiro, quero registrar adiantamento e quitação do CIOT com retorno da instituição | 🟠 | M |
| US-094 | Como financeiro, quero que o vale-pedágio que a transportadora compra como embarcadora equiparada gere título a pagar à fornecedora, vinculado à viagem para compor o custo por km | 🟠 | M |
| US-095 | Como gestor, quero relatório de CIOT emitidos, pagos e pendentes | 🟡 | M |
| US-140 | Como operador, quero comprar o vale-pedágio direto pelo sistema, por integração com a fornecedora (FVPO), em vez de digitar o IDVPO obtido no portal dela | 🟡 | G |

> O registro e a declaração do vale-pedágio no MDF-e estão na **Sprint 9** (US-132 a US-139). O que sobra aqui é o lado financeiro e a compra automatizada — que, com sorte, é o mesmo fornecedor do CIOT (ver seção 6.3.5 da especificação fiscal).

> **Alerta de cronograma:** a homologação da NT 2026.001 abre em 21/09/2026 e a produção em 23/11/2026. A contratação da instituição de pagamento precisa estar resolvida **antes** da Sprint 12 — é decisão comercial, não técnica, e costuma demorar.

### Sprint 13 — Acerto de viagem e comissões (EP-10)

| ID | História | Pri | Est |
|---|---|---|---|
| US-096 | Como financeiro, quero registrar adiantamentos ao motorista por viagem, com finalidade e forma | 🟠 | M |
| US-097 | Como financeiro, quero fazer o acerto de viagem confrontando adiantado, comprovado e glosado | 🟠 | G |
| US-098 | Como financeiro, quero calcular comissão de motorista/agregado por percentual do frete ou por km | 🟠 | M |
| US-099 | Como financeiro, quero calcular diárias por viagem conforme parâmetro | 🟡 | M |
| US-100 | Como motorista, quero ver o extrato do meu acerto (adiantamentos, despesas, comissão, saldo) | 🟡 | M |

### Sprints 14-15 — Distribuição DF-e e portal do cliente (EP-11)

| ID | História | Pri | Est |
|---|---|---|---|
| US-101 | Como operador fiscal, quero baixar automaticamente da SEFAZ os DF-e emitidos contra o CNPJ da filial, com controle de NSU | 🟠 | G |
| US-102 | Como operador fiscal, quero manifestar ciência, confirmação ou desconhecimento da operação | 🟠 | M |
| US-103 | Como operador, quero que a NF-e baixada preencha automaticamente a ordem de coleta e o CT-e | 🟠 | G |
| US-104 | Como cliente, quero acessar um portal e rastrear minhas cargas por número de CT-e ou NF-e | 🟠 | G |
| US-105 | Como cliente, quero baixar a 2ª via do CT-e (XML e DACTE) e o canhoto digitalizado | 🟠 | M |
| US-106 | Como cliente, quero consultar minhas faturas e a 2ª via do boleto | 🟡 | M |
| US-107 | Como cliente, quero receber notificação automática de coleta, saída e entrega | 🟡 | M |

---

## 6. Fase 3 — Inteligência e mobilidade

### Sprints 16-18 — App do motorista, PWA offline-first (EP-12)

| ID | História | Pri | Est |
|---|---|---|---|
| US-108 | Como motorista, quero ver minha viagem do dia com rota, cargas e entregas, mesmo sem sinal | 🟠 | GG |
| US-109 | Como motorista, quero fazer o checklist de saída do veículo com fotos | 🟠 | G |
| US-110 | Como motorista, quero registrar abastecimento com foto do cupom, offline, sincronizando depois | 🟠 | G |
| US-111 | Como motorista, quero registrar despesa de viagem com comprovante fotografado | 🟠 | M |
| US-112 | Como motorista, quero capturar o canhoto de entrega com a câmera e assinatura na tela | 🟠 | G |
| US-113 | Como motorista, quero registrar ocorrência com foto e geolocalização | 🟠 | M |
| US-114 | Como motorista, quero confirmar entrega e disparar o evento eletrônico de comprovante | 🟠 | M |
| US-115 | Como arquiteto, quero fila de sincronização com resolução de conflito, para que nada se perca em área sem sinal | 🔴 | G |

### Sprints 19-20 — Roteirização e telemetria (EP-13)

| ID | História | Pri | Est |
|---|---|---|---|
| US-116 | Como operador, quero calcular distância, tempo e pedágio da rota por API de mapas | 🟠 | G |
| US-117 | Como operador, quero sugestão de sequência de entregas otimizada para carga fracionada | 🟡 | G |
| US-118 | Como gestor, quero ver a posição atual dos veículos em mapa, por integração de telemetria | 🟡 | G |
| US-119 | Como gestor, quero alerta de desvio de rota e de parada não prevista | 🟡 | M |
| US-120 | Como gestor, quero cerca eletrônica em pontos críticos | 🟢 | M |

### Sprint 21 — BI e custo por km (EP-14)

| ID | História | Pri | Est |
|---|---|---|---|
| US-121 | Como gestor, quero o custo por km por veículo, decomposto em combustível, manutenção, pneus, motorista e custos fixos rateados | 🟠 | G |
| US-122 | Como gestor, quero a rentabilidade por cliente, rota e tipo de carga | 🟠 | G |
| US-123 | Como gestor, quero o índice de ociosidade da frota e de retorno vazio | 🟡 | M |
| US-124 | Como gestor, quero um dashboard executivo com os indicadores do mês contra o mês anterior | 🟠 | G |
| US-125 | Como gestor, quero exportar qualquer relatório em XLSX e PDF | 🟡 | M |

### Sprints 22-23 — Pneus e integração PetroWeb (EP-15, EP-16)

| ID | História | Pri | Est |
|---|---|---|---|
| US-126 | Como gestor de frota, quero cadastrar pneus por número de fogo com vida, sulco e valor | 🟡 | M |
| US-127 | Como gestor de frota, quero registrar instalação, rodízio, remoção, conserto, recapagem e sucateamento | 🟡 | G |
| US-128 | Como gestor, quero o custo por km de cada pneu ao longo de suas vidas | 🟡 | M |
| US-129 | Como gestor, quero mapa de posições de pneus por veículo, com sulco atual | 🟢 | M |
| US-130 | Como transportadora cliente do PetroWeb Postos, quero que os abastecimentos feitos em posto integrado entrem no PetroWeb Frota sem digitação | 🟠 | GG |
| US-131 | Como gestor, quero conciliar automaticamente o abastecimento importado com a viagem em curso do veículo | 🟠 | G |

---

## 7. Débito técnico assumido

Registrar em `DEBITOS_TECNICOS.md` desde o primeiro commit, como no PetroWeb.

| ID | Débito | Origem | Quando revisitar |
|---|---|---|---|
| DT-01 | Custos denormalizados em `viagens`, mantidos por observer + reconciliação noturna | Performance do painel de operação | Se houver divergência recorrente, migrar para evento-sourcing do custo |
| DT-02 | Multi-tenant por discriminador, não por banco separado | Simplicidade e custo | Ao chegar em clientes com exigência de isolamento físico |
| DT-03 | Emissão fiscal via API comercial | Time-to-market e risco de layout na RTC | Fase 3+, quando o volume justificar a internalização |
| DT-04 | `payload` JSON ao lado de colunas fiscais | Layout fiscal instável | Estabilização da RTC |
| DT-05 | Sem roteirizador próprio | Fora do core | Só se o custo de API se tornar proibitivo |

---

## 8. Definition of Done

Uma história só está concluída quando **todos** os itens abaixo forem verdadeiros:

1. Mockup aprovado antes da implementação (histórias com tela).
2. Código revisado, com PHPStan/Larastan no nível acordado sem erro novo.
3. Testes automatizados cobrindo o caminho feliz e ao menos um caminho de erro.
4. **Teste de isolamento multi-tenant** quando a história cria ou altera tabela de negócio.
5. Textos de interface começando com letra maiúscula (convenção HALC).
6. Nenhuma chamada externa dentro do request HTTP (histórias fiscais e de integração).
7. Migration reversível e testada em `migrate:fresh`.
8. Documentação atualizada quando a história altera modelagem, regra fiscal ou contrato de interface.
9. Débito técnico introduzido registrado em `DEBITOS_TECNICOS.md`.
10. Demonstrada e aceita pelo responsável do produto.

---

## 9. Resumo de cronograma

| Fase | Sprints | Semanas | Marco |
|---|---|---|---|
| 0 — Fundação | 1-2 | 4 | Sistema multi-tenant autenticado, com deploy |
| 1 — MVP | 3-10 | 16 | Ciclo completo: cadastro → CT-e → viagem → MDF-e → custo |
| 2 — Financeiro e conformidade | 11-15 | 10 | Faturamento, CIOT (prazo legal), portal do cliente |
| 3 — Inteligência e mobilidade | 16-23 | 16 | App do motorista, BI, integração PetroWeb |

**Total até o fim da Fase 2: 30 semanas (~7 meses).**

> Duas datas regulatórias atravessam esse cronograma e não são negociáveis: a produção da NT 2026.002 em **31/08/2026** (grupos IBS/CBS no CT-e — precisa estar na modelagem desde a Sprint 7) e a produção da NT 2026.001 em **23/11/2026** (CIOT no MDF-e — Sprint 12). Se o cronograma escorregar, essas duas se movem para dentro, não para fora.
