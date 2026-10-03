# PetroWeb Frota — instruções para o Claude Code

Sistema SaaS multi-tenant de gestão de transporte rodoviário de cargas (TMS)
com emissão de CT-e e MDF-e. Terceiro produto da família PetroWeb, ao lado do
**PetroWeb** (ERP de postos) e do **PetroWeb PDV**.

A documentação de produto está em `docs/` — leia antes de implementar
qualquer coisa. O `01_DOCUMENTO_MESTRE.md` é o contrato do projeto.

---

## Stack

| Camada | Versão |
|---|---|
| PHP | 8.3 (VPS) · `^8.2` no composer |
| Laravel | 12 — o 11 saiu do suporte de segurança em mar/2026 |
| Livewire | 4.3 |
| Banco | **PostgreSQL 16** — não MySQL |
| Tenancy | `stancl/tenancy`, banco por tenant |
| Permissões | `spatie/laravel-permission` |
| Auditoria | `spatie/laravel-activitylog` |
| CSS | Tailwind 3 + Alpine, tokens do PetroWeb |

---

## As duas camadas de isolamento

Confundi-las é o erro mais caro possível neste código.

```
Tenant (stancl)  →  banco próprio: frota_<slug>, subdomínio <slug>.frota.petroweb.app
    └─ Empresa   →  discriminador empresa_id DENTRO daquele banco
         └─ Filial  →  estabelecimento: CNPJ, IE, certificado e séries próprios
```

Um grupo com três transportadoras é **um** tenant com **três** empresas — não
três tenants.

**Regras invioláveis:**

1. Toda tabela de negócio tem `empresa_id` NOT NULL, com índice composto começando por ele.
2. Todo model de negócio usa a trait `App\Models\Concerns\PertenceAEmpresa`.
3. `empresa_id` **nunca** vem do request — vem do `TenantContext`.
4. Job e comando agendado recebem o contexto no payload e reidratam no `handle()`.
5. Unicidade de negócio é sempre composta com `empresa_id`.
6. Bypass do escopo é explícito e justificado: `TenantContext::semEscopo(fn () => ...)`.

---

## Convenções

**Idioma.** Código, tabelas, colunas e comentários em português. Nomes de
tabela no plural (`veiculos`, `ordens_coleta`), colunas em `snake_case`.

**Banco.** PostgreSQL. `decimal` vira `numeric`; use `jsonb`, nunca `json`.
Tipo `ENUM` de banco é proibido — `ALTER TYPE` não roda em transação. Em
compensação, use **índices parciais** e **constraints EXCLUDE**: regras como
"uma matriz por empresa" e "um MDF-e aberto por veículo" são do banco, não da
aplicação.

**Enum fiscal ≠ tabela de negócio.** O fisco tem 6 carrocerias; a
transportadora usa vinte e poucas. Toda classificação tem duas camadas: a
tabela operacional que o cliente edita, e o enum da SEFAZ para onde ela é
traduzida na geração do XML. **Nenhuma tela de operação mostra código fiscal.**

**Textos de interface começam com letra maiúscula** — rótulos, chips,
sublegendas e placeholders. Exceções: slugs, e-mails de exemplo e fragmentos
no meio de frase.

**Mockup antes de implementar.** Nenhuma tela nova é codificada sem mockup
aprovado. Os da Fase 1 estão publicados; peça o link se não tiver.

**Deploy na VPS (fluxo que funciona).** A VPS (`root@179.198.123.188`,
`/var/www/petroweb-frota`) tem o remote do GitHub com chave **somente leitura**
— ela puxa, mas não faz `git push`. O bundle vai do sandbox para a VPS por
`scp` **rodado no PC** (janela sem `ssh`, com aspas no caminho):
`scp "$HOME\Downloads\<bundle>" root@179.198.123.188:/tmp/`. Na VPS:
`git fetch /tmp/<bundle> main` → `git merge --no-edit FETCH_HEAD` → migrate/seed/
build. **Nunca `git reset --hard origin/main` na VPS** enquanto o GitHub estiver
atrás — apaga o que só existe na VPS. Sincronizar o GitHub é pelo clone local do
PC (`C:\projetos\petroweb-frota`), que tem chave de escrita. Colar base64 grande
no terminal não funciona (trava); `scp` é o caminho.

