# PetroWeb Frota — Cadastros e Tabelas de Domínio

> Tipos de carga, classificação de veículos, cadastro de veículos, motoristas e produtos (com produtos perigosos e demais controles).
> Documento complementar a `01_DOCUMENTO_MESTRE.md`. Versão 1.3 — 21/08/2026.

> ⚠️ **Como usar este documento.** As tabelas marcadas **✅ Fiscal** são enums fechados da SEFAZ: semeie exatamente como estão, com `codigo` em `VARCHAR` (preservando o zero à esquerda — `'01'`, nunca `1`) e a descrição idêntica ao MOC. As marcadas **⚙️ Operacional** são tabelas nossas, editáveis pelo cliente. As marcadas **⚠️ Referência** são valores de mercado ou derivados: servem de padrão de cadastro, não de verdade normativa.

---

## Índice

| Seção | Conteúdo |
|---|---|
| 1 | Princípio: separar domínio fiscal de domínio operacional |
| 2 | Tipos de carga |
| 3 | Tipos de veículo e combinações (CVC) |
| 4 | Carrocerias |
| 5 | Cadastro de veículos |
| 6 | Motoristas: fixo, agregado e autônomo |
| 7 | Cadastro de produtos e mercadorias |
| 8 | Produtos perigosos |
| 9 | Outros controles de produto |
| 10 | Unidades de medida e cubagem |
| 11 | Resumo do que semear |

---

## 1. Princípio: separar domínio fiscal de domínio operacional

Este é o erro estrutural mais comum em TMS brasileiro: usar o enum da SEFAZ como tabela de negócio.

O layout fiscal tem **6 tipos de carroceria**. A transportadora trabalha com **vinte e poucos** — baú, sider, graneleiro, prancha, tanque, silo, cegonha, boiadeiro, basculante, canavieiro, florestal, munck, poliguindaste. Se o cadastro só oferecer os 6 códigos fiscais, o operador não consegue descrever a própria frota; se o sistema inventar códigos, o XML é rejeitado.

**A solução é sempre a mesma:** duas tabelas, com a operacional apontando para a fiscal.

```
carrocerias (operacional, editável)  ──FK──►  tp_car (fiscal, read-only, 6 registros)
tipos_carga (operacional)            ──FK──►  tp_carga (fiscal, 12 registros)
cvc_configuracoes (operacional)      ──FK──►  tp_rod (fiscal, 6 registros)
```

O operador escolhe "Sider"; o sistema envia `tpCar = 05`. O operador escolhe "Tanque"; o sistema envia a convenção que **nós** fixamos, porque tanque não tem código fiscal próprio.

Regra: **nenhuma tela mostra código fiscal ao usuário final.** Códigos aparecem só em telas de configuração e no XML.

---

## 2. Tipos de carga

### 2.1 `tpCarga` — enum fiscal do MDF-e ✅ Fiscal

Fica em `prodPred/tpCarga` (grupo do produto predominante).

| Código | Descrição |
|---|---|
| `01` | Granel sólido |
| `02` | Granel líquido |
| `03` | Frigorificada |
| `04` | Conteinerizada |
| `05` | Carga Geral |
| `06` | Neogranel |
| `07` | Perigosa (granel sólido) |
| `08` | Perigosa (granel líquido) |
| `09` | Perigosa (carga frigorificada) |
| `10` | Perigosa (conteinerizada) |
| `11` | Perigosa (carga geral) |
| `12` | Granel pressurizada |

> **Observe o padrão:** "perigosa" **não é um tipo isolado** — é o cruzamento de natureza × periculosidade. Os códigos 07 a 11 são as versões perigosas de 01, 02, 03, 04 e 05.
>
> **Consequência de modelagem:** não crie 12 registros soltos no cadastro. Modele `natureza_carga` + `flag_perigosa` e **derive** o `tpCarga`:
>
> ```
> granel_solido      + perigosa=false → 01   + perigosa=true → 07
> granel_liquido     + perigosa=false → 02   + perigosa=true → 08
> frigorificada      + perigosa=false → 03   + perigosa=true → 09
> conteinerizada     + perigosa=false → 04   + perigosa=true → 10
> carga_geral        + perigosa=false → 05   + perigosa=true → 11
> neogranel          → 06        granel_pressurizada → 12
> ```
>
> Derivar em vez de armazenar elimina a classe inteira de bugs em que o operador marca "carga geral" e "produto perigoso" e o sistema envia `05` em vez de `11`.

### 2.2 Naturezas de carga ⚙️ Operacional

| Natureza | Definição | Carroceria típica | O que exige da operação |
|---|---|---|---|
| **Carga geral solta** | Unidades manuseadas individualmente: caixas, sacas, fardos, bobinas | Baú, sider, carga seca | Conferência unitária; maior tempo de doca; maior índice de avaria |
| **Carga geral unitizada** | Agrupada em pallet, big bag ou fardo | Sider, baú | Empilhadeira nas duas pontas; melhor cubagem; doca mais rápida |
| **Granel sólido** | Sem embalagem, homogêneo, carregado por gravidade ou esteira | Graneleiro, basculante, silo | Pesagem em balança rodoviária; controle de quebra de peso; classificação por umidade e impureza |
| **Granel líquido** | Em tanque | Tanque mono ou multicompartimentado | Tanque certificado (CIV/CIPP); arqueação; se combustível, regime ANP e ICMS-ST |
| **Granel pressurizado** | Gases liquefeitos, GLP, amônia anidra | Tanque pressurizado ou criogênico | Quase sempre perigoso — MOPP, painéis, kit de emergência |
| **Neogranel** | Volumosos sem embalagem individual: veículos, máquinas agrícolas, toras | Cegonha, prancha, florestal | Amarração normatizada; alta cubagem e baixa densidade; frete por unidade, não por peso |
| **Frigorificada / refrigerada** | Cadeia de frio contínua | Baú frigorífico ou refrigerado | Registro contínuo de temperatura; SIF/vigilância sanitária; seguro com avaria térmica. Parada prolongada = perda total |
| **Carga viva** | Animais vivos | Boiadeiro, gaiola | GTA (Guia de Trânsito Animal); tempo máximo de viagem; a Res. CONTRAN 882/2021 admite CVC articulada de até 25 m para animais vivos |
| **Carga perigosa** | Classificada pela ONU (9 classes) | Tanque, gaiola, baú sinalizado | MOPP/CETPP; rotulagem e painéis; seguro RCTR-C + RC-DC ambiental; restrição de rota, horário e estacionamento |
| **Carga indivisível / excedente** | Não pode ser fracionada sem perder a função; excede peso e/ou dimensão legal | Prancha, lowboy, extensível, linha de eixos | **AET obrigatória**; batedores; projeto com ART/CREA; circulação diurna; curso de Transporte de Carga Indivisível |
| **Conteinerizada** | Em contêiner ISO lacrado | Bug / porta-container / esqueleto | Twist-locks; o peso do contêiner conta no PBTC; VGM no comércio exterior; lacre integra o CT-e |

### 2.3 Regime de contratação ⚙️ Operacional

Transversal à natureza — vale a pena ser campo separado, porque muda a operação inteira.

| Regime | Sigla | Definição | Impacto no sistema |
|---|---|---|---|
| **Lotação** | FTL | Um embarcador ocupa o veículo inteiro | Um CT-e por viagem; ponto a ponto; frete negociado por viagem |
| **Fracionada** | LTL | Vários embarcadores/destinatários no mesmo veículo | **N CT-e por MDF-e**; roteirização e cross-docking; frete por peso/cubagem com faixa mínima. É o caso que exige a relação N:N da RN-02 |

