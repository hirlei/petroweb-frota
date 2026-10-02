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

**Verificação de e-mail.** O usuário é convidado pelo gestor — não há fluxo de
`verification.notice`. Todo usuário nasce com `email_verified_at` preenchido
(seeder e formulário de Usuários). Sem isso, o middleware `verified` derruba o
acesso com "Route [verification.notice] not defined".
