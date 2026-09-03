#!/usr/bin/env python3
"""
SPM Privileged Agent Daemon
Securely executes whitelisted system commands for Squid Proxy Manager
"""

import socket
import os
import sys
import json
import subprocess
import logging
import re
import struct
import pwd
import grp
import base64
import time

SOCKET_PATH = "/run/spmd.sock"
PID_FILE = "/run/spmd.pid"
LOG_FILE = "/var/log/spmd.log"

PARSE_FILE = "/opt/spm/storage/tmp/squid.conf.parse"
ALLOWED_UIDS = None

ALLOWED_COMMANDS = {
    "squid_reconfigure": ["/usr/sbin/squid", "-k", "reconfigure"],
    "squid_restart": ["/usr/bin/systemctl", "restart", "squid"],
    "squid_start": ["/usr/bin/systemctl", "start", "squid"],
    "squid_stop": ["/usr/bin/systemctl", "stop", "squid"],
    "squid_status": ["/usr/bin/systemctl", "is-active", "squid"],
    "squid_syntax": ["/usr/sbin/squid", "-f", PARSE_FILE, "-k", "parse"],
    "squid_version": ["/usr/sbin/squid", "-v"],
    "winbind_status": ["/usr/bin/systemctl", "is-active", "winbind"],
    "kinit_test": ["/usr/bin/kinit", "-k", "-t"],
    "wbinfo_test": ["/usr/bin/wbinfo", "-t"],
    "wbinfo_groups": ["/usr/bin/wbinfo", "-g"],
    "net_ads_info": ["/usr/bin/net", "ads", "info"],
    "acl_file_install": ["__acl_file_install__"],
    "keytab_install": ["__keytab_install__"],
    "ad_ldap_groups": ["__ad_ldap_groups__"],
    "squid_listen_apply": ["__squid_listen_apply__"],
    "squid_policy_apply": ["__squid_policy_apply__"],
    "nginx_allow_apply": ["__nginx_allow_apply__"],
}

ACL_SRC = "/opt/spm/storage/acl"
ACL_DST = "/etc/squid/acl.d"
ACL_FILE = re.compile(r"^[A-Za-z0-9._-]+\.txt$")


def install_acl_file(filename):
    if not isinstance(filename, str) or not ACL_FILE.fullmatch(filename):
        raise ValueError("Invalid ACL list filename")
    src_dir = os.path.realpath(ACL_SRC)
    src = os.path.realpath(os.path.join(ACL_SRC, filename))
    if os.path.dirname(src) != src_dir or not os.path.isfile(src):
        raise ValueError("ACL list working copy not found")
    size = os.path.getsize(src)
    if size > 5 * 1024 * 1024:
        raise ValueError("ACL list file is too large")
    os.makedirs(ACL_DST, mode=0o755, exist_ok=True)
    dst = os.path.join(ACL_DST, filename)
    tmp = dst + ".tmp"
    with open(src, "rb") as fh:
        data = fh.read()
    with open(tmp, "wb") as fh:
        fh.write(data)
        fh.flush()
        os.fsync(fh.fileno())
    os.chmod(tmp, 0o644)
    os.replace(tmp, dst)
    try:
        squid_uid = pwd.getpwnam("squid").pw_uid
        squid_gid = pwd.getpwnam("squid").pw_gid
        os.chown(dst, squid_uid, squid_gid)
    except KeyError:
        pass
    return dst


def install_keytab(filename):
    if not isinstance(filename, str) or not KEYTAB_NAME.fullmatch(filename):
        raise ValueError("Invalid keytab filename")
    src_dir = os.path.realpath(KEYTAB_SRC)
    src = os.path.realpath(os.path.join(KEYTAB_SRC, filename))
    if os.path.dirname(src) != src_dir or not os.path.isfile(src):
        raise ValueError("Keytab staging file not found")
    size = os.path.getsize(src)
    if size > KEYTAB_MAX:
        raise ValueError("Keytab file is too large")
    with open(src, "rb") as fh:
        data = fh.read()
    if len(data) < 2 or data[:2] not in (b"\x05\x01", b"\x05\x02"):
        raise ValueError("File is not a MIT keytab")
    os.makedirs(KEYTAB_DIR, mode=0o755, exist_ok=True)
    dst = os.path.join(KEYTAB_DIR, filename)
    tmp = dst + ".tmp"
    with open(tmp, "wb") as fh:
        fh.write(data)
        fh.flush()
        os.fsync(fh.fileno())
    os.chmod(tmp, 0o640)
    os.replace(tmp, dst)
    try:
        squid_uid = pwd.getpwnam("squid").pw_uid
        squid_gid = pwd.getpwnam("squid").pw_gid
        os.chown(dst, squid_uid, squid_gid)
    except KeyError:
        pass
    try:
        os.unlink(src)
    except OSError:
        pass
    return dst


