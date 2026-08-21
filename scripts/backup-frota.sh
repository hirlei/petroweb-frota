#!/usr/bin/env bash
# ============================================================================
# backup-frota.sh — dump dos bancos do PetroWeb Frota
# ============================================================================
# O backup_producao.sh que já roda na VPS só enxerga o banco do PetroWeb.
# Depois do provisionamento existem o banco CENTRAL do Frota e um banco POR
# CLIENTE (frota_<slug>) — e nenhum deles estaria coberto.
#
# Este script descobre os bancos sozinho, em vez de guardar uma lista: cliente
# novo entra no backup sem ninguém lembrar de editar arquivo nenhum. Lista
# manual em script de backup é como se perde o banco do cliente que entrou
# ontem.
#
# Uso (como root, ou como o usuário postgres):
#   bash /var/www/petroweb-frota/scripts/backup-frota.sh
#
# Cron sugerido — 02h10, depois do backup do PetroWeb:
#   10 2 * * * /var/www/petroweb-frota/scripts/backup-frota.sh >> /var/log/backup-frota.log 2>&1
# ============================================================================

set -euo pipefail

DESTINO="${DESTINO:-/var/backups/petroweb-frota}"
RETENCAO_DIAS="${RETENCAO_DIAS:-14}"
BANCO_CENTRAL="${BANCO_CENTRAL:-PetroWebFrota}"
PREFIXO_TENANT="${PREFIXO_TENANT:-frota_}"
CARIMBO="$(date '+%Y%m%d-%H%M')"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }

# ── Pré-checagens ──────────────────────────────────────────────────────────
command -v pg_dump >/dev/null || { log "ERRO: pg_dump não encontrado."; exit 1; }

mkdir -p "${DESTINO}"

# Backup em diretório que qualquer um lê é vazamento de dado fiscal.
chmod 700 "${DESTINO}"

ESPACO_LIVRE_MB=$(df -Pm "${DESTINO}" | awk 'NR==2 {print $4}')

if [ "${ESPACO_LIVRE_MB}" -lt 2048 ]; then
    log "ERRO: só ${ESPACO_LIVRE_MB} MB livres em ${DESTINO}. Abortado antes de gerar dump pela metade."
    exit 1
fi

# ── Descobrir os bancos ────────────────────────────────────────────────────
# `datallowconn` filtra os templates. O prefixo vem do config/tenancy.php.
BANCOS=$(sudo -u postgres psql -At -c \
    "SELECT datname FROM pg_database
      WHERE datallowconn
        AND (datname = '${BANCO_CENTRAL}' OR datname LIKE '${PREFIXO_TENANT}%')
      ORDER BY datname;")

if [ -z "${BANCOS}" ]; then
    log "ERRO: nenhum banco do Frota encontrado. O provisionamento chegou a rodar?"
    exit 1
fi

QTD=$(echo "${BANCOS}" | wc -l)
log "=== Backup do Frota — ${QTD} banco(s) ==="

FALHAS=0

for BANCO in ${BANCOS}; do
    ARQUIVO="${DESTINO}/${BANCO}-${CARIMBO}.dump"

    log "Dump de ${BANCO}…"

    # -Fc: formato custom, comprimido e restaurável seletivamente com pg_restore.
    #
    # O dump sai por STDOUT e quem grava é ESTE shell, não o pg_dump. Com
    # `-f`, o arquivo seria criado pelo usuário postgres — que não tem acesso
    # a um diretório 700 do root. Descobri testando: falhava com "Permission
    # denied" em todos os bancos.
    # shellcheck disable=SC2024
    # O aviso é sobre o redirect não herdar o sudo — e é exatamente o que se
    # quer aqui: quem grava precisa ser o root, dono do diretório.
    if sudo -u postgres pg_dump -Fc --no-owner --no-privileges "${BANCO}" > "${ARQUIVO}"; then
        chmod 600 "${ARQUIVO}"

        TAMANHO=$(stat -c %s "${ARQUIVO}")

        # Dump de banco povoado que sai com menos de 5 KB é dump quebrado.
        if [ "${TAMANHO}" -lt 5120 ]; then
            log "  ATENÇÃO: ${ARQUIVO} tem só ${TAMANHO} bytes. Banco vazio ou dump truncado."
        fi

        log "  ok — $(numfmt --to=iec "${TAMANHO}" 2>/dev/null || echo "${TAMANHO} bytes")"
    else
        log "  FALHOU: ${BANCO}"
        rm -f "${ARQUIVO}"
        FALHAS=$((FALHAS + 1))
    fi
done

# ── Verificar que os dumps abrem ───────────────────────────────────────────
# Backup que ninguém tenta ler é esperança, não backup. `pg_restore -l` lê o
# índice do arquivo: é barato e pega dump corrompido ou cortado pela metade.
log "Conferindo a integridade dos dumps…"

for ARQUIVO in "${DESTINO}"/*-"${CARIMBO}".dump; do
    [ -e "${ARQUIVO}" ] || continue

    # pg_restore -l só LÊ o arquivo, não toca no banco: roda como root mesmo,
    # que é quem enxerga o diretório 700.
    if pg_restore -l "${ARQUIVO}" >/dev/null 2>&1; then
        log "  ok — $(basename "${ARQUIVO}")"
    else
        log "  CORROMPIDO — $(basename "${ARQUIVO}")"
        FALHAS=$((FALHAS + 1))
    fi
done

# ── Retenção ───────────────────────────────────────────────────────────────
# Só apaga se ESTE backup deu certo. Rotacionar depois de uma falha é trocar
# um backup bom por nenhum.
if [ "${FALHAS}" -eq 0 ]; then
    APAGADOS=$(find "${DESTINO}" -name '*.dump' -mtime "+${RETENCAO_DIAS}" -print -delete | wc -l)
    log "Retenção: ${APAGADOS} arquivo(s) com mais de ${RETENCAO_DIAS} dias removidos."
else
    log "Retenção NÃO executada: houve ${FALHAS} falha(s) neste backup."
fi

log "=== Fim — ${QTD} banco(s), ${FALHAS} falha(s) ==="
log "Destino: ${DESTINO}"

# ── O lembrete que importa ─────────────────────────────────────────────────
if [ "${FALHAS}" -eq 0 ]; then
    cat <<'AVISO'

  Estes dumps estão na MESMA MÁQUINA que o banco. Isso cobre erro humano e
  migration ruim — não cobre perda da VPS. Leve uma cópia para fora:

      rsync -az /var/backups/petroweb-frota/ usuario@outro-host:/backups/frota/

  E teste uma restauração de verdade antes de precisar dela:

      sudo -u postgres createdb frota_teste_restore
      sudo -u postgres pg_restore -d frota_teste_restore < <arquivo>.dump
      sudo -u postgres psql -d frota_teste_restore -c "SELECT count(*) FROM pessoas;"
      sudo -u postgres dropdb frota_teste_restore

AVISO
fi

exit "$(( FALHAS > 0 ? 1 : 0 ))"
