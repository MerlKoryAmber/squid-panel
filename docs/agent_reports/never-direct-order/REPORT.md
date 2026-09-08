# never_direct order (proxy_auth footgun)

Дата: 2026-09-08, 18:55 МСК.  
Статус: РЕАЛИЗОВАНО НО НЕ ПРИНЯТО. Git: `8c9eaae`.

## Суть

`never_direct allow` с `proxy_auth` выше src-ACL → для USER `-` Squid даёт AUTH_REQUIRED и не смотрит нижние never_direct → HIER_DIRECT.

## Фикс

Builder эмитит: never_direct (fast) → always_direct → never_direct (proxy_auth).

## Проверка

- Лаба: `php tests/prod_conf_never_direct_cli.php` PASS + squid -k parse.
- Тест/прод: `update.sh --keep-db` → Apply → лог LinuxToMBProxy без HIER_DIRECT.

Handoff: `docs/agent_reports/handoff/2026-09-08.md`.
