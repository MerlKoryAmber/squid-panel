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
from pathlib import Path

SOCKET_PATH = "/run/spmd.sock"
PID_FILE = "/run/spmd.pid"
LOG_FILE = "/var/log/spmd.log"

ALLOWED_COMMANDS = {
    "squid_reconfigure": ["/usr/sbin/squid", "-k", "reconfigure"],
    "squid_restart": ["/usr/bin/systemctl", "restart", "squid"],
    "squid_start": ["/usr/bin/systemctl", "start", "squid"],
    "squid_stop": ["/usr/bin/systemctl", "stop", "squid"],
    "squid_status": ["/usr/bin/systemctl", "status", "squid", "--no-pager", "-o", "short"],
    "squid_syntax": ["/usr/sbin/squid", "-k", "parse"],
    "squid_version": ["/usr/sbin/squid", "-v"],
    "winbind_status": ["/usr/bin/systemctl", "status", "winbind", "--no-pager", "-o", "short"],
    "kinit_test": ["/usr/bin/kinit", "-k", "-t"],
    "wbinfo_test": ["/usr/bin/wbinfo", "-t"],
    "net_ads_info": ["/usr/bin/net", "ads", "info"],
}

def setup_logging():
    logging.basicConfig(
        level=logging.INFO,
        format='%(asctime)s [%(levelname)s] %(message)s',
        handlers=[
            logging.FileHandler(LOG_FILE),
            logging.StreamHandler(sys.stdout)
        ]
    )

def validate_command(command_key, extra_args):
    if command_key not in ALLOWED_COMMANDS:
        raise ValueError(f"Command not in whitelist: {command_key}")

    cmd = list(ALLOWED_COMMANDS[command_key])

    # Validate extra args against injection
    for arg in extra_args:
        if any(c in arg for c in [";", "&", "|", "<", ">", "$", "`", "\\"]):
            raise ValueError(f"Invalid character in argument: {arg}")
        cmd.append(arg)

    return cmd

def handle_client(conn):
    try:
        data = b""
        while True:
            chunk = conn.recv(4096)
            if not chunk:
                break
            data += chunk

        if not data:
            return

        request = json.loads(data.decode('utf-8'))
        command_key = request.get('command')
        extra_args = request.get('args', [])

        logging.info(f"Executing: {command_key} with args: {extra_args}")

        cmd = validate_command(command_key, extra_args)

        result = subprocess.run(
            cmd,
            capture_output=True,
            text=True,
            timeout=30
        )

        response = {
            "success": result.returncode == 0,
            "exit_code": result.returncode,
            "stdout": result.stdout,
            "stderr": result.stderr,
        }

        logging.info(f"Result: exit_code={result.returncode}")

    except Exception as e:
        logging.error(f"Error: {str(e)}")
        response = {
            "success": False,
            "error": str(e)
        }

    conn.sendall(json.dumps(response).encode('utf-8'))

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
    sock.bind(SOCKET_PATH)
    sock.listen(5)

    # Set permissions so squidmgr can connect
    os.chmod(SOCKET_PATH, 0o660)

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
