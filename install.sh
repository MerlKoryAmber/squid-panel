#!/bin/bash
# Squid Proxy Manager — Installation Script
# Target: CentOS 9 Stream / Rocky 9 / AlmaLinux 9
#
# Installs the web panel alongside an existing Squid installation.
# After import, formats /etc/squid/squid.conf for the panel (parse first).
# Does not systemctl restart Squid (reconfigure only).

set -e

SPM_DIR="/opt/spm"
WEB_USER="squidmgr"
SQUID_CONF="/etc/squid/squid.conf"
NGINX_SPM_CONF="/etc/nginx/conf.d/spm.conf"
PANEL_PORT="${PANEL_PORT:-8443}"
INSTALL_META="/etc/spm/install.env"

echo "=== Squid Proxy Manager Installer ==="
echo "This installer keeps a running Squid instance intact."
echo ""

if [ "$EUID" -ne 0 ]; then
    echo "ERROR: Please run as root"
    exit 1
fi

if ! grep -qE "CentOS Stream 9|CentOS Stream 10|Rocky Linux 9|AlmaLinux 9" /etc/os-release 2>/dev/null; then
    echo "WARNING: This script is designed for CentOS 9 Stream / Rocky 9 / AlmaLinux 9"
    read -p "Continue anyway? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

if ! command -v squid &>/dev/null; then
    echo "ERROR: Squid is not installed. Install and configure Squid first:"
    echo "  dnf install -y squid"
    echo "  systemctl enable --now squid"
    exit 1
fi

if [ ! -f "$SQUID_CONF" ] || [ ! -s "$SQUID_CONF" ]; then
    echo "ERROR: $SQUID_CONF is missing or empty."
    echo "Configure a working Squid instance before installing the panel."
    exit 1
fi

SQUID_VERSION=$(squid -v 2>/dev/null | head -1 | grep -oP 'Version \K[0-9.]+' || echo "unknown")
SQUID_STATUS=$(systemctl is-active squid 2>/dev/null || echo "unknown")
echo "Detected Squid version: $SQUID_VERSION"
echo "Squid service status:   $SQUID_STATUS"
if [ "$SQUID_STATUS" != "active" ]; then
    echo "WARNING: Squid is not running. The panel will still import squid.conf and will not start Squid."
fi
echo ""

# DEV: no password prompt. Login admin / admin. Turn off before production.
GENERATED_ADMIN_PASSWORD=0
PRINT_ADMIN_PASSWORD="admin"
ADMIN_PASSWORD="admin"
echo "DEV: panel login admin / admin (no password prompt)."
echo ""

echo "[1/9] Installing dependencies..."
dnf install -y -q epel-release 2>/dev/null || true
dnf install -y nginx php php-fpm php-pdo php-sqlite3 python3 samba-winbind krb5-workstation openldap-clients sudo tar policycoreutils-python-utils acl openssl
dnf install -y php-json php-mbstring php-xml 2>/dev/null || true

echo "[2/9] Creating system user..."
if ! id "$WEB_USER" &>/dev/null; then
    if getent group "$WEB_USER" &>/dev/null; then
        useradd -r -s /sbin/nologin -d "$SPM_DIR" -M -g "$WEB_USER" "$WEB_USER"
    else
        useradd -r -s /sbin/nologin -d "$SPM_DIR" -M "$WEB_USER"
    fi
fi
usermod -aG squid "$WEB_USER" 2>/dev/null || true
# nginx talks to php-fpm via listen.owner=nginx — do not add nginx to squidmgr
# (that group can reach /run/spmd.sock).
if id nginx &>/dev/null && id -nG nginx 2>/dev/null | grep -qw "$WEB_USER"; then
    gpasswd -d nginx "$WEB_USER" 2>/dev/null || true
    echo "Removed nginx from group $WEB_USER (spmd socket must not be reachable by nginx)."
fi

