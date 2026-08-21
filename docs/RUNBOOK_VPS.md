# Runbook — primeira subida na VPS

Instalação inaugural do **PetroWeb Frota** em `frota.petroweb.app`, na mesma
VPS que já roda a homologação do PetroWeb (AlmaLinux 10, 8 GB, 2 núcleos).

Para **atualizações** depois desta primeira vez, use `./deploy.sh` — este
documento é só para o banco vazio.

> **Ambiente.** Tudo aqui sobe em **homologação da SEFAZ**. Nenhum documento
> emitido nesta instalação tem valor fiscal, e é assim que deve ficar até o
> certificado A1 real entrar e a filial ser virada para produção.

---

## 0. O que precisa estar pronto antes

| Item | Como conferir |
|---|---|
| DNS do domínio raiz | `dig +short frota.petroweb.app` → IP da VPS |
| **DNS curinga dos clientes** | `dig +short serraazul.frota.petroweb.app` → mesmo IP |
| Acesso root na VPS | `ssh root@<ip>` |
| Repositório no GitHub | passo 1 abaixo |
| E-mail para o Let's Encrypt | qualquer caixa que você leia |

### Sobre o DNS curinga — o ponto que mais atrasa

Cada cliente vive em **`<slug>.frota.petroweb.app`**. O domínio raiz responde
apenas o painel do provedor. Sem o registro do subdomínio, o cliente não abre.

No painel de DNS do `petroweb.app`, crie:

```
Tipo  Nome                    Valor
A     frota                   179.198.123.188      (já existe)
A     *.frota                 179.198.123.188      (criar)
```

O curinga de **DNS** é fácil. O curinga de **certificado** não é — ele exige
desafio DNS-01 com token da API do provedor. Por isso o script emite
certificado para os nomes exatos: `frota.petroweb.app` e os subdomínios dos
clientes que você informar. Cada cliente novo depois pede um `--expand`
(o script imprime o comando no final).

---

## 1. Publicar o repositório (da sua máquina)

Crie **`petroweb-frota`** como repositório **privado** em github.com/hirlei —
sem README, sem .gitignore, sem licença (o projeto já tem os três).

Depois, em `C:\projetos\petroweb-frota`:

```bash
git remote add origin git@github.com:hirlei/petroweb-frota.git
git branch -M main
git push -u origin main
```

Se o `git remote add` reclamar que `origin` já existe:

```bash
git remote set-url origin git@github.com:hirlei/petroweb-frota.git
git push -u origin main
```

### Chave de deploy da VPS

A VPS precisa conseguir clonar. Na VPS, como root:

```bash
ssh-keygen -t ed25519 -C "vps-frota" -f /root/.ssh/id_ed25519_frota -N ""
cat /root/.ssh/id_ed25519_frota.pub
```

Cole essa chave em **Settings → Deploy keys → Add deploy key** do repositório
(pode deixar sem permissão de escrita). Ainda na VPS:

```bash
cat >> /root/.ssh/config <<'EOF'
Host github.com
    IdentityFile /root/.ssh/id_ed25519_frota
    IdentitiesOnly yes
EOF

ssh -T git@github.com   # deve responder "Hi hirlei/petroweb-frota!"
```

---

## 2. Provisionar (na VPS, como root)

```bash
curl -fsSL -o /root/provisionar-frota-el10.sh \
  https://raw.githubusercontent.com/hirlei/petroweb-frota/main/scripts/provisionar-frota-el10.sh
```

> Repositório privado? Então copie o arquivo da sua máquina:
> `scp scripts/provisionar-frota-el10.sh root@<ip>:/root/`

```bash
bash /root/provisionar-frota-el10.sh
```

Ele pergunta domínio, repositório, e-mail do Let's Encrypt e os **slugs dos
clientes iniciais** (deixe `serraazul` para a demonstração). Depois faz, nesta
ordem: swap · banco e usuário no PostgreSQL · clone · composer · `.env` e
`APP_KEY` · migrations do banco central · build do Vite · permissões e SELinux
· **pool PHP-FPM dedicado** · nginx · HTTPS · worker de fila · cron · caches.