**Botão é sempre leve.** O padrão do PetroWeb Frota (como no ERP) é
preenchimento suave + texto colorido, peso médio (`fill-100 / text-900`) —
nunca fill sólido saturado com texto branco. É o que o `x-button` já entrega
(`primary` = âmbar suave sobre texto âmbar escuro). Toda tela usa o `x-button`;
`bg-primary text-white` só em elemento que NÃO é botão de ação (avatar, número
de passo, chip de toggle).

**A moldura é a do ERP.** Menu lateral H6, barra superior, fita de indicadores,
paleta Ctrl K, cores e tela inicial são **iguais ao PetroWeb ERP** (decisão de
02/10/2026). O CSS vem copiado do ERP em `resources/views/partials/moldura-estilos.blade.php`
— ao mudar algo lá, traga para cá. A montagem do menu (rotina ativa, bolinhas de
pendência, Recentes, Favoritos e o sino) é `App\Support\Navegacao`; grupo novo
precisa de `icon` no `config/navegacao.php`. Conteúdo das telas segue os tokens
âmbar; azul `#1A3DA3` e laranja `#FF6200` só na moldura.

**Documento fiscal autorizado é imutável.** Nunca `UPDATE` em CT-e ou MDF-e
autorizado — corrige-se por evento ou cancela-se e reemite.

**Faturamento (5010) e contas a receber (5020).** A fatura é documento
comercial, não fiscal: agrupa CT-e **autorizados** de **um** tomador. Um CT-e só
pode estar em uma fatura ativa — índice parcial `fatura_ctes(cte_id) WHERE ativo`.
Cada parcela é um título; juros e multa entram no caixa mas não abatem saldo;
cancelar fatura só sem recebimento válido (estorne antes); estorno marca, não
apaga. Toda mudança de valor/status passa por `App\Services\Financeiro\Faturamento`
— tela nenhuma faz `update` direto em fatura, título ou recebimento. Parcelas e
encargos são lógica pura em `App\Domain\Financeiro` (centavos inteiros: a soma
das parcelas é sempre o total).

**CIOT (4050) e vale-pedágio saem junto com o MDF-e.** Desde o "CIOT para Todos"
(Res. ANTT 6.078/2026, Lei 15.485/2026) **toda** viagem remunerada tem CIOT — não
só a de TAC. Quem registra é decidido por `App\Domain\Fiscal\RegrasCiot::modalidade`
(via `Viagem::modalidadeCiot()`): TAC → instituição de pagamento (`ipef`); frota
própria → direto na ANTT (`antt`); caminhão de outra transportadora → número
informado (`informado`); carga própria/transferência em veículo próprio → dispensado.
Um clique em "Emitir" no 4020 roda `App\Services\Fiscal\EmissaoMdfeCompleta`:
CIOT → vale → SEFAZ, nessa ordem; sem CIOT o MDF-e não sai (rejeição 684 —
homologação já, produção a partir de `ciot.obrigatorio_desde`). CIOT e vale são da
**viagem**, não do MDF-e: rejeição/reemissão reaproveita os mesmos — nunca registre
nem pague de novo. Toda mudança passa por `ServicoCiot` / `ServicoValePedagio`
(pagamento com `lockForUpdate`); 4050 e 4030 são consulta, saldo, cancelamento e
reenvio. Gateways atrás de `CiotGateway` e `ValePedagioGateway`; hoje só o
**emissor de teste** (`config/ciot.php`) — número fictício, não vale na fiscalização.
Fornecedoras de vale (`fornecedores_vpo`, catálogo global do tenant) se cadastram na
janela `ValesPedagio\Fornecedoras` (4030 e bloco do vale no 4020, evento
`abrir-fornecedoras`); sem fornecedora ativa o vale não sai. Nascem quatro **de
exemplo** (CNPJ fictício, migration 2026_10_03_010000) — em produção, cadastrar a
real e desativar as de exemplo.