echo "[3/9] Setting up SPM directory..."
SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
mkdir -p "$SPM_DIR" "$SPM_DIR/database" "$SPM_DIR/storage"

if [ "${SPM_DROP_DB:-}" = "1" ]; then
    echo "Dropping panel database (SPM_DROP_DB=1)."
    rm -f "$SPM_DIR/database/spm.db"
fi

HAD_EXISTING_DB=0
PRESERVED_DB=""
if [ -f "$SPM_DIR/database/spm.db" ]; then
    HAD_EXISTING_DB=1
    PRESERVED_DB=$(mktemp /tmp/spm.db.XXXXXX)
    cp -a "$SPM_DIR/database/spm.db" "$PRESERVED_DB"
    echo "Existing SPM database will be preserved."
fi

if [ "$SCRIPT_DIR" != "$SPM_DIR" ]; then
    echo "Copying files from $SCRIPT_DIR to $SPM_DIR ..."
    find "$SPM_DIR" -mindepth 1 -maxdepth 1 ! -name database ! -name storage -exec rm -rf {} +
    cp -a "$SCRIPT_DIR"/. "$SPM_DIR/"
else
    echo "Installer already in $SPM_DIR, skipping copy"
fi

if [ ! -f "$SPM_DIR/public/index.php" ]; then
    echo "ERROR: $SPM_DIR/public/index.php missing after copy from $SCRIPT_DIR"
    exit 1
fi

mkdir -p "$SPM_DIR/storage/logs" "$SPM_DIR/storage/tmp" "$SPM_DIR/storage/acl" "$SPM_DIR/database" "$SPM_DIR/views/users"

if [ "$HAD_EXISTING_DB" = "1" ] && [ -f "$PRESERVED_DB" ]; then
    mkdir -p "$SPM_DIR/database"
    cp -a "$PRESERVED_DB" "$SPM_DIR/database/spm.db"
    rm -f "$PRESERVED_DB"
fi

chown -R "$WEB_USER:$WEB_USER" "$SPM_DIR"
# nginx must traverse /opt/spm to serve /public, but must not be in group squidmgr (spmd socket).
chmod 751 "$SPM_DIR"
chmod 750 "$SPM_DIR/database"
chmod 700 "$SPM_DIR/storage"
chmod 700 "$SPM_DIR/storage/logs"
chmod 700 "$SPM_DIR/storage/tmp"
chmod 700 "$SPM_DIR/storage/acl"
mkdir -p /etc/squid/acl.d
chmod 755 /etc/squid/acl.d
SRC_KT="/etc/krb5.keytab"
DST_KT="/etc/squid/krb5.keytab"
if [ -f "$DST_KT" ]; then
    echo "Keytab $DST_KT already present, not overwritten."
elif [ -f "$SRC_KT" ]; then
    cp -a "$SRC_KT" "$DST_KT"
    if id squid &>/dev/null; then
        chown squid:squid "$DST_KT"
    fi
    chmod 640 "$DST_KT"
    echo "Copied $SRC_KT -> $DST_KT (mode 640, owner squid). Live squid.conf was not changed."
else
    echo "No $SRC_KT; upload a keytab in the panel to $DST_KT."
fi
chmod 750 "$SPM_DIR/agent" 2>/dev/null || true
chmod 750 "$SPM_DIR/app" "$SPM_DIR/config" "$SPM_DIR/views" 2>/dev/null || true
chmod 755 "$SPM_DIR/public"
find "$SPM_DIR/public" -type d -exec chmod 755 {} +
find "$SPM_DIR/public" -type f -exec chmod 644 {} +
chmod 644 "$SPM_DIR/agent/sudoers.spm" 2>/dev/null || true
setfacl -m "u:nginx:x" "$SPM_DIR" 2>/dev/null || true
setfacl -R -m "u:nginx:rX" "$SPM_DIR/public" 2>/dev/null || true