---

## 3. Tipos de veículo e combinações (CVC)

### 3.1 `tpRod` — tipo de rodado ✅ Fiscal

| Código | Descrição |
|---|---|
| `01` | Truck |
| `02` | Toco |
| `03` | Cavalo Mecânico |
| `04` | VAN |
| `05` | Utilitário |
| `06` | Outros |

> **`tpRod` descreve a unidade tratora, não a combinação.** Bitrem, rodotrem, carreta e vanderleia são **todos** `03 — Cavalo Mecânico`. A configuração da combinação é informação nossa, na tabela da seção 3.3, que se relaciona com `tpRod` por FK.

### 3.2 `categCombVeic` — categoria de combinação veicular ✅ Fiscal

Campo do grupo `valePed` do MDF-e. Obrigatório quando há vale-pedágio, sob pena de **rejeição 731**.

| Código | Descrição |
|---|---|
| `02` | Veículo Comercial 2 eixos |
| `04` | Veículo Comercial 3 eixos |
| `06` | Veículo Comercial 4 eixos |
| `07` | Veículo Comercial 5 eixos |
| `08` | Veículo Comercial 6 eixos |
| `10` | Veículo Comercial 7 eixos |
| `11` | Veículo Comercial 8 eixos |
| `12` | Veículo Comercial 9 eixos |
| `13` | Veículo Comercial 10 eixos |
| `14` | Veículo Comercial acima de 10 eixos |

> ⚠️ **Armadilha confirmada:** a sequência **não é contínua**. Os códigos **`03`, `05` e `09` não existem** — a numeração vem da tabela tarifária de pedágio da ANTT, e os intermediários correspondem a categorias não comerciais (motocicleta, automóvel, automóvel com reboque) que ficaram fora do domínio do MDF-e. São **10 valores válidos**, não 13. Quem gera o código por aritmética simples (`eixos + n`) produz XML inválido.

**Regra de derivação — implementar como função pura sobre o número de eixos:**

```php
match (true) {
    $eixos <= 2  => '02',
    $eixos === 3 => '04',
    $eixos === 4 => '06',
    $eixos === 5 => '07',
    $eixos === 6 => '08',
    $eixos === 7 => '10',
    $eixos === 8 => '11',
    $eixos === 9 => '12',
    $eixos === 10 => '13',
    default      => '14',
};
```

### 3.3 Configurações de combinação veicular ⚠️ Referência / ⚙️ Operacional

Base normativa dos limites: **Resolução CONTRAN nº 882, de 13/12/2021** (sucessora das Res. 210 e 211/2006, a "Lei da Balança").

| # | Nome popular | Eixos | Tração | PBT/PBTC | Compr. máx. | Capac. típica (kg) | AET | `tpRod` | `categCombVeic` | CNH |
|---|---|---|---|---|---|---|---|---|---|---|
| 1 | VUC | 2 | 4x2 | ~6.300 kg | 6,30 m ❓ | 3.000–3.500 | Não | `05` | `02` | B/C |
| 2 | 3/4 | 2 | 4x2 | ~10.000 kg | 14,00 m | 5.500–6.500 | Não | `02` | `02` | C |
| 3 | Toco | 2 | 4x2 | 16.000 kg | 14,00 m | 9.000–11.000 | Não | `02` | `02` | C |
| 4 | Truck | 3 | 6x2 / 6x4 | 23.000 kg | 14,00 m | 14.000–16.000 | Não | `01` | `04` | C |
| 5 | Bitruck | 4 | 8x2 / 8x4 | **29.000 kg** | 14,00 m | 18.000–23.000 | Não | `01` | `06` | C |
| 6 | Carreta simples 2 eixos | 4 | 4x2 | 33.000 kg | 18,60 m | 20.000–24.000 | Não | `03` | `06` | **E** |
| 7 | Carreta 2 eixos distanciados | 4 | 4x2 | 36.000 kg | 18,60 m | 23.000–26.000 | Não | `03` | `06` | **E** |
| 8 | Carreta simples 3 eixos | 5 | 4x2 | 41.500 kg | 18,60 m | 27.000–30.000 | Não | `03` | `07` | **E** |
| 9 | Vanderleia | 5 | 4x2 | 46.000 kg | 18,60 m | 30.000–33.000 | Não | `03` | `07` | **E** |
| 10 | Carreta LS (cavalo trucado + SR 3 eixos) | 6 | 6x2 / 6x4 | 48.500 kg | 18,60 m | 32.000–35.000 | Não | `03` | `08` | **E** |
| 11 | Vanderleia trucada | 6 | 6x2 / 6x4 | 53.000 kg | 18,60 m | 36.000–39.000 | Não | `03` | `08` | **E** |
| 12 | Romeu e Julieta | 6 | 6x2 / 6x4 | 50.000 kg | 19,80 m | 33.000–36.000 | Não | `01` | `08` | **E** |
| 13 | Carreta 7 eixos | 7 | 6x4 | **58.500 kg** | 18,60 m | 40.000–43.000 | Não | `03` | `10` | **E** |
| 14 | Bitrem 7 eixos (≤ 19,80 m) | 7 | 6x4 | 57.000 kg | 19,80 m | 37.000–40.000 | Não | `03` | `10` | **E** |
| 15 | Treminhão | 7 | 6x4 | 63.000 kg | 25–30 m | 42.000–46.000 | **Sim** | `01` | `10` | **E** |
| 16 | Bitrem 8 eixos | 8 | 6x4 | 65.500 kg | 25–30 m | 44.000–48.000 | **Sim** | `03` | `11` | **E** |
| 17 | Bitrem 9 eixos ("bitrenzão") | 9 | 6x4 | 74.000 kg | 25–30 m | 50.000–54.000 | **Sim** | `03` | `12` | **E** |
| 18 | Rodotrem 9 eixos | 9 | 6x4 | 74.000 kg | 19,80–30 m | 48.000–53.000 | **Sim** | `03` | `12` | **E** |
| 19 | Tritrem | 9 | 6x4 | 74.000 kg | 25–30 m | 50.000–54.000 | **Sim** | `03` | `12` | **E** |

**Limites por eixo (Res. 882/2021, art. 6º):**

| Configuração do eixo | Limite |
|---|---|
| Eixo isolado, 2 pneus | 6 t |
| Eixo isolado, 4 pneus | 10 t |
| 2 eixos direcionais (distância ≥ 1,20 m) | 12 t |
| 2 eixos em tandem (1,20 m < d ≤ 2,40 m) | 17 t |
| 2 eixos não em tandem | 15 t |
| 3 eixos em tandem em semirreboque | 25,5 t |
| Eixos distanciados (d > 2,40 m) | contam como isolados, 10 t cada |

**Dimensões (art. 4º):** largura 2,60 m · altura 4,40 m · comprimento 14,00 m (não articulado), 18,60 m (cavalo + semirreboque), 19,80 m (caminhão/ônibus + reboque e CVC de mais de duas unidades).

**Tolerância de fiscalização (art. 50):** 5% sobre PBT/PBTC e 12,5% sobre o peso por eixo.

**AET (arts. 17 e 18):** exigida para CVC com mais de duas unidades quando o PBTC passa de **57 t** ou o comprimento passa de **19,80 m**. A AET admite até **74 t** e comprimento inferior a 25 m, com tração 6x4 acima de 58,5 t, freios conjugados, velocidade máxima de 80 km/h e circulação do amanhecer ao pôr do sol.