REALM_RE = re.compile(r"^[A-Za-z0-9.-]+$")
HOST_RE = re.compile(r"^[A-Za-z0-9.-]+$")
PRINC_RE = re.compile(r"^[A-Za-z0-9./_@-]+$")
LDAPSEARCH = "/usr/bin/ldapsearch"
KINIT = "/usr/bin/kinit"
KDESTROY = "/usr/bin/kdestroy"
LDAP_CCACHE = "/run/spmd/krb5_ldap.ccache"
LDAP_STAGING_DIR = "/opt/spm/storage/tmp"
LDAP_STAGING_RE = re.compile(r"^ad-ldap-list\.json$")
BIND_DN_RE = re.compile(r"^[A-Za-z0-9 =,_.*@\-]{1,512}$")


def _ldap_uri(host, port, use_ssl):
    if not HOST_RE.fullmatch(host):
        raise ValueError("Invalid LDAP host")
    port = int(port)
    if port < 1 or port > 65535:
        raise ValueError("Invalid LDAP port")
    scheme = "ldaps" if use_ssl else "ldap"
    return "%s://%s:%d" % (scheme, host, port)


def list_ad_ldap_groups_simple(cfg):
    """Simple bind ldapsearch (KWTS-style). Password via -y file, not argv."""
    if not os.path.isfile(LDAPSEARCH):
        raise ValueError("ldapsearch not found (install openldap-clients)")
    servers = cfg.get("servers") or []
    if not isinstance(servers, list) or not servers:
        raise ValueError("LDAP servers required for simple bind")
    host = servers[0]
    port = int(cfg.get("port") or 389)
    use_ssl = bool(cfg.get("use_ssl"))
    bind_dn = cfg.get("bind_dn") or ""
    password = cfg.get("bind_password") or ""
    base = cfg.get("base_dn") or ""
    if not isinstance(bind_dn, str) or not BIND_DN_RE.fullmatch(bind_dn):
        raise ValueError("Invalid bind DN")
    if not isinstance(password, str) or not password or len(password) > 256:
        raise ValueError("Invalid bind password")
    if not isinstance(base, str) or not base:
        realm = cfg.get("realm") or ""
        if not isinstance(realm, str) or not REALM_RE.fullmatch(realm):
            raise ValueError("base_dn or realm required")
        base = _ldap_base_dn(realm)
    elif not BIND_DN_RE.fullmatch(base):
        raise ValueError("Invalid base DN")
    uri = _ldap_uri(host, port, use_ssl)
    os.makedirs("/run/spmd", mode=0o700, exist_ok=True)
    pass_path = "/run/spmd/ldap-bind.pass"
    try:
        with open(pass_path, "w", encoding="utf-8") as fh:
            fh.write(password)
            fh.flush()
            os.fsync(fh.fileno())
        os.chmod(pass_path, 0o600)
        filter_str = "(objectClass=group)"
        base_cmd = [
            LDAPSEARCH, "-x", "-H", uri, "-D", bind_dn, "-y", pass_path,
            "-b", base, "-LLL", "-o", "nettimeout=15",
        ]
        if use_ssl:
            env = os.environ.copy()
            env["LDAPTLS_REQCERT"] = "never"
        else:
            env = os.environ.copy()
        cmd = base_cmd + ["-E", "pr=1000/noprompt", filter_str, "sAMAccountName"]
        r = subprocess.run(cmd, capture_output=True, text=True, timeout=30, env=env)
        if r.returncode != 0:
            cmd = base_cmd + [filter_str, "sAMAccountName"]
            r = subprocess.run(cmd, capture_output=True, text=True, timeout=30, env=env)
        if r.returncode != 0 and not r.stdout:
            err = (r.stderr or r.stdout or "ldapsearch failed").strip()
            raise ValueError(err)
        names = _parse_sam_names(r.stdout or "")
        names = list(dict.fromkeys(names))
        if len(names) > 2000:
            names = names[:2000]
        logging.info("LDAP simple group list: %s names from %s", len(names), host)
        return names
    finally:
        try:
            os.unlink(pass_path)
        except OSError:
            pass


