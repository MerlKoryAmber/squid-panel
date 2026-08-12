#!/bin/bash
# Squid Proxy Manager — Installation Script
# Target: CentOS 9 Stream
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
if ! grep -q "CentOS Stream 9\|CentOS Stream 10\|Rocky Linux 9\|AlmaLinux 9" /etc/os-release 2>/dev/null; then
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
dnf install -y -y nginx php php-fpm php-pdo php-sqlite3 python3 samba-winbind krb5-workstation sudo tar policycoreutils-python-utils

# Install PHP extensions if available
dnf install -y php-json php-mbstring php-xml 2>/dev/null || true

echo "[2/9] Creating system user..."
if ! id "$WEB_USER" &>/dev/null; then
    useradd -r -s /sbin/nologin -d "$SPM_DIR" -M "$WEB_USER"
fi
usermod -aG squid "$WEB_USER" 2>/dev/null || true

echo "[3/9] Setting up SPM directory..."
mkdir -p "$SPM_DIR"
rm -rf "$SPM_DIR"/*
cp -r . "$SPM_DIR/"

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

cat > "$NGINX_SPM_CONF" << EOF
server {
    listen 443 ssl http2;
    server_name _;

    ssl_certificate /etc/pki/tls/certs/spm-selfsigned.crt;
    ssl_certificate_key /etc/pki/tls/private/spm-selfsigned.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    root $SPM_DIR/public;
    index index.php;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:/run/php-fpm/spm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)\$ {
        expires 1M;
        add_header Cache-Control "public, immutable";
    }
}

server {
    listen 80;
    server_name _;
    return 301 https://\$host\$request_uri;
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
        -subj "/C=RU/O=SPM/CN=$SERVER_IP" 2>/dev/null
    chmod 600 /etc/pki/tls/private/spm-selfsigned.key
    chmod 644 /etc/pki/tls/certs/spm-selfsigned.crt
fi

echo "[5/9] Configuring PHP-FPM..."
mkdir -p /etc/php-fpm.d /run/php-fpm

# Detect PHP version
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;")
PHP_INI="/etc/php.ini"
PHP_FPM_DIR="/etc/php-fpm.d"

# Backup existing php-fpm config
cp "$PHP_INI" "$PHP_INI.bak.$(date +%Y%m%d%H%M%S)" 2>/dev/null || true

cat > "$PHP_FPM_DIR/spm.conf" << EOF
[spm]
user = $WEB_USER
group = $WEB_USER
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
php_admin_value[open_basedir] = $SPM_DIR:/tmp:/var/log/squid:/etc/squid
php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source
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
if ! visudo -c &>/dev/null; then
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
    restorecon -Rv "$SPM_DIR" 2>/dev/null || true
    setsebool -P httpd_can_network_connect 1 2>/dev/null || true
fi

echo "[8/9] Parsing existing squid.conf..."
if [ -f "$SQUID_CONF" ] && [ -s "$SQUID_CONF" ]; then
    php -r "
        define('SPM_ROOT', '$SPM_DIR');
        define('SPM_STORAGE', '$SPM_DIR/storage');
        define('SPM_CONFIG', '$SPM_DIR/config');
        define('DB_PATH', '$SPM_DIR/database/spm.db');
        define('SQUID_CONF', '$SQUID_CONF');
        define('SQUID_CONF_DIR', '/etc/squid');
        define('SQUID_LOG_DIR', '/var/log/squid');
        define('SQUID_ACCESS_LOG', '/var/log/squid/access.log');
        define('SQUID_CACHE_LOG', '/var/log/squid/cache.log');
        define('SQUID_BINARY', '/usr/sbin/squid');
        define('AGENT_SOCKET', '/run/spmd.sock');
        define('AGENT_ENABLED', true);
        define('SESSION_LIFETIME', 3600);
        define('CSRF_TOKEN_NAME', 'spm_csrf_token');
        define('AUDIT_RETENTION_DAYS', 30);
        define('DEFAULT_LANG', 'ru');

        require_once '$SPM_DIR/app/Core/Database.php';
        require_once '$SPM_DIR/app/Services/SquidConfigParser.php';

        Database::init();

        echo "Importing configuration from $SQUID_CONF...\n";
        \$result = SquidConfigParser::parseAndImport('$SQUID_CONF');

        if (\$result['success']) {
            echo "Import successful!\n";
            foreach (\$result['stats'] as \$key => \$count) {
                echo "  - \$key: \$count\n";
            }
        } else {
            echo "WARNING: Import failed: " . \$result['error'] . "\n";
        }
    "
else
    echo "No existing squid.conf found or file is empty. Starting with default configuration."

    # Insert minimal defaults
    php -r "
        define('SPM_ROOT', '$SPM_DIR');
        define('SPM_STORAGE', '$SPM_DIR/storage');
        define('SPM_CONFIG', '$SPM_DIR/config');
        define('DB_PATH', '$SPM_DIR/database/spm.db');
        define('SQUID_CONF', '$SQUID_CONF');
        define('SQUID_CONF_DIR', '/etc/squid');
        define('SQUID_LOG_DIR', '/var/log/squid');
        define('SQUID_ACCESS_LOG', '/var/log/squid/access.log');
        define('SQUID_CACHE_LOG', '/var/log/squid/cache.log');
        define('SQUID_BINARY', '/usr/sbin/squid');
        define('AGENT_SOCKET', '/run/spmd.sock');
        define('AGENT_ENABLED', true);
        define('SESSION_LIFETIME', 3600);
        define('CSRF_TOKEN_NAME', 'spm_csrf_token');
        define('AUDIT_RETENTION_DAYS', 30);
        define('DEFAULT_LANG', 'ru');

        require_once '$SPM_DIR/app/Core/Database.php';
        Database::init();

        Database::query("INSERT OR IGNORE INTO squid_globals (http_port, icp_port, cache_dir) VALUES ('3128', '3130', 'ufs /var/spool/squid 100 16 256')");
        Database::query("INSERT OR IGNORE INTO settings (language, theme) VALUES ('ru', 'light')");
    "
fi

echo ""
echo "[9/9] Starting services..."
systemctl enable nginx php-fpm 2>/dev/null || true
systemctl restart nginx php-fpm

# Check services
sleep 2
NGINX_STATUS=$(systemctl is-active nginx 2>/dev/null || echo "unknown")
PHPFPM_STATUS=$(systemctl is-active php-fpm 2>/dev/null || echo "unknown")
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