> **Cuidados com esta tabela:**
>
> 1. **A capacidade de carga é referência, não verdade.** É `PBTC − tara`, e a tara varia por fabricante e por implemento. Use como valor sugerido no cadastro e calcule a real a partir da tara do CRLV. Modele `capacidade_min_kg` e `capacidade_max_kg`, nunca um número único.
> 2. **Bitruck: o limite legal é 29 t**, teto do art. 6º, alínea "a", para veículo não articulado. Fontes de mercado citam 32 t — está errado.
> 3. **VUC é definição municipal**, não federal. Os 6,30 m e ~6.300 kg são a referência clássica da CET-SP, mas houve portaria municipal ampliando o comprimento e não conseguimos confirmar o valor vigente em 2026. **Trate como parâmetro por município.**
> 4. **Tritrem e treminhão exigem AET** por comprimento, ainda que algumas tabelas de mercado digam o contrário. Vale a norma.

---

## 4. Carrocerias

### 4.1 `tpCar` — tipo de carroceria ✅ Fiscal

| Código | Descrição |
|---|---|
| `00` | Não aplicável |
| `01` | Aberta |
| `02` | Fechada/Baú |
| `03` | Granelera |
| `04` | Porta Container |
| `05` | Sider |

> ⚠️ A grafia oficial é **"Granelera"**, com E — não "Graneleira". Alguns validadores e o DAMDFE reproduzem a descrição literal.

### 4.2 Carrocerias de mercado ⚙️ Operacional

| Carroceria | O que transporta | `tpCar` | Confiança |
|---|---|---|---|
| Baú / furgão | Caixas, sacas, paletes, carga seca | `02` | Direta |
| Baú frigorífico | Congelados, −15 a −20 °C | `02` | Direta |
| Baú refrigerado | 0 a −10 °C: laticínios, medicamentos, hortifrúti | `02` | Direta |
| Sider | Paletizados, autopeças, bebidas — carga lateral | `05` | Direta |
| Graneleiro (grade alta) | Grãos, adubos, fertilizantes | `03` | Direta |
| Carga seca (grade baixa) | Encaixotados, chapas e barras de aço | `01` | Direta |
| Prancha / lowboy | Cargas indivisíveis: máquinas, transformadores | `01` | Direta |
| Bug / porta-container / esqueleto | Contêineres 20', 40', HC | `04` | Direta |
| Basculante / caçamba | Areia, brita, terra, minério, entulho | `01` | Convenção |
| Canavieiro | Cana-de-açúcar | `01` | Convenção |
| Florestal / toreiro | Toras, eucalipto, cavaco | `01` | Convenção |
| Boiadeiro / gaiola de gado | Bovinos, equinos, suínos vivos | `01` | Convenção |
| **Tanque** | Combustíveis, etanol, químicos, leite, óleos | `01` **ou** `02` | ⚠️ **Sem código próprio** |
| **Silo** | Cimento, cal, cinzas, farinha | `03` **ou** `02` | ⚠️ **Sem código próprio** |
| Cegonha | Veículos zero-km e seminovos | `01` | Convenção |
| Gaiola de botijões | GLP P13, P45 | `01` | Convenção |
| Munck | Cargas que exigem içamento no local | `01` | Convenção |
| Plataforma / guincho | Socorro e remoção | `01` | Convenção |
| Poliguindaste | Caçambas estacionárias, resíduos | `01` | Convenção |

> **Só 5 dos 19 tipos de mercado têm correspondência fiscal direta.** Tanque e silo — justamente os de maior valor no segmento de combustíveis, que é onde a HALC já atua — **não têm código próprio**. Fixe a convenção no seed, documente a escolha e não deixe o operador decidir caso a caso, ou dois MDF-e da mesma frota sairão com códigos diferentes.

**Estrutura sugerida da tabela `carrocerias`:**

`empresa_id` (nulo = padrão do sistema), `codigo`, `nome`, `tp_car_fiscal`, `natureza_padrao`, `exige_temperatura_controlada`, `exige_civ_cipp`, `exige_certificacao_inmetro`, `aceita_produto_perigoso`, `permite_conteiner`, `capacidade_m3_referencia`, `ativo`.

---

## 5. Cadastro de veículos

O cadastro se organiza em cinco blocos. O que muda em relação à versão anterior da modelagem é o detalhamento — em especial `eixos`, que passou a ser obrigatório porque alimenta `categCombVeic`.

### 5.1 Identificação

| Campo | Observação |
|---|---|
| `placa` | Aceitar padrão antigo e Mercosul. `unique(empresa_id, placa)` |
| `renavam`, `chassi` | Chassi com 17 posições |
| `tipo` | `tracao` \| `reboque` \| `semirreboque` \| `dolly` |
| `marca`, `modelo`, `ano_fabricacao`, `ano_modelo` | |
| `cor`, `uf_licenciamento`, `municipio_licenciamento_id` | |
| `categoria_crlv` | Conforme documento |

### 5.2 Configuração física — o bloco que alimenta o fiscal

| Campo | Origem / uso |
|---|---|
| `tp_rod` | `tpRod` do MDF-e — só para unidade de tração |
| `tp_car` | `tpCar` do MDF-e |
| `carroceria_id` | FK para a carroceria de mercado (seção 4.2) |
| `cvc_configuracao_id` | FK para a configuração da seção 3.3 |
| **`eixos`** | **Obrigatório.** Base do `categCombVeic`. Sem ele, rejeição 731 |
| `tracao` | `4x2`, `6x2`, `6x4`, `8x2`, `8x4` |
| `tara_kg` | Do CRLV. Campo do MDF-e |
| `pbt_kg`, `pbtc_kg` | Do CRLV e da configuração |
| `capacidade_kg` | **Calculado:** `pbtc_kg − tara_kg`. Campo do MDF-e |
| `capacidade_m3` | Campo do MDF-e |
| `comprimento_m`, `largura_m`, `altura_m` | Para restrição de rota |
| `qtd_compartimentos`, `compartimentos` (json) | Tanques multicompartimentados |
| `exige_aet` | Derivado da CVC |

### 5.3 Propriedade e vínculo

| Campo | Observação |
|---|---|
| `propriedade` | `propria` \| `arrendada` \| `agregada` \| `terceiro` |
| `proprietario_id` | FK `pessoas`. **Obrigatório** quando não for própria |
| `proprietario_rntrc` | Vai ao MDF-e no grupo `prop` |
| `proprietario_tp_transp` | `1` ETC, `2` TAC, `3` CTC |
| `contrato_agregacao_id` | Quando agregado — ver seção 6.4 |

### 5.4 Documentação e conformidade

| Documento | Quando | Bloqueia operação? |
|---|---|---|
| Licenciamento (CRLV) | Sempre | Sim |
| Seguro (RCTR-C / RCF-DC) | Sempre | Sim |
| ANTT / RNTRC do proprietário | Frota agregada e terceiros | Sim |
| **CIV** — Certificado de Inspeção Veicular | Tanques e produtos perigosos | Sim |
| **CIPP / CTPP** | Transporte de produtos perigosos a granel | Sim |
| Cronotacógrafo | Conforme categoria | Não |
| AET | CVC acima de 57 t ou 19,80 m | Sim, para a viagem que exigir |
| Certificação INMETRO do tanque | Granel líquido | Sim |

> **Os originais do CIPP/CTPP e do CIV são exigidos em circulação** pelo art. 23 da Res. ANTT 5.998/2022 no transporte de perigosos a granel. Emissão eletrônica desde 04/04/2021 — o sistema deve guardar o arquivo, não só a data.

