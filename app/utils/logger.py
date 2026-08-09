# app/utils/logger.py
from flask import current_app, g, abort

def log_action(app, user, action):
    audit = AuditLog(user=user, action=action)
    db.session.add(audit)
    db.session.commit()