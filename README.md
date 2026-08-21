# PetroWeb Frota

Sistema SaaS multi-tenant de gestão de transporte rodoviário de cargas: frota,
rotas, viagens, custos e emissão de CT-e e MDF-e.

Terceiro produto da família **PetroWeb**, ao lado do PetroWeb (ERP de postos) e
do PetroWeb PDV.

---

## Instalação local

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

# PostgreSQL — dois bancos: o central e o do tenant de desenvolvimento
createdb frota_central
createdb frota_dev

php artisan migrate --database=central --path=database/migrations/central
php artisan migrate --path=database/migrations/tenant

npm run dev
php artisan serve
```

## Documentação

Tudo em `docs/`. Comece pelo `README.md` de lá, ou direto:

| Documento | Conteúdo |
|---|---|
| `01_DOCUMENTO_MESTRE.md` | Visão, escopo, regras de negócio, roadmap, critérios de aceite |
| `02_MODELAGEM_DADOS.md` | ERD, dicionário de dados, ordem de migrations |
| `03_ESPECIFICACAO_FISCAL_CTE_MDFE.md` | CT-e, MDF-e, vale-pedágio, CIOT, contingência |
| `04_BACKLOG.md` | 163 histórias em 23 sprints |
| `05_CADASTROS_E_TABELAS_DE_DOMINIO.md` | Tabelas prontas para os seeds |
| `06_ARRANQUE_E_INFRA.md` | Provisionamento na VPS |

`CLAUDE.md` na raiz tem as instruções de trabalho para o Claude Code.

## Deploy

```bash
# Instalação inaugural, como root na VPS
bash scripts/provisionar-frota-el10.sh

# Atualizações
./deploy.sh
```
