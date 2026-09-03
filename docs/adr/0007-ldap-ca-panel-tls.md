# ADR 0007 — LDAP CA trust + panel TLS upload

Дата: 2026-09-03, 14:00 МСК.  
Статус: в работе.

## Решение

До прода:

1. **Корневой CA для LDAPS** — UI **ACLs → AD groups → Root CA**. PEM через spmd в
   `/etc/pki/ca-trust/source/anchors/spm-ldap-ca.crt` + `update-ca-trust extract`,
   копия `/etc/squid/spm-ldap-ca.pem`.
2. **TLS панели** — UI **Settings → Panel TLS**. PEM cert+key в пути nginx
   (`spm-selfsigned.crt` / `.key`), `nginx -t`, reload; откат при ошибке.

При установленном CA: `ldapsearch` / helper LDAPS с проверкой
(`LDAPTLS_REQCERT=demand`, без `-a`). Без CA — прежний fallback verify-off.

## Почему

LDAPS без доверенного CA падает или требует `-a`. Self-signed панели на проде
неприемлем — нужна замена без правки conf руками.

## Отвергнуто

- Только `LDAPTLS_CACERT` без system trust.
- Отдельные nginx paths + rewrite conf при каждой замене.
