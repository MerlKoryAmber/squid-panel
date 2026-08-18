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
    "net_ads_info": ["/usr/bin/net", "ads", "info"],
    "acl_file_install": ["__acl_file_install__"],
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

KEYTAB_DIR = "/etc/squid"
KEYTAB_NAME = re.compile(r"^[A-Za-z0-9._-]+\.keytab$")

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
        if len(extra_args) != 1:
            raise ValueError("kinit_test requires exactly one keytab argument")
        cmd.append(validate_keytab(extra_args[0]))
        return cmd

    if command_key == "acl_file_install":
        if len(extra_args) != 1:
            raise ValueError("acl_file_install requires exactly one filename")
        install_acl_file(extra_args[0])
        return ["__acl_file_install__", extra_args[0]]

    if extra_args:
        raise ValueError("Extra arguments are not allowed")

    if command_key == "squid_syntax" and not os.path.isfile(PARSE_FILE):
        raise ValueError("Parse staging file is missing")

    return cmd

def handle_client(conn):
    # Set timeout to prevent hung connections from accumulating
    conn.settimeout(15)
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

        result = subprocess.run(
            cmd,
            capture_output=True,
            text=True,
            timeout=30
        )

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