def list_ad_ldap_groups_from_staging(filename):
    if not isinstance(filename, str) or not LDAP_STAGING_RE.fullmatch(filename):
        raise ValueError("Invalid LDAP staging filename")
    path = os.path.join(LDAP_STAGING_DIR, filename)
    if not os.path.isfile(path):
        raise ValueError("LDAP staging file missing")
    try:
        with open(path, "r", encoding="utf-8") as fh:
            raw = fh.read(65536)
        cfg = json.loads(raw)
    finally:
        try:
            os.unlink(path)
        except OSError:
            pass
    if not isinstance(cfg, dict):
        raise ValueError("Invalid LDAP staging JSON")
    mode = cfg.get("bind_mode") or "simple"
    if mode != "simple":
        raise ValueError("AD groups LDAP requires simple bind (GSSAPI for groups disabled)")
    return list_ad_ldap_groups_simple(cfg)


def _ldap_base_dn(realm):
    parts = []
    for label in realm.split("."):
        if not label or not re.fullmatch(r"[A-Za-z0-9-]+", label):
            raise ValueError("Invalid realm for LDAP base")
        parts.append("DC=" + label)
    if len(parts) < 2:
        raise ValueError("Realm must have at least two labels")
    return ",".join(parts)


def _parse_sam_names(ldif_text):
    names = []
    for raw in ldif_text.splitlines():
        line = raw.strip()
        if line.lower().startswith("samaccountname::"):
            blob = line.split(":", 2)[-1].strip()
            try:
                decoded = base64.b64decode(blob).decode("utf-8", "replace").strip()
            except Exception:
                continue
            if decoded:
                names.append(decoded)
        elif line.lower().startswith("samaccountname:"):
            val = line.split(":", 1)[1].strip()
            if val:
                names.append(val)
    return names


def list_ad_ldap_groups(keytab_path, realm, ldap_host, principal):
    keytab = validate_keytab(keytab_path)
    if not isinstance(realm, str) or not REALM_RE.fullmatch(realm):
        raise ValueError("Invalid Kerberos realm")
    if not isinstance(ldap_host, str) or not HOST_RE.fullmatch(ldap_host):
        raise ValueError("Invalid LDAP host")
    princ = ""
    if principal and principal not in ("-", ""):
        if not isinstance(principal, str) or not PRINC_RE.fullmatch(principal):
            raise ValueError("Invalid principal")
        princ = principal
    if not os.path.isfile(LDAPSEARCH):
        raise ValueError("ldapsearch not found (install openldap-clients)")
    base = _ldap_base_dn(realm)
    os.makedirs("/run/spmd", mode=0o700, exist_ok=True)
    env = os.environ.copy()
    env["KRB5CCNAME"] = "FILE:" + LDAP_CCACHE
    kinit_cmd = [KINIT, "-k", "-t", keytab]
    if princ:
        kinit_cmd.append(princ)
    k1 = subprocess.run(kinit_cmd, capture_output=True, text=True, timeout=15, env=env)
    if k1.returncode != 0:
        raise ValueError("kinit failed: " + (k1.stderr or k1.stdout or "error").strip())
    try:
        uri = "ldap://" + ldap_host
        filter_str = "(objectClass=group)"
        base_cmd = [
            LDAPSEARCH, "-Y", "GSSAPI", "-Q",
            "-H", uri, "-b", base, "-LLL",
            "-o", "nettimeout=15",
        ]
        cmd = base_cmd + ["-E", "pr=1000/noprompt", filter_str, "sAMAccountName"]
        r = subprocess.run(cmd, capture_output=True, text=True, timeout=30, env=env)
        if r.returncode != 0:
            cmd = base_cmd + [filter_str, "sAMAccountName"]
            r = subprocess.run(cmd, capture_output=True, text=True, timeout=30, env=env)
        if r.returncode != 0 and not r.stdout:
            err = (r.stderr or r.stdout or "ldapsearch failed").strip()
            raise ValueError(err)
        names = _parse_sam_names(r.stdout or "")
        names = list(dict.fromkeys(names))
        if len(names) > 2000:
            names = names[:2000]
        logging.info("LDAP group list: %s names from %s", len(names), ldap_host)
        return names
    finally:
        subprocess.run([KDESTROY, "-c", env["KRB5CCNAME"]], capture_output=True, timeout=5, env=env)
        try:
            os.unlink(LDAP_CCACHE)
        except OSError:
            pass