**Contas a pagar (5030).** Toda mudança passa por `App\Services\Financeiro\ContasPagar`.
"Lançamentos esperando" = abastecimento com posto (uma conta por posto), OS encerrada em
oficina externa (uma por OS) e CIOT de TAC (um por CIOT). Uma origem em no máximo uma
conta ativa — índice parcial `conta_pagar_origens(empresa_id, origem_tipo, origem_id) WHERE ativo`.
Cancelar cancela o **lançamento inteiro** (todas as parcelas do `grupo`) e devolve as
origens. Conta de CIOT não se paga nem estorna aqui: espelha os `ciot_pagamentos` (forma
`instituicao_ciot`) — o `ServicoCiot` chama o espelho depois do commit. A relação de
`PagamentoConta` é `contaPagar()` (a coluna `conta` é a conta bancária). Vencimento pelo
prazo do favorecido (`pessoas.prazo_faturamento`), sem prazo 30 dias.

**Acerto de viagem (3070).** Só motorista CLT (RN-12) — agregado/autônomo são pagos pelo
CIOT. Toda mudança passa por `App\Services\Operacao\Acertos`; a conta pura é
`App\Domain\Operacao\CalculoAcerto` (centavos): ficou = adiantado − gasto do adiantamento
aceito; saldo = bolso aceito + diárias + comissão − ficou (> 0 empresa paga, < 0 motorista
devolve). Conferência da despesa: `aprovada` = aceita, `glosada` = glosa, nenhum = pendente
(CHECK impede os dois). Só entram despesas `adiantamento`/`reembolso`. Fecha só viagem
entregue/encerrada e sem pendentes; um fechado por viagem (índice parcial). Fechado,
despesas e adiantamentos da viagem travam (adicionar/conferir pegam o `lockForUpdate` da
viagem, como o fechar). Saldo > 0 vira conta no 5030 (`origem = acerto`), que **não** se
cancela no 5030 — só reabrindo o acerto, e só sem pagamento. Reabrir marca `reaberto`
(não apaga). Diária/comissão padrão = `motoristas.valor_diaria`/`percentual_comissao`.
Fila e bolinha só a partir de `financeiro.acerto.desde`. Recibo: `operacao/recibo-acerto`
(dompdf, `?html=1`).

**Eventos do CT-e.** Carta de correção (110110): regras puras em
`App\Domain\Fiscal\RegrasCce` (vedações do Conv. SINIEF 06/89 art. 58-B / NT 2024.001,
limite de 20, a última consolida as anteriores — a tela já abre com as correções vigentes);
transmissão em `App\Services\Fiscal\CartaCorrecao` (trava o CT-e; recusa da SEFAZ não
consome a sequência). Cancelamento só dentro de `fiscal.cte.cancelamento_horas` (168h).
**Inutilização de CT-e não existe** desde o CT-e 4.00 (Ajuste SINIEF 31/2022) — não criar;
a 4060 só mostra a conferência da numeração (`ConferenciaNumeracao`). Exportar XML (4060):
`App\Services\Fiscal\ExportadorXml`, mês no fuso `fiscal.fuso` (banco em UTC), ZIP no
disco `fiscal.xml.disco` + `resumo.csv` (";", BOM). Relação ordenada + `count()`/`max()`
quebra no Postgres: use `reorder()` ou relação sem `orderBy`.

**Custo e margem (3080).** O custo da viagem NÃO se digita: `App\Services\Operacao\CustosDaViagem`
monta os componentes dos lançamentos ligados (abastecimento → combustível; CIOT ipef/informado →
terceiro; despesas aceitas → pedágio/motorista/outros; vale-pedágio pago pela transportadora
(fornecido ou compra) → pedágio; acerto fechado → diárias/comissão, aberto → estimadas pelo
cadastro do motorista CLT; OS da viagem → manutenção) e grava nas colunas `custo_*` da viagem
(`saveQuietly`). Componente sem lançamento usa `custos_digitados` (legado). O observer
`RecalculaCustoDaViagem` agenda o recálculo depois do commit; `viagens:recalcular-custos` roda
toda noite (cron do `schedule:run`). Cálculo puro em `App\Domain\Operacao\CustoViagem`
(centavos; rateio por cliente na proporção da receita). A saída da viagem é hora local: o corte
do mês é o do calendário, sem converter fuso.