### 5.5 Operação e custo

`odometro_atual`, `horimetro_atual`, `combustivel`, `capacidade_tanque_l`, `media_referencia_kml`, `custo_km_alvo`, `centro_custo_id`, `rastreador_id`, `status`.

### 5.6 Validações do cadastro

1. Placa em formato antigo ou Mercosul; dígito verificador do RENAVAM.
2. `capacidade_kg` não pode exceder `pbtc_kg − tara_kg`.
3. Reboque e semirreboque **não** recebem `tp_rod` — o campo é da tração.
4. `eixos` obrigatório em todos, porque o `categCombVeic` soma os eixos da composição inteira.
5. Veículo com `propriedade != propria` exige proprietário com RNTRC ativo.
6. Carroceria tanque exige CIV e, para perigosos, CIPP.

---

## 6. Motoristas: fixo, agregado e autônomo

### 6.1 Os três tipos de transportador ✅ Fiscal — `tpTransp` do MDF-e

Base: **Lei 11.442/2007, art. 2º**.

| Código | Sigla | Definição |
|---|---|---|
| `1` | **ETC** | Empresa de Transporte Rodoviário de Cargas — pessoa jurídica |
| `2` | **TAC** | Transportador Autônomo de Cargas — pessoa física que tem no transporte sua atividade profissional |
| `3` | **CTC** | Cooperativa de Transporte Rodoviário de Cargas — registrada na OCB |

### 6.2 As duas subcategorias de TAC — art. 4º da Lei 11.442/2007

| Subcategoria | Definição legal | Natureza do contrato |
|---|---|---|
| **TAC-agregado** (§1º) | Coloca **veículo próprio** a serviço de um contratante, **com exclusividade, mediante remuneração certa** | Continuado |
| **TAC-independente** (§2º) | Presta serviço em **caráter eventual e sem exclusividade, mediante frete ajustado** a cada viagem | Por viagem |

### 6.3 Quadro comparativo — o que muda no sistema

| Dimensão | **Fixo (CLT)** | **Agregado (TAC-agregado)** | **Autônomo (TAC-independente)** |
|---|---|---|---|
| Natureza jurídica | Contrato de trabalho | **Contrato comercial** | **Contrato comercial** |
| Dono do veículo | A transportadora | **O motorista** | **O motorista** |
| Exclusividade | Sim, com subordinação | **Sim, sem subordinação** | Não |
| Remuneração | Salário e adicionais | **Remuneração certa** | **Frete ajustado por viagem** |
| Vínculo de emprego | Sim | **Não** (art. 5º) | **Não** (art. 5º) |
| RNTRC | O da empresa | **Próprio**, categoria TAC | **Próprio**, categoria TAC |
| Emite CT-e | Não | Não (PF); a contratante emite | Não |
| **CIOT** | **Não se aplica** | **Obrigatório** | **Obrigatório** |
| **Vale-pedágio** | Não se aplica | **Sim** — antecipado pelo contratante | **Sim** |
| Pagamento do frete | Folha | **Exclusivamente eletrônico**, via instituição homologada | Idem |
| Seguros | RCTR-C da empresa | **RCTR-C, RC-DC e RC-V próprios** | Idem |
| Encargos | INSS, FGTS, 13º, férias | Sem encargos trabalhistas; INSS de contribuinte individual | Idem |

**Art. 5º da Lei 11.442/2007**, em texto literal: *"As relações decorrentes do contrato de transporte de cargas […] são sempre de natureza comercial, não ensejando, em nenhuma hipótese, a caracterização de vínculo de emprego."* O STF confirmou a constitucionalidade do dispositivo no julgamento da **ADC 48 e da ADI 3961**, em 2020.

### 6.4 A decisão de produto mais delicada deste módulo

O risco residual é jurisprudencial: a Justiça do Trabalho ainda reconhece vínculo quando encontra **remuneração fixa desvinculada de viagem, controle de jornada e subordinação hierárquica direta**.

> **Regra de projeto:** o PetroWeb Frota **não modela jornada, escala ou ponto para agregado e autônomo.** Para eles existem `contrato_agregacao`, `tarifa_acordada` e `ordem de coleta` — não `jornada_trabalho` nem `registro_ponto`. Um sistema que gera espelho de ponto de um agregado está produzindo prova contra o próprio cliente em uma eventual reclamatória.
>
> Isso não é preciosismo: é a diferença entre uma tela útil e um passivo. As telas de jornada só aparecem para `vinculo = clt`.

**Tabela `contratos_agregacao`** ⚙️ Operacional:

`empresa_id`, `pessoa_id` (o TAC), `veiculo_id`, `numero`, `inicio`, `fim`, `exclusividade` (bool), `modalidade_remuneracao` (`remuneracao_certa` \| `percentual_frete` \| `por_km` \| `por_viagem`), `valor_base`, `percentual`, `rntrc`, `apolice_rctrc`, `apolice_rcdc`, `apolice_rcv`, `validade_apolices`, `conta_pagamento` (dados para o pagamento eletrônico obrigatório), `status`, `arquivo_contrato_path`.

### 6.5 Requisitos documentais do motorista

**CNH — art. 143 do CTB:**

| Categoria | Habilita |
|---|---|
| **B** | PBT até 3.500 kg |
| **C** | Veículo de carga com PBT acima de 3.500 kg |
| **D** | C + passageiros acima de 8 lugares |
| **E** | **Combinação com unidade acoplada de PBT igual ou superior a 6.000 kg** |

> **Regra de validação:** `categoria_cnh >= 'E'` sempre que o veículo alocado tiver unidade acoplada com PBT ≥ 6.000 kg. Na prática, **todas as configurações das linhas 6 a 19 da seção 3.3 exigem CNH E**. A categoria C exige um ano de habilitação prévia em B. O motorista profissional precisa da observação **EAR** na CNH.

**Exame toxicológico:**

| Item | Regra |
|---|---|
| Quem | Categorias **C, D e E**, mesmo sem atividade remunerada |
| Periodicidade | **30 meses** (2 anos e 6 meses), com tolerância de 30 dias |
| Também exigido | Na obtenção e na renovação da CNH |
| Base legal | Art. 148-A do CTB (Lei 13.103/2015, alterado pelas Leis 14.071/2020 e 14.599/2023), regulamentado pela Res. CONTRAN 843/2021 |
| Janela de detecção | 90 dias (cabelo, pelo ou unha) |
| Validade do laudo | 90 dias da coleta |
| CLT | Admissional e demissional obrigatórios, cumulativos com o periódico |
| Penalidade | Infração gravíssima, 7 pontos, multa 5x e suspensão do direito de dirigir — aplicada automaticamente 30 dias após o vencimento |

> ⚠️ Uma fonte setorial menciona 24 meses; duas outras confirmam 30. Semeie como parâmetro (`periodicidade_toxicologico_meses = 30`), não como constante.

**MOPP / CETPP — atenção, mudou em dezembro de 2025:**

| Item | Situação |
|---|---|
| Nome atual | **CETPP** — Curso Especializado em Transporte de Produto Perigoso. O mercado ainda diz "MOPP" |
| Quando é exigido | Sempre que houver produto perigoso classificado, salvo as isenções de quantidade limitada (seção 8.5) |
| Norma anterior | Res. CONTRAN 789/2020 — validade de 5 anos com atualização obrigatória |
| Norma vigente | **Res. CONTRAN 1.020/2025**, de 01/12/2025 |
| O que mudou | A nova resolução **não estabelece prazo de validade nem exigência de atualização** para o CETPP. Só o curso de Ambulância manteve validade explícita de 5 anos |

