#!/bin/bash
# Squid Proxy Manager — Installation Script
# Target: CentOS 9 Stream / Rocky 9 / AlmaLinux 9
# Squid must be installed separately before running this script

set -e

SPM_DIR="/opt/spm"
WEB_USER="squidmgr"
SQUID_CONF="/etc/squid/squid.conf"
NGINX_SPM_CONF="/etc/nginx/conf.d/spm.conf"

echo "=== Squid Proxy Manager Installer ==="
echo ""

# Check root
if [ "$EUID" -ne 0 ]; then
    echo "ERROR: Please run as root"
    exit 1
fi

# Check OS
if ! grep -qE "CentOS Stream 9|CentOS Stream 10|Rocky Linux 9|AlmaLinux 9" /etc/os-release 2>/dev/null; then
    echo "WARNING: This script is designed for CentOS 9 Stream / Rocky 9 / AlmaLinux 9"
    read -p "Continue anyway? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Check if Squid is installed
if ! command -v squid &> /dev/null; then
    echo "ERROR: Squid is not installed. Please install Squid first:"
    echo "  dnf install -y squid"
    echo "  systemctl enable squid"
    exit 1
fi

SQUID_VERSION=$(squid -v 2>/dev/null | head -1 | grep -oP 'Version \K[0-9.]+' || echo "unknown")
echo "Detected Squid version: $SQUID_VERSION"

echo ""
echo "[1/9] Installing dependencies..."
dnf install -y -q epel-release 2>/dev/null || true
dnf install -y nginx php php-fpm php-pdo php-sqlite3 python3 samba-winbind krb5-workstation sudo tar policycoreutils-python-utils acl

# Install PHP extensions if available
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

# Add nginx to web user group so it can read /opt/spm (chmod 750)
if id nginx &>/dev/null; then
    usermod -aG "$WEB_USER" nginx 2>/dev/null || true
fi

echo "[3/9] Setting up SPM directory..."
SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)

mkdir -p "$SPM_DIR"

