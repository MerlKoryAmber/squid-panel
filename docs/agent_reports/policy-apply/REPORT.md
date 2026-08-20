# Отчёт: Apply политики в Squid (v1)

Дата: 2026-08-21, 00:40 МСК.  
Статус: **РЕАЛИЗОВАНО НО НЕ ПРИНЯТО**.

## Сделано

- Фрагменты: `SquidConfigBuilder` → `spm-acl.conf` / `spm-peers.conf` / `spm-http_access.conf`.
- Staging PHP + `spmd` `squid_policy_apply`: backup live `*.spm-policy-*`, `# SPM-moved` на acl/http_access/cache_peer*, include, parse, reconfigure; parse/reconfigure fail → откат.
- UI: Settings **Apply to Squid**, то же на Simple Access. Save правил без кнопки live не трогает.
- CLI: `tests/squid_fragments_cli.php`. PHP на Windows агента нет.

## Не в v1

Auth / ssl_bump / refresh_pattern из БД. Авто-Save→Squid.