echo "[4/9] Configuring Nginx on port $PANEL_PORT (does not bind :80/:443)..."
mkdir -p /etc/nginx/conf.d
if [ ! -f /etc/nginx/conf.d/spm-allow.inc ]; then
    echo "# SPM panel IP allowlist. Empty = all IPs." > /etc/nginx/conf.d/spm-allow.inc
    chmod 644 /etc/nginx/conf.d/spm-allow.inc
fi

SERVER_IP=$(hostname -I 2>/dev/null | awk '{print $1}')
if [ -z "$SERVER_IP" ]; then
    SERVER_IP="127.0.0.1"
fi

cat > "$NGINX_SPM_CONF" << EOF
server {
    listen ${PANEL_PORT} ssl;
    server_name _;

    ssl_certificate /etc/pki/tls/certs/spm-selfsigned.crt;
    ssl_certificate_key /etc/pki/tls/private/spm-selfsigned.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    root /opt/spm/public;
    index index.php;

    include /etc/nginx/conf.d/spm-allow.inc;

    location / {
        try_files \$uri \$uri/ @front;
    }

    location @front {
        include fastcgi_params;
        fastcgi_pass unix:/run/php-fpm/spm.sock;
        fastcgi_param SCRIPT_FILENAME /opt/spm/public/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
        fastcgi_read_timeout 300;
    }

    location ~ \\.php\$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php-fpm/spm.sock;
        fastcgi_param SCRIPT_FILENAME /opt/spm/public/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
        fastcgi_read_timeout 300;
    }

    location ~ /\\.(?!well-known).* {
        deny all;
    }

    location ~* \\.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)\$ {
        expires 1h;
        add_header Cache-Control "public, max-age=3600";
    }
}
EOF

if [ ! -f /etc/nginx/fastcgi_params ]; then
    echo "ERROR: /etc/nginx/fastcgi_params missing"
    exit 1
fi

if ! grep -q 'location @front' "$NGINX_SPM_CONF"; then
    echo "ERROR: $NGINX_SPM_CONF has no @front location"
    exit 1
fi

mkdir -p /etc/pki/tls/certs /etc/pki/tls/private
if [ ! -f /etc/pki/tls/certs/spm-selfsigned.crt ]; then
    echo "Generating self-signed SSL certificate..."
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout /etc/pki/tls/private/spm-selfsigned.key \
        -out /etc/pki/tls/certs/spm-selfsigned.crt \
        -subj "/C=RU/O=SPM/CN=$SERVER_IP"
    chmod 600 /etc/pki/tls/private/spm-selfsigned.key
    chmod 644 /etc/pki/tls/certs/spm-selfsigned.crt
fi

echo "[5/9] Configuring PHP-FPM..."
mkdir -p /etc/php-fpm.d /run/php-fpm
chown root:root /run/php-fpm
chmod 755 /run/php-fpm

cat > /etc/php-fpm.d/spm.conf << 'EOF'
[spm]
user = squidmgr
group = squidmgr
listen = /run/php-fpm/spm.sock
listen.owner = nginx
listen.group = nginx
listen.mode = 0660
pm = dynamic
pm.max_children = 20
pm.start_servers = 3
pm.min_spare_servers = 2
pm.max_spare_servers = 5
pm.max_requests = 500
php_admin_value[open_basedir] = /opt/spm:/tmp:/var/log/squid:/etc/squid:/run:/proc:/etc/krb5.conf:/var/lib/php
php_admin_value[session.save_path] = /opt/spm/storage/tmp
php_admin_value[disable_functions] = exec,passthru,system,curl_exec,curl_multi_exec,parse_ini_file,show_source,proc_open,popen
php_admin_value[upload_max_filesize] = 10M
php_admin_value[post_max_size] = 10M
php_admin_value[max_execution_time] = 300
php_admin_value[memory_limit] = 256M
EOF

