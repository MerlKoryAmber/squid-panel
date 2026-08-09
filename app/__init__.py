# app/__init__.py
from flask import Flask
from app.config import create_app
from app.utils.squid_parser import parse_squid_conf, import_existing_config
import app.blueprints.auth
import app.blueprints.dashboard
import app.blueprints.config_editor
import app.blueprints.acls
import app.blueprints.rules
import app.blueprints.peers
import app.blueprints.auth_methods
import app.blueprints.logs
import app.blueprints.backup
from app.utils.logger import log_action

app = create_app()

# Run imports to populate blueprints and models for Flask extension registration
app.register_blueprint(auth.auth, url_prefix="/auth")
app.register_blueprint(dashboard.dashboard, url_prefix="/dashboard")
app.register_blueprint(config_editor.config_editor, url_prefix="/config")
app.register_blueprint(acls.acls, url_prefix="/acls")
app.register_blueprint(rules.rules, url_prefix="/rules")
app.register_blueprint(peers.peers, url_prefix="/peers")
app.register_blueprint(auth_methods.auth_methods, url_prefix="/auth_methods")
app.register_blueprint(logs.logs, url_prefix="/logs")
app.register_blueprint(backup.backup, url_prefix="/backup")

# Initialize SQLAlchemy and Flask-Login before any route runs so models are known
from app.models import db, UserMixin, AuditLog

import app.utils.sudo
import app.utils.logger

log_action(app, "app_initialized", "Initialized Squid Proxy Manager application")