#!/bin/bash
# Squid Proxy Manager — rollout updater (keep at /opt/update.sh during deployment)
#
# Full panel reinstall from GitHub. Drops spm.db after a successful clone.
# install.sh then imports and formats /etc/squid/squid.conf (parse first).
# Does not systemctl restart Squid.
#
# Do not overwrite this running script in place (bash then parses garbage).

set -e

REPO_URL="https://github.com/MerlKoryAmber/squid-panel"
CLONE_DIR="/opt/squid-panel"
CLONE_NEW="/opt/squid-panel.new"
SPM_DIR="/opt/spm"
SELF="/opt/update.sh"

if [ "$1" != "--continue" ]; then
    echo "=== SPM update.sh ==="
    echo "Repo: $REPO_URL"
    echo "Will drop panel database during install. squid.conf is formatted after parse."
    echo ""

    if [ "$EUID" -ne 0 ]; then
        echo "ERROR: Please run as root"
        echo "  sudo bash /opt/update.sh"
        exit 1
    fi

    if ! command -v git &>/dev/null; then
        echo "Installing git..."
        dnf install -y git
    fi

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
    exec /bin/bash "$CLONE_NEW/update.sh" --continue
fi

if [ "$EUID" -ne 0 ]; then
    echo "ERROR: Please run as root"
    exit 1
fi

install -m 700 "$CLONE_NEW/update.sh" "$SELF"

echo "[2/4] Stopping panel agent (Squid stays up)..."
systemctl stop spmd 2>/dev/null || true

echo "[3/4] Switching clone dir..."
rm -rf "$CLONE_DIR"
mv "$CLONE_NEW" "$CLONE_DIR"

echo "[4/4] Running install.sh..."
echo ""
cd "$CLONE_DIR"
export SPM_DROP_DB=1
exec ./install.sh
