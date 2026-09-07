# Discover + disable cache

Дата: 2026-09-07, 00:35 МСК.  
Статус: РЕАЛИЗОВАНО НО НЕ ПРИНЯТО. Git: `f2d35a0` (+ этот docs-коммит).

## Сделано

- ADR 0008: Settings → disable object cache → Apply 0005.
- ADR 0009: меню Domain discover → spmd headless NetLog → **2nd-level** domains.
- Ads filter: **короткий curated denylist** (`app/Data/discover_ad_denylist.txt`). Peter Lowe full — отменён.
- Deferred: 0008/0009 сняты с backlog.
- Handoff: `docs/agent_reports/handoff/2026-09-07.md`.

## Лаба

- Выложено в `/opt/spm`; Chromium установлен; `restart spmd` после `spmd.py`.
- CLI: `php tests/domain_discover_cli.php` — ok short denylist.
- UI: `/` и `/discover` → 302 (без сессии).

## Хвост

- Приёмка Merl (0006–0009).
- Не возвращать полный Peter Lowe без команды.
- 2026-09-07: NetLog шум Chromium (google/youtube на fx.interros.ru) — фикс flags + parse events only; тест `domain_discover_netlog_cli.py`.