**DACTE e DAMDFE (PDF A4).** `App\Services\Fiscal\Impressao\DocumentoAuxiliar` monta
os dados; o leiaute é `resources/views/fiscal/{dacte,damdfe}.blade.php`, convertido pelo
**dompdf** — por isso só tabela (sem flex/grid) e imagens como SVG em data URI. Código de
barras CODE-128C da chave é `App\Domain\Fiscal\CodigoBarras128` (puro, conferido contra
o reportlab); QR Code é `App\Support\Impressao\QrCodeSvg` (codificador do bacon,
retângulos à mão). QR usa o texto da SEFAZ (`payload.qrcode`) e, sem ele, a URL de
`fiscal.qrcode`. Marca d'água: homologação "Sem valor fiscal", rascunho/rejeitado
"Prévia", cancelado "Cancelado". `?html=1` na rota mostra a página em HTML.

**Nenhuma chamada à SEFAZ dentro do request.** Tudo é job com retentativa,
backoff e status persistido, atrás da `SefazGatewayInterface`.

---

## Estrutura

```
app/
├─ Domain/            regras de negócio puras, sem Laravel
│  ├─ Frota/  Operacao/  Fiscal/  Financeiro/
├─ Services/          casos de uso
│  └─ Fiscal/Sefaz/   gateway: interface + implementações + fake
├─ Jobs/              tudo que fala com o mundo externo
├─ Livewire/          componentes de tela
├─ Models/
│  ├─ Central/        models do banco central (Licenca)
│  ├─ Concerns/       PertenceAEmpresa
│  └─ Scopes/         EmpresaScope, EmpresaViaPaiScope
└─ Support/           TenantContext

database/migrations/
├─ central/           tenants, domains, licencas
└─ tenant/            todo o resto
```

---

## Comandos

```bash
# Central
php artisan migrate --database=central --path=database/migrations/central

# Tenants
php artisan tenants:migrate
php artisan tenants:seed

# Ícones (sprite local, sem CDN) — rodar quando usar ícone novo
npm run icones

# Qualidade — rodar antes de todo commit
composer lint      # pint --test
composer analyse   # phpstan
composer test

# Sem vendor/ instalado (ambiente sem packagist)
composer verificar # domínio puro + estrutura das views
```

---

## Regras de negócio que decidem a modelagem

Estão inteiras no `docs/01_DOCUMENTO_MESTRE.md`. As três que mais influenciam
o código:

- **RN-02** — CT-e e viagem é **N:N**. Um CT-e pode atender uma viagem; uma
  viagem carrega N CT-e (carga fracionada); um CT-e pode ser transbordado em
  várias viagens. Modelar como 1:1 obriga a refazer o sistema.
- **RN-11** — enum fiscal não é tabela de negócio (acima).
- **RN-12** — agregado e autônomo **não têm jornada** no sistema. Telas de
  jornada, escala e ponto existem apenas para `vinculo = clt`. Modelar jornada
  de TAC é produzir prova de vínculo empregatício contra o próprio cliente.

---

## Armadilhas fiscais já mapeadas

Todas verificadas contra o MOC e a legislação — não as reintroduza copiando
documentação de terceiros:

| Armadilha | Verdade |
|---|---|
| Comprovante de entrega do CT-e | Evento **110180** (cancelamento 110181). O 110160 é "Registros do Multimodal" |
| Alteração de pagamento no MDF-e | Evento **110118**. O 110116 é "Pagamento da Operação de Transporte" |
| `categCombVeic` | **10 valores**. Os códigos `03`, `05` e `09` **não existem** |
| Grupo `peri` no CT-e rodoviário | **Não existe** — só no MDF-e (e no CT-e aéreo) |
| Grupo `valePed` no CT-e | **Não existe** desde o layout 3.00 — só no MDF-e |
| Vale-pedágio no frete | **Não integra** o valor do frete nem a base do ICMS |
| Multa de vale-pedágio | **R$ 3.000** por veículo/viagem. R$ 550 é do regime revogado |
| Ficha de emergência | **Não é mais de porte obrigatório** desde a Res. ANTT 5.848/2019 |
| CNPJ | **String de 14**, nunca inteiro — a NT Conjunta 2025.001 o torna alfanumérico |
| Fator de cubagem | **Não há norma.** Parâmetro em cascata: tabela de frete → cliente → produto → empresa |
| Bitruck | Teto legal **29 t** (Res. 882/2021 art. 6º "a"). Tabelas de mercado citam 32 t — está errado |
| Treminhão e tritrem | **Exigem AET.** Mais de duas unidades acima de 57 t ou 19,80 m (art. 17) |
| `UNIQUE (empresa_id, codigo)` com `empresa_id` nulo | **NULL nunca é igual a NULL.** O catálogo do sistema precisa de índice parcial `WHERE empresa_id IS NULL`, senão duplica |

