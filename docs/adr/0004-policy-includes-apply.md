# ADR 0004 — Выкладка политики панели в Squid через include

Дата: 2026-08-21, 00:22 МСК.  
Статус: **заменено ADR 0005** (2026-08-22). Includes больше не модель выкладки.

## Решение

Панель по-прежнему Save → `spm.db`. На живой Squid политика уходит **только** кнопкой **Apply to Squid**.

Как listen (ADR 0002): не переписывать `/etc/squid/squid.conf` целиком. Генератор пишет **фрагменты**:

- `/etc/squid/spm-acl.conf` — `acl` (inline и `" /etc/squid/acl.d/<name>.txt "`)
- `/etc/squid/spm-http_access.conf` — `http_access` (включая `deny all` в конце фрагмента)
- `/etc/squid/spm-peers.conf` — `cache_peer`, `cache_peer_access`, `never_direct` / `always_direct`

В live conf: закомментировать прежние директивы этих типов, добавить `include` **после** последнего `auth_param` / `external_acl_type` / `spm-listen` (иначе `acl … external TYPE` падает: helper ещё не объявлен). Чужое (`refresh_pattern`, `ssl_bump`, `http_port` уже в `spm-listen.conf`, auth helper) не трогать в v1.

Конвейер Apply (fail-closed):

1. Baseline: копия live conf `*.spm-policy-*`.
2. Собрать фрагменты из БД + staging conf.
3. `squid -k parse` на staging. Нет — live не менять.
4. Положить фрагменты, поправить include, `reconfigure`.
5. Проверка: `is-active` / ошибка cache.log. Провал — откат backup.

Save Access/Lists/Cascade **не** вызывает этот конвейер.

## Почему

Импорт в БД неполный. Полный `SquidConfigBuilder` → live conf съест директивы, которых нет в `spm.db`. `SquidConfigBuilder::save()` уже отказывается писать live.

## Отвергнуто

- Полная перезапись `squid.conf` из генератора.
- Apply на каждый Save.
- Только ручной копипаст фрагмента без кнопки Apply.
