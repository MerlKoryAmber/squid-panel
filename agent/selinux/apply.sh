#!/bin/bash
# Apply SPM SELinux policy. Safe no-op if SELinux is Disabled or tools missing.
# Does not change Squid or squid.conf.

set -euo pipefail

TE="${1:-/opt/spm/agent/selinux/spm.te}"

if ! command -v getenforce >/dev/null 2>&1; then
    echo "SELinux: getenforce not found, skip"
    exit 0
fi

ENFORCE=$(getenforce 2>/dev/null || echo Disabled)
if [ "$ENFORCE" = "Disabled" ]; then
    echo "SELinux: Disabled, skip policy (panel uses DAC only)"
    exit 0
fi

echo "SELinux: $ENFORCE — installing spm module"

if ! command -v checkmodule >/dev/null 2>&1 || ! command -v semodule_package >/dev/null 2>&1; then
    dnf install -y checkpolicy policycoreutils 2>/dev/null || true
fi
if ! command -v checkmodule >/dev/null 2>&1 || ! command -v semodule_package >/dev/null 2>&1; then
    echo "WARNING: checkmodule/semodule_package missing, skip SELinux module"
    exit 0
fi

if [ ! -f "$TE" ]; then
    echo "WARNING: $TE missing, skip"
    exit 0
fi

WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT
checkmodule -M -m -o "$WORK/spm.mod" "$TE" 2>"$WORK/cm.err" || {
    echo "checkmodule failed (maybe no squid_var_run_t), retry without squid pid rule"
    grep -v squid_var_run_t "$TE" > "$WORK/spm-min.te"
    checkmodule -M -m -o "$WORK/spm.mod" "$WORK/spm-min.te"
}
semodule_package -o "$WORK/spm.pp" -m "$WORK/spm.mod"
semodule -i "$WORK/spm.pp" 2>/dev/null || {
    semodule -r spm 2>/dev/null || true
    semodule -i "$WORK/spm.pp"
}

if [ -S /run/spmd.sock ]; then
    if command -v semanage >/dev/null 2>&1; then
        semanage fcontext -a -t httpd_var_run_t '/run/spmd\.sock' 2>/dev/null \
            || semanage fcontext -m -t httpd_var_run_t '/run/spmd\.sock' 2>/dev/null \
            || true
    fi
    restorecon -v /run/spmd.sock 2>/dev/null || true
    chcon -t httpd_var_run_t /run/spmd.sock 2>/dev/null || true
fi
if [ -f /run/squid.pid ]; then
    restorecon -v /run/squid.pid 2>/dev/null || true
fi

echo "SELinux: spm module installed"
