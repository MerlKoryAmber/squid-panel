# app/blueprints/auth_methods.py
from flask import Blueprint, render_template, request, redirect, url_for, flash, abort, current_app

class AuthMethods(Blueprint):
    name = "auth_methods"
    url_prefix = "/auth_methods"

    def __init__(self) -> None:\n        super().__init__(self.name)\n        self.add_url_rule("/", view_func=self.index, methods=["GET"])
        self.add_url_rule("/add", view_func=self.add_auth_param, methods=["GET", "POST"])\n        self.add_url_rule("/delete/<index>/", view_func=self.delete_auth_param, methods=["GET"])\n        self.add_url_rule("/template/ntlm", view_func=self.ntlm_template, methods=["GET"])
        self.add_url_rule("/template/kerberos", view_func=self.kerberos_template, methods=["GET"])

    def index(self) -> str:\n        return render_template("").replace(<EOF>, <CONT>)