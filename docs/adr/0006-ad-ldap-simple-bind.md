# ADR 0006 — AD groups via dedicated LDAP(S) bind (KWTS-style)

Дата: 2026-09-03, 12:50 МСК.  
Статус: в работе.

## Решение

Членство в AD для Squid и список групп в панели могут идти **отдельным LDAP(S)** (bind DN + пароль + явные DC), **мимо GSSAPI/keytab**.

- UI: **ACLs → AD groups** (карточка LDAP settings).
- Таблица `ad_ldap_config`: `bind_mode` = `simple` (основной) | `gssapi` (резерв), servers, port, use_ssl, bind_dn, bind_password, base_dn.
- **Primary:** `simple` — helper с `-u/-p/-b/-l`/`-S`; список групп — `ldapsearch -x` через spmd.
- **Reserve:** `gssapi` — keytab, если simple не выбран или не заполнен (нет servers/DN/password).
- Неполный simple → effective mode автоматически `gssapi`.

Negotiate/Kerberos для **аутентификации пользователя** не меняется.

## Почему

Прод: `Can't contact LDAP server` на SASL/GSSAPI bind при живом SRV (в т.ч. bhdc). KWTS разделяет SSO и каталог. Simple bind к pin’нутым `hdc-*` предсказуее.

## Отвергнуто

- Только panel simple, Squid всё ещё GSSAPI — не лечит cache.log helper.
- Хранить пароль только в env без БД — хуже для backup/restore панели.
- Отдельный бинарный helper вместо `ext_kerberos_ldap_group_acl` — лишняя зависимость.

## Риски

- Пароль bind попадает в `squid.conf` (`-p`) — conf читать могут squidmgr/root; mode 640 / ACL.
- Ротация пароля учётки в AD — руками в панели.

## Лаба

Проверка на лабе до прода: Save simple → `grep external_acl_type` → список групп → allow по группе → нет `ldap_sasl_interactive_bind_s` / Can't contact.
