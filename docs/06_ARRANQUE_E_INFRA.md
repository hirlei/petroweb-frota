# PetroWeb Frota — Arranque e Infraestrutura

> Do repositório vazio ao primeiro login em `https://frota.petroweb.app`.
> Documento complementar a `01_DOCUMENTO_MESTRE.md`. Versão 1.0 — 21/08/2026.

---

## 1. Decisões travadas

| Decisão | Escolha |
|---|---|
| Domínio | **frota.petroweb.app** |
| Base do código | **Fork do esqueleto do PetroWeb** — herda design system, tenancy, permissões, auditoria, 2FA e deploy |
| Infraestrutura | **Mesma VPS** — 8 GB de RAM, 2 núcleos, 100 GB de SSD, AlmaLinux 10. Já dimensionada, sem upgrade a fazer |

---

## 2. O que a leitura do repositório mudou na documentação

Ao abrir o `C:\projetos\posto\Retaguarda` para os mockups, três coisas apareceram que **contrariam o que os documentos 01 e 02 diziam**. Já foram corrigidas, mas vale saber o que mudou:

| Documento dizia | Realidade do PetroWeb | Consequência |
|---|---|---|
| MySQL 8 / MariaDB | **PostgreSQL 16**, nativo do AppStream do EL10 | Tipos, índices e `lockForUpdate` mudam de dialeto. `decimal` vira `numeric`, `json` vira `jsonb` |
| Laravel LTS vigente | **Laravel 11**, PHP ^8.2 (8.3 na VPS) | Sem novidade estrutural, mas fixe a versão |
| Livewire 3 | **Livewire 4.3** | API de componentes mudou entre 3 e 4 |
| Multi-tenant só por `empresa_id` | `stancl/tenancy` **está no composer**, junto com o `Tenant.php` e o `TenantScope` | Precisa ser esclarecido antes da primeira migration — ver seção 6 |

**Correção de banco no documento 02.** Onde se lia MySQL, leia PostgreSQL:

| Convenção anterior | Convenção correta |
|---|---|
| `decimal(15,2)` | `numeric(15,2)` — o Laravel gera certo, mas SQL cru precisa disso |
| `json` | `jsonb` — indexável, e é o que o PetroWeb usa |
| `ENUM` proibido (migrar dói) | Continua proibido, e no Postgres dói ainda mais: `ALTER TYPE` não roda em transação |
| Índice composto começando por `empresa_id` | Igual, mas avalie **índice parcial** (`WHERE ativo = true`) — recurso que o MySQL não tem |
| `unique(empresa_id, chave)` | Igual. Para busca insensível a caixa use `citext` ou índice em `lower()` |

Há um ganho relevante: o Postgres tem `EXCLUDE` constraints. A regra de **um MDF-e aberto por veículo** (RN-03) pode virar restrição de banco em vez de validação de aplicação.

---

## 3. Ordem das operações

Nada aqui é paralelizável — cada passo depende do anterior.

| # | Passo | Quem | Quando |
|---|---|---|---|
| 1 | Criar o registro **A** de `frota.petroweb.app` para o IP da VPS | Você | Propagação leva de minutos a horas |
| 2 | Criar o repositório `hirlei/petroweb-frota` (privado) | Você | Pode ser em paralelo ao DNS |
| 3 | Fazer a cirurgia do fork | Claude Code, na sua máquina | Seção 4 |
| 4 | Primeiro push | Claude Code | — |
| 5 | Rodar `provisionar-frota-el10.sh` na VPS | Você, como root | Seção 5 — só depois do DNS propagar |
| 6 | Seeds, primeira empresa, primeiro login | Claude Code / você | Seção 7 |

> A única espera real é o DNS. Enquanto ele propaga, os passos 2 a 4 rodam em paralelo — quando o `frota.petroweb.app` resolver, o provisionamento acha o repositório pronto.

---

## 4. A cirurgia do fork

O PetroWeb tem cerca de 180 models. A maioria é de posto de combustível e sai; um núcleo relevante fica e economiza as Sprints 1 e 2 quase inteiras.

