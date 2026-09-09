#!/bin/bash
# SPM — interactive management CLI (s-ui style)
# Install: /usr/bin/spm (+ /usr/local/bin/spm) from /opt/spm/spm.sh
# Usage: spm | spm help | spm <command>

SPM_DIR="/opt/spm"
UPDATE_SH="/opt/update.sh"
UNINSTALL_SH="${SPM_DIR}/uninstall.sh"
CLONE_UNINSTALL="/opt/squid-panel/uninstall.sh"
SQUID_CONF="/etc/squid/squid.conf"
BACKUP_DIR="${SPM_DIR}/storage/backup"
INSTALL_META="/etc/spm/install.env"
PANEL_PORT="8443"

red='\033[0;31m'
green='\033[0;32m'
yellow='\033[0;33m'
plain='\033[0m'

need_root() {
    if [ "${EUID:-$(id -u)}" -ne 0 ]; then
        echo -e "${red}ERROR:${plain} run as root (sudo spm …)"
        exit 1
    fi
}

confirm() {
    local prompt="${1:-Continue?}"
    local def="${2:-n}"
    local reply
    if [ "$def" = "y" ]; then
        read -r -p "$prompt [Y/n] " reply
        case "$reply" in
            ""|y|Y|yes|YES) return 0 ;;
            *) return 1 ;;
        esac
    else
        read -r -p "$prompt [y/N] " reply
        case "$reply" in
            y|Y|yes|YES) return 0 ;;
            *) return 1 ;;
        esac
    fi
}

press_enter() {
    if [ "${SPM_MENU:-0}" = "1" ]; then
        echo ""
        read -r -p "Press Enter to return to menu…" _
    fi
}

svc_line() {
    local name="$1"
    local st
    st=$(systemctl is-active "$name" 2>/dev/null || echo "unknown")
    printf "  %-12s %s\n" "$name:" "$st"
}

cmd_status() {
    echo "=== SPM status ==="
    svc_line spmd
    svc_line nginx
    local fpm
    for fpm in php-fpm php84-php-fpm php83-php-fpm php82-php-fpm php81-php-fpm; do
        if systemctl cat "${fpm}.service" &>/dev/null; then
            svc_line "$fpm"
            break
        fi
    done
    svc_line squid
    echo ""
    if [ -f "${SPM_DIR}/database/spm.db" ]; then
        echo "  spm.db: present"
    else
        echo "  spm.db: missing"
    fi
    if [ -x "$UPDATE_SH" ]; then
        echo "  update.sh: $UPDATE_SH"
    else
        echo "  update.sh: missing ($UPDATE_SH)"
    fi
}

cmd_url() {
    local ip port
    port="$PANEL_PORT"
    if [ -f "$INSTALL_META" ]; then
        # shellcheck disable=SC1090
        . "$INSTALL_META"
        port="${PANEL_PORT:-$port}"
    fi
    ip=$(hostname -I 2>/dev/null | awk '{print $1}')
    [ -z "$ip" ] && ip=$(hostname -f 2>/dev/null || echo "127.0.0.1")
    echo "Panel URL: https://${ip}:${port}/"
    echo "  (TLS may be self-signed)"
}

cmd_update_keep() {
    if [ ! -x "$UPDATE_SH" ] && [ ! -f "$UPDATE_SH" ]; then
        echo -e "${red}ERROR:${plain} $UPDATE_SH not found"
        return 1
    fi
    if ! confirm "Update panel from GitHub main (KEEP spm.db)?"; then
        echo "Cancelled."
        return 0
    fi
    bash "$UPDATE_SH" --keep-db
}

cmd_update_drop() {
    if [ ! -f "$UPDATE_SH" ]; then
        echo -e "${red}ERROR:${plain} $UPDATE_SH not found"
        return 1
    fi
    echo -e "${yellow}WARNING:${plain} this DROPS spm.db and re-imports live squid.conf."
    if ! confirm "Really DROP database and update?"; then
        echo "Cancelled."
        return 0
    fi
    if ! confirm "Type confirmation again — DROP spm.db?"; then
        echo "Cancelled."
        return 0
    fi
    bash "$UPDATE_SH" --drop-db
}

cmd_uninstall() {
    local sh=""
    if [ -f "$UNINSTALL_SH" ]; then
        sh="$UNINSTALL_SH"
    elif [ -f "$CLONE_UNINSTALL" ]; then
        sh="$CLONE_UNINSTALL"
    else
        echo -e "${red}ERROR:${plain} uninstall.sh not found"
        return 1
    fi
    if ! confirm "Uninstall panel only (Squid stays with current conf)?"; then
        echo "Cancelled."
        return 0
    fi
    bash "$sh"
}

