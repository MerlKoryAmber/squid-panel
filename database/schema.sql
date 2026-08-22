-- Squid Proxy Manager Database Schema
-- SQLite

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'operator' CHECK(role IN ('admin', 'operator')),
    language TEXT DEFAULT 'ru',
    policy_ui TEXT NOT NULL DEFAULT 'expert' CHECK(policy_ui IN ('simple', 'expert')),
    created_at TEXT,
    updated_at TEXT
);

CREATE TABLE IF NOT EXISTS acls (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    type TEXT NOT NULL,
    entries TEXT NOT NULL,
    storage TEXT NOT NULL DEFAULT 'inline',
    description TEXT,
    group_name TEXT DEFAULT '',
    created_at TEXT,
    updated_at TEXT
);

CREATE TABLE IF NOT EXISTS http_access_rules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    action TEXT NOT NULL CHECK(action IN ('allow', 'deny')),
    acls TEXT NOT NULL,
    description TEXT,
    enabled INTEGER NOT NULL DEFAULT 1,
    sort_order INTEGER DEFAULT 0,
    created_at TEXT,
    updated_at TEXT
);

CREATE TABLE IF NOT EXISTS cache_peers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL DEFAULT '',
    hostname TEXT NOT NULL,
    peer_type TEXT NOT NULL CHECK(peer_type IN ('parent', 'sibling', 'multicast')),
    http_port INTEGER DEFAULT 3128,
    icp_port INTEGER DEFAULT 0,
    proxy_only INTEGER DEFAULT 0,
    no_query INTEGER DEFAULT 0,
    no_digest INTEGER DEFAULT 0,
    weight INTEGER DEFAULT 0,
    login TEXT DEFAULT '',
    connect_timeout INTEGER DEFAULT 0,
    access_acl TEXT DEFAULT '',
    options TEXT DEFAULT '',
    status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active', 'disabled')),
    created_at TEXT,
    updated_at TEXT
);

CREATE TABLE IF NOT EXISTS cache_peer_access_rules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    peer_id INTEGER NOT NULL,
    hostname TEXT NOT NULL,
    acl_name TEXT NOT NULL,
    acl_entries TEXT NOT NULL DEFAULT '',
    action TEXT NOT NULL CHECK(action IN ('allow', 'deny')),
    negated INTEGER DEFAULT 0,
    sort_order INTEGER DEFAULT 0,
    created_at TEXT,
    updated_at TEXT,
    FOREIGN KEY (peer_id) REFERENCES cache_peers(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS routing_rules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    directive TEXT NOT NULL CHECK(directive IN ('never_direct', 'always_direct', 'prefer_direct')),
    action TEXT NOT NULL CHECK(action IN ('allow', 'deny')),
    acl_name TEXT NOT NULL,
    negated INTEGER DEFAULT 0,
    sort_order INTEGER DEFAULT 0,
    created_at TEXT
);

CREATE TABLE IF NOT EXISTS cascade_routes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    from_acls TEXT NOT NULL DEFAULT '[]',
    to_acls TEXT NOT NULL DEFAULT '[]',
    channel TEXT NOT NULL CHECK(channel IN ('peer', 'direct')),
    peer_id INTEGER,
    sort_order INTEGER DEFAULT 0,
    created_at TEXT,
    updated_at TEXT,
    FOREIGN KEY (peer_id) REFERENCES cache_peers(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS auth_config (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    scheme TEXT NOT NULL CHECK(scheme IN ('basic', 'digest', 'negotiate', 'ntlm')),
    program TEXT,
    children INTEGER DEFAULT 5,
    realm TEXT,
    credentialsttl TEXT,
    keep_alive TEXT,
    domain TEXT,
    dc TEXT,
    backup_dc TEXT,
    keytab_path TEXT,
    principal TEXT DEFAULT '',
    kdc TEXT DEFAULT '',
    admin_server TEXT DEFAULT '',
    helper TEXT DEFAULT '',
    children_extra TEXT DEFAULT '',
    created_at TEXT,
    updated_at TEXT
);

CREATE TABLE IF NOT EXISTS squid_globals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    http_port TEXT DEFAULT '3128',
    icp_port TEXT DEFAULT '',
    cache_dir TEXT DEFAULT '',
    visible_hostname TEXT DEFAULT '',
    dns_nameservers TEXT DEFAULT '',
    coredump_dir TEXT DEFAULT '',
    extra_conf TEXT DEFAULT '',
    request_header_access TEXT DEFAULT '',
    updated_at TEXT
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user TEXT NOT NULL,
    action TEXT NOT NULL,
    details TEXT,
    ip_address TEXT,
    created_at TEXT
);

CREATE TABLE IF NOT EXISTS external_acl_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL,
    format TEXT NOT NULL DEFAULT '%LOGIN',
    ttl INTEGER DEFAULT 3600,
    negative_ttl INTEGER DEFAULT 60,
    children INTEGER DEFAULT 10,
    program TEXT NOT NULL,
    options TEXT DEFAULT '',
    created_at TEXT,
    updated_at TEXT
);

CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    language TEXT DEFAULT 'ru',
    theme TEXT DEFAULT 'light',
    panel_allow_ips TEXT NOT NULL DEFAULT '',
    simple_ui_enabled INTEGER NOT NULL DEFAULT 0,
    updated_at TEXT
);

CREATE INDEX IF NOT EXISTS idx_audit_user ON audit_logs(user);
CREATE INDEX IF NOT EXISTS idx_audit_action ON audit_logs(action);
CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_acl_name ON acls(name);
CREATE INDEX IF NOT EXISTS idx_acl_group ON acls(group_name);
CREATE INDEX IF NOT EXISTS idx_ext_acl_name ON external_acl_types(name);
CREATE INDEX IF NOT EXISTS idx_peer_access_peer ON cache_peer_access_rules(peer_id);
CREATE INDEX IF NOT EXISTS idx_peer_access_acl ON cache_peer_access_rules(acl_name);