### 4.1 Como fazer o fork

```bash
# Na sua máquina, ao lado do repositório atual
git clone --no-hardlinks C:/projetos/posto/Retaguarda C:/projetos/frota/PetroWebFrota
cd C:/projetos/frota/PetroWebFrota

# Desliga a origem antiga antes de qualquer coisa — evita push acidental no PetroWeb
git remote remove origin
git remote add origin git@github.com:hirlei/petroweb-frota.git

# Histórico limpo: o histórico do PetroWeb não interessa ao Frota e carrega
# arquivos que serão removidos de qualquer forma.
rm -rf .git
git init
git branch -M main
```

> `--no-hardlinks` importa: sem ele o clone usa hardlinks, e foi exatamente isso que impediu alguns arquivos de serem lidos durante a análise.

### 4.2 O que **fica** — o núcleo herdado

| Área | Itens | Por que fica |
|---|---|---|
| **Plataforma** | `User`, `Tenant`, `Empresa`, `Licenca`, `PreferenciaTenantUsuario`, `Models/Scopes/`, `Models/Concerns/`, `Models/Central/` | É a tenancy e o isolamento, prontos e já testados em uso real |
| **Segurança** | `TrustedDevice`, `DeviceTrustLink`, `RecoveryCode`, `VerificacaoDispositivo`, 2FA com `google2fa` | Sprint 1 inteira resolvida |
| **Permissões** | `spatie/laravel-permission` já configurado, telas de usuário e papéis | Sprint 2 resolvida |
| **Auditoria** | `spatie/laravel-activitylog`, `timeline-auditoria.blade.php` | RN de auditoria resolvida |
| **Fiscal — base** | `CertificadoDigital`, `NumeracaoFiscal`, `InutilizacaoFiscal`, `ManifestoDfe`, `ManifestoDfeEvento` | Certificado, numeração e Distribuição DF-e já existem. O `ManifestoDfe` é a base do módulo de Distribuição da Fase 2 |
| **Fiscal — tributário** | `RegraIcms`, `RegraIbsCbs`, `RegraPisCofins`, `PerfilTributario`, `OperacaoFiscal`, `FiscalPerfilRegistro` | **O `RegraIbsCbs` já existe** — a Reforma Tributária já foi enfrentada uma vez neste código |
| **Tabelas fiscais** | `NcmTabela`, `CestTabela`, `CfopTabela`, `NcmCestTabela`, `NcmPerfil`, `CfopCategoria` | Seeds prontos, e a NCM já tem rotina de atualização |
| **Cadastros** | `Pessoa`, `GrupoCliente`, `Produto`, `ProdutoEmpresaDados` | `Pessoa` já é o modelo unificado com papéis — exatamente o que o documento 02 pede |
| **Frota — semente** | **`Motorista`**, **`VeiculoCliente`** | Já existem. São pequenos, mas dão o ponto de partida |
| **Financeiro** | `ContaBancaria`, `PlanoContaFinanceira`, `ContaContabil`, `ContaPagar` e baixas, `Notinha` e baixas, `ContaMovimentacao`, `FormaPagamento` | A Fase 2 nasce com metade pronta. `Notinha` é o contas a receber |
| **Pagamentos** | `IntegracaoPix`, `ChavePix`, `PixTransacao`, `IntegracaoBoleto`, `Boleto`, `AdministradoraCartao` | Cobrança da fatura de frete |
| **Infra** | `ConfiguracaoEmail`, `Contabilidade`, `AgendaEvento`, `RoadmapItem`, `SpedArquivo` | Utilidades transversais |
| **Design system** | `tailwind.config.js`, `resources/css/app.css`, **todos** os `resources/views/components/`, `layouts/app.blade.php`, `layouts/guest.blade.php`, sprite de ícones | É o que os mockups reproduzem. Não recriar |
| **Operacional** | `deploy.sh`, `docs/RUNBOOK_VPS.md`, `docs/BACKUP.md`, `scripts/backup_producao.sh`, `scripts/gerar-sprite-icones.js` | Adaptar, não reescrever |

