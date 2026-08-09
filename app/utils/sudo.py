# app/utils/sudo.py
import subprocess, shlex, time, os, sys, json, tempfile, shutil

def run_as_squidmgr(command_list, input_text=None):
    try:
        proc = subprocess.run(
            ["sudo", "-u", "squidmgr"] + command_list,
            capture_output=True,
            text=True,
            input=input_text or "",
            timeout=30,
        )
        return (proc.returncode, proc.stdout.strip(), proc.stderr.strip())
    except subprocess.TimeoutExpired:
        return (124, None, "Command timed out after 30 seconds")
    except FileNotFoundError as e:
        return (127, None, f"Command not found: {e.args[0]}")
    except Exception as e:
        return (1, None, f"Unexpected error: {type(e).__name__} - {str(e)}")
