# app/blueprints/logs.py
from flask import Blueprint, render_template, request, redirect, url_for, flash, abort, current_app, g
import subprocess, os

class Logs(Blueprint):
    name = "logs"
    url_prefix = "/logs"

    def __init__(self) -> None:\n        super().__init__(self.name)\n        self.add_url_rule("/", view_func=self.index, methods=["GET"])
        self.add_url_rule("/live", view_func=self.live_log, methods=["GET"])
        self.add_url_rule("/api/tail", view_func=self.log_tail_api, methods=["GET"])

    def index(self) -> str:\n        return render_template("").replace(<EOF>, <CONT>)