### 4.3 O que **sai** — o posto

```
app/Models/     Bomba* Bico* Tanque* MovimentacaoTanque* Medidor* FichaMedicao
                Turno* Caixa SangriaSuprimento DespesaCaixa FaltaCaixaFrentista
                Frentista Venda ItemVenda PagamentoVenda Nfce NfceItem
                Abastecimento    ← ver alerta abaixo
                Lmc* AnpTabela ComposicaoPreco HistoricoPreco TrocaPreco*
                NegociacaoPreco* ConcorrenciaPreco CampanhaProduto
                Posto PostoBackup PostoChave PostoHeartbeat PessoaPosto
                ComandoPendente EventoRecebido IdentfidCard
                SnapshotTotalizador HistoricoAliquotaMonofasica
                Folha* RegistroPonto ValeFuncionario FolhaConfigConta
                ConferenciaEstoque* EntradaEstoque* MovimentacaoEstoque
                TransferenciaEstoque PedidoCompra* DevolucaoCompra*
                Recebimento* Remessa* FaturamentoConsolidado*

resources/views/admin/       bombas/ tanques/ frentistas/ lmc/ medidores/
                             movimentacao-tanques/ sangrias/ vendas/
                             abastecimentos/ identfid/
resources/views/livewire/admin/  bombas/ tanques/ turnos/ frentistas/ lmc/
                             precos/ precificacao/ negociacao-precos/ pdvs/
                             estoque/ rh/ faturamento/
resources/views/livewire/turnos/
docs/            tudo que for de posto (LMC, bombas, turnos, PDV, SPED de posto)
```

> ⚠️ **Cuidado com `Abastecimento`.** No PetroWeb, abastecimento é uma **venda na bomba**. No Frota, é um **abastecimento da frota** — outro conceito, outros campos, outra tabela. Remova o model do PetroWeb e construa o do Frota do zero, seguindo o documento 02, seção 5.6. Reaproveitar este seria o pior tipo de economia.

> ⚠️ **`Produto` sai magro.** O `Produto.php` do PetroWeb tem 11 KB, quase todo de regime ANP, monofásico e composição de preço de combustível. Mantenha a espinha fiscal (NCM, CEST, unidades, regras tributárias) e corte o resto. O que entra no lugar está no documento 05, seções 7 a 9.

### 4.4 Renomeações

| Onde | De | Para |
|---|---|---|
| `.env` | `APP_NAME=PetroWeb` | `APP_NAME="PetroWeb Frota"` |
| `.env` | `DB_DATABASE=PetrolinkERP` | `DB_DATABASE=PetroWebFrota` |
| `layouts/app.blade.php` | `<title>@yield('title', 'PetroWeb')</title>` | `'PetroWeb Frota'` |
| `layouts/app.blade.php` | chave de tema `autopostos-theme` | `frota-theme` — senão os dois apps brigam pelo mesmo localStorage se abertos no mesmo navegador |
| `brand-logo.blade.php` | tagline "Gestão Inteligente para Postos" | "Gestão de transporte rodoviário de cargas" |
| Sidebar | grupos e códigos de rotina do posto | grupos do Frota — ver os mockups |
| systemd | `petroweb-worker.service` | `frota-worker.service` |
| nginx | `petroweb.conf` | `petroweb-frota.conf` |

> A troca da chave de tema no `localStorage` parece detalhe e não é: você vai ter os dois sistemas abertos lado a lado o tempo todo.

---

## 5. Provisionamento

O script **`provisionar-frota-el10.sh`** acompanha este documento. Foi escrito a partir do `scripts/provisionar-homolog-el10.sh` do PetroWeb, com as diferenças que um segundo app na mesma máquina exige.

### 5.1 O que ele faz de diferente

