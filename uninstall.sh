#!/bin/bash
# Squid Proxy Manager — Uninstallation Script
# Removes the web panel and leaves Squid running with its current squid.conf.

set -e

SPM_DIR="/opt/spm"
CLONE_DIR="/opt/squid-panel"
WEB_USER="squidmgr"
NGINX_SPM_CONF="/etc/nginx/conf.d/spm.conf"
NGINX_ALLOW_INC="/etc/nginx/conf.d/spm-allow.inc"
SQUID_CONF="/etc/squid/squid.conf"
SQUID_LISTEN="/etc/squid/spm-listen.conf"
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
echo "  - Git clone leftover $CLONE_DIR (if present)"
echo "  - Nginx vhost $NGINX_SPM_CONF"
echo "  - Nginx allowlist $NGINX_ALLOW_INC"
echo "  - PHP-FPM pool /etc/php-fpm.d/spm.conf"
echo "  - Privileged agent (spmd), /run/spmd, /var/log/spmd.log"
echo "  - Restricted sudo rules /etc/sudoers.d/spm"
echo "  - Panel TLS cert /etc/pki/tls/certs/spm-selfsigned.crt"
echo ""
echo "Squid will NOT be stopped, removed, or reconfigured."
echo "Left in place (Squid may still use them):"
echo "  - $SQUID_CONF (including include/spm-listen and # SPM-moved lines)"
echo "  - $SQUID_LISTEN"
echo "  - /etc/squid/acl.d/"
echo "  - /etc/squid/*.keytab (panel copy, e.g. krb5.keytab)"
echo "  - ${SQUID_CONF}.spm-install-* and ${SQUID_CONF}.spm-listen-* backups"
echo "  - /etc/krb5.keytab (never touched)"
echo ""
read -p "Uninstall SPM? (yes/no): " CONFIRM
if [ "$CONFIRM" != "yes" ]; then
    echo "Uninstallation cancelled."
    exit 0
fi

echo ""
echo "[1/8] Stopping SPM agent..."
systemctl stop spmd 2>/dev/null || true
systemctl disable spmd 2>/dev/null || true

echo "[2/8] Removing systemd unit..."
rm -f /etc/systemd/system/spmd.service
systemctl daemon-reload 2>/dev/null || true

echo "[3/8] Removing Nginx vhost and panel allowlist..."
rm -f "$NGINX_SPM_CONF"
rm -f "$NGINX_ALLOW_INC"
if systemctl list-unit-files | grep -q '^nginx.service'; then
    if systemctl is-active nginx &>/dev/null; then
        if nginx -t 2>/dev/null; then
            systemctl reload nginx 2>/dev/null || systemctl restart nginx
        else
            echo "WARNING: Nginx config test failed after removing SPM vhost."
        fi
    fi
fi

echo "[4/8] Removing PHP-FPM pool..."
rm -f /etc/php-fpm.d/spm.conf
PHP_FPM_SERVICE="php-fpm"
if systemctl list-unit-files | grep -qE '^php[0-9]+\.[0-9]+-fpm\.service'; then
    PHP_FPM_SERVICE=$(systemctl list-unit-files | grep -E '^php[0-9]+\.[0-9]+-fpm\.service' | head -1 | awk '{print $1}' | sed 's/\.service$//')
fi
systemctl reload "$PHP_FPM_SERVICE" 2>/dev/null || true

echo "[5/8] Removing sudoers rules..."
rm -f /etc/sudoers.d/spm

echo "[6/8] Leaving Squid config and data unchanged..."
if [ -f "$SQUID_CONF" ]; then
    echo "Preserved $SQUID_CONF"
fi
if [ -f "$SQUID_LISTEN" ]; then
    echo "Preserved $SQUID_LISTEN (still referenced if squid.conf has include)"
fi
if [ -d /etc/squid/acl.d ]; then
    echo "Preserved /etc/squid/acl.d"
fi
SQUID_STATUS=$(systemctl is-active squid 2>/dev/null || echo "unknown")
echo "Squid status: $SQUID_STATUS"

echo "[7/8] Removing panel sockets, logs, TLS, clone..."
rm -f /run/spmd.sock /run/spmd.pid /run/php-fpm/spm.sock
rm -f /run/spmd/krb5_ldap.ccache
rmdir /run/spmd 2>/dev/null || true
rm -f /var/log/spmd.log
rm -f /etc/pki/tls/certs/spm-selfsigned.crt
rm -f /etc/pki/tls/private/spm-selfsigned.key

if [ "$FIREWALL_OPENED" = "1" ] && command -v firewall-cmd &>/dev/null && firewall-cmd --state &>/dev/null; then
    firewall-cmd --permanent --remove-port="${PANEL_PORT}/tcp" 2>/dev/null || true
    firewall-cmd --reload 2>/dev/null || true
    echo "Removed firewall port ${PANEL_PORT}/tcp"
fi

if [ -d "$CLONE_DIR" ]; then
    rm -rf "$CLONE_DIR"
    echo "Removed $CLONE_DIR"
fi

echo "[8/8] Removing panel files..."
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
echo "SPM has been removed. Squid remains with the current config."
echo ""
echo "Optional leftovers (not removed):"
echo "  - nginx / php-fpm packages (may be used by other sites)"
echo "  - $SQUID_LISTEN and include in $SQUID_CONF"
echo "  - /etc/squid/acl.d/*.txt"
echo "  - /etc/squid/*.keytab (not /etc/krb5.keytab)"
echo "  - ${SQUID_CONF}.spm-install-*  ${SQUID_CONF}.spm-listen-*"
echo "  - SELinux fcontext rules added for $SPM_DIR (harmless if dir is gone)"
echo "  - /opt/update.sh (if you copied it there)"
echo ""
