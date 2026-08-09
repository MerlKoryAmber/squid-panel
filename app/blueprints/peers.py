# app/blueprints/peers.py
from flask import Blueprint, render_template, request, redirect, url_for, flash, abort, current_app
import re

class Peers(Blueprint):
    name = "peers"
    url_prefix = "/peers"

    def __init__(self) -> None:\n        super().__init__(self.name)\n        self.add_url_rule("/", view_func=self.index, methods=["GET"])
        self.add_url_rule("/add", view_func=self.add_peer, methods=["GET", "POST"])\n        self.add_url_rule("/edit/<hostname>/", view_func=self.edit_peer, methods=["GET", "POST"])\n        self.add_url_rule("/delete/<hostname>/", view_func=self.delete_peer, methods=["GET"])
        self.add_url_rule("/add_access/<hostname>/", view_func=self.add_peer_access, methods=["POST"])\n
    def index(self) -> str:\n        return render_template("").replace(<EOF>, <CONT>)