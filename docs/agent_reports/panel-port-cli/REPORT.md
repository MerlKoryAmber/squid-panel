# REPORT — panel HTTPS port via spm CLI

Дата: 2026-09-29, 14:25 МСК.  
Статус: РЕАЛИЗОВАНО НО НЕ ПРИНЯТО.

## Что сделано

1. `spm.sh`: пункт меню **10** / `spm port [N]` — смена nginx `listen`, запись `PANEL_PORT` в `/etc/spm/install.env`, firewall old→new, `nginx -t` + reload; 80/443 запрещены; **занятый порт — отказ** (`ss -tlnp`, fail-closed); backup `spm.conf.spm-port-*`.
2. `install.sh`: если `PANEL_PORT` не задан в env — читает из `/etc/spm/install.env` (иначе default 8443).
3. `update.sh`: перед `install.sh` экспортирует сохранённый `PANEL_PORT` (двойная страховка).

## Не трогает

Squid `http_port` (это Settings / Listen, не этот CLI).

## Проверка на лабе (после выкладки)

- `spm status` → panel port
- `spm port 8444` → URL меняется, `curl -kI https://IP:8444/`
- `spm update` / keep-db → порт остаётся 8444
