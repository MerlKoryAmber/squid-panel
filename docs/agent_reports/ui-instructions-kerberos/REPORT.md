# Instructions, Kerberos children, Dashboard Negotiate

Дата: 2026-08-23, 14:56 МСК.

Статус: **ПРИНЯТО** Merl, 2026-08-23 (лаба, git `6501891`).

## Instructions

- Sidebar: одна ссылка **Instructions** (EN), блок над footer.
- Маршруты: `GET /instructions`, статьи `?g=keytab`, `?g=keytab-merge` (не path `/instructions/keytab` — nginx 404).
- Контент: keytab create / merge (KWTS 166438 + ktutil), credit в footer статей.

## Kerberos auth_param children

Squid 5.5 на лабе. UI вместо «Children extra»:

- **Children** — max `negotiate_kerberos_auth` processes
- **Startup** — `startup=N` at start/reconfigure
- **Idle** — spare pool `idle=N`

Save Kerberos → `auth_config` only; live conf не переписывается (как раньше). В conf при Apply: `auth_param negotiate children N startup=A idle=B`.

## Dashboard Negotiate helpers

- Источник: хвост `/var/log/squid/cache.log` (+ `.1`), окно **24h**, при открытии Dashboard (не cron, не squidclient).
- Парс: `All N/N negotiateauthenticator processes are busy`, `pending requests queued`, FATAL `Too many queued negotiateauthenticator`.
- Показывает children/startup/idle из БД + счётчики busy/max queue/last event.
- Тест: `php /opt/spm/tests/negotiate_helper_stats.php`.

## Верификация (автор)

- Код: контроллер, view, `NegotiateHelperStats.php`, builder уже писал `children_extra`.
- Лаба: `scp`, тест `ALL_OK` на 192.168.0.178.
- UI в браузере агентом не прогнан (TLS self-signed).

## Хвост

- ~~Приёмка Merl~~ — ПРИНЯТО 2026-08-23.
- Каскад Edit — отложено (Delete+Add).
