# TLS CA + panel cert (pre-prod)

Дата: 2026-09-03, ~14:10 МСК.  
Статус: РЕАЛИЗОВАНО НО НЕ ПРИНЯТО.

## Сделано

- ADR 0007.
- spmd: `ca_trust_install`, `panel_tls_install`.
- UI: AD groups → Root CA; Settings → Panel TLS.
- LDAPS: при CA — verify on; без CA — `-a` / `REQCERT=never`.

## Проверка

- Лаба 2026-09-03 ~15:33 МСК: scp + `systemctl restart spmd` → active.
- `php tests/panel_tls_cli.php` — ok.
- Routes: `/acl/ad-groups/ca`, `/settings/tls`.

## Хвост

- UI глазами: upload CA + panel cert на лабе.
- Commit / push — по команде Merl.
