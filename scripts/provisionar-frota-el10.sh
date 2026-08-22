#!/usr/bin/env bash
# ============================================================================
# provisionar-frota-el10.sh — instalação inaugural do PetroWeb Frota
# ============================================================================
# Alvo: a MESMA VPS que já roda a homologação do PetroWeb (AlmaLinux 10 / EL10).
# Escrito a partir de scripts/provisionar-homolog-el10.sh do PetroWeb, com as
# diferenças que um SEGUNDO app na mesma máquina exige:
#
#   • diretório, banco, usuário de banco e serviço próprios — nenhum recurso
#     é compartilhado com o PetroWeb, exceto o PostgreSQL e o nginx;
#   • POOL PHP-FPM DEDICADO (frota) — sem isso, uma rajada em um dos apps
#     consome todos os filhos do pool www e derruba o outro;
#   • worker de fila próprio (frota-worker.service);
#   • contextos SELinux para o novo diretório (SELinux fica ENFORCING);
#   • não toca em nada do PetroWeb — pode rodar com a homologação no ar.
#
# A VPS alvo tem 8 GB de RAM, 2 núcleos e 100 GB de SSD — folga confortável
# para os dois produtos. O gargalo aqui é CPU (2 núcleos), não memória.
#
# PRÉ-REQUISITOS (o script confere e para se faltar):
#   1. registro DNS A de frota.petroweb.app apontando para o IP da VPS,
#      já propagado (o certbot falha se o nome ainda não resolver);
#   2. repositório github.com/hirlei/petroweb-frota criado e com o primeiro
#      push feito;
#   3. chave de deploy do repositório já no authorized_keys do GitHub.
#
# USO (como root):
#   bash provisionar-frota-el10.sh
#
# Idempotente na medida do razoável: re-rodar após falha retoma sem destruir
# banco nem .env existentes.
# ============================================================================

set -euo pipefail

# ── Parâmetros ─────────────────────────────────────────────────────────────
DOMINIO_PADRAO="frota.petroweb.app"
REPO_PADRAO="git@github.com:hirlei/petroweb-frota.git"
APP_DIR="/var/www/petroweb-frota"
APP_USER="nginx"
DB_NAME="PetroWebFrota"
DB_USER="frota_app"
POOL="frota"
CRED="/root/.petroweb-frota-db"

read -rp "Domínio [${DOMINIO_PADRAO}]: " DOMINIO
DOMINIO="${DOMINIO:-$DOMINIO_PADRAO}"
read -rp "Repositório [${REPO_PADRAO}]: " REPO
REPO="${REPO:-$REPO_PADRAO}"
read -rp "E-mail para o Let's Encrypt: " EMAIL_LE
read -rp "Slugs dos clientes iniciais, separados por espaço [serraazul]: " TENANTS_INICIAIS
TENANTS_INICIAIS="${TENANTS_INICIAIS:-serraazul}"

echo
echo "  Domínio ....... ${DOMINIO}"
echo "  Repositório ... ${REPO}"
echo "  Diretório ..... ${APP_DIR}"
echo "  Banco ......... ${DB_NAME} (usuário ${DB_USER})"
echo "  Pool PHP-FPM .. ${POOL}"
echo "  Clientes ...... ${TENANTS_INICIAIS}  (viram <slug>.${DOMINIO})"
echo
read -rp "Confirma? [s/N] " OK
case "$OK" in [sS]*) ;; *) echo "Abortado."; exit 1 ;; esac

log() { echo -e "\n\033[1;33m▸ $*\033[0m"; }

# ── 0. Pré-checagens ───────────────────────────────────────────────────────
log "Pré-checagens"

RAM_MB=$(free -m | awk '/^Mem:/{print $2}')
CPUS=$(nproc)
if [ "$RAM_MB" -lt 3500 ]; then
    echo "ERRO: a VPS tem apenas ${RAM_MB} MB de RAM — esperado 8 GB." >&2
    echo "      Dois apps Laravel com worker cada, mais o Postgres, não cabem aqui." >&2
    echo "      Confira se está na máquina certa. Para forçar: FORCAR=1 bash $0" >&2
    [ "${FORCAR:-0}" = "1" ] || exit 1
    echo "      FORCAR=1 — seguindo sob risco."
fi