SQUID_CONF_LIVE = "/etc/squid/squid.conf"
LISTEN_DST = "/etc/squid/spm-listen.conf"
LISTEN_INCLUDE = "include /etc/squid/spm-listen.conf"
NGINX_ALLOW_DST = "/etc/nginx/conf.d/spm-allow.inc"
NGINX_BIN = "/usr/sbin/nginx"
LISTEN_LINE = re.compile(
    r"^(#.*|http_port\s+\S.*|visible_hostname\s+[A-Za-z0-9._-]+)$"
)
POLICY_FILE_RE = re.compile(r"^spm-(acl|peers|http_access)\.conf$")
POLICY_LINE = re.compile(
    r"^(#.*|acl\s+\S.*|http_access\s+\S.*|cache_peer\s+\S.*|"
    r"cache_peer_access\s+\S.*|never_direct\s+\S.*|always_direct\s+\S.*)$"
)
POLICY_MARK = "# SPM managed ACL / access / cascade"
POLICY_INCLUDES = [
    "include /etc/squid/spm-acl.conf",
    "include /etc/squid/spm-peers.conf",
    "include /etc/squid/spm-http_access.conf",
]
POLICY_DSTS = [
    "/etc/squid/spm-acl.conf",
    "/etc/squid/spm-peers.conf",
    "/etc/squid/spm-http_access.conf",
]
POLICY_STAGING = [
    "spm-acl.conf",
    "spm-peers.conf",
    "spm-http_access.conf",
]
MANAGED_DIR = re.compile(
    r"^(acl|http_access|cache_peer|cache_peer_access|never_direct|always_direct)\s"
)
NGINX_ALLOW_LINE = re.compile(
    r"^(#.*|allow\s+[0-9a-fA-F.:/]+;|deny\s+all;)$"
)


def _read_tmp_named(filename, pattern, max_bytes):
    if not isinstance(filename, str) or not pattern.fullmatch(filename):
        raise ValueError("Invalid staging filename")
    src_dir = os.path.realpath("/opt/spm/storage/tmp")
    src = os.path.realpath(os.path.join("/opt/spm/storage/tmp", filename))
    if os.path.dirname(src) != src_dir or not os.path.isfile(src):
        raise ValueError("Staging file not found")
    if os.path.getsize(src) > max_bytes:
        raise ValueError("Staging file too large")
    with open(src, "r", encoding="utf-8") as fh:
        text = fh.read()
    return src, text


def _atomic_write(path, text, mode=0o644):
    tmp = path + ".tmp"
    os.makedirs(os.path.dirname(path), mode=0o755, exist_ok=True)
    with open(tmp, "w", encoding="utf-8") as fh:
        fh.write(text)
        if not text.endswith("\n"):
            fh.write("\n")
        fh.flush()
        os.fsync(fh.fileno())
    os.chmod(tmp, mode)
    os.replace(tmp, path)


def _validate_lines(text, line_re, what):
    for raw in text.splitlines():
        line = raw.strip()
        if line == "":
            continue
        if not line_re.fullmatch(line):
            raise ValueError("Invalid " + what + " line: " + line)


def _comment_listen_directives(text):
    out = []
    for line in text.splitlines(True):
        stripped = line.lstrip()
        if stripped.startswith("#"):
            out.append(line)
            continue
        if re.match(r"^(http_port|visible_hostname)\s", stripped):
            ending = "\n" if line.endswith("\n") else ""
            body = line[:-1] if line.endswith("\n") else line
            out.append("# SPM-moved " + body + ending)
            continue
        out.append(line)
    return "".join(out)


