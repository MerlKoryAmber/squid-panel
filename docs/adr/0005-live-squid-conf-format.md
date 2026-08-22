# ADR 0005 — Панель форматирует и пишет live squid.conf

Дата: 2026-08-22, 15:10 МСК.  
Статус: **согласовано в чате** (Save ACL/Access/Cascade/Listen → Apply; полный conf из БД + extra; parse first). Принято человеком ещё нет.

## Решение

Источник правды — `spm.db`. После Save политики (ACL, HTTP Access, Cascade, Listen) панель **переписывает** `/etc/squid/squid.conf` целиком:

1. Staging `/opt/spm/storage/tmp/squid.conf.parse`.
2. `squid -k parse` на staging. Нет — live не менять.
3. Backup `*.spm-policy-*`, запись live, `reconfigure`. Провал reconfigure — откат backup.

Install после импорта гоняет тот же формат (`install/format_live.php`). Parse fail → restore `*.spm-install-*`.

Порядок в файле: extra (неуправляемое) → `auth_param` → `external_acl_type` → `acl` → каскад → `http_access` → listen/`coredump_dir`.

`coredump_dir` и `request_header_access` — колонки `squid_globals` + Settings. Чужое (`cache`, `cache_mem`, …) — `extra_conf`, не путать с `cache_peer*`.

`external_acl_type` из БД выкладывается, пока строки есть. Удаление helper в панели — оператор донастроит AD groups.

PHP `/etc/squid/squid.conf` не пишет. Только spmd / root installer.

## Лаба

Откат: `/etc/squid/squid.conf.spm-lab-baseline` и gitignored `storage/local/squid.conf.lab-baseline-20260822`.

## Почему не includes (ADR 0004)

Человек разрешил править live conf. Includes оставляли auth/cache_mem «за бортом генератора» и Save ≠ Squid.

## Отвергнуто

- Генератор без `auth_param` (сломает Kerberos).
- Wildcard «выкинуть cache*» (съест `cache_peer`).
- Save Kerberos сразу в live (пока нет; conf всё равно содержит текущий `auth_config` при Save политики).
