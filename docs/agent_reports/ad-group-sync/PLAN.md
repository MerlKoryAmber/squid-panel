# План: AD groups — sync членов в БД + файл (вариант A)

Дата: 2026-09-07, 13:20 МСК.  
Обновлено: 2026-09-07, 13:17 МСК.  
Статус: **реализация в коде** (ждёт лабы / ПРИНЯТО).

Человек: вариант A; члены = **БД + экспорт в файл** для Squid.

ADR 0001 отвергал dump в `proxy_auth` — **ADR 0010 перекрывает** (нет пароля в conf важнее live LDAP).

## Цель

- В squid.conf **нет** bind-пароля и **нет** LDAP-helper для групп.
- Члены: `spm.db` (правда) → `/etc/squid/acl.d/ad_*.txt` (экспорт) → `acl … proxy_auth "…txt"`.
- Sync ~30 мин + Sync now.

## Модель

1. Импорт → ACL `ad_<ident>`, тип `proxy_auth`, storage `file`.
2. Таблица членства в БД (напр. `ad_group_members`: group/acl + username).
3. Sync: LDAP (пароль только spmd) → UPSERT БД → выгрузка файла → `acl_file_install` → Apply/reconfigure при изменении.
4. Логины: **оба** `user` и `user@REALM`.
5. LDAP fail: не чистить БД/файл.
6. Убрать `kg_*` / `ext_kerberos_ldap_group_acl` для ad_*.
7. Builder fail-closed: нет `-p`/`-w` для AD.

## Секрет

Только для sync: secret-файл / staging `-y`. Не conf.  
`bind_password` в БД пока для UI (шифрование — отдельно).

## Timer / UI

- systemd timer 30 мин; Sync now; last sync / error в UI.

## Docs / тесты / лаба

ADR 0010, CODEMAP, CLI + `update.sh --keep-db` на лабе.

## Зафиксировано

- A (sync).
- БД + файл-экспорт.
- Оба формата логина.

## После ОК

Реализация по этому PLAN → лаба → commit/push по команде.