> ⚠️ **Não confirmado:** a Res. 1.020/2025 não menciona revogação expressa da 789/2020 no texto acessível, e o próprio setor registra pontos pendentes de esclarecimento. **Decisão de produto:** manter o campo `mopp_validade` ativo, com o bloqueio de alocação **configurável** (`bloquear_por_mopp_vencido`), em vez de fixo no código. Não desligue o controle interno com base em uma leitura ainda em disputa.

**Outros cursos especializados (Res. 1.020/2025, art. 67)** — dos sete previstos, três importam ao transporte de carga: **Transporte de Produto Perigoso (CETPP)**, **Transporte de Carga Indivisível** (ligado a AET e prancha) e **Transporte Remunerado de Mercadorias com Motocicletas** (motofrete). A carga horária foi delegada a normativo da SENATRAN — modele `carga_horaria_h` como nullable.

**Jornada — Lei 13.103/2015**, aplicável integralmente ao CLT:

| Regra | Valor |
|---|---|
| Jornada normal | 8 h, prorrogável em até 2 h |
| Direção contínua máxima | **5h30** ininterruptas |
| Intervalo intrajornada | 30 min, fracionável |
| Descanso interjornada | 11 h em 24 h, com no mínimo 8 h ininterruptas |
| Hora extra | +50% |

### 6.6 Regra de negócio: o TAC que cresce

Um TAC pode ser proprietário, coproprietário, comodatário ou arrendatário de **até 3 veículos**. Passando disso, precisa migrar para **ETC** (Res. ANTT 5.982/2022, com procedimento atualizado pela Res. 6.068/2025).

> O sistema deve alertar quando um proprietário TAC cadastrado atingir 3 veículos — é informação útil para o cliente e evita contratar quem está irregular.

---

## 7. Cadastro de produtos e mercadorias

### 7.1 Blocos do cadastro

| Bloco | Campos |
|---|---|
| **Identificação** | `codigo_interno`, `descricao`, `descricao_complementar`, `marca`, `ativo` |
| **Fiscal** | `ncm` (8), `cest` (7), `gtin`, `gtin_tributavel`, `unidade_comercial`, `unidade_tributavel`, `cunid_cte` (2), `cunid_mdfe` (2) |
| **Físico/logístico** | `peso_bruto_kg`, `peso_liquido_kg`, `comprimento_m`, `largura_m`, `altura_m`, `volume_m3` (calculado), `densidade_kg_m3`, `fator_cubagem_kg_m3`, `empilhamento_max_camadas`, `permite_empilhar`, `carga_max_sobre_topo_kg`, `fragil`, `sentido_obrigatorio` |
| **Natureza** | `natureza_carga`, `tipo_carga_id`, `carroceria_recomendada_id` |
| **Temperatura** | `exige_temp_controlada`, `temp_min_c`, `temp_max_c`, `exige_registro_continuo` |
| **Perigoso** | Ver seção 8 |
| **Controles setoriais** | Ver seção 9 |

### 7.2 NCM

- **8 dígitos.** Os 6 primeiros seguem o Sistema Harmonizado; os 2 últimos são do Mercosul.
- Hierarquia: capítulo (2) → posição (4) → subposição (5-6) → item (7) → subitem (8).
- Universo: 21 seções, 96 capítulos, mais de 10.000 códigos.

> ⚠️ **A NCM muda o tempo todo** por Resoluções Gecex — só em 2026 houve alterações em fevereiro e março. **A tabela precisa de rotina de atualização periódica**, não pode ser seed estático. Modele com `vigencia_inicio` e `vigencia_fim` e sincronize.

### 7.3 GTIN / EAN

| Formato | Uso |
|---|---|
| GTIN-8 | Embalagens muito pequenas |
| GTIN-12 | Mercado norte-americano |
| GTIN-13 | Unidade de consumo — padrão no Brasil |
| **GTIN-14** | **Caixa / embalagem de despacho — o que interessa ao transportador** |

Prefixos 789 e 790 são brasileiros e validados contra o Cadastro Centralizado de GTIN da SEFAZ. Quando o produto não tem código, o valor literal é **`SEM GTIN`**.

---

## 8. Produtos perigosos

### 8.1 Regulamento vigente

| Norma | Situação |
|---|---|
| **Decreto 96.044/1988** | Vigente — norma-matriz do Regulamento para o Transporte Rodoviário de Produtos Perigosos |
| **Resolução ANTT 5.998/2022** | **Vigente** desde 01/06/2023 — aprova o Regulamento e as Instruções Complementares (Anexo) |
| Resolução ANTT 6.016/2023 | Altera a 5.998/2022 |
| Resolução ANTT 6.056/2024 | Altera a 5.998/2022 — sinalização em cargas mistas, alinhamento sanitário, reclassificação de infrações. Efeitos desde 28/02/2025 |
| Res. 5.232/2016, 5.848/2019, 5.947/2021 | **Revogadas** |

**Normas ABNT aplicáveis:** NBR 7500 (identificação para transporte — painéis e rótulos), NBR 7501 (terminologia), NBR 7503 (ficha de emergência e envelope), NBR 9735 (conjunto de equipamentos para emergências), NBR 14619 (incompatibilidade química).

### 8.2 Classes e subclasses de risco ✅ Referência normativa

| Código | Classe | Descrição |
|---|---|---|
| `1` | 1 | **Explosivos** |
| `1.1` | 1 | Com risco de explosão em massa |
| `1.2` | 1 | Com risco de projeção, sem risco de explosão em massa |
| `1.3` | 1 | Com risco de fogo e pequeno risco de explosão ou projeção |
| `1.4` | 1 | Sem risco significativo |
| `1.5` | 1 | Muito insensíveis, com risco de explosão em massa |
| `1.6` | 1 | Extremamente insensíveis, sem risco de explosão em massa |
| `2.1` | 2 | **Gases inflamáveis** |
| `2.2` | 2 | **Gases não inflamáveis, não tóxicos** — asfixiantes ou oxidantes |
| `2.3` | 2 | **Gases tóxicos** |
| `3` | 3 | **Líquidos inflamáveis** — vapor inflamável até 60 °C em vaso fechado. Sem subclasses |
| `4.1` | 4 | **Sólidos inflamáveis**, autorreagentes, polimerizantes e explosivos sólidos insensibilizados |
| `4.2` | 4 | **Sujeitas a combustão espontânea** — pirofóricas e auto-aquecíveis |
| `4.3` | 4 | **Em contato com água emitem gases inflamáveis** |
| `5.1` | 5 | **Substâncias oxidantes** |
| `5.2` | 5 | **Peróxidos orgânicos** |
| `6.1` | 6 | **Substâncias tóxicas** |
| `6.2` | 6 | **Substâncias infectantes** |
| `7` | 7 | **Material radioativo** — sem subclasses; usa categorias de rótulo I-BRANCA, II-AMARELA, III-AMARELA |
| `8` | 8 | **Substâncias corrosivas** — sem subclasses |
| `9` | 9 | **Perigosos diversos**, incluindo risco ao meio ambiente |

**Grupos de compatibilidade da Classe 1** — para explosivos a classificação é `subclasse + letra` (ex.: `1.4S`, `1.1D`): A, B, C, D, E, F, G, H, J, K, L, N, S. *Não existe a letra I*, para não confundir com o algarismo 1.

