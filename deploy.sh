#!/usr/bin/env bash
# ============================================================================
# deploy.sh — atualização de um comando (degrau 2 do runbook).
# ============================================================================
# Uso: ./deploy.sh   (rodar como o usuário dono da aplicação, ex.: www-data
#                     via sudo -u www-data, ou o usuário de deploy dedicado)
#
# Pra instalação inaugural (banco vazio, primeira vez), usar
# docs/RUNBOOK_VPS.md — este script pressupõe app já rodando, com dado real.
#
# Idempotente: rodar duas vezes seguidas sem mudança no repo não faz nada
# destrutivo (git pull vira no-op, composer/npm reinstalam sem efeito
# observável, migrate --force não acha migration pendente).
#
# Para no primeiro erro (set -e + pipefail) — nenhum passo roda com o
# anterior tendo falhado silenciosamente.
# ============================================================================

set -euo pipefail

# ── 0. Pré-checagens ─────────────────────────────────────────────────────
cd "$(dirname "${BASH_SOURCE[0]}")"

if [ ! -f artisan ]; then
    echo "ERRO: artisan não encontrado — rode este script da raiz do repositório." >&2
    exit 1
fi

if [ ! -f .env ]; then
    echo "ERRO: .env não encontrado. Isto parece uma instalação nova — use docs/RUNBOOK_VPS.md, não este script." >&2
    exit 1
fi

LOG_FILE="storage/logs/deploy.log"
COMMIT_ANTES="$(git rev-parse HEAD)"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "$LOG_FILE"
}

log "=== Deploy iniciado (commit atual: ${COMMIT_ANTES:0:12}) ==="

# ── 1. Modo de manutenção ────────────────────────────────────────────────
log "Ativando modo de manutenção..."
php artisan down --retry=30 || true
# `|| true`: se já estava em manutenção (deploy anterior travou no meio),
# `artisan down` retorna erro, mas não deve travar ESTE deploy por isso.

# Garante que `up` roda mesmo se o script falhar mais adiante — nunca
# deixa a aplicação presa em manutenção por um erro em qualquer passo daqui pra baixo.
trap 'php artisan up || true' EXIT

# ── 2. Atualizar código ──────────────────────────────────────────────────
log "git pull..."
git pull --ff-only
COMMIT_DEPOIS="$(git rev-parse HEAD)"
log "Commit após pull: ${COMMIT_DEPOIS:0:12}"

# ── 3. Aviso de migration sensível ───────────────────────────────────────
MIGRATIONS_NOVAS="$(git diff --name-only "$COMMIT_ANTES" "$COMMIT_DEPOIS" -- database/migrations || true)"

if [ -n "$MIGRATIONS_NOVAS" ]; then
    echo ""
    echo "⚠️  ATENÇÃO — migrations novas neste deploy:"
    echo "$MIGRATIONS_NOVAS" | sed 's/^/    /'
    echo ""
    echo "⚠️  Antes de continuar: confira docs/AUDITORIA_ATUAL.md (changelog) e"
    echo "    docs/DEBITOS_TECNICOS.md pra qualquer nota sobre migration de dado"
    echo "    sensível (backfill, alteração de coluna, NOT NULL). Migration aditiva"
    echo "    e reversível é o padrão deste projeto — mas confirme antes de seguir."
    echo ""
    read -r -p "Continuar com 'php artisan migrate --force'? [s/N] " RESPOSTA
    case "$RESPOSTA" in
        [sS][iI][mM]|[sS])
            log "Confirmado — prosseguindo com migrate."
            ;;
        *)
            log "Deploy interrompido pelo operador antes do migrate. Código já atualizado (${COMMIT_DEPOIS:0:12}), banco intacto."
            echo "Código atualizado mas migrations NÃO rodaram. App segue em manutenção."
            echo "Rode 'php artisan migrate --force' manualmente quando decidir, depois 'php artisan up'."
            exit 1
            ;;
    esac
else
    log "Nenhuma migration nova neste deploy — pulando confirmação."
fi

# ── 4. Dependências ───────────────────────────────────────────────────────
log "composer install --no-dev..."
composer install --no-dev --optimize-autoloader --no-interaction

log "npm ci && npm run build..."
npm ci
npm run build

# ── 5. Migrations ─────────────────────────────────────────────────────────
log "php artisan migrate --force..."
php artisan migrate --force

# ── 6. Caches ──────────────────────────────────────────────────────────────
log "Reconstruindo caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ── 7. Fila ────────────────────────────────────────────────────────────────
log "Reiniciando worker de fila..."
php artisan queue:restart || true   # sinaliza o worker a encerrar após o job atual
if command -v supervisorctl >/dev/null 2>&1 && sudo supervisorctl status frota-worker:* >/dev/null 2>&1; then
    sudo supervisorctl restart frota-worker:* || log "AVISO: supervisorctl restart falhou — reiniciar o worker manualmente."
elif systemctl list-unit-files frota-worker.service --no-legend 2>/dev/null | grep -q frota-worker; then
    # EL10 (scripts/provisionar-homolog-el10.sh): worker roda como unit do systemd.
    sudo systemctl restart frota-worker || log "AVISO: systemctl restart frota-worker falhou — reiniciar manualmente."
else
    log "AVISO: nenhum gerenciador de worker encontrado (supervisor/systemd) — reinicie o worker manualmente."
fi

# ── 8. Sair do modo de manutenção ────────────────────────────────────────
log "Saindo do modo de manutenção..."
php artisan up
trap - EXIT

log "=== Deploy concluído. Commit deployado: ${COMMIT_DEPOIS:0:12} (${COMMIT_DEPOIS}) ==="
