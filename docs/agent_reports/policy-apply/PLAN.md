# План: Apply политики в Squid (ADR 0004)

Дата: 2026-08-21, 00:22 МСК.  
Статус: подход согласован (includes + явный Apply). **Код v1 в репо.** Принято человеком ещё нет.

База: ADR 0004. Живой conf целиком не переписывать. `update.sh` / install по-прежнему не apply политики.

## Цель

Кнопка **Apply to Squid**: БД → файлы include → parse → live → reconfigure. Save в панели остаётся черновиком.

## Вне скоупа v1

- Auth / keytab / `external_acl_type` в тех же файлах (остаются в live, как импорт).
- `ssl_bump`, `refresh_pattern`, delay pools.
- Авто-apply на Save.
- Полный `SquidConfigBuilder::save()` на `/etc/squid/squid.conf`.

## Шаги

1. Нарезать генератор: три фрагмента (acl / http_access / peers+routing), не целый conf. `deny all` только в http_access-фрагменте, без дубля если уже есть в live (после вырезания старых `http_access` дубля не будет).
2. spmd: команда `squid_policy_apply` (как `squid_listen_apply`): backup, правка include, атомарная замена файлов, parse, reconfigure, откат.
3. Первая выкладка: закомментировать в live строки `acl `, `http_access`, `cache_peer`, `cache_peer_access`, `never_direct`, `always_direct` (не `http_port` — это listen). Добавить три `include`.
4. UI: кнопка Apply (admin), превью diff/фрагмента, ошибка parse в flash. Не на каждом Save.
5. Тесты CLI: генерация фрагмента из фикстуры БД; parse-fail не трогает live (моки/фикстуры). Проверка: backup есть, include есть.

## Риски

- ACL в live, которых нет в БД (правили conf руками после импорта) — пропадут из Squid после Apply, пока не импорт/ручной ввод в панель.
- Порядок директив Squid: `acl` до `http_access`; include в правильном месте live (после auth helper, до access).
- Два `http_access deny all` — не допускать.