def apply_squid_listen():
    _src, body = _read_tmp_named("spm-listen.conf", re.compile(r"^spm-listen\.conf$"), 8192)
    _validate_lines(body, LISTEN_LINE, "listen")
    if "http_port " not in body:
        raise ValueError("listen file has no http_port")
    _atomic_write(LISTEN_DST, body, 0o644)
    try:
        squid_uid = pwd.getpwnam("squid").pw_uid
        squid_gid = pwd.getpwnam("squid").pw_gid
        os.chown(LISTEN_DST, squid_uid, squid_gid)
    except KeyError:
        pass
    if not os.path.isfile(SQUID_CONF_LIVE):
        raise ValueError("squid.conf missing")
    with open(SQUID_CONF_LIVE, "r", encoding="utf-8", errors="replace") as fh:
        original = fh.read()
    ts = time.strftime("%Y%m%d%H%M%S")
    backup = SQUID_CONF_LIVE + ".spm-listen-" + ts
    _atomic_write(backup, original, 0o644)
    text = _comment_listen_directives(original)
    if LISTEN_INCLUDE not in text:
        if text and not text.endswith("\n"):
            text += "\n"
        text += "# SPM managed listen/hostname\n" + LISTEN_INCLUDE + "\n"
    parse_dir = os.path.dirname(PARSE_FILE)
    os.makedirs(parse_dir, mode=0o755, exist_ok=True)
    _atomic_write(PARSE_FILE, text, 0o600)
    p = subprocess.run(
        ["/usr/sbin/squid", "-f", PARSE_FILE, "-k", "parse"],
        capture_output=True,
        text=True,
        timeout=30,
    )
    if p.returncode != 0:
        raise ValueError((p.stderr or p.stdout or "squid parse failed").strip())
    _atomic_write(SQUID_CONF_LIVE, text, 0o644)
    r = subprocess.run(
        ["/usr/sbin/squid", "-k", "reconfigure"],
        capture_output=True,
        text=True,
        timeout=30,
    )
    if r.returncode != 0:
        raise ValueError((r.stderr or r.stdout or "reconfigure failed").strip())
    logging.info("squid listen applied backup=%s", backup)
    return "listen applied, backup " + backup


def _managed_directive(stripped):
    if stripped.startswith("#"):
        return False
    return bool(MANAGED_DIR.match(stripped))


def _comment_policy_directives(text):
    out = []
    for line in text.splitlines(True):
        stripped = line.lstrip()
        if stripped.startswith("#"):
            out.append(line)
            continue
        if _managed_directive(stripped):
            ending = "\n" if line.endswith("\n") else ""
            body = line[:-1] if line.endswith("\n") else line
            out.append("# SPM-moved " + body + ending)
            continue
        out.append(line)
    return "".join(out)


def _strip_policy_includes(text):
    drop = set(POLICY_INCLUDES)
    drop.add(POLICY_MARK)
    out = []
    for line in text.splitlines(True):
        if line.strip() in drop:
            continue
        out.append(line)
    return "".join(out)


def _ensure_policy_includes(text):
    """acl … external TYPE needs external_acl_type TYPE already parsed — after helpers, not at first acl."""
    text = _strip_policy_includes(text)
    block = POLICY_MARK + "\n" + "\n".join(POLICY_INCLUDES) + "\n"
    lines = text.splitlines(True)
    insert_at = 0
    for i, line in enumerate(lines):
        s = line.lstrip()
        if s.startswith("#"):
            continue
        if (
            s.startswith("auth_param ")
            or s.startswith("external_acl_type ")
            or s.startswith("include /etc/squid/spm-listen.conf")
        ):
            insert_at = i + 1
    return "".join(lines[:insert_at]) + block + "".join(lines[insert_at:])


def _restore_files(saved):
    for path, old in saved.items():
        if old is None:
            try:
                os.unlink(path)
            except OSError:
                pass
        else:
            _atomic_write(path, old, 0o644)