cmd_password() {
    local p1 p2
    if [ ! -f "${SPM_DIR}/install/set_admin_password.php" ]; then
        echo -e "${red}ERROR:${plain} set_admin_password.php missing"
        return 1
    fi
    if [ ! -f "${SPM_DIR}/database/spm.db" ]; then
        echo -e "${red}ERROR:${plain} spm.db missing"
        return 1
    fi
    read -r -s -p "New admin password: " p1
    echo ""
    read -r -s -p "Repeat password: " p2
    echo ""
    if [ -z "$p1" ]; then
        echo "Empty password — cancelled."
        return 1
    fi
    if [ "$p1" != "$p2" ]; then
        echo "Passwords do not match."
        return 1
    fi
    SPM_ADMIN_PASSWORD="$p1" /usr/bin/php "${SPM_DIR}/install/set_admin_password.php"
}

cmd_restart_spmd() {
    systemctl restart spmd
    systemctl is-active spmd
}

cmd_restart_web() {
    systemctl restart nginx || true
    local fpm=""
    for fpm in php-fpm php84-php-fpm php83-php-fpm php82-php-fpm php81-php-fpm; do
        if systemctl cat "${fpm}.service" &>/dev/null; then
            systemctl restart "$fpm" || true
            echo "restarted $fpm"
            break
        fi
    done
    systemctl is-active nginx 2>/dev/null || true
}

cmd_backup() {
    local ts dest
    ts=$(date +%Y%m%d%H%M%S)
    mkdir -p "$BACKUP_DIR"
    dest="${BACKUP_DIR}/spm-${ts}"
    mkdir -p "$dest"
    if [ -f "${SPM_DIR}/database/spm.db" ]; then
        cp -a "${SPM_DIR}/database/spm.db" "${dest}/spm.db"
    else
        echo "WARN: spm.db missing"
    fi
    if [ -f "$SQUID_CONF" ]; then
        cp -a "$SQUID_CONF" "${dest}/squid.conf"
    else
        echo "WARN: $SQUID_CONF missing"
    fi
    echo "Backup: $dest"
    ls -la "$dest"
}

show_usage() {
    echo "SPM management (s-ui style)"
    echo ""
    echo "  spm                 Interactive menu"
    echo "  spm status          Service / db status"
    echo "  spm url             Panel URL"
    echo "  spm update          Update (keep spm.db)"
    echo "  spm update-drop     Update and DROP spm.db"
    echo "  spm uninstall       Remove panel (Squid stays)"
    echo "  spm password        Reset admin password"
    echo "  spm restart-spmd    systemctl restart spmd"
    echo "  spm restart-web     restart nginx + php-fpm"
    echo "  spm backup          Backup spm.db + squid.conf"
    echo "  spm help            This text"
    echo ""
    echo "Squid restart is not offered here (only by explicit ops)."
}

show_menu() {
    echo ""
    echo -e "  ${green}SPM${plain} — Squid Proxy Manager"
    echo "  ------------------------------------------"
    echo -e "  ${green}1.${plain} Update (keep spm.db)"
    echo -e "  ${green}2.${plain} Update + DROP spm.db"
    echo -e "  ${green}3.${plain} Uninstall panel"
    echo -e "  ${green}4.${plain} Reset admin password"
    echo -e "  ${green}5.${plain} Status"
    echo -e "  ${green}6.${plain} Restart spmd"
    echo -e "  ${green}7.${plain} Restart nginx + php-fpm"
    echo -e "  ${green}8.${plain} Backup spm.db + squid.conf"
    echo -e "  ${green}9.${plain} Show panel URL"
    echo -e "  ${green}0.${plain} Exit"
    echo "  ------------------------------------------"
}

run_menu() {
    export SPM_MENU=1
    while true; do
        show_menu
        read -r -p "Select [0-9]: " choice
        case "$choice" in
            1) cmd_update_keep; press_enter ;;
            2) cmd_update_drop; press_enter ;;
            3) cmd_uninstall; press_enter ;;
            4) cmd_password; press_enter ;;
            5) cmd_status; press_enter ;;
            6) cmd_restart_spmd; press_enter ;;
            7) cmd_restart_web; press_enter ;;
            8) cmd_backup; press_enter ;;
            9) cmd_url; press_enter ;;
            0|q|Q) exit 0 ;;
            *) echo "Invalid option" ;;
        esac
    done
}

need_root

case "${1:-}" in
    "") run_menu ;;
    help|-h|--help) show_usage ;;
    status) cmd_status ;;
    url) cmd_url ;;
    update) cmd_update_keep ;;
    update-drop) cmd_update_drop ;;
    uninstall) cmd_uninstall ;;
    password) cmd_password ;;
    restart-spmd) cmd_restart_spmd ;;
    restart-web) cmd_restart_web ;;
    backup) cmd_backup ;;
    *)
        echo -e "${red}Unknown:${plain} $1"
        show_usage
        exit 1
        ;;
esac
