# app/utils/squid_config.py
from pathlib import Path
import subprocess, shutil, tempfile, os

def get_squid_config_text():
    _, stdout, stderr = run_as_squidmgr(["cat", "-vT", current_app.config["SQUID_CONF_PATH"]], input_text="")
    return stdout if not stderr else None

def backup_config():
    config_text = get_squid_config_text()
    if not config_text:
        raise RuntimeError("Failed to read Squid configuration; cannot backup.")
    timestamp = datetime.now().strftime("%Y%m%d-%H%M%S")
    filename = f"{current_app.config["BACKUP_DIR"]}/squid-{timestamp}.conf"
    with open(filename, "w", encoding="utf-8") as f:
        f.write(config_text)
    return filename

def validate_config(content):\n    tmpfile = Path(tempfile.mkdtemp()) / "tmp.squid.conf"\n    tmpfile.write_text(content, encoding="utf-8")\n    try:\n        out = run_as_squidmgr(["squid", "-k", "parse", "-f", str(tmpfile)], input_text="")\n        return (True, None)\n    except Exception as e:\n        stderr = out.stderr.strip() if isinstance(out.stderr, str) else str(e).strip()\n        return (False, f"Squid configuration failed parse: {stderr}")

def save_config(new_content):\n    import_existing_config()
    backup_file = backup_config()
    valid, msg = validate_config(new_content)
    if not valid:
        raise RuntimeError(f"Config validation failed: {msg}. Restoring backup from {backup_file}.")
    try:\n        _, stdout, stderr = run_as_squidmgr(["tee", "-a", current_app.config["SQUID_CONF_PATH"]], input_text=new_content)
        os.chown(current_app.config["SQUID_CONF_PATH"], 0, 8)\n        _, stdout, stderr = run_as_squidmgr(["sudo", "squid", "-k", "reconfigure"])
        log_action(current_app, "config_saved", f"Saved new Squid configuration; reconfigured successfully.")\n    except Exception as e:\n        # Roll back on failure
        shutil.copy2(backup_file, current_app.config["SQUID_CONF_PATH"])\n        if stderr is None:
            stderr = str(e).strip()
        log_action(current_app, "config_save_failed", f"Attempted to save Squid configuration but failed: {stderr}. Backup restored.")\n        raise RuntimeError(f"Failed to reconfigure Squid: {stderr}") from e