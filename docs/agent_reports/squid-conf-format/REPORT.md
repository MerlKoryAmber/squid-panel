# Отчёт: live squid.conf с панели

Дата: 2026-08-22, 15:15 МСК. Задача: Save/install пишут squid.conf после parse; лаба — откат.

## Сделано

- Генератор пишет полный conf: extra (`cache`/`cache_mem`/headers) → `auth_param` → `external_acl_type` → acl/peers/http_access → listen/`coredump_dir`.
- `auth_param` уже был в `auth_config`; в live не попадал. Теперь пишется. Negotiate `realm` из principal в `auth_param` не дублируется.
- Save ACL / HTTP Access / Cascade / Listen → `SquidLiveApply` → spmd parse → backup `*.spm-policy-*` → live. Kerberos Save UI — только БД.
- Install: `format_live.php` после импорта. Parse fail → restore `*.spm-install-*`.
- Лаба: `/etc/squid/squid.conf.spm-lab-baseline` и `storage/local/squid.conf.lab-baseline-20260822` (gitignore).

## Проверка

- `php tests/squid_fragments_cli.php`
- `php tests/policy_include_place.php`
- На лабе после выкладки: `squid -k parse`, `auth_param negotiate` в live, `coredump_dir`, нет дубля `http_access deny all`. Откат: `cp /etc/squid/squid.conf.spm-lab-baseline /etc/squid/squid.conf && squid -k reconfigure`.

## Хвост

- Выкладка на lab (`update.sh` снесёт `spm.db`).
- Kerberos Save → live не авто.
- `ssl_bump`/`refresh_pattern` только через extra, UI нет.