def apply_squid_policy():
    if not os.path.isfile(PARSE_FILE):
        raise ValueError("staging squid.conf.parse missing")
    if os.path.getsize(PARSE_FILE) > 2 * 1024 * 1024:
        raise ValueError("staging squid.conf.parse too large")
    with open(PARSE_FILE, "r", encoding="utf-8", errors="replace") as fh:
        body = fh.read()
    if "http_access deny all" not in body:
        raise ValueError("generated conf has no http_access deny all")
    if "http_port " not in body:
        raise ValueError("generated conf has no http_port")
    if not os.path.isfile(SQUID_CONF_LIVE):
        raise ValueError("squid.conf missing")
    with open(SQUID_CONF_LIVE, "r", encoding="utf-8", errors="replace") as fh:
        original = fh.read()
    ts = time.strftime("%Y%m%d%H%M%S")
    backup = SQUID_CONF_LIVE + ".spm-policy-" + ts
    _atomic_write(backup, original, 0o644)
    p = subprocess.run(
        ["/usr/sbin/squid", "-f", PARSE_FILE, "-k", "parse"],
        capture_output=True,
        text=True,
        timeout=30,
    )
    if p.returncode != 0:
        raise ValueError((p.stderr or p.stdout or "squid parse failed").strip())
    _atomic_write(SQUID_CONF_LIVE, body, 0o644)
    r = subprocess.run(
        ["/usr/sbin/squid", "-k", "reconfigure"],
        capture_output=True,
        text=True,
        timeout=30,
    )
    if r.returncode != 0:
        _atomic_write(SQUID_CONF_LIVE, original, 0o644)
        raise ValueError((r.stderr or r.stdout or "reconfigure failed").strip())
    logging.info("squid.conf applied backup=%s", backup)
    return "squid.conf applied, backup " + backup


def apply_nginx_allow():
    _src, body = _read_tmp_named("spm-allow.inc", re.compile(r"^spm-allow\.inc$"), 8192)
    _validate_lines(body, NGINX_ALLOW_LINE, "nginx allow")
    old = ""
    if os.path.isfile(NGINX_ALLOW_DST):
        with open(NGINX_ALLOW_DST, "r", encoding="utf-8", errors="replace") as fh:
            old = fh.read()
    _atomic_write(NGINX_ALLOW_DST, body, 0o644)
    t = subprocess.run([NGINX_BIN, "-t"], capture_output=True, text=True, timeout=15)
    if t.returncode != 0:
        if old != "":
            _atomic_write(NGINX_ALLOW_DST, old, 0o644)
        raise ValueError((t.stderr or t.stdout or "nginx -t failed").strip())
    r = subprocess.run(
        ["/usr/bin/systemctl", "reload", "nginx"],
        capture_output=True,
        text=True,
        timeout=15,
    )
    if r.returncode != 0:
        raise ValueError((r.stderr or r.stdout or "nginx reload failed").strip())
    logging.info("nginx allowlist applied")
    return "nginx allowlist applied"


KEYTAB_DIR = "/etc/squid"
KEYTAB_SRC = "/opt/spm/storage/tmp"
KEYTAB_NAME = re.compile(r"^[A-Za-z0-9._-]+\.keytab$")
KEYTAB_MAX = 512 * 1024

def setup_logging():
    logging.basicConfig(
        level=logging.INFO,
        format='%(asctime)s [%(levelname)s] %(message)s',
        handlers=[
            logging.FileHandler(LOG_FILE),
            logging.StreamHandler(sys.stdout)
        ]
    )

def validate_keytab(path):
    if not isinstance(path, str) or "\x00" in path:
        raise ValueError("Invalid keytab path")

    base = os.path.basename(path)
    if not KEYTAB_NAME.fullmatch(base):
        raise ValueError("Keytab must be a .keytab file under /etc/squid")

    candidate = os.path.join(KEYTAB_DIR, base)
    real = os.path.realpath(candidate)
    if os.path.dirname(real) != KEYTAB_DIR or not os.path.isfile(real):
        raise ValueError("Keytab not found under /etc/squid")

    return real

def allowed_peer_uids():
    global ALLOWED_UIDS
    if ALLOWED_UIDS is not None:
        return ALLOWED_UIDS
    uids = {0}
    try:
        uids.add(pwd.getpwnam("squidmgr").pw_uid)
    except KeyError:
        logging.error("User squidmgr not found; only root may call spmd")
    ALLOWED_UIDS = uids
    return ALLOWED_UIDS

def peer_credentials(conn):
    # Linux SO_PEERCRED (CentOS 9): pid, uid, gid
    SO_PEERCRED = 17
    creds = conn.getsockopt(socket.SOL_SOCKET, SO_PEERCRED, struct.calcsize("3i"))
    pid, uid, gid = struct.unpack("3i", creds)
    return pid, uid, gid

