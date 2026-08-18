#!/bin/bash
# Squid Proxy Manager — Uninstallation Script
# Removes the web panel and leaves Squid running with its current squid.conf.

set -e

SPM_DIR="/opt/spm"
WEB_USER="squidmgr"
NGINX_SPM_CONF="/etc/nginx/conf.d/spm.conf"
SQUID_CONF="/etc/squid/squid.conf"
INSTALL_META="/etc/spm/install.env"

PANEL_PORT="8443"
FIREWALL_OPENED=0
if [ -f "$INSTALL_META" ]; then
    # shellcheck disable=SC1090
    . "$INSTALL_META"
fi

echo "=== Squid Proxy Manager Uninstaller ==="
echo ""

if [ "$EUID" -ne 0 ]; then
    echo "ERROR: Please run as root"
    exit 1
fi

echo "This will remove Squid Proxy Manager only:"
echo "  - Web panel and database under $SPM_DIR"
echo "  - Nginx vhost $NGINX_SPM_CONF"
echo "  - PHP-FPM pool /etc/php-fpm.d/spm.conf"
echo "  - Privileged agent (spmd)"
echo "  - Restricted sudo rules /etc/sudoers.d/spm"
echo ""
echo "Squid will NOT be stopped, removed, or reconfigured."
echo "Current $SQUID_CONF will be left as-is."
echo ""
read -p "Uninstall SPM? (yes/no): " CONFIRM
if [ "$CONFIRM" != "yes" ]; then
    echo "Uninstallation cancelled."
    exit 0
fi

echo ""
echo "[1/7] Stopping SPM agent..."
systemctl stop spmd 2>/dev/null || true
systemctl disable spmd 2>/dev/null || true

echo "[2/7] Removing systemd unit..."
rm -f /etc/systemd/system/spmd.service
systemctl daemon-reload 2>/dev/null || true

echo "[3/7] Removing Nginx vhost for the panel..."
rm -f "$NGINX_SPM_CONF"
if systemctl list-unit-files | grep -q '^nginx.service'; then
    if systemctl is-active nginx &>/dev/null; then
        if nginx -t 2>/dev/null; then
            systemctl reload nginx 2>/dev/null || systemctl restart nginx
        else
            echo "WARNING: Nginx config test failed after removing SPM vhost."
        fi
    fi
fi

echo "[4/7] Removing PHP-FPM pool..."
rm -f /etc/php-fpm.d/spm.conf
PHP_FPM_SERVICE="php-fpm"
if systemctl list-unit-files | grep -qE '^php[0-9]+\.[0-9]+-fpm\.service'; then
    PHP_FPM_SERVICE=$(systemctl list-unit-files | grep -E '^php[0-9]+\.[0-9]+-fpm\.service' | head -1 | awk '{print $1}' | sed 's/\.service$//')
fi
systemctl reload "$PHP_FPM_SERVICE" 2>/dev/null || true

echo "[5/7] Removing sudoers rules (including kinit /etc/squid/*.keytab)..."
rm -f /etc/sudoers.d/spm

echo "[6/7] Leaving squid.conf unchanged..."
if [ -f "$SQUID_CONF" ]; then
    echo "Preserved $SQUID_CONF"
fi
SQUID_STATUS=$(systemctl is-active squid 2>/dev/null || echo "unknown")
echo "Squid status: $SQUID_STATUS"

echo "[7/7] Removing panel files..."
rm -f /run/spmd.sock /run/spmd.pid /run/php-fpm/spm.sock
rm -f /etc/pki/tls/certs/spm-selfsigned.crt
rm -f /etc/pki/tls/private/spm-selfsigned.key

if [ "$FIREWALL_OPENED" = "1" ] && command -v firewall-cmd &>/dev/null && firewall-cmd --state &>/dev/null; then
    firewall-cmd --permanent --remove-port="${PANEL_PORT}/tcp" 2>/dev/null || true
    firewall-cmd --reload 2>/dev/null || true
    echo "Removed firewall port ${PANEL_PORT}/tcp"
fi

if [ -d "$SPM_DIR" ]; then
    rm -rf "$SPM_DIR"
    echo "Removed $SPM_DIR"
fi
rm -f "$INSTALL_META"
rmdir /etc/spm 2>/dev/null || true

read -p "Remove system user '$WEB_USER'? (yes/no): " REMOVE_USER
if [ "$REMOVE_USER" = "yes" ]; then
    gpasswd -d nginx "$WEB_USER" 2>/dev/null || true
    userdel "$WEB_USER" 2>/dev/null || true
    echo "User '$WEB_USER' removed."
fi

echo ""
echo "=== Uninstallation Complete ==="
echo ""
echo "SPM has been removed. Squid remains installed and running with the current config."
echo ""
echo "Optional leftovers (not removed):"
echo "  - nginx / php-fpm packages (may be used by other sites)"
echo "  - ${SQUID_CONF}.spm-install-* copies made at install time"
echo ""