# If script is not already in SPM_DIR, copy everything over
if [ "$SCRIPT_DIR" != "$SPM_DIR" ]; then
    echo "Copying files from $SCRIPT_DIR to $SPM_DIR ..."
    rm -rf "$SPM_DIR"/*
    cp -r "$SCRIPT_DIR"/* "$SPM_DIR/" 2>/dev/null || true
    # Copy hidden files (.*) if any
    for f in "$SCRIPT_DIR"/.*; do
        [ -e "$f" ] && [ "$(basename "$f")" != "." ] && [ "$(basename "$f")" != ".." ] && cp -r "$f" "$SPM_DIR/" 2>/dev/null || true
    done
else
    echo "Installer already in $SPM_DIR, skipping copy"
fi

# Ensure all required directories exist
mkdir -p "$SPM_DIR/storage"
mkdir -p "$SPM_DIR/storage/backups"
mkdir -p "$SPM_DIR/storage/logs"
mkdir -p "$SPM_DIR/storage/tmp"
mkdir -p "$SPM_DIR/database"
mkdir -p "$SPM_DIR/views/backup"
mkdir -p "$SPM_DIR/views/users"

# Set permissions
chown -R "$WEB_USER:$WEB_USER" "$SPM_DIR"
chmod 750 "$SPM_DIR"
chmod 750 "$SPM_DIR/database"
chmod 700 "$SPM_DIR/storage"
chmod 700 "$SPM_DIR/storage/backups"
chmod 700 "$SPM_DIR/storage/logs"
chmod 700 "$SPM_DIR/storage/tmp"
chmod 755 "$SPM_DIR/public"
chmod 644 "$SPM_DIR/public/.htaccess" 2>/dev/null || true

echo "[4/9] Configuring Nginx..."
mkdir -p /etc/nginx/conf.d

# Detect server IP for self-signed cert
SERVER_IP=$(hostname -I | awk '{print $1}')

cat > "$NGINX_SPM_CONF" << 'EOF'
server {
    listen 443 ssl http2 default_server;
    server_name _;

    ssl_certificate /etc/pki/tls/certs/spm-selfsigned.crt;
    ssl_certificate_key /etc/pki/tls/private/spm-selfsigned.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    root /opt/spm/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php-fpm/spm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1M;
        add_header Cache-Control "public, immutable";
    }
}

server {
    listen 80 default_server;
    server_name _;
    return 301 https://$host$request_uri;
}
EOF

# Remove default nginx welcome page
rm -f /etc/nginx/conf.d/default.conf 2>/dev/null || true
rm -f /etc/nginx/conf.d/example_ssl.conf 2>/dev/null || true

# Generate self-signed certificate
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

# Verify certificates exist
if [ ! -f /etc/pki/tls/certs/spm-selfsigned.crt ] || [ ! -f /etc/pki/tls/private/spm-selfsigned.key ]; then
    echo "WARNING: Self-signed SSL certificate generation failed or files are missing."
    echo "         HTTPS will not work until certificates are created manually."
fi

echo "[5/9] Configuring PHP-FPM..."
mkdir -p /etc/php-fpm.d /run/php-fpm
chown root:root /run/php-fpm
chmod 755 /run/php-fpm

# Backup existing php.ini
cp /etc/php.ini "/etc/php.ini.bak.$(date +%Y%m%d%H%M%S)" 2>/dev/null || true

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
php_admin_value[open_basedir] = /opt/spm:/tmp:/var/log/squid:/etc/squid:/run
php_admin_value[disable_functions] = exec,passthru,passthru,system,curl_exec,curl_multi_exec,parse_ini_file,show_source
php_admin_value[upload_max_filesize] = 10M
php_admin_value[post_max_size] = 10M
php_admin_value[max_execution_time] = 300
php_admin_value[memory_limit] = 256M
EOF

echo "[6/9] Installing privileged agent..."
cp "$SPM_DIR/agent/spmd.service" /etc/systemd/system/spmd.service
systemctl daemon-reload
systemctl enable spmd
systemctl start spmd || echo "WARNING: spmd failed to start, sudo fallback will be used"

# Setup sudo fallback
cp "$SPM_DIR/agent/sudoers.spm" /etc/sudoers.d/spm
chmod 440 /etc/sudoers.d/spm
if ! visudo -c -f /etc/sudoers.d/spm &>/dev/null; then
    echo "WARNING: sudoers syntax check failed. Removing spm sudoers file."
    rm -f /etc/sudoers.d/spm
fi

echo "[7/9] Setting up Squid permissions..."
if [ ! -f "$SQUID_CONF" ]; then
    touch "$SQUID_CONF"
    echo "# Empty config created by SPM installer" > "$SQUID_CONF"
fi

chown root:squid "$SQUID_CONF"
chmod 644 "$SQUID_CONF"

# Ensure log directory exists and is readable by web user
mkdir -p /var/log/squid
chown squid:squid /var/log/squid
chmod 755 /var/log/squid

# Allow web user to read squid logs
setfacl -m u:$WEB_USER:rx /var/log/squid 2>/dev/null || true
setfacl -m u:$WEB_USER:r /var/log/squid/access.log 2>/dev/null || true
setfacl -m u:$WEB_USER:r /var/log/squid/cache.log 2>/dev/null || true

# SELinux context (if enabled)
if command -v semanage &>/dev/null; then
    semanage fcontext -a -t httpd_sys_content_t "$SPM_DIR(/.*)?" 2>/dev/null || true
    semanage fcontext -a -t httpd_sys_rw_content_t "$SPM_DIR/database(/.*)?" 2>/dev/null || true
    semanage fcontext -a -t httpd_sys_rw_content_t "$SPM_DIR/storage(/.*)?" 2>/dev/null || true
    restorecon -Rv "$SPM_DIR" 2>/dev/null || true
    setsebool -P httpd_can_network_connect 1 2>/dev/null || true
fi

echo "[8/9] Parsing existing squid.conf..."
if [ -f "$SQUID_CONF" ] && [ -s "$SQUID_CONF" ]; then
    php "$SPM_DIR/install/import.php"
else
    echo "No existing squid.conf found or file is empty. Starting with default configuration."
    php "$SPM_DIR/install/defaults.php"
fi

# Fix database ownership — import.php runs as root, so spm.db may be root-owned
if [ -f "$SPM_DIR/database/spm.db" ]; then
    chown "$WEB_USER:$WEB_USER" "$SPM_DIR/database/spm.db"
    chmod 660 "$SPM_DIR/database/spm.db"
fi

echo ""
echo "[9/9] Starting services..."

# Detect correct php-fpm service name
PHP_FPM_SERVICE="php-fpm"
if systemctl list-unit-files | grep -qE '^php[0-9]+\.[0-9]+-fpm\.service'; then
    PHP_FPM_SERVICE=$(systemctl list-unit-files | grep -E '^php[0-9]+\.[0-9]+-fpm\.service' | head -1 | awk '{print $1}' | sed 's/\.service$//')
fi

# Enable services only if unit files exist
if systemctl list-unit-files | grep -q '^nginx.service'; then
    systemctl enable nginx 2>/dev/null || true
else
    echo "WARNING: nginx.service not found. Nginx may not be installed or systemd is unavailable."
fi

if systemctl list-unit-files | grep -q "^${PHP_FPM_SERVICE}.service"; then
    systemctl enable "$PHP_FPM_SERVICE" 2>/dev/null || true
else
    echo "WARNING: ${PHP_FPM_SERVICE}.service not found. PHP-FPM may not be installed."
fi

# Restart services only if unit files exist
if systemctl list-unit-files | grep -q '^nginx.service'; then
    NGINX_TEST_OUTPUT=$(nginx -t 2>&1)
    if [ $? -eq 0 ]; then
        systemctl restart nginx
    else
        echo "WARNING: Nginx config test failed."
        echo "Details:"
        echo "$NGINX_TEST_OUTPUT"
        echo "Please check /etc/nginx/conf.d/spm.conf and /etc/nginx/nginx.conf"
    fi
else
    echo "WARNING: Skipping nginx restart — nginx.service not found."
fi

if systemctl list-unit-files | grep -q "^${PHP_FPM_SERVICE}.service"; then
    systemctl restart "$PHP_FPM_SERVICE"
else
    echo "WARNING: Skipping PHP-FPM restart — ${PHP_FPM_SERVICE}.service not found."
fi

# Check services
sleep 2
NGINX_STATUS=$(systemctl is-active nginx 2>/dev/null || echo "unknown")
PHPFPM_STATUS=$(systemctl is-active "$PHP_FPM_SERVICE" 2>/dev/null || systemctl is-active php-fpm 2>/dev/null || echo "unknown")
SPMD_STATUS=$(systemctl is-active spmd 2>/dev/null || echo "unknown")

echo ""
echo "=== Installation Complete ==="
echo ""
echo "Service Status:"
echo "  Nginx:    $NGINX_STATUS"
echo "  PHP-FPM:  $PHPFPM_STATUS"
echo "  spmd:     $SPMD_STATUS"
echo ""
echo "SPM is available at:"
echo "  https://$SERVER_IP/"
echo ""
echo "Default credentials:"
echo "  Username: admin"
echo "  Password: admin"
echo ""
echo "IMPORTANT: Change the default password immediately after first login!"
echo ""
echo "To uninstall SPM, run:"
echo "  $SPM_DIR/uninstall.sh"
echo ""
