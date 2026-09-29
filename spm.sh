#!/bin/bash
# SPM — interactive management CLI
# Install: /usr/bin/spm (+ /usr/local/bin/spm) from /opt/spm/spm.sh
# Usage: spm | spm help | spm <command>

SPM_DIR="/opt/spm"
UPDATE_SH="/opt/update.sh"
UNINSTALL_SH="${SPM_DIR}/uninstall.sh"
CLONE_UNINSTALL="/opt/squid-panel/uninstall.sh"
SQUID_CONF="/etc/squid/squid.conf"
NGINX_SPM_CONF="/etc/nginx/conf.d/spm.conf"
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

# update.sh removes /opt/squid-panel — never run update from a dead cwd.
ensure_valid_cwd() {
    if ! pwd >/dev/null 2>&1; then
        cd /tmp 2>/dev/null || cd / || true
    fi
}

run_update_sh() {
    ensure_valid_cwd
    cd /tmp 2>/dev/null || cd / || true
    env SPM_UPDATE_CWD=/tmp bash "$UPDATE_SH" "$@"
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
    echo "  panel port: $(current_panel_port)"
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

load_install_meta() {
    if [ -f "$INSTALL_META" ]; then
        # shellcheck disable=SC1090
        . "$INSTALL_META"
    fi
}

current_panel_port() {
    local port="$PANEL_PORT"
    load_install_meta
    port="${PANEL_PORT:-$port}"
    echo "$port"
}

cmd_url() {
    local ip port
    port=$(current_panel_port)
    ip=$(hostname -I 2>/dev/null | awk '{print $1}')
    [ -z "$ip" ] && ip=$(hostname -f 2>/dev/null || echo "127.0.0.1")
    echo "Panel URL: https://${ip}:${port}/"
    echo "  (TLS may be self-signed)"
}

# Change nginx panel HTTPS port; persist in /etc/spm/install.env so update keeps it.
cmd_set_port() {
    local old new bak holders
    old=$(current_panel_port)
    load_install_meta

    echo "Current panel HTTPS port: $old"
    if [ -n "${1:-}" ]; then
        new="$1"
    else
        read -r -p "New port (1-65535, not 80/443) [${old}]: " new
        [ -z "$new" ] && new="$old"
    fi

    if ! [[ "$new" =~ ^[0-9]+$ ]] || [ "$new" -lt 1 ] || [ "$new" -gt 65535 ]; then
        echo -e "${red}ERROR:${plain} port must be an integer 1–65535"
        return 1
    fi
    if [ "$new" = "80" ] || [ "$new" = "443" ]; then
        echo -e "${red}ERROR:${plain} ports 80/443 are reserved (panel must not bind them)"
        return 1
    fi
    if [ "$new" = "$old" ]; then
        echo "Port unchanged ($old)."
        cmd_url
        return 0
    fi

    if [ ! -f "$NGINX_SPM_CONF" ]; then
        echo -e "${red}ERROR:${plain} $NGINX_SPM_CONF missing — is the panel installed?"
        return 1
    fi
    if ! grep -qE "listen[[:space:]]+${old}[[:space:]]+ssl" "$NGINX_SPM_CONF"; then
        echo -e "${red}ERROR:${plain} $NGINX_SPM_CONF has no 'listen ${old} ssl' (current port mismatch?)"
        return 1
    fi

    # Fail-closed: do not change if something already listens on the new port.
    holders=""
    if command -v ss &>/dev/null; then
        holders=$(ss -tlnp 2>/dev/null | awk -v p=":${new}" 'NR>1 && $4 ~ p"$" {print}')
        if [ -z "$holders" ]; then
            holders=$(ss -tlnH "sport = :${new}" 2>/dev/null || true)
        fi
    fi
    if [ -n "$holders" ]; then
        echo -e "${red}ERROR:${plain} port ${new} is already in use — refuse"
        echo "$holders"
        return 1
    fi

    if ! confirm "Change panel HTTPS port ${old} → ${new}?"; then
        echo "Cancelled."
        return 0
    fi

    cp -a "$NGINX_SPM_CONF" "${NGINX_SPM_CONF}.spm-port-${old}-$(date +%Y%m%d%H%M%S)"
    # IPv4 listen N ssl; and optional IPv6 listen [::]:N ssl;
    sed -i -E "s/listen([[:space:]]+)${old}([[:space:]]+ssl)/listen\1${new}\2/" "$NGINX_SPM_CONF"
    sed -i -E "s/listen([[:space:]]+)\[::\]:${old}([[:space:]]+ssl)/listen\1[::]:${new}\2/" "$NGINX_SPM_CONF"

    if ! nginx -t; then
        echo -e "${red}ERROR:${plain} nginx -t failed — restoring previous spm.conf"
        bak=$(ls -1t "${NGINX_SPM_CONF}".spm-port-"${old}"-* 2>/dev/null | head -1)
        if [ -n "$bak" ] && [ -f "$bak" ]; then
            cp -a "$bak" "$NGINX_SPM_CONF"
        fi
        return 1
    fi

    systemctl reload nginx || systemctl restart nginx

    mkdir -p /etc/spm
    if [ -f "$INSTALL_META" ]; then
        if grep -qE '^PANEL_PORT=' "$INSTALL_META"; then
            sed -i -E "s/^PANEL_PORT=.*/PANEL_PORT=${new}/" "$INSTALL_META"
        else
            printf 'PANEL_PORT=%s\n' "$new" >> "$INSTALL_META"
        fi
    else
        printf 'PANEL_PORT=%s\nFIREWALL_OPENED=0\nINSTALLED_AT=%s\n' "$new" "$(date +%Y%m%d%H%M%S)" > "$INSTALL_META"
        chmod 600 "$INSTALL_META"
    fi
    PANEL_PORT="$new"

    if command -v firewall-cmd &>/dev/null && firewall-cmd --state &>/dev/null; then
        firewall-cmd --permanent --remove-port="${old}/tcp" 2>/dev/null || true
        if firewall-cmd --permanent --add-port="${new}/tcp"; then
            firewall-cmd --reload || true
            if grep -qE '^FIREWALL_OPENED=' "$INSTALL_META" 2>/dev/null; then
                sed -i -E 's/^FIREWALL_OPENED=.*/FIREWALL_OPENED=1/' "$INSTALL_META"
            else
                printf 'FIREWALL_OPENED=1\n' >> "$INSTALL_META"
            fi
            echo "Firewall: closed ${old}/tcp, opened ${new}/tcp"
        else
            echo -e "${yellow}WARNING:${plain} could not open firewall port ${new}/tcp"
        fi
    fi

    echo -e "${green}OK:${plain} panel HTTPS port is now ${new}"
    cmd_url
}

cmd_update_keep() {
    if [ ! -x "$UPDATE_SH" ] && [ ! -f "$UPDATE_SH" ]; then
        echo -e "${red}ERROR:${plain} $UPDATE_SH not found"
        return 1
    fi
    # Menu item already chose keep-db; only confirm running update.
    if ! confirm "Run update from GitHub main now?"; then
        echo "Cancelled."
        return 0
    fi
    run_update_sh --keep-db
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
    run_update_sh --drop-db
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
    echo "SPM management CLI"
    echo ""
    echo "  spm                 Interactive menu"
    echo "  spm status          Service / db status"
    echo "  spm url             Panel URL"
    echo "  spm port [N]        Change panel HTTPS port (persists across update)"
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
    echo -e "  ${green}10.${plain} Change panel HTTPS port"
    echo -e "  ${green}0.${plain} Exit"
    echo "  ------------------------------------------"
}

run_menu() {
    export SPM_MENU=1
    while true; do
        show_menu
        read -r -p "Select [0-10]: " choice
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
            10) cmd_set_port; press_enter ;;
            0|q|Q) exit 0 ;;
            *) echo "Invalid option" ;;
        esac
    done
}

need_root
ensure_valid_cwd

case "${1:-}" in
    "") run_menu ;;
    help|-h|--help) show_usage ;;
    status) cmd_status ;;
    url) cmd_url ;;
    port|set-port) cmd_set_port "${2:-}" ;;
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
