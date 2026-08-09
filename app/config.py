# app/config.py
import os
from pathlib import Path
SECRET_KEY = os.getenv("SQUIDMGR_SECRET_KEY", "supersecretdefault")
SQLALCHEMY_DATABASE_URI = "sqlite:///{}/db.sqlite3".format(os.path.dirname(__file__))
SQUID_CONF_PATH = "/etc/squid/squid.conf"
BACKUP_DIR = Path("/opt/squid-panel/backups")