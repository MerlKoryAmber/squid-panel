# ADR 0002 — Squid listen include + nginx panel allowlist

Дата: 2026-08-18, 20:45 МСК.  
Статус: **ПРИНЯТО** (Merl, 2026-09-28). Выкладка listen — через пайплайн ADR 0005 (не отдельный include-only).

## Решение

- `http_port` (все строки) и `visible_hostname` — Settings. Apply: `/etc/squid/spm-listen.conf`, в живом `squid.conf` коммент старых `http_port`/`visible_hostname`, `include`, backup, `squid -k parse` на staging, затем live + reconfigure.
- Доступ к панели по IP: nginx `include /etc/nginx/conf.d/spm-allow.inc`. Пусто = без фильтра. Непусто = allow + deny all. Всегда 127.0.0.1/::1. Текущий IP добавляется при Save. `nginx -t`, иначе откат файла, затем reload.

## Отвергнуто

- Полная перегенерация squid.conf.
- Fail-closed пустого whitelist (запирает панель).
- Смена порта 8443 **в рамках этой ADR** (отложено).

## Дополнено (2026-09-29)

Смена HTTPS-порта панели: `spm` меню пункт **10** / `spm port [N]`. Значение в `/etc/spm/install.env` (`PANEL_PORT`). `install.sh` / `update.sh` **не сбрасывают** порт при апдейте (читают install.env, если env не задан). Занятый порт — отказ (`ss`). 80/443 **не** запрещены продуктом (если свободны).
