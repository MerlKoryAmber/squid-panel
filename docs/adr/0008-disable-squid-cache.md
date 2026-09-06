# ADR 0008 — Disable Squid object cache from Settings

Дата: 2026-09-06, 19:30 МСК.  
Статус: в работе.

## Решение

Settings → **Squid object cache** — checkbox `disable_cache`.

- Колонки `squid_globals.disable_cache`, `cache_dir_saved`.
- При выкл.: builder пишет managed-блок `cache deny all` + `cache_mem 0`, **не** эмитит `cache_dir`.
- При вкл.: блок снимается; `cache_dir` снова из колонки (restore из `cache_dir_saved` если пусто).
- Save → пайплайн ADR 0005 (parse → backup → live).

## Почему

Оператору нужен явный выключатель без ручного extra_conf. Флаг в БД = намерение UI.

## Отвергнуто

- Только правка free-text `extra_conf` без флага.
- `cache_dir null` как единственный способ (зависит от сборки).