### 8.3 Grupo de embalagem

| Grupo | Grau de risco |
|---|---|
| **I** | Alto |
| **II** | Médio |
| **III** | Baixo |

Não se aplica às classes **1**, **2**, **5.2**, **6.2** e **7**, que usam critérios próprios.

### 8.4 O grupo `peri` — onde ele realmente fica

> ⚠️ **Correção importante em relação à versão anterior desta documentação.** O modal **rodoviário do CT-e não tem grupo `peri`**. O grupo existe no CT-e apenas no **modal aéreo**. No transporte rodoviário, quem carrega a informação de produto perigoso é o **MDF-e**.

**MDF-e — grupo `peri`** ✅ Fiscal. Ocorre em três pontos, com a mesma composição interna: dentro de `infCTe`, dentro de `infNFe` (ambos sob `infMunDescarga`) e dentro de `infMDFeTransp` (aquaviário).

| Tag | Descrição | Tipo | Ocorr. | Tam. |
|---|---|---|---|---|
| `peri` | Grupo — preenchido quando há produto classificado pela ONU como perigoso | grupo | **0-n** | — |
| `nONU` | **Número ONU** | string | **1-1 obrigatório** | **4** |
| `xNomeAE` | Nome apropriado para embarque | string | 0-1 | 1-150 |
| `xClaRisco` | Classe ou subclasse **e risco subsidiário** | string | 0-1 | 1-40 |
| `grEmb` | Grupo de embalagem | string | 0-1 | 1-6 |
| `qTotProd` | Quantidade total por produto | **string** | **1-1 obrigatório** | 1-20 |
| `qVolTipo` | Quantidade e tipo de volumes | **string** | 0-1 | 1-60 |

**Detalhes de implementação que evitam retrabalho:**

- `qTotProd` e `qVolTipo` são **strings**, não numéricos — a unidade vai no próprio texto: `"1000 KG"`, `"20 CAIXAS DE 5 L"`. Formate no sistema, não deixe o operador digitar livre.
- `nONU` tem **exatamente 4 posições** — grave com zeros à esquerda: `0004`, `1203`, `1993`.
- `xClaRisco` recebe classe **e** risco subsidiário no mesmo campo: `"6.1 (3)"`, `"3 (8)"`, `"1.4S"`.

Exemplo de preenchimento:

```
nONU      = 1203
xNomeAE   = GASOLINA
xClaRisco = 3
grEmb     = II
qTotProd  = 5000 L
qVolTipo  = 1 TANQUE
```

> **Tags que não existem** e que aparecem em documentação de terceiros: `pontoFulgor` e `qTotGed`. O ponto de fulgor é atributo **cadastral** (vem da FISPQ) e serve para derivar o grupo de embalagem da Classe 3, mas **não é transmitido**. `qTotEmb` existe apenas no CT-e aéreo.

### 8.5 Quantidade limitada

**Existe** no regulamento brasileiro — capítulo 3.4 do Anexo da Res. 5.998/2022.

> ⚠️ **"Quantidade excetuada" (códigos E0–E5 do ADR/ONU) não foi adotada no Brasil.** O capítulo 3.5 do Anexo brasileiro trata de "transporte de embalagens vazias e não limpas". Não modele E0–E5 como regra nacional.

**O que a quantidade limitada dispensa** (item 3.4.3.4, isenção por veículo):

| Item | Dispensado? |
|---|---|
| Rótulos de risco no veículo | Sim |
| Painéis de segurança no veículo | Sim |
| Símbolo de risco ambiental no veículo | Sim |
| **Curso MOPP do condutor** | **Sim** |
| EPIs e kit de emergência NBR 9735 | Sim, **exceto extintores** |
| Restrições de itinerário, estacionamento e local de carga/descarga | Sim |

**O que permanece obrigatório:** marcação do nº ONU no volume; marca de "Quantidade Limitada" da NBR 7500; rótulos de risco **no volume**; a expressão **"quantidade limitada"** ou **"QUANT. LTDA"** no documento de transporte (item 5.4.1.6.2); extintor; embalagem homologada e íntegra; verificação de incompatibilidade.

**Em carga mista prevalece o limite do produto com a menor quantidade isenta** (item 3.4.3.2).

### 8.6 Documentos — o que mudou e o que muitos sistemas erram

> ⚠️ **A Ficha de Emergência e o Envelope para Transporte NÃO são mais de porte obrigatório.** A dispensa veio com a Res. 5.848/2019 e foi mantida pela 5.947/2021 e pela 5.998/2022. Muito TMS ainda bloqueia a viagem por falta de ficha.
>
> A dispensa do **porte** não elimina a NBR 7503 como referência de informação de emergência, e a ficha continua exigida por contrato e por normas setoriais — agrotóxicos, por exemplo. **Modele como campo recomendado, não bloqueante.**

> ⚠️ **A declaração-padrão do expedidor foi extinta** em 01/06/2023, com a entrada em vigor da 5.998/2022. Permanecem declarações específicas ligadas a Provisões Especiais (PE 223, líquidos inflamáveis em IBC composto, produtos de higiene e cosméticos).

**Documentos exigidos em circulação — art. 23 da Res. 5.998/2022:**

| # | Documento | Quando |
|---|---|---|
| I | Originais do **CTPP ou CIPP** e do **CIV** | Transporte a granel |
| II | Documento de transporte com as informações dos produtos | Sempre — na prática, a NF-e e o CT-e |
| III | Outros exigidos por Provisões Especiais | Conforme a coluna 7 da Relação de Produtos Perigosos |

Artigos correlatos: **art. 20** (condutor aprovado em curso do CONTRAN), **art. 21** (normas de segurança em carga, descarga e transbordo) e **art. 22** (condutor e auxiliares com calça comprida, camisa com mangas e calçado fechado).

### 8.7 Como o produto perigoso aparece no documento fiscal

Ordem obrigatória dos elementos — capítulo 5.4 do Anexo:

```
ONU <nº>, <NOME APROPRIADO PARA EMBARQUE>, <subclasse>, (<risco subsidiário>), GE <I|II|III>, <quantidade total>
```

Exemplo oficial:

```
ONU 1098, ÁLCOOL ALÍLICO, Subclasse 6.1, (Classe 3), GE I, 1000 kg
```

Acrescentar **"QUANTIDADE LIMITADA"** ou **"QUANT. LTDA"** quando aplicável, e **"RESÍDUO"** ou **"VAZIO, NÃO LIMPO"** nos casos correspondentes.

> **O sistema monta essa string automaticamente** a partir do cadastro do produto. Digitação livre aqui é fonte garantida de autuação.

### 8.8 A Relação de Produtos Perigosos — o seed que importa

A Relação (capítulo 3.2 do Anexo) tem **9 colunas**, e é ela que deve virar a tabela `produtos_perigosos`:

| Coluna | Conteúdo | Campo |
|---|---|---|
| 1 | Número ONU | `num_onu` char(4) |
| 2 | Nome apropriado para embarque | `nome_embarque` varchar(150) |
| 3 | Classe ou subclasse (+ grupo de compatibilidade na Classe 1) | `classe_risco` varchar(10) |
| 4 | Risco subsidiário | `risco_subsidiario` varchar(20) |
| 5 | Número de risco | `num_risco` varchar(4) |
| 6 | Grupo de embalagem | `grupo_embalagem` char(3) |
| 7 | Provisões especiais | `provisoes_especiais` text |
| 8 | **Quantidade limitada por veículo** | `qtd_lim_veiculo` decimal |
| 9 | **Quantidade limitada por embalagem interna** | `qtd_lim_emb_interna` decimal |