def validate_command(command_key, extra_args):
    if command_key not in ALLOWED_COMMANDS:
        raise ValueError(f"Command not in whitelist: {command_key}")

    if extra_args is None:
        extra_args = []
    if not isinstance(extra_args, list):
        raise ValueError("Invalid args")

    cmd = list(ALLOWED_COMMANDS[command_key])

    if command_key == "kinit_test":
        if len(extra_args) != 2:
            raise ValueError("kinit_test requires keytab and principal")
        cmd.append(validate_keytab(extra_args[0]))
        princ = extra_args[1]
        if not isinstance(princ, str) or not PRINC_RE.fullmatch(princ):
            raise ValueError("Invalid principal")
        cmd.append(princ)
        return cmd

    if command_key == "acl_file_install":
        if len(extra_args) != 1:
            raise ValueError("acl_file_install requires exactly one filename")
        install_acl_file(extra_args[0])
        return ["__acl_file_install__", extra_args[0]]

    if command_key == "keytab_install":
        if len(extra_args) != 1:
            raise ValueError("keytab_install requires exactly one filename")
        install_keytab(extra_args[0])
        return ["__keytab_install__", extra_args[0]]

    if command_key == "ad_ldap_groups":
        if len(extra_args) == 1:
            names = list_ad_ldap_groups_from_staging(extra_args[0])
        elif len(extra_args) == 4:
            names = list_ad_ldap_groups(extra_args[0], extra_args[1], extra_args[2], extra_args[3])
        else:
            raise ValueError("ad_ldap_groups requires staging file or keytab,realm,host,principal")
        return ["__ad_ldap_groups__", "\n".join(names)]

    if command_key == "squid_listen_apply":
        if extra_args:
            raise ValueError("Extra arguments are not allowed")
        msg = apply_squid_listen()
        return ["__squid_listen_apply__", msg]

    if command_key == "squid_policy_apply":
        if extra_args:
            raise ValueError("Extra arguments are not allowed")
        msg = apply_squid_policy()
        return ["__squid_policy_apply__", msg]

    if command_key == "nginx_allow_apply":
        if extra_args:
            raise ValueError("Extra arguments are not allowed")
        msg = apply_nginx_allow()
        return ["__nginx_allow_apply__", msg]

    if extra_args:
        raise ValueError("Extra arguments are not allowed")

    if command_key == "squid_syntax" and not os.path.isfile(PARSE_FILE):
        raise ValueError("Parse staging file is missing")

    return cmd

