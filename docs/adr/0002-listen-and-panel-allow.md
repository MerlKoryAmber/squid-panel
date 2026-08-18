# ADR 0002 — Squid listen include + nginx panel allowlist

Дата: 2026-08-18, 20:45 МСК.  
Статус: в работе (выбор в чате).

## Решение

- `http_port` (все строки) и `visible_hostname` — Settings. Apply: `/etc/squid/spm-listen.conf`, в живом `squid.conf` коммент старых `http_port`/`visible_hostname`, `include`, backup, `squid -k parse` на staging, затем live + reconfigure.
- Доступ к панели по IP: nginx `include /etc/nginx/conf.d/spm-allow.inc`. Пусто = без фильтра. Непусто = allow + deny all. Всегда 127.0.0.1/::1. Текущий IP добавляется при Save. `nginx -t`, иначе откат файла, затем reload.

## Отвергнуто

- Полная перегенерация squid.conf.
- Fail-closed пустого whitelist (запирает панель).
- Смена порта 8443 в этой задаче.