O pool dedicado é o ponto crítico da convivência: pool compartilhado significa
que uma rajada em um dos produtos derruba o outro.

Se parar no meio, corrija a causa e rode de novo — ele não destrói banco nem
`.env` existentes.

---

## 3. Criar o cliente e os dados

Ainda na VPS:

```bash
cd /var/www/petroweb-frota

# Banco frota_serraazul, migrations, permissões e catálogo padrão — tudo junto
sudo -u nginx php artisan tenant:criar serraazul --nome="Transportes Serra Azul"

# Municípios do IBGE (a VPS tem rede; o CT-e exige o código IBGE)
sudo -u nginx php artisan tenants:run municipios:importar --tenants=serraazul

# SÓ para demonstração — empresa, filial, usuários e clientes fictícios
sudo -u nginx php artisan tenants:run db:seed --tenants=serraazul \
  --argument="class=Database\\Seeders\\DemonstracaoSeeder"
```

Ao final:

```
https://serraazul.frota.petroweb.app
admin@serraazul.com.br / frota2026
```

**Troque essa senha antes de qualquer uso que não seja a apresentação.**

Outros perfis, para mostrar que a permissão muda a tela:

| E-mail | Papel | O que enxerga |
|---|---|---|
| `admin@serraazul.com.br` | Administrador | Tudo |
| `fiscal@serraazul.com.br` | Fiscal | Emissão, sem cadastro |
| `operacao@serraazul.com.br` | Operação | Cadastro e frota, sem fiscal |
| `financeiro@serraazul.com.br` | Financeiro | Consulta e tabelas de frete |

---

## 4. Conferir

```bash
# A aplicação responde
curl -sI https://serraazul.frota.petroweb.app/login | head -1     # 200
curl -sI https://frota.petroweb.app | head -1                      # 200

# O PetroWeb continua no ar — este é o teste que importa
curl -sI https://homolog.petroweb.app | head -1
systemctl status php-fpm nginx petroweb-worker frota-worker --no-pager | grep -E 'Active|●'

# Bancos
sudo -u postgres psql -l | grep -i frota

# Logs, se algo falhar
tail -50 /var/www/petroweb-frota/storage/logs/laravel.log
tail -50 /var/log/nginx/frota-error.log
```

---

## 5. O que está pronto para mostrar

Sendo direto sobre o estado do produto:

**Funciona de ponta a ponta**

- Acesso, com 2FA exigido de quem tem permissão fiscal
- Início com os módulos e seus códigos de rotina
- **Pessoas (1010)** — cadastro unificado completo: papéis acumulados,
  indicador de IE alimentando o `indIEDest`, endereços com código IBGE,
  contatos que recebem XML e DACTE, e o painel de pendências que separa o
  que impede salvar do que impede emitir

**Existe mas ainda não tem tela**

Migrations, models e regras de veículos, composições, motoristas, carrocerias
e CVC. O banco já recusa segunda matriz, dois certificados ativos, capacidade
acima do PBTC e vínculo inválido de motorista.

**Só mockup**

Frota, operação, CT-e, MDF-e e vale-pedágio — 27 telas aprovadas.

> **Sugestão para a apresentação.** Mostre o sistema rodando no cadastro real
> e o canvas de mockups para o resto do produto. Os dois juntos contam a
> história inteira sem que nada precise ser encenado — e a plateia percebe a
> diferença entre o que roda e o que está desenhado.

---

## 6. Pendências assumidas

| Pendência | Risco | Quando |
|---|---|---|
| Backup não cobre os bancos do Frota | **Alto** | Antes da apresentação |
| `shared_buffers` do PostgreSQL provavelmente em 128 MB | Médio | Fora de horário de uso |
| Certificado por nome, não curinga | Baixo | Quando houver muitos clientes |
| Sem tela de configuração de 2FA | Médio | Sprint de segurança |
| Senha padrão no seed de demonstração | **Alto se virar uso real** | Ao trocar de demo para piloto |

O backup é o item que eu resolveria primeiro: o `scripts/backup_producao.sh`
da VPS hoje só enxerga o banco do PetroWeb.