echo "[6/9] Installing privileged agent and restricted sudoers..."
cp "$SPM_DIR/agent/spmd.service" /etc/systemd/system/spmd.service
systemctl daemon-reload
systemctl enable spmd
systemctl restart spmd || echo "WARNING: spmd failed to start, sudo fallback will be used"

cp "$SPM_DIR/agent/sudoers.spm" /etc/sudoers.d/spm
chmod 440 /etc/sudoers.d/spm
if ! visudo -c -f /etc/sudoers.d/spm &>/dev/null; then
    echo "ERROR: sudoers syntax check failed. Refusing to leave an invalid /etc/sudoers.d/spm."
    rm -f /etc/sudoers.d/spm
    exit 1
fi
echo "Sudoers installed: kinit is limited to /etc/squid/*.keytab."

if [ -S /run/spmd.sock ]; then
    chown root:"$WEB_USER" /run/spmd.sock 2>/dev/null || true
    chmod 660 /run/spmd.sock
fi
if [ -f "$SPM_DIR/agent/selinux/apply.sh" ]; then
    chmod 755 "$SPM_DIR/agent/selinux/apply.sh"
    bash "$SPM_DIR/agent/selinux/apply.sh" "$SPM_DIR/agent/selinux/spm.te" || echo "WARNING: SELinux policy not applied"
fi

echo "[7/9] Granting the panel read access to Squid files..."
TS=$(date +%Y%m%d%H%M%S)
cp -a "$SQUID_CONF" "${SQUID_CONF}.spm-install-${TS}"
echo "Copied squid.conf to ${SQUID_CONF}.spm-install-${TS}"

setfacl -m "u:${WEB_USER}:rx" /etc/squid 2>/dev/null || true
setfacl -m "u:${WEB_USER}:r" "$SQUID_CONF" 2>/dev/null || chmod o+r "$SQUID_CONF"