**"Zero" na coluna 8 ou 9 significa que o transporte naquele regime não é permitido** — não é ausência de limite.

### 8.9 Número de risco — o painel laranja

O número superior do painel é o número de identificação de perigo; o inferior é o número ONU.

| Algarismo | Significado |
|---|---|
| `2` | Desprendimento de gás por pressão ou reação química |
| `3` | Inflamabilidade de líquidos e gases, ou líquido sujeito a autoaquecimento |
| `4` | Inflamabilidade de sólidos, ou sólido sujeito a autoaquecimento |
| `5` | Efeito oxidante — intensifica o fogo |
| `6` | Toxicidade ou risco de infecção |
| `7` | Radioatividade |
| `8` | Corrosividade |
| `9` | Risco de reação espontânea violenta |
| `0` | Sem significado — segundo algarismo quando não há risco subsidiário |
| `X` (prefixo) | **Reage perigosamente com água — proibido o uso de água** |

A repetição de um algarismo indica intensidade: `33` líquido muito inflamável, `88` muito corrosivo, `X423` sólido inflamável que reage com água emitindo gases inflamáveis. Valores frequentes: `30`, `33`, `20`, `23`, `26`, `50`, `60`, `80`, `90`.

**Sinalização (NBR 7500):** painel de segurança de 400 × 300 mm em veículos em geral e 350 × 250 mm em utilitários até 3,5 t, fundo laranja com borda e algarismos pretos. Rótulos de risco em losango de no mínimo 300 × 300 mm no veículo e 100 × 100 mm em embalagens; Classe 7 obrigatoriamente 250 × 250 mm.

> ⚠️ Essas cotas vêm da edição de 2003 com a Emenda 1:2004, a única publicamente acessível. **A edição vigente é a NBR 7500:2026**, publicada em 08/01/2026. Reconfira no exemplar oficial da ABNT antes de gravar como constante — e como o sistema não imprime painel, isso é informação de apoio ao cliente, não regra de emissão.

### 8.10 Incompatibilidade química

A **NBR 14619** define a tabela de segregação para carregamento conjunto, exigida por remissão dos arts. 13, 17 e 18 da Res. 5.998/2022.

| Símbolo | Significado |
|---|---|
| `x` | Transporte conjunto permitido |
| `(a)` | Permitido apenas com substâncias da subclasse 1.4, grupo S |
| `(b)` | Permitido entre Classe 1 e produtos específicos da Classe 9 (ONU 2990, 3072, 3268) |
| `(c)` | Permitido entre infladores e módulos de airbag específicos |
| `(d)` | Permitido entre certos explosivos de demolição e nitratos |
| célula vazia | **Incompatíveis** — carregamento conjunto proibido |

Regras gerais para o motor de checagem: as subclasses **1.1, 1.2 e 1.3 são incompatíveis com quase todas as demais** — bloqueio praticamente absoluto. As classes 2.1, 2.2, 2.3, 3, 4.1, 4.2, 4.3, 6.1, 6.2, 7, 8 e 9 são majoritariamente compatíveis entre si, com exceções por par. Além da matriz por classe, há **incompatibilidade específica por produto** (ácidos × cianetos, oxidantes × combustíveis) — o sistema precisa suportar exceção por número ONU, não só por classe.

```sql
incompatibilidades (classe_a, classe_b, situacao, nota_condicao)
incompatibilidades_onu (num_onu_a, num_onu_b, situacao, observacao)
```

> A norma é paga e sua redistribuição não é livre. O seed inicial pode partir da versão consolidada publicada pela Defesa Civil do Paraná, mas a conferência precisa ser feita contra o exemplar da ABNT.

---

## 9. Outros controles de produto

| Controle | Órgão | O que exige do transportador |
|---|---|---|
| **Produto controlado — Exército (PCE)** | Exército (DFPC/SFPC) | **CR** e **Guia de Tráfego** por embarque |
| **Produto químico controlado — PF** | Polícia Federal (DCPQ) | **CRC**, **CLF** e mapa no **SIPROQUIM2** |
| **Origem animal** | MAPA (DIPOA) | Registro SIF/SISBI/SIE, veículo aprovado, controle de temperatura, documento de trânsito |
| **Agrotóxicos** | MAPA, IBAMA e ANVISA | Regime de produto perigoso; **vedado transportar junto com alimentos, medicamentos, rações e bebidas**; logística reversa de embalagens |
| **Medicamentos** | ANVISA — RDC 430/2020 | Qualificação térmica, mapeamento, registro contínuo de temperatura, procedimento de desvio, rastreabilidade, contrato formal |
| **Origem animal — transporte** | Decreto 9.013/2017 (RIISPOA) | Veículo isotérmico, limpeza e desinfecção, manutenção de temperatura |

Campos correspondentes no cadastro:

```
pce_exercito, exige_guia_trafego,
controlado_pf, exige_mapa_siproquim,
origem_animal, tipo_inspecao, numero_registro_inspecao,
eh_agrotoxico, registro_mapa,
exige_temp_controlada, temp_min_c, temp_max_c, exige_registro_continuo
```

> **A regra de segregação de agrotóxicos vale a pena ser validação de sistema**, não só nota no cadastro: ao montar o romaneio, se houver agrotóxico e alimento na mesma carga, bloqueie.

---

## 10. Unidades de medida e cubagem

### 10.1 `cUnid` — atenção, são duas tabelas diferentes ✅ Fiscal

**CT-e — grupo `infQ`:**

| Código | Descrição |
|---|---|
| `00` | M3 |
| `01` | KG |
| `02` | TON |
| `03` | UNIDADE |
| `04` | LITROS |
| `05` | MMBTU |

**MDF-e — grupo `tot`:**

| Código | Descrição |
|---|---|
| `01` | KG |
| `02` | TON |

> ⚠️ **A assimetria é real e é fonte de bug.** O CT-e tem 6 valores; o MDF-e tem **apenas 2**. São enums distintos — **não compartilhe a mesma tabela de domínio no banco**, e não deixe o usuário escolher "LITROS" numa tela cujo destino é o MDF-e.

**`tpMed` do CT-e** é string livre de 1 a 20 caracteres. Valores praticados: `PESO BRUTO`, `PESO DECLARADO`, `PESO CUBADO`, `PESO AFORADO`, `PESO AFERIDO`, `PESO BASE DE CÁLCULO`, `LITRAGEM`, `CAIXAS`. Padronize em constante da aplicação — se cada operador digitar o que quiser, o relatório de peso não fecha.

### 10.2 Unidades de transporte e de carga ✅ Fiscal

`infUnidTransp` (a unidade que se move) **contém** `infUnidCarga` (o que está dentro dela). Ambos aceitam N ocorrências e opcionalmente lacres e quantidade rateada.

**`tpUnidTransp`** (1 posição): `1` Rodoviário Tração · `2` Rodoviário Reboque · `3` Navio · `4` Balsa · `5` Aeronave · `6` Vagão · `7` Outros.

**`tpUnidCarga`** (1 posição): `1` Container · `2` ULD · `3` Pallet · `4` Outros.

### 10.3 Cubagem e peso taxável ⚠️ Prática de mercado

```
Volume (m³)       = comprimento × largura × altura × nº de volumes
Peso cubado (kg)  = volume (m³) × fator de cubagem (kg/m³)
Peso taxável (kg) = MAX(peso real bruto, peso cubado)
```

