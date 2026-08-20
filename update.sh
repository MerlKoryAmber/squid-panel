#!/bin/bash
# Squid Proxy Manager — rollout updater (keep at /opt/update.sh during deployment)
#
# Full panel reinstall from GitHub. Drops /opt/spm including spm.db.
# Does not stop Squid and does not rewrite /etc/squid/squid.conf.

set -e

REPO_URL="https://github.com/MerlKoryAmber/squid-panel"
CLONE_DIR="/opt/squid-panel"
SPM_DIR="/opt/spm"
SELF="/opt/update.sh"

echo "=== SPM update.sh ==="
echo "Repo: $REPO_URL"
echo "Removes $SPM_DIR (including the panel database)."
echo "Squid and /etc/squid/squid.conf are not touched."
echo ""

if [ "$EUID" -ne 0 ]; then
    echo "ERROR: Please run as root"
    echo "  sudo bash /opt/update.sh"
    exit 1
fi

if [ -f "$SELF" ] && [ "$(readlink -f "$0" 2>/dev/null || realpath "$0" 2>/dev/null || echo "$0")" != "$(readlink -f "$SELF" 2>/dev/null || echo "$SELF")" ]; then
    echo "Copying this script to $SELF ..."
    cp -a "$0" "$SELF"
    chmod 700 "$SELF"
fi

if ! command -v git &>/dev/null; then
    echo "Installing git..."
    dnf install -y git
fi

echo "[1/4] Stopping panel agent (Squid stays up)..."
systemctl stop spmd 2>/dev/null || true

echo "[2/4] Removing previous panel trees (including spm.db)..."
# Do not delete $SELF. Do not delete /etc/squid.
rm -rf "$SPM_DIR" "$CLONE_DIR"

echo "[3/4] Cloning $REPO_URL ..."
GIT_TERMINAL_PROMPT=0 git clone --depth 1 --branch main "$REPO_URL" "$CLONE_DIR"
echo "Cloned commit:"
git -C "$CLONE_DIR" log -1 --oneline
if ! grep -q 'location @front' "$CLONE_DIR/install.sh"; then
    echo "ERROR: cloned install.sh has no nginx @front fix. Wrong repo/branch?"
    exit 1
fi
cp -a "$CLONE_DIR/update.sh" "$SELF"
chmod 700 "$SELF"
chmod 755 "$CLONE_DIR/install.sh" "$CLONE_DIR/uninstall.sh"

echo "[4/4] Running install.sh (fresh database)..."
echo ""
cd "$CLONE_DIR"
exec ./install.sh
