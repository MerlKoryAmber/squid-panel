#!/bin/bash
# Squid Proxy Manager — rollout updater (keep at /opt/update.sh during deployment)
#
# Full panel reinstall from GitHub. Drops spm.db after a successful clone.
# Does not stop Squid and does not rewrite /etc/squid/squid.conf.
# Does not delete /opt/spm until install.sh is ready (password prompt first).

set -e

REPO_URL="https://github.com/MerlKoryAmber/squid-panel"
CLONE_DIR="/opt/squid-panel"
CLONE_NEW="/opt/squid-panel.new"
SPM_DIR="/opt/spm"
SELF="/opt/update.sh"

echo "=== SPM update.sh ==="
echo "Repo: $REPO_URL"
echo "Will drop panel database during install. Squid and squid.conf are not touched."
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
chmod 755 "$CLONE_NEW/install.sh" "$CLONE_NEW/uninstall.sh"
cp -a "$CLONE_NEW/update.sh" "$SELF"
chmod 700 "$SELF"

echo "[2/4] Stopping panel agent (Squid stays up)..."
systemctl stop spmd 2>/dev/null || true

echo "[3/4] Switching clone dir..."
rm -rf "$CLONE_DIR"
mv "$CLONE_NEW" "$CLONE_DIR"

echo "[4/4] Running install.sh (password next; /opt/spm is replaced only after that)..."
echo ""
cd "$CLONE_DIR"
export SPM_DROP_DB=1
exec ./install.sh
