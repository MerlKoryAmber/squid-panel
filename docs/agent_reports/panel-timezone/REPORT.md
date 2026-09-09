# Panel timezone (Settings)

Дата: 2026-09-09, 15:55 МСК.  
Статус: РЕАЛИЗОВАНО НО НЕ ПРИНЯТО.

## Решение

Settings → General → **Timezone**: `Europe/Moscow` | `UTC` (default Moscow).

- БД: `settings.timezone`
- `PanelTimezone::apply()` в `public/index.php` после `Database::init`
- Влияет на PHP `date()` (Access Logs, audit и т.п.)
- Не трогает Squid `access.log` (epoch) и TZ ОС

## Проверка

1. `update.sh --keep-db`
2. Settings → Timezone → Europe/Moscow → Save
3. Access Logs: часы = МСК (на ~3 ч позже прежнего UTC)