if ! command -v psql >/dev/null 2>&1; then
    echo "ERRO: PostgreSQL não encontrado. Este script pressupõe a VPS já provisionada" >&2
    echo "      pelo provisionar-homolog-el10.sh do PetroWeb." >&2
    exit 1
fi

IP_VPS="$(curl -fsS https://api.ipify.org || echo '')"
IP_DNS="$(getent hosts "$DOMINIO" | awk '{print $1}' | head -1 || echo '')"
if [ -z "$IP_DNS" ]; then
    echo "ERRO: ${DOMINIO} não resolve. Crie o registro A e espere propagar." >&2
    exit 1
fi
if [ -n "$IP_VPS" ] && [ "$IP_DNS" != "$IP_VPS" ]; then
    echo "AVISO: ${DOMINIO} resolve para ${IP_DNS}, mas esta VPS é ${IP_VPS}."
    read -rp "Continuar assim? [s/N] " C; case "$C" in [sS]*) ;; *) exit 1 ;; esac
fi
echo "  OK — ${RAM_MB} MB de RAM, ${CPUS} núcleo(s), ${DOMINIO} → ${IP_DNS}"

# ── 1. Swap ────────────────────────────────────────────────────────────────
# Com 8 GB de RAM o swap deixa de ser muleta e vira só rede de segurança:
# 2 GB bastam. Não mexemos no swap existente se já atender — ele é do PetroWeb.
log "Swap"
SWAP_MB=$(free -m | awk '/^Swap:/{print $2}')
if [ "$SWAP_MB" -lt 2000 ]; then
    echo "  Swap atual: ${SWAP_MB} MB — criando 2 GB como rede de segurança."
    fallocate -l 2G /swapfile-frota
    chmod 600 /swapfile-frota
    mkswap /swapfile-frota
    swapon /swapfile-frota
    grep -q '^/swapfile-frota' /etc/fstab || echo '/swapfile-frota none swap sw 0 0' >> /etc/fstab
else
    echo "  Swap de ${SWAP_MB} MB já suficiente — não mexo."
fi

# ── 2. Banco de dados ──────────────────────────────────────────────────────
log "PostgreSQL — banco e usuário do Frota"
if sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='${DB_USER}'" | grep -q 1; then
    echo "  Usuário ${DB_USER} já existe — reaproveitando credenciais de ${CRED}."
    DB_PASS="$(grep -oP '(?<=DB_PASSWORD=).*' "$CRED" 2>/dev/null || true)"
    if [ -z "$DB_PASS" ]; then
        echo "ERRO: usuário existe mas ${CRED} não tem a senha. Recupere ou remova o usuário." >&2
        exit 1
    fi

    # Idempotente: instalação antiga pode ter ficado sem o atributo.
    sudo -u postgres psql -c "ALTER ROLE ${DB_USER} CREATEDB;" >/dev/null
else
    DB_PASS="$(openssl rand -base64 24 | tr -d '/+=' | head -c 28)"
    sudo -u postgres psql <<SQL
CREATE USER ${DB_USER} WITH PASSWORD '${DB_PASS}';
CREATE DATABASE "${DB_NAME}" OWNER ${DB_USER};
GRANT ALL PRIVILEGES ON DATABASE "${DB_NAME}" TO ${DB_USER};
SQL
    sudo -u postgres psql -d "${DB_NAME}" -c "GRANT ALL ON SCHEMA public TO ${DB_USER};"

    # CADA TENANT É UM BANCO NOVO, e quem o cria é o stancl com ESTAS
    # credenciais. GRANT ALL PRIVILEGES não dá esse direito — CREATEDB é
    # atributo de role, não privilégio de banco. Sem isto, `tenant:criar`
    # morre em "permission denied to create database".
    sudo -u postgres psql -c "ALTER ROLE ${DB_USER} CREATEDB;"
    { echo "DB_DATABASE=${DB_NAME}"; echo "DB_USERNAME=${DB_USER}"; echo "DB_PASSWORD=${DB_PASS}"; } > "$CRED"
    chmod 600 "$CRED"
    echo "  Criado. Credenciais em ${CRED} (modo 600)."
fi

# ── 3. Clone ───────────────────────────────────────────────────────────────
log "Clonando o repositório"
if [ ! -d "${APP_DIR}/.git" ]; then
    mkdir -p "$(dirname "$APP_DIR")"
    git clone "$REPO" "$APP_DIR"
else
    echo "  ${APP_DIR} já é um repositório — pulando clone."
