# app/decorators.py
def admin_required(f):
    def wrapped(*args, **kwargs):
        if not current_user.is_authenticated or current_user.role != "admin":
            abort(403)
        return f(*args, **kwargs)
    wrapped.__name__ = f.__name__
    return wrapped
def operator_required(f):
    def wrapped(*args, **kwargs):
        if not current_user.is_authenticated or current_user.role not in ("admin", "operator"):
            abort(403)
        return f(*args, **kwargs)
    wrapped.__name__ = f.__name__
    return wrapped