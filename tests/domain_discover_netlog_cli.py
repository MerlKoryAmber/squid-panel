#!/usr/bin/env python3
"""CLI: NetLog parser for domain discover — request URLs only, ignore constants noise."""
import importlib.util
import json
import os
import sys
import tempfile
import types

# spmd imports pwd/grp (Unix); stub on Windows for unit test.
for name in ("pwd", "grp"):
    if name not in sys.modules:
        sys.modules[name] = types.ModuleType(name)

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SPMD = os.path.join(ROOT, "agent", "spmd.py")

spec = importlib.util.spec_from_file_location("spm_spmd", SPMD)
mod = importlib.util.module_from_spec(spec)
spec.loader.exec_module(mod)

fail = 0


def expect(ok, msg):
    global fail
    if not ok:
        sys.stderr.write("FAIL %s\n" % msg)
        fail += 1
    else:
        print("ok %s" % msg)


# Constants noise must not become hosts (old regex bug).
noise = {
    "constants": {
        "clientInfo": {
            "command_line": "https://www.google.com/ https://www.youtube.com/ https://fonts.gstatic.com/"
        }
    },
    "events": [
        {
            "params": {"url": "https://fx.interros.ru/"},
            "phase": 1,
            "type": 1,
        },
        {
            "params": {"url": "https://fx.interros.ru/assets/index.js"},
            "phase": 1,
            "type": 1,
        },
        {
            "params": {"url": "https://cdn.partner.example.com/x.js"},
            "phase": 1,
            "type": 1,
        },
        {
            "params": {"url": "chrome-extension://abcdef/page.html"},
            "phase": 1,
            "type": 1,
        },
        {
            "params": {"url_before_redirect": "http://old.partner.example.com/r"},
            "phase": 1,
            "type": 1,
        },
    ],
}

fd, path = tempfile.mkstemp(suffix=".json")
os.close(fd)
try:
    with open(path, "w", encoding="utf-8") as fh:
        json.dump(noise, fh)
    got = mod._hosts_from_netlog(path)
finally:
    try:
        os.unlink(path)
    except OSError:
        pass

expect(got == ["example.com", "interros.ru"], "hosts from events only: %s" % got)
expect("google.com" not in got, "no google from constants")
expect("youtube.com" not in got, "no youtube from constants")
expect("gstatic.com" not in got, "no gstatic from constants")

fd, bad = tempfile.mkstemp(suffix=".json")
os.close(fd)
try:
    with open(bad, "w", encoding="utf-8") as fh:
        fh.write("not-json https://www.google.com/\n")
    got_bad = mod._hosts_from_netlog(bad)
finally:
    try:
        os.unlink(bad)
    except OSError:
        pass
expect(got_bad == [], "invalid JSON -> empty (no regex fallback)")

expect(mod._host_from_http_url("https://A.Example.COM/x") == "a.example.com", "host normalize")
expect(mod._host_from_http_url("data:text/html,hi") is None, "skip data:")
expect(mod._to_second_level("fx.interros.ru") == "interros.ru", "2nd level fx")

sys.exit(1 if fail else 0)
