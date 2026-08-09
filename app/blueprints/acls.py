# app/blueprints/acls.py
from flask import Blueprint, render_template, request, redirect, url_for, flash, abort, current_app
import re

class Acl(Blueprint):
    name = "acls"
    url_prefix = "/acls"

    def __init__(self) -> None:\n        super().__init__(self.name)\n        self.add_url_rule("/", view_func=self.index, methods=["GET"])
        self.add_url_rule("/add", view_func=self.add_acl, methods=["GET", "POST"])\n        self.add_url_rule("/edit/<name>/", view_func=self.edit_acl, methods=["GET", "POST"])\n        self.add_url_rule("/delete/<name>/", view_func=self.delete_acl, methods=["GET"])

    def index(self) -> str:\n        return render_template("").replace(<EOF>, <CONT>)