---

## O que não fazer

- Não usar MySQL nem assumir sintaxe dele.
- Não criar model de negócio sem `PertenceAEmpresa`.
- Não passar `empresa_id` por request, rota ou formulário.
- Não chamar a SEFAZ fora de um job.
- Não fazer `UPDATE` em documento fiscal autorizado.
- Não mostrar código fiscal em tela de operação.
- Não criar tela de jornada para agregado ou autônomo.
- Não usar `deleted_at` em tabela fiscal.
- Não usar ícone que não está em `public/img/icons/sprite.svg` — rode `npm run icones`.
- Não criar rota de auto-cadastro: usuário é convidado, não se registra.
- Não usar `"*"` como restrição de versão no composer.json, e não subir sem `composer.lock`
  commitado: build sem lock não é reprodutível.
- Não criar tabela de motorista, cliente ou proprietário separada — tudo é `pessoas` + `pessoa_papeis`.
- Não gravar `categ_comb_veic` no cadastro — deriva dos eixos, sempre.
- Model com PK string (Tenant) DEVE ter `public $incrementing = false;` e
  `protected $keyType = 'string';` — senão o create() lê o id como `(int)` = 0.

---

## RESOLVIDO — 419 no login e a regra do `Route::view` sob tenant

**Sintoma.** POST `/login` no tenant voltava 419; depois de corrigido, virou laço
de redirect (ERR_TOO_MANY_REDIRECTS) na `/`.

**Causa raiz.** `Route::view()` (e provavelmente `Route::redirect()`) NÃO recebe a
mesma ordenação de middleware que uma rota de controller. Em rota de view, a
tenancy inicializava DEPOIS do `StartSession` — a sessão (com o token CSRF) caía
no banco **CENTRAL** no GET, enquanto o POST (controller) lia do **TENANT**. Token
não batia → 419. Com o `/login` corrigido mas a `/` ainda em `Route::view`, o
`/login` via logado (tenant) e mandava pra `/`; a `/` via deslogado (central) e
mandava pro `/login` → laço.

**Correção.** Nenhuma rota sob o middleware `tenant` usa `Route::view`. Login e
início viraram controllers (`LoginController::mostrar`, `InicioController`). Como
controller, GET e POST passam pela mesma pilha, na mesma ordem, e a sessão vive
sempre no banco do tenant. (O `prependToPriorityList` no bootstrap/app.php e o
grupo `tenant` com a pilha explícita ajudam, mas o que fecha é não usar view-route.)

**REGRA:** sob o middleware `tenant`, use SEMPRE rota de controller ou componente
Livewire — **nunca `Route::view` nem `Route::redirect`**. Rota de view só no grupo
`central` (que não tem tenancy). É cacheável e determinística.

**Livewire também.** O POST de atualização do Livewire vai para `/livewire/frota/update`,
registrado no `TenancyServiceProvider` com o grupo `tenant` (`Livewire::setUpdateRoute`;
caminho próprio porque o Livewire 4 dá 404 na rota padrão quando há customizada).
Sem isso, toda interação Livewire volta 419 "This page has expired" — a sessão
é lida do banco central. Se um dia usar upload (`WithFileUploads`), a rota de
upload precisa do mesmo tratamento.

**Verificação de e-mail.** O usuário é convidado pelo gestor — não há fluxo de
`verification.notice`. Todo usuário nasce com `email_verified_at` preenchido
(seeder e formulário de Usuários). Sem isso, o middleware `verified` derruba o
acesso com "Route [verification.notice] not defined".