| Recurso | PetroWeb | Frota | Por quê |
|---|---|---|---|
| Diretório | `/var/www/petroweb` | `/var/www/petroweb-frota` | — |
| Banco | `PetrolinkERP` / `petroweb_app` | `PetroWebFrota` / `frota_app` | Isolamento — um dump não leva o outro junto |
| **Pool PHP-FPM** | `www` (padrão) | **`frota`, dedicado, 12 filhos** | **O ponto crítico.** Com pool compartilhado, uma rajada em um app esgota os filhos e derruba o outro |
| Socket | `/run/php-fpm/www.sock` | `/run/php-fpm/frota.sock` | — |
| Worker | `petroweb-worker.service` | `frota-worker.service` | Filas separadas |
| nginx | `petroweb.conf` | `petroweb-frota.conf` | Server blocks independentes |
| Swap | do PetroWeb | preservado; cria 2 GB só se não houver | Com 8 GB o swap é rede de segurança, não muleta |

O script **não toca em nada do PetroWeb** — pode rodar com a homologação no ar.

### 5.2 O que muda com 8 GB e 2 núcleos

A folga de memória tira do caminho os problemas que eu tinha antecipado — build de assets travando, swap insuficiente, pool apertado. **O gargalo passa a ser CPU.**

| Recurso | Situação |
|---|---|
| **RAM (8 GB)** | Confortável. Dois pools PHP-FPM, dois workers e o Postgres cabem com sobra |
| **Núcleos (2)** | **É o limite.** O build de assets, os dois pools e o Postgres disputam os mesmos 2 núcleos |
| **SSD (100 GB)** | Folgado, mas XML fiscal acumula por 132 meses — monitorar a partir do primeiro cliente real |
| **Banda (8 TB)** | Irrelevante para este perfil de uso |

Duas consequências práticas:

1. **Rode o build de assets fora do horário de uso da homologação do PetroWeb.** Em 2 núcleos, o `npm run build` come a máquina por alguns minutos. Se precisar subir durante o expediente, use `PULAR_BUILD=1` e envie o `public/build` pronto da sua máquina.
2. **O pool do Frota tem teto de 12 filhos.** Não é memória que limita — é CPU. Aumentar isso não dá vazão, só dá fila mais longa.

### 5.3 PostgreSQL: o ajuste que o script deliberadamente não faz

O `postgresql.conf` do EL10 vem dimensionado para máquina pequena — `shared_buffers` de 128 MB. A VPS subiu para 8 GB e agora terá **dois bancos ativos**, mas o Postgres provavelmente continua com o padrão.

O script **não mexe nisso de propósito**: o Postgres é compartilhado com o PetroWeb, e alterá-lo aqui mudaria o comportamento do outro produto sem aviso. Fica como tarefa separada, para fazer com calma e fora de horário:

```bash
# Conferir o que está valendo hoje
sudo -u postgres psql -c "SHOW shared_buffers;"

# Sugestão para 8 GB com dois bancos
shared_buffers       = 2GB      # padrão 128MB
effective_cache_size = 5GB
work_mem             = 16MB
maintenance_work_mem = 512MB
max_connections      = 100
```

`shared_buffers` exige restart do Postgres — ou seja, derruba os dois apps por alguns segundos. Trate como janela de manutenção.

### 5.4 Pré-checagens que ele faz antes de mexer em qualquer coisa

1. **RAM abaixo de 3,5 GB → aborta.** Só dispara se o script estiver rodando na máquina errada. Existe `FORCAR=1`, mas é escolha consciente, não acidente.
2. **PostgreSQL ausente → aborta.** O script pressupõe a VPS já provisionada pelo script do PetroWeb.
3. **Domínio não resolve → aborta.** O certbot falharia depois, com o script já na metade.
4. **Domínio resolve para outro IP → pergunta.** Costuma ser DNS ainda propagando.

### 5.5 Uso

```bash
# Como root, na VPS, com o DNS já propagado
bash provisionar-frota-el10.sh

# Se o build de assets estourar memória, gere na sua máquina e envie public/build
PULAR_BUILD=1 bash provisionar-frota-el10.sh
```

### 5.6 Depois de rodar

O script imprime os próximos passos. O que não pode ser esquecido:

- **Incluir `PetroWebFrota` no `scripts/backup_producao.sh`** — hoje ele só cobre o banco do PetroWeb. Um backup que não cobre o banco novo é pior que não ter backup, porque dá falsa segurança.
- **Conferir que o PetroWeb continua no ar** — `systemctl status php-fpm nginx petroweb-worker` e um `curl -sI` na homologação.
- **Revisar o `.env`** antes de qualquer emissão.

---

## 6. A pendência que precisa ser resolvida antes da primeira migration

O `composer.json` do PetroWeb tem **`stancl/tenancy`**, e existem `Tenant.php`, `Models/Central/` e um `TenantScope`. Mas a memória do projeto registra a Fase 1 da arquitetura multi-tenant como **isolamento por `empresa_id`**, que é um modelo diferente — banco único com discriminador, não banco por tenant.

São duas coisas que podem coexistir (o `stancl` fazendo a resolução de tenant por domínio, e o `empresa_id` fazendo o escopo dentro do banco), mas **a documentação do Frota assumiu apenas o discriminador**.

> **Antes de escrever a primeira migration:** abrir o `app/Models/Tenant.php`, o `config/tenancy.php` e o `TenantScope` do PetroWeb e responder — o `stancl` está resolvendo tenant por domínio, criando banco por tenant, ou está lá sem uso efetivo? A resposta muda a modelagem inteira do documento 02, e é barata agora e cara depois.

---

## 7. Primeiros passos com o app no ar

```bash
# 1. Seeds de domínio — sem os municípios IBGE o CT-e não emite
php artisan db:seed --class=EnumsFiscaisSeeder      # tpRod, tpCar, tpCarga, categCombVeic…
php artisan db:seed --class=MunicipiosIbgeSeeder    # ~5.570 registros
php artisan db:seed --class=TabelasDominioSeeder    # carrocerias, CVC, naturezas de carga
php artisan db:seed --class=ProdutosPerigososSeeder # Relação ONU

# 2. Empresa e usuário de homologação
php artisan frota:criar-empresa --nome="Transportes HALC" --cnpj=...
php artisan frota:criar-usuario --admin

# 3. Conferir
php artisan about
systemctl status frota-worker
```

Os quatro seeders da etapa 1 vêm do documento 05, seção 11. Os valores já estão prontos ali — é transcrição, não pesquisa.

---

## 8. Riscos deste arranque

| Risco | Probabilidade | Mitigação |
|---|---|---|
| Fork trazer código morto de posto que ninguém remove | **Alta** | Fazer a cirurgia da seção 4 **antes** do primeiro push, não depois |
| Backup continuar cobrindo só o PetroWeb | **Alta** | Item explícito na seção 5.6 — hoje o `backup_producao.sh` só cobre o `PetrolinkERP` |
| Ambiguidade do `stancl/tenancy` só aparecer na Sprint 6 | Média | Seção 6 — resolver antes da primeira migration |
| Build de assets engasgar a homologação do PetroWeb | Média | 2 núcleos disputados — rodar fora do expediente ou usar `PULAR_BUILD=1` |
| Postgres continuar com `shared_buffers` de 128 MB | Média | Seção 5.3 — tarefa separada, com janela de manutenção |
| SSD encher com XML fiscal ao longo dos 132 meses de guarda | Baixa agora | 100 GB dão folga longa; monitorar a partir do primeiro cliente real |

---

## 9. Documentos relacionados

| Documento | Conteúdo |
|---|---|
| `01_DOCUMENTO_MESTRE.md` | Visão, escopo, regras de negócio, roadmap |
| `02_MODELAGEM_DADOS.md` | ERD e dicionário — **ler junto com a seção 2 deste documento** |
| `03_ESPECIFICACAO_FISCAL_CTE_MDFE.md` | CT-e, MDF-e, vale-pedágio, CIOT |
| `04_BACKLOG.md` | 163 histórias em 23 sprints |
| `05_CADASTROS_E_TABELAS_DE_DOMINIO.md` | Tabelas prontas para os seeds da seção 7 |
| `provisionar-frota-el10.sh` | O script desta seção 5 |
