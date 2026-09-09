#!/bin/bash
# Squid Proxy Manager — rollout updater (keep at /opt/update.sh during deployment)
#
# Clones GitHub main, then runs install.sh.
# Asks whether to drop spm.db (default: no).
# Flags: --drop-db / --keep-db (skip prompt). Env SPM_DROP_DB=1|0 also works.
# Does not systemctl restart Squid.
#
# Do not overwrite this running script in place (bash then parses garbage).

set -e

REPO_URL="https://github.com/MerlKoryAmber/squid-panel"
CLONE_DIR="/opt/squid-panel"
CLONE_NEW="/opt/squid-panel.new"
SELF="/opt/update.sh"

# If cwd was deleted (common: launch from /opt/squid-panel), getcwd/git break under set -e.
if ! pwd >/dev/null 2>&1; then
    cd /tmp 2>/dev/null || cd / || true
fi

safe_pwd() {
    pwd -P 2>/dev/null || pwd 2>/dev/null || echo /tmp
}

drop_db=""

for arg in "$@"; do
    case "$arg" in
        --drop-db) drop_db=1 ;;
        --keep-db) drop_db=0 ;;
        --continue) ;;
        *)
            if [ "$arg" != "" ]; then
                echo "ERROR: unknown argument: $arg"
                echo "Usage: sudo bash /opt/update.sh [--drop-db|--keep-db]"
                exit 1
            fi
            ;;
    esac
done

if [ -z "$drop_db" ]; then
    if [ "${SPM_DROP_DB:-}" = "1" ]; then
        drop_db=1
    elif [ "${SPM_DROP_DB:-}" = "0" ]; then
        drop_db=0
    fi
fi

ask_drop_db() {
    if [ -n "$drop_db" ]; then
        return
    fi
    echo "Drop panel database (spm.db)?"
    echo "  y = drop, then import live squid.conf"
    echo "  N = keep current spm.db (default)"
    if [ -t 0 ]; then
        read -r -p "Drop spm.db? [y/N] " reply
    else
        echo "No TTY: keeping spm.db."
        reply=""
    fi
    case "$reply" in
        y|Y|yes|YES) drop_db=1 ;;
        *) drop_db=0 ;;
    esac
}

if [ "$1" != "--continue" ]; then
    echo "=== SPM update.sh ==="
    echo "Repo: $REPO_URL"
    echo "squid.conf format after parse still runs in install.sh."
    echo ""

    # Non-root: escalate, then cd back in THIS shell only if sourced/function.
    # Plain "bash /opt/update.sh" is a child — parent cwd still stale after rm -rf.
    if [ "$EUID" -ne 0 ]; then
        _spm_cwd=$(safe_pwd)
        sudo env SPM_UPDATE_CWD="$_spm_cwd" bash "$SELF" "$@"
        _ec=$?
        cd "$_spm_cwd" 2>/dev/null || cd /tmp 2>/dev/null || true
        exit $_ec
    fi

    ask_drop_db
    if [ "$drop_db" = "1" ]; then
        echo "Will DROP spm.db."
        cont_flag="--drop-db"
    else
        echo "Will KEEP spm.db."
        cont_flag="--keep-db"
    fi

    if ! command -v git &>/dev/null; then
        echo "Installing git..."
        dnf install -y git
    fi

    # Remember launch cwd across exec --continue (and after clone dir swap).
    if [ -z "${SPM_UPDATE_CWD:-}" ]; then
        SPM_UPDATE_CWD=$(safe_pwd)
    fi
    # Stale path string (deleted inode) → fall back so later cd does not matter.
    if ! cd "$SPM_UPDATE_CWD" 2>/dev/null; then
        SPM_UPDATE_CWD=/tmp
        cd /tmp 2>/dev/null || cd / || true
    fi
    export SPM_UPDATE_CWD
    printf '%s\n' "$SPM_UPDATE_CWD" > /run/spm-update-cwd
    chmod 644 /run/spm-update-cwd 2>/dev/null || true

    # Always clone from a valid cwd (not a removed /opt/squid-panel).
    cd /tmp 2>/dev/null || cd / || true

    echo "[1/4] Cloning $REPO_URL (live panel stays until install starts)..."
    rm -rf "$CLONE_NEW"
    GIT_TERMINAL_PROMPT=0 git clone --depth 1 --branch main "$REPO_URL" "$CLONE_NEW"
    echo "Cloned commit:"
    git -C "$CLONE_NEW" log -1 --oneline
    if ! grep -q 'location @front' "$CLONE_NEW/install.sh"; then
        echo "ERROR: cloned install.sh has no nginx @front fix. Wrong repo/branch?"
        rm -rf "$CLONE_NEW"
        exit 1
    fi
    chmod 755 "$CLONE_NEW/install.sh" "$CLONE_NEW/uninstall.sh" "$CLONE_NEW/update.sh"
    if [ -f "$CLONE_NEW/spm.sh" ]; then
        chmod 755 "$CLONE_NEW/spm.sh"
    fi
    exec env SPM_UPDATE_CWD="$SPM_UPDATE_CWD" /bin/bash "$CLONE_NEW/update.sh" --continue "$cont_flag"
fi

if [ "$EUID" -ne 0 ]; then
    echo "ERROR: Please run as root"
    exit 1
fi

ask_drop_db

install -m 700 "$CLONE_NEW/update.sh" "$SELF"

# Interactive restore: function runs in caller's shell (sudo bash cannot).
cat > /etc/profile.d/spm-update.sh <<'EOF'
# SPM: restore cwd after update (same shell). Usage: spm-update --keep-db
spm-update() {
    local d ec
    d=$(pwd -P 2>/dev/null || pwd 2>/dev/null || echo /tmp)
    if ! cd "$d" 2>/dev/null; then
        d=/tmp
        cd /tmp 2>/dev/null || true
    fi
    cd /tmp 2>/dev/null || true
    sudo env SPM_UPDATE_CWD="$d" bash /opt/update.sh "$@"
    ec=$?
    cd "$d" 2>/dev/null || cd /tmp 2>/dev/null || true
    return $ec
}
EOF
chmod 644 /etc/profile.d/spm-update.sh

echo "[2/4] Stopping panel agent (Squid stays up)..."
systemctl stop spmd 2>/dev/null || true

echo "[3/4] Switching clone dir..."
rm -rf "$CLONE_DIR"
mv "$CLONE_NEW" "$CLONE_DIR"

echo "[4/4] Running install.sh..."
echo ""
cd "$CLONE_DIR"
export SPM_DROP_DB="$drop_db"
if [ "$drop_db" = "0" ]; then
    export SPM_SKIP_ADMIN_PASSWORD=1
fi
./install.sh || _ec=$?
_ec=${_ec:-0}
if [ -n "${SPM_UPDATE_CWD:-}" ]; then
    cd "$SPM_UPDATE_CWD" 2>/dev/null || cd "$SPM_UPDATE_CWD" 2>/dev/null || true
fi
exit $_ec