mkdir -p /var/log/squid
setfacl -m "u:${WEB_USER}:rx" /var/log/squid 2>/dev/null || true
setfacl -d -m "u:${WEB_USER}:r" /var/log/squid 2>/dev/null || true
if ls /var/log/squid/*.log >/dev/null 2>&1; then
    setfacl -m "u:${WEB_USER}:r" /var/log/squid/*.log 2>/dev/null || true
fi

if command -v semanage &>/dev/null; then
    semanage fcontext -a -t httpd_sys_content_t "$SPM_DIR(/.*)?" 2>/dev/null || true
    semanage fcontext -a -t httpd_sys_rw_content_t "$SPM_DIR/database(/.*)?" 2>/dev/null || true
    semanage fcontext -a -t httpd_sys_rw_content_t "$SPM_DIR/storage(/.*)?" 2>/dev/null || true
    restorecon -Rv "$SPM_DIR" 2>/dev/null || true
    setsebool -P httpd_can_network_connect 1 2>/dev/null || true
fi

echo "[8/9] Importing existing squid.conf and formatting it for the panel..."
if [ "$HAD_EXISTING_DB" = "1" ]; then
    echo "Skipped re-import because an existing SPM database was preserved."
else
    if php "$SPM_DIR/install/import.php"; then
        echo "Imported live Squid configuration into spm.db."
    else
        echo "ERROR: Import failed. Live squid.conf was not modified."
        exit 1
    fi
fi

if php "$SPM_DIR/install/format_live.php"; then
    echo "Live squid.conf replaced after squid -k parse."
    echo "Rollback copies: ${SQUID_CONF}.spm-install-${TS} and ${SQUID_CONF}.spm-lab-baseline (if present)."
    chown "$WEB_USER:$WEB_USER" "$SPM_DIR/storage/tmp/squid.conf.parse" 2>/dev/null || true
    chmod 600 "$SPM_DIR/storage/tmp/squid.conf.parse" 2>/dev/null || true
else
    echo "ERROR: format/parse failed. Restoring ${SQUID_CONF}.spm-install-${TS}"
    cp -a "${SQUID_CONF}.spm-install-${TS}" "$SQUID_CONF"
    exit 1
fi

export SPM_ADMIN_PASSWORD="$ADMIN_PASSWORD"
php "$SPM_DIR/install/set_admin_password.php"
unset SPM_ADMIN_PASSWORD
ADMIN_PASSWORD=""

if [ -f "$SPM_DIR/database/spm.db" ]; then
    chown "$WEB_USER:$WEB_USER" "$SPM_DIR/database/spm.db"
    chmod 660 "$SPM_DIR/database/spm.db"
fi

echo "[9/9] Starting panel services (Squid will not be restarted)..."
PHP_FPM_SERVICE="php-fpm"
if systemctl list-unit-files | grep -qE '^php[0-9]+\.[0-9]+-fpm\.service'; then
    PHP_FPM_SERVICE=$(systemctl list-unit-files | grep -E '^php[0-9]+\.[0-9]+-fpm\.service' | head -1 | awk '{print $1}' | sed 's/\.service$//')
fi

if systemctl list-unit-files | grep -q '^nginx.service'; then
    systemctl enable nginx 2>/dev/null || true
    echo "nginx -t:"
    nginx -t
    systemctl enable nginx 2>/dev/null || true
    systemctl restart nginx
fi

if systemctl list-unit-files | grep -q "^${PHP_FPM_SERVICE}.service"; then
    systemctl enable "$PHP_FPM_SERVICE" 2>/dev/null || true
    systemctl restart "$PHP_FPM_SERVICE"
fi

FIREWALL_OPENED=0
if command -v firewall-cmd &>/dev/null && firewall-cmd --state &>/dev/null; then
    if firewall-cmd --permanent --add-port="${PANEL_PORT}/tcp"; then
        firewall-cmd --reload || true
        FIREWALL_OPENED=1
        echo "Opened firewall port ${PANEL_PORT}/tcp"
    fi
fi

mkdir -p /etc/spm
cat > "$INSTALL_META" << EOF
PANEL_PORT=${PANEL_PORT}
FIREWALL_OPENED=${FIREWALL_OPENED}
SQUID_CONF_BACKUP=${SQUID_CONF}.spm-install-${TS}
INSTALLED_AT=${TS}
EOF
chmod 600 "$INSTALL_META"

sleep 1
NGINX_STATUS=$(systemctl is-active nginx 2>/dev/null || echo "unknown")
PHPFPM_STATUS=$(systemctl is-active "$PHP_FPM_SERVICE" 2>/dev/null || echo "unknown")
SPMD_STATUS=$(systemctl is-active spmd 2>/dev/null || echo "unknown")
SQUID_STATUS_AFTER=$(systemctl is-active squid 2>/dev/null || echo "unknown")

echo ""
echo "=== Installation Complete ==="
echo ""
echo "Service Status:"
echo "  Squid:    $SQUID_STATUS_AFTER  (not restarted by this installer)"
echo "  Nginx:    $NGINX_STATUS"
echo "  PHP-FPM:  $PHPFPM_STATUS"
echo "  spmd:     $SPMD_STATUS"
echo ""
echo "Panel URL:"
echo "  https://${SERVER_IP}:${PANEL_PORT}/"
echo ""
echo "Credentials:"
echo "  Username: admin"
echo "  Password: admin"
echo "  DEV default — change before production."
echo ""
echo "Sudoers: kinit may use only /etc/squid/*.keytab"
if [ -f /etc/squid/krb5.keytab ]; then
    echo "Panel keytab copy: /etc/squid/krb5.keytab (live helper path in squid.conf was not edited)."
fi
echo "Live squid.conf was formatted for the panel after parse. Copies:"
echo "  ${SQUID_CONF}.spm-install-${TS}"
echo "  ${SQUID_CONF}.spm-lab-baseline  (lab rollback, if copied)"
echo ""
echo "To uninstall the panel only (Squid stays):"
echo "  $SPM_DIR/uninstall.sh"
echo ""