def handle_client(conn):
    # Set timeout to prevent hung connections from accumulating
    conn.settimeout(60)
    try:
        try:
            pid, uid, gid = peer_credentials(conn)
        except OSError as e:
            logging.warning(f"SO_PEERCRED failed: {e}")
            try:
                conn.sendall(json.dumps({"success": False, "error": "peer check failed"}).encode("utf-8"))
            except (BrokenPipeError, OSError):
                pass
            return
        if uid not in allowed_peer_uids():
            logging.warning(f"Rejected socket client uid={uid} pid={pid} gid={gid}")
            try:
                conn.sendall(json.dumps({"success": False, "error": "unauthorized"}).encode("utf-8"))
            except (BrokenPipeError, OSError):
                pass
            return

        data = b""
        while True:
            try:
                chunk = conn.recv(4096)
            except socket.timeout:
                logging.warning("Client socket read timeout")
                break
            if not chunk:
                break
            data += chunk

        if not data:
            return

        try:
            request = json.loads(data.decode('utf-8'))
        except json.JSONDecodeError as e:
            logging.error(f"Invalid JSON received: {str(e)}")
            response = {"success": False, "error": "Invalid JSON: " + str(e)}
            try:
                conn.sendall(json.dumps(response).encode('utf-8'))
            except (BrokenPipeError, OSError):
                pass
            return

        command_key = request.get('command')
        extra_args = request.get('args', [])

        logging.info(f"Executing: {command_key} with args: {extra_args}")

        cmd = validate_command(command_key, extra_args)

        if cmd and cmd[0] == "__acl_file_install__":
            response = {
                "success": True,
                "exit_code": 0,
                "stdout": "installed " + cmd[1],
                "stderr": "",
            }
            logging.info("Result: acl file installed %s", cmd[1])
            try:
                conn.sendall(json.dumps(response).encode("utf-8"))
            except (BrokenPipeError, OSError) as e:
                logging.warning(f"Failed to send response: {str(e)}")
            return

        if cmd and cmd[0] == "__keytab_install__":
            response = {
                "success": True,
                "exit_code": 0,
                "stdout": "installed " + cmd[1],
                "stderr": "",
            }
            logging.info("Result: keytab installed %s", cmd[1])
            try:
                conn.sendall(json.dumps(response).encode("utf-8"))
            except (BrokenPipeError, OSError) as e:
                logging.warning(f"Failed to send response: {str(e)}")
            return

        if cmd and cmd[0] == "__ad_ldap_groups__":
            response = {
                "success": True,
                "exit_code": 0,
                "stdout": cmd[1],
                "stderr": "",
            }
            logging.info("Result: LDAP groups listed")
            try:
                conn.sendall(json.dumps(response).encode("utf-8"))
            except (BrokenPipeError, OSError) as e:
                logging.warning(f"Failed to send response: {str(e)}")
            return

        if cmd and cmd[0] in ("__squid_listen_apply__", "__squid_policy_apply__", "__nginx_allow_apply__"):
            response = {
                "success": True,
                "exit_code": 0,
                "stdout": cmd[1],
                "stderr": "",
            }
            logging.info("Result: %s", cmd[0])
            try:
                conn.sendall(json.dumps(response).encode("utf-8"))
            except (BrokenPipeError, OSError) as e:
                logging.warning(f"Failed to send response: {str(e)}")
            return

        run_kw = {"capture_output": True, "text": True, "timeout": 30}
        if command_key == "kinit_test":
            os.makedirs("/run/spmd", mode=0o700, exist_ok=True)
            env = os.environ.copy()
            env["KRB5CCNAME"] = "FILE:/run/spmd/kinit-test.ccache"
            run_kw["env"] = env
            run_kw["timeout"] = 12
        try:
            result = subprocess.run(cmd, **run_kw)
        except subprocess.TimeoutExpired:
            response = {
                "success": False,
                "exit_code": 124,
                "stdout": "",
                "stderr": "kinit timed out (KDC/DNS). Check Service Principal and krb5.conf.",
            }
            try:
                conn.sendall(json.dumps(response).encode("utf-8"))
            except (BrokenPipeError, OSError) as e:
                logging.warning(f"Failed to send response: {str(e)}")
            return
        finally:
            if command_key == "kinit_test":
                try:
                    subprocess.run(
                        [KDESTROY, "-c", "FILE:/run/spmd/kinit-test.ccache"],
                        capture_output=True,
                        timeout=5,
                    )
                except Exception:
                    pass
                try:
                    os.unlink("/run/spmd/kinit-test.ccache")
                except OSError:
                    pass

        exit_ok = result.returncode == 0
        if command_key in ("squid_status", "winbind_status") and result.returncode in (0, 3, 4):
            exit_ok = True

        response = {
            "success": exit_ok,
            "exit_code": result.returncode,
            "stdout": result.stdout,
            "stderr": result.stderr,
        }

        logging.info(f"Result: exit_code={result.returncode}")

        try:
            conn.sendall(json.dumps(response).encode('utf-8'))
        except (BrokenPipeError, OSError) as e:
            logging.warning(f"Failed to send response: {str(e)}")

    except Exception as e:
        logging.error(f"Error: {str(e)}")
        try:
            response = {"success": False, "error": str(e)}
            conn.sendall(json.dumps(response).encode('utf-8'))
        except (BrokenPipeError, OSError):
            pass

def main():
    setup_logging()

    # Check root
    if os.geteuid() != 0:
        logging.error("spmd must run as root")
        sys.exit(1)

    # Cleanup old socket
    if os.path.exists(SOCKET_PATH):
        os.unlink(SOCKET_PATH)

    # Write PID
    with open(PID_FILE, 'w') as f:
        f.write(str(os.getpid()))

    sock = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
    sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    sock.bind(SOCKET_PATH)
    sock.listen(10)

    # squidmgr must be able to connect; process runs as root:squidmgr
    os.chmod(SOCKET_PATH, 0o660)
    try:
        import grp
        gid = grp.getgrnam("squidmgr").gr_gid
        os.chown(SOCKET_PATH, 0, gid)
    except KeyError:
        pass

    logging.info(f"SPM Agent started on {SOCKET_PATH}")

    try:
        while True:
            conn, addr = sock.accept()
            try:
                handle_client(conn)
            finally:
                conn.close()
    except KeyboardInterrupt:
        logging.info("Shutting down...")
    finally:
        sock.close()
        if os.path.exists(SOCKET_PATH):
            os.unlink(SOCKET_PATH)
        if os.path.exists(PID_FILE):
            os.unlink(PID_FILE)

if __name__ == "__main__":
    main()
