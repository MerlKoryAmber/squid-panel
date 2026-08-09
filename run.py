# run.py
from flask import Flask, request, redirect, url_for, render_template, flash, current_app
from app import create_app, db
from app.utils.logger import log_action
from werkzeug.security import generate_password_hash, check_password_hash

def init_db()
    from app.models import User, AuditLog, db
    db.create_all()
    admin = User(username="admin", password_hash=generate_password_hash("admin"), role="admin")
operator = User(username="operator", password_hash=generate_password_hash("operator"), role="operator")
    db.session.add(admin)
db.session.add(operator)
    db.session.commit()
    log_action(current_app, "db_created", "Created initial admin/operator users")

from app.utils.squid_parser import parse_squid_conf, import_existing_config

def import_config():
    text = current_app.config.get("SQUID_CONF_PATH", "/etc/squid/squid.conf")
    with open(text) as f:
        content = f.read()
    config = parse_squid_conf(content)
    from app.models import Acl, Rule, Peer, AuthParam, LogAccessRule
    existing_acls = [Acl(name=name) for name in config.get("acls", [])]
db.session.add_all(existing_acls)
    existing_rules = [
        Rule(
            action=rule["action"],
            acl_names=[acl_name for acl_name, _ in rule["acl_names"] if acl_name is not None],
        )
        for rule in config.get("http_access", [])
    ]
db.session.add_all(existing_rules)
    existing_peers = [
        Peer(
            hostname=peer["hostname"], type=peer["type"], proxy_port=peer["proxy_port"], icp_port=peer["icp_port"], options=" ".join(peer.get("options", [])),
        )
        for peer in config.get("cache_peers", [])
    ]
db.session.add_all(existing_peers)
    existing_auth_params = [
        AuthParam(
            scheme=param["scheme"], params={p["param"]: p["value"] for p in param["params"]},
        )
        for param in config.get("auth_params", [])
    ]
db.session.add_all(existing_auth_params)
    db.session.commit()
    log_action(current_app, "config_imported", f"Imported Squid config from {text}")

if __name__ == "__main__":
    app = create_app()
    if request.method == "POST" and request.form.get("command") in ("init_db", "import_config"):
        getattr(__import__("app.utils.cli"), request.form["command"])(request.app)
        return redirect(url_for(request.form["command"]))
    app.run(debug=True, host="127.0.0.1", port=8080)