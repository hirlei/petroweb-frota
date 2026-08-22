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

**Documento fiscal autorizado é imutável.** Nunca `UPDATE` em CT-e ou MDF-e
autorizado — corrige-se por evento ou cancela-se e reemite.

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

## Em investigação — 419 Page Expired no login (tenant)

**Sintoma.** POST `/login` em `<slug>.frota.petroweb.app` volta 419, inclusive em
aba anônima. Reproduzível no servidor via curl (GET pega o token, POST devolve 419).

**Causa raiz confirmada.** A sessão do GET `/login` é gravada no banco **CENTRAL**,
e o POST lê do banco do **TENANT** — o token CSRF não bate. Prova (com as duas
tabelas `sessions` zeradas): após o GET, `central=1 tenant=0`; após o POST,
`central=1 tenant=1` (o POST criou sessão nova, vazia, no tenant).

**O que já foi tentado e NÃO resolveu:**
- `prependToPriorityList(before: StartSession, prepend: InitializeTenancyByDomain)`.
- Grupo `tenant` com a pilha de sessão/cookie/CSRF explícita, tenancy em primeiro,
  sem envolver as rotas no grupo `web`.

Ou seja: mesmo com a tenancy declarada antes do StartSession, o GET ainda grava no
central. O `SortedMiddleware` do Laravel provavelmente reordena, OU o switch de
conexão do stancl não vira o `default` a tempo do StartSession no GET.

**Próximos passos (amanhã), em ordem:**
1. Logar `DB::getDefaultConnection()` dentro de um middleware logo antes e depois do
   StartSession, no GET e no POST — descobrir a conexão real no momento da escrita.
2. Se o GET estiver mesmo em central: forçar a sessão a seguir o tenant de forma
   determinística — registrar um bootstrapper do stancl para a sessão, ou fixar
   `config(['session.connection' => 'tenant'])` dentro do InitializeTenancyByDomain
   (sem quebrar o painel central, que usa o grupo `central`).
3. Alternativa de contorno para a demo: `SESSION_DRIVER=cookie` no tenant (sem
   tabela, sem banco) — tira o problema de conexão da frente enquanto a causa é
   resolvida direito.
