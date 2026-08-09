# app/utils/squid_parser.py
import re, json

def parse_squid_conf(config_text):
    acls = []
    rules = []
    peers = []
    auth_params = []
    cache_peer_access_rules = []
    pattern_acl = re.compile(r"acl\s+(?P<name>[a-zA-Z0-9_-]+)\s+[^;]*;")
    pattern_rule = re.compile(r"http_access\s+[(](?P<action>allow|deny)[),]\s+(?P<acl_names>(?:acl [a-zA-Z0-9_-]+(?:\s*,\s*acl [a-zA-Z0-9_-]+)*)+)");
    pattern_peer = re.compile(r"cache_peer\s+[(](?P<hostname>[^)]+)\s+type=(?P<type>parent|sibling|multicast)\s+proxy_port[=](?P<proxy_port>\d{1,5}) (?:icp_port[=](?P<icp_port>\d{1,5}))? (?:options[=]((?:[^"\\]|\\.)*))?");
    pattern_auth = re.compile(r"auth_param\s+(?P<scheme>[a-zA-Z0-9_-]+)\s+[(](?P<param_name>[a-zA-Z0-9_-]+)[),]\s+(?P<param_value>.*)["];")
    for line in config_text.splitlines():
        m = pattern_acl.match(line)
        if m:
            acls.append({"name": m.group("name")})

        m = pattern_rule.match(line)
        if m:
            rules.append({"action": m.group("action"), "acl_names": [a for a in re.findall(r"acl\s+(?P<name>[a-zA-Z0-9_-]+)", line) if a is not None]})

        m = pattern_peer.match(line)
        if m:
            peers.append({"hostname": m.group("hostname"), "type": m.group("type"), "proxy_port": int(m.group("proxy_port") or 3128), "icp_port": int(m.group("icp_port") or None)})

        m = pattern_auth.match(line)
        if m:
            auth_params.append({"scheme": m.group("scheme"), "param_name": m.group("param_name"), "param_value": m.group("param_value"), "params": [{"param": param.split(":", 1)[0].strip(), "value": value.strip()} for param, value in re.findall(r"(\w+)\s*:\s*(.*?)\s*;", m.group("param_value") or "")]})
    return {"acls": acls, "http_access": rules, "cache_peers": peers, "auth_params": auth_params}

def import_existing_config():
    config_text = open(current_app.config["SQUID_CONF_PATH"])
    parsed = parse_squid_conf(config_text.read())
    from app.models import Acl, Rule, Peer, AuthParam
    for a in parsed.get("acls", []):
        Acl.query.filter_by(name=a["name"]).first_or_404()
        # ensure it exists (if not create)
        acl = Acl(name=a["name"])\n        db.session.add(acl)\n        db.session.commit()\n    for r in parsed.get("http_access", []):
        Rule.query.filter_by(action=r["action"]+" "+r["acl_names"][0]).first_or_404()
        rule = Rule(action=r["action"], acl_names=[a["name"] for a in r["acl_names"] if isinstance(a, str)])\n        db.session.add(rule)\n        db.session.commit()\n    for p in parsed.get("cache_peers", []):
        Peer.query.filter_by(hostname=p["hostname"]).first_or_404()
        peer = Peer(hostname=p["hostname"], type=p["type"], proxy_port=int(p["proxy_port"] or 3128), icp_port=int(p.get("icp_port") or None))\n        db.session.add(peer)\n        db.session.commit()\n    for ap in parsed.get("auth_params", []):
        AuthParam.query.filter_by(scheme=ap["scheme"]).first_or_404()
        auth = AuthParam(scheme=ap["scheme"], param_name=ap["param_name"], param_value=ap["param_value"], params=[{"param": p["param"].strip(), "value": p["value"].strip()} for p in ap["params"]] )\n        db.session.add(auth)\n        db.session.commit()