O fator predominante no rodoviário fracionado brasileiro é **300 kg/m³**; variações contratuais usam 200, 250 ou 333.

> ⚠️ **Não existe norma legal nem regulamentação da ANTT que fixe o fator de cubagem.** É livre pactuação contratual. O fisco apenas reconhece o conceito, reservando `PESO CUBADO` como `tpMed` do CT-e.
>
> **Consequência direta:** o fator de cubagem é **parâmetro por tabela de frete, por cliente e por rota**, com fallback no cadastro do produto e depois no padrão da empresa. Constante fixa no código é erro — a primeira negociação comercial do cliente já a invalida.

O peso taxável é o que alimenta `qCarga` com `tpMed = "PESO BASE DE CÁLCULO"`.

---

## 11. Resumo do que semear

| Tabela | Tipo | Registros | Editável pelo cliente? |
|---|---|---|---|
| `tp_rod` | ✅ Fiscal | 6 | Não |
| `tp_car` | ✅ Fiscal | 6 | Não |
| `tp_carga` | ✅ Fiscal | 12 | Não |
| `categ_comb_veic` | ✅ Fiscal | **10** (não 13) | Não |
| `tp_transp` | ✅ Fiscal | 3 | Não |
| `tp_unid_transp` | ✅ Fiscal | 7 | Não |
| `tp_unid_carga` | ✅ Fiscal | 4 | Não |
| `cunid_cte` | ✅ Fiscal | 6 | Não |
| `cunid_mdfe` | ✅ Fiscal | 2 | Não |
| `classes_risco` | ✅ Referência | 21 (com subclasses) | Não |
| `grupos_compatibilidade` | ✅ Referência | 13 | Não |
| `grupos_embalagem` | ✅ Referência | 3 | Não |
| `numeros_risco` | ✅ Referência | 10 algarismos + combinações | Não |
| `produtos_perigosos` (Relação ONU) | ✅ Referência | ~3.500 | Não — sincronizada |
| `incompatibilidades` | ✅ Referência | matriz de classes | Não |
| `cvc_configuracoes` | ⚠️ Referência | 19 | Sim |
| `carrocerias` | ⚙️ Operacional | 19 padrão | Sim |
| `naturezas_carga` | ⚙️ Operacional | 11 | Sim |
| `municipios` (IBGE) | ✅ Referência | ~5.570 | Não |
| `ncm` | ✅ Referência | 10.000+ | Não — **atualização periódica** |
| `fornecedores_vpo` | ✅ Referência | ~16 | Não — sincronizada do SVRS |

---

## 12. Pontos que precisam de conferência antes de virar código

| # | Item | Por quê |
|---|---|---|
| 1 | Convenção de `tpCar` para **tanque** e **silo** | Não têm código fiscal próprio. Decisão nossa, precisa ser fixa e documentada |
| 2 | Comprimento e PBT vigentes do **VUC em São Paulo** | Definição municipal; houve portaria de ampliação não confirmada |
| 3 | Revogação expressa da Res. CONTRAN 789/2020 pela **1.020/2025** | Afeta a validade do MOPP. Manter o controle configurável até esclarecer |
| 4 | Cotas de painéis e rótulos na **NBR 7500:2026** | As cotas aqui vêm da edição de 2003+Emd.1:2004 |
| 5 | Matriz completa da **NBR 14619** | Norma paga; obtivemos legenda e regras gerais |
| 6 | Limite adicional de peso bruto no regime de **quantidade limitada** | Fontes secundárias divergem entre 1.000 e 2.000 kg |
| 7 | Periodicidade do **toxicológico**: 24 × 30 meses | Duas fontes para 30, uma para 24. Adotamos 30, como parâmetro |
| 8 | Carga horária dos **cursos especializados** | Delegada a normativo da SENATRAN, não localizado |

---

## 13. Fontes consultadas

**Veículos e trânsito**

- [Resolução CONTRAN nº 882/2021 — texto oficial (PDF)](https://www.gov.br/transportes/pt-br/assuntos/transito/conteudo-contran/resolucoes/Resolucao8822021.pdf)
- [Resolução CONTRAN nº 1.020/2025 — texto oficial (PDF)](https://www.gov.br/transportes/pt-br/assuntos/transito/conteudo-contran/resolucoes/Resolucao10202025.pdf)
- [CTB — Art. 143, categorias de habilitação](https://ctbdigital.com.br/artigo/art143/)
- [Guia do TRC — Lei da Balança: pesos e dimensões das principais configurações](https://guiadotrc.com.br/pagina/lei-da-balanca-pesos-e-dimensoes-maximas-permitidas-das-principais-configuracoes-de-veiculos-usadas-no-brasil/3)
- [Guia do TRC — Tipos de carroceria mais comuns](https://guiadotrc.com.br/publicacao/os-tipos-de-carrocerias-mais-comuns-nas-estradas-brasileiras/35280)
- [SETCOM-MG — Res. CONTRAN 1.020/2025: o que muda no MOPP/CETPP](https://setcommg.com/resolucao-contran-1020-2025-mopp-cetpp-produtos-perigosos/)
- [Serasa — Exame toxicológico: regras e prazos em 2026](https://www.serasa.com.br/blog/exame-toxicologico-renovacao-cnh/)

**Layout fiscal**

- [FlexDocs — Guia MDF-e: veículo de tração (`tpRod`, `tpCar`)](https://www.flexdocs.net/guiaMDFe/gerarMDFe.modal.rodo.veicTracao.html)
- [FlexDocs — Guia MDF-e: grupo `infANTT`](https://flexdocs.net/guiaMDFe/gerarMDFe.modal.rodo.infANTT.html)
- [FocusNFe — MDFeXML: `tpUnidTransp`, `tpUnidCarga`, `tpCarga`, `tpTransp`](https://campos.focusnfe.com.br/mdfe/MDFeXML.html)
- [Oobj — Rejeição 731 e tabela `categCombVeic`](https://oobj.com.br/bc/rejeicao-731-como-resolver/)
- [Portal Nacional do CT-e — MOC e schemas](https://www.cte.fazenda.gov.br/portal/listaConteudo.aspx?tipoConteudo=0xlG1bdBass%3D)

**Transportadores e ANTT**

- [Lei nº 11.442/2007](https://www2.camara.leg.br/legin/fed/lei/2007/lei-11442-5-janeiro-2007-549026-publicacaooriginal-64305-pl.html)
- [ANTT — RNTRC: requisitos por categoria](https://www.gov.br/antt/pt-br/assuntos/cargas/rntrc-1/requisitos)
- [ANTT — CIOT e Pagamento Eletrônico de Frete](https://portal.antt.gov.br/en/resultado/-/asset_publisher/m2By5inRuGGs/content/id/404581)
- [SETCESP — STF declara constitucional a Lei 11.442/07 (ADC 48 e ADI 3961)](https://setcesp.org.br/noticias/stf-lei-11-442-07-adc-48-e-adi-3961/)

**Produtos perigosos**

- [ANTT — Transporte rodoviário de produtos perigosos](https://www.gov.br/antt/pt-br/assuntos/cargas/transporte-rodoviario-de-produtos-perigosos)
- [Decreto nº 96.044/1988](https://www.planalto.gov.br/ccivil_03/decreto/antigos/d96044.htm)
- [ANVISA — RDC nº 430/2020, transporte de medicamentos](https://www.gov.br/anvisa/pt-br)
- [Decreto nº 9.013/2017 — RIISPOA](https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2017/decreto/d9013.htm)
