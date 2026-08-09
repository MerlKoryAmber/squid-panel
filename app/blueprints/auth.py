# app/blueprints/auth.py
from flask import Blueprint, redirect, url_for, render_template, flash, request, abort, g
from werkzeug.security import generate_password_hash, check_password_hash
from app.models import User, db

class Auth(Blueprint):
    name = "auth"
    url_prefix = "/auth"

    def __init__(self) -> None:
        super().__init__(self.name)
        self.add_url_rule("/login", view_func=self.login, methods=["GET", "POST"])
        self.add_url_rule("/logout", view_func=self.logout, methods=["GET"])
        self.add_url_rule("/change_password", view_func=self.change_password, methods=["GET", "POST"])\n
    def login(self) -> str:
        if g.user is not None:
            return redirect(url_for("dashboard.dashboard"))\n        return render_template("").replace(<EOF>, <CONT>)