fi
cd "$APP_DIR"

# ── 4. Dependências e .env ─────────────────────────────────────────────────
log "Composer, .env, chave e assets"
# Como root, o Composer desliga plugins por segurança — e sem plugins o
# package discovery do Laravel não roda. Aqui é uma VPS dedicada, provisionada
# como root de propósito; a variável é explícita para não virar surpresa.
export COMPOSER_ALLOW_SUPERUSER=1

if [ -f composer.lock ]; then
    composer install --no-dev --optimize-autoloader --no-interaction
else
    # Sem lock, `install` cai em `update` e resolve tudo do zero. Deixamos
    # explícito, e o lock gerado DEVE ser commitado depois — build sem lock
    # não é reprodutível, e a versão que sobe hoje não é a de amanhã.
    echo "  AVISO: composer.lock ausente — resolvendo dependências do zero."
    echo "         Commite o lock gerado no repositório após esta instalação."
    composer update --no-dev --optimize-autoloader --no-interaction
fi

if [ ! -f .env ]; then
    # O modelo é o .env.example do repositório. Não existe
    # .env.production.example — a diferença de produção está nos sed abaixo.
    cp .env.example .env
    sed -i "s|^APP_NAME=.*|APP_NAME=\"PetroWeb Frota\"|" .env
    sed -i "s|^APP_URL=.*|APP_URL=https://${DOMINIO}|" .env
    sed -i "s|^APP_ENV=.*|APP_ENV=production|" .env
    sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|" .env
    sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=pgsql|" .env
    sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" .env
    sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" .env
    sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" .env

    # A conexão `central` cai para CENTRAL_DB_DATABASE, cujo padrão é
    # 'frota_central' — que NÃO é o banco criado acima. Sem esta linha, o
    # migrate do central tenta um banco inexistente.
    sed -i "s|^CENTRAL_DB_DATABASE=.*|CENTRAL_DB_DATABASE=${DB_NAME}|" .env
    grep -q '^CENTRAL_DB_DATABASE=' .env || echo "CENTRAL_DB_DATABASE=${DB_NAME}" >> .env

    sed -i "s|^PROVEDOR_DOMAIN=.*|PROVEDOR_DOMAIN=admin.${DOMINIO}|" .env

    # Sessão em cookie de host — cada tenant com o seu. Cookie de domínio pai
    # seria compartilhado entre clientes.
    sed -i "s|^SESSION_DOMAIN=.*|SESSION_DOMAIN=null|" .env

    php artisan key:generate --force
    echo "  .env criado. REVISE antes de usar em produção — SMTP, provedor fiscal, Pix."
else
    echo "  .env já existe — preservado."
fi

# O banco CENTRAL tem conexão e caminho próprios. `php artisan migrate` puro
# não roda nada: as migrations estão em subpastas (central/ e tenant/), e as
# de tenant só rodam dentro do banco de cada cliente. A 000050 cria as tabelas
# de framework (cache, fila, sessão) do central — sem elas o domínio central dá
# 500 com CACHE_STORE/SESSION_DRIVER=database.
php artisan migrate --force --database=central --path=database/migrations/central
php artisan storage:link || true

# Build de assets: com 8 GB o vite passa folgado. O custo aqui é CPU —
# em 2 núcleos o build concorre com o PHP-FPM dos dois apps, então evite
# rodar em horário de uso da homologação do PetroWeb.
log "Build de assets (npm)"
if [ "${PULAR_BUILD:-0}" = "1" ]; then
    echo "  PULAR_BUILD=1 — envie public/build pronto da sua máquina."
else
    npm ci
    NODE_OPTIONS="--max-old-space-size=3072" npm run build
fi

# ── 5. Permissões e SELinux ────────────────────────────────────────────────
log "Permissões e contextos SELinux"
chown -R "${APP_USER}:${APP_USER}" "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
find "${APP_DIR}/storage" -type d -exec chmod 775 {} \;
find "${APP_DIR}/storage" -type f -exec chmod 664 {} \;
chmod -R 775 "${APP_DIR}/bootstrap/cache"

semanage fcontext -a -t httpd_sys_rw_content_t "${APP_DIR}/storage(/.*)?"        2>/dev/null || true
semanage fcontext -a -t httpd_sys_rw_content_t "${APP_DIR}/bootstrap/cache(/.*)?" 2>/dev/null || true
restorecon -R "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
setsebool -P httpd_can_network_connect on

# storage/app/certificados guarda os .pfx — nunca pode ser servido pelo nginx.
mkdir -p "${APP_DIR}/storage/app/certificados"
chmod 700 "${APP_DIR}/storage/app/certificados"
chown "${APP_USER}:${APP_USER}" "${APP_DIR}/storage/app/certificados"

# ── 6. Pool PHP-FPM dedicado ───────────────────────────────────────────────
log "Pool PHP-FPM dedicado (${POOL})"
cat > "/etc/php-fpm.d/${POOL}.conf" <<FPM
; Pool dedicado ao PetroWeb Frota.
; Motivo: com pool compartilhado, uma rajada em um app esgota os filhos e
; derruba o outro. Cada produto tem o seu, com teto próprio.
[${POOL}]
user  = ${APP_USER}
group = ${APP_USER}
listen = /run/php-fpm/${POOL}.sock
listen.owner = ${APP_USER}
listen.group = ${APP_USER}
listen.mode  = 0660

; Dimensionado para 8 GB de RAM e 2 núcleos: memória sobra, CPU não.
; 12 filhos a 256M dão teto de ~3 GB, e ondemand só sobe o que for usado —
; o limite prático é a CPU, e ela é compartilhada com o pool do PetroWeb.
pm = ondemand
pm.max_children      = 12
pm.process_idle_timeout = 30s
pm.max_requests      = 500

php_admin_value[memory_limit]       = 256M
php_admin_value[upload_max_filesize] = 12M
php_admin_value[post_max_size]       = 14M
php_admin_value[error_log] = /var/log/php-fpm/${POOL}-error.log
php_admin_flag[log_errors] = on

php_value[session.save_handler] = files
php_value[session.save_path]    = /var/lib/php/session
FPM

systemctl restart php-fpm
echo "  Pool ativo em /run/php-fpm/${POOL}.sock"

# ── 7. nginx ───────────────────────────────────────────────────────────────
log "nginx"
cat > "/etc/nginx/conf.d/petroweb-frota.conf" <<NGINX
server {
    listen 80;
    listen [::]:80;
    # O domínio raiz responde o painel do provedor; cada cliente vive em
    # <slug>.${DOMINIO}. Sem o curinga aqui, o subdomínio do tenant cai no
    # server_name padrão do nginx e devolve a página de outro app.
    server_name ${DOMINIO} *.${DOMINIO};
    root ${APP_DIR}/public;

    index index.php;
    charset utf-8;

    client_max_body_size 14M;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php\$ {
        fastcgi_pass unix:/run/php-fpm/${POOL}.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 120s;
    }

    # Nada de dotfile servido — .env, .git e afins.
    location ~ /\.(?!well-known).* { deny all; }

    access_log /var/log/nginx/frota-access.log;
    error_log  /var/log/nginx/frota-error.log;
}
NGINX

nginx -t
systemctl reload nginx

# ── 8. HTTPS ───────────────────────────────────────────────────────────────
log "Certificado Let's Encrypt"
# ATENÇÃO: certificado CURINGA (*.dominio) exige desafio DNS-01, com token da
# API do provedor de DNS. O --nginx usa HTTP-01, que só emite para nomes
# exatos. Por isso emitimos para o domínio raiz MAIS os subdomínios de tenant
# informados — cada cliente novo precisa de uma linha a mais aqui.
NOMES=(-d "${DOMINIO}")

for SLUG in ${TENANTS_INICIAIS}; do
    NOMES+=(-d "${SLUG}.${DOMINIO}")
done

certbot --nginx "${NOMES[@]}" --non-interactive --agree-tos -m "${EMAIL_LE}" --redirect \
    --cert-name "${DOMINIO}"
systemctl enable --now certbot-renew.timer 2>/dev/null || true

echo "  Certificado emitido para: ${DOMINIO} ${TENANTS_INICIAIS:+e os subdomínios ${TENANTS_INICIAIS}}"
echo "  Para adicionar um cliente depois, reemita incluindo o novo nome:"
echo "    certbot --nginx --cert-name ${DOMINIO} -d ${DOMINIO} -d novo.${DOMINIO} --expand" 

# ── 9. Worker de fila ──────────────────────────────────────────────────────
log "Worker de fila (systemd)"
cat > /etc/systemd/system/frota-worker.service <<UNIT
[Unit]
Description=PetroWeb Frota — worker de fila
After=network.target postgresql.service
Requires=postgresql.service

[Service]
User=${APP_USER}
Group=${APP_USER}
WorkingDirectory=${APP_DIR}
# --tries=3 e backoff: a SEFAZ cai e devolve timeout; nenhuma transmissão
# pode morrer na primeira tentativa (RN-07 da documentação do produto).
ExecStart=/usr/bin/php ${APP_DIR}/artisan queue:work --queue=fiscal,default \\
          --sleep=3 --tries=3 --backoff=60,300,900 --max-time=3600 --timeout=180
Restart=always
RestartSec=5
StandardOutput=append:${APP_DIR}/storage/logs/worker.log
StandardError=append:${APP_DIR}/storage/logs/worker.log

[Install]
WantedBy=multi-user.target
UNIT

systemctl daemon-reload
systemctl enable --now frota-worker
systemctl status frota-worker --no-pager -l | head -12

# ── 10. Agendador ──────────────────────────────────────────────────────────
log "Agendador (cron)"
CRON_LINE="* * * * * cd ${APP_DIR} && /usr/bin/php artisan schedule:run >> /dev/null 2>&1"
( crontab -u "${APP_USER}" -l 2>/dev/null | grep -v "${APP_DIR}" ; echo "$CRON_LINE" ) | crontab -u "${APP_USER}" -
echo "  Agendador registrado para ${APP_USER}."

# ── 11. Caches ─────────────────────────────────────────────────────────────
log "Caches de produção"
cd "$APP_DIR"
sudo -u "${APP_USER}" php artisan config:cache
sudo -u "${APP_USER}" php artisan route:cache
sudo -u "${APP_USER}" php artisan view:cache

# ── Fim ────────────────────────────────────────────────────────────────────
cat <<FIM

════════════════════════════════════════════════════════════════════
  PetroWeb Frota provisionado.

  URL .............. https://${DOMINIO}
  Diretório ........ ${APP_DIR}
  Banco ............ ${DB_NAME}
  Credenciais ...... ${CRED}
  Pool PHP-FPM ..... ${POOL}  (/run/php-fpm/${POOL}.sock)
  Worker ........... systemctl status frota-worker
  Logs ............. ${APP_DIR}/storage/logs/
                     /var/log/nginx/frota-{access,error}.log

  PRÓXIMOS PASSOS — nenhum deles é opcional:

  1. Revise o .env: SMTP, provedor fiscal, ambiente da SEFAZ.
     O ambiente é atributo DA FILIAL, mas confira o padrão global.

  2. Crie o primeiro cliente (banco, migrations e catálogo padrão saem juntos):
       php artisan tenant:criar serraazul --nome="Transportes Serra Azul"

  3. Importe os municípios do IBGE dentro do tenant.
     Sem eles o endereço não salva e o CT-e não emite:
       php artisan tenants:run municipios:importar --tenants=serraazul

  4. Só para DEMONSTRAÇÃO — empresa, filial, usuários e clientes fictícios:
       php artisan demo:semear serraazul
     A filial nasce em homologação. Troque a senha padrão antes de qualquer
     uso real.

  5. Confira que o PetroWeb continua no ar:
       systemctl status php-fpm nginx petroweb-worker
        curl -sI https://homolog.petroweb.app | head -1

  6. Backup: inclua ${DB_NAME} e os bancos frota_<slug> no scripts/backup_producao.sh —
     o script atual só cobre o banco do PetroWeb.

  7. PostgreSQL: com 8 GB de RAM e agora DOIS bancos ativos, vale rever
     o postgresql.conf — o padrão do EL10 vem dimensionado para uma
     máquina pequena e não foi ajustado ao subir de 2 para 8 GB.
     Deliberadamente NÃO alterei nada: o Postgres é compartilhado com o
     PetroWeb, e mexer nele aqui mudaria o comportamento do outro produto.
     Sugestão para revisar com calma, fora de horário de uso:
         shared_buffers      = 2GB      (padrão 128MB)
         effective_cache_size = 5GB
         work_mem            = 16MB
         maintenance_work_mem = 512MB
         max_connections     = 100
     Confira o valor atual antes:
         sudo -u postgres psql -c "SHOW shared_buffers;"
════════════════════════════════════════════════════════════════════
FIM
