# ADR 0006 — AD groups via LDAP(S) simple bind only

Дата: 2026-09-03, 12:50 МСК.  
Обновлено: 2026-09-03, 13:35 МСК.  
Статус: в работе.

## Решение

Членство в AD для Squid и список групп в панели — **только LDAP simple bind** (bind DN + пароль + явные DC).

- UI: **ACLs → AD groups → LDAP directory**.
- Таблица `ad_ldap_config`: servers, port, use_ssl, bind_dn, bind_password, base_dn (`bind_mode` всегда `simple`).
- Helper `ext_kerberos_ldap_group_acl`: `-u/-p/-b/-l`/`-S`.
- Список групп: `ldapsearch -x` через spmd (пароль в temp-файле `-y`).

**GSSAPI/keytab для групп не используется.** Kerberos Negotiate остаётся только для аутентификации пользователя.

## Почему

Прод: `Can't contact` на SASL/GSSAPI; SRV тянул чужие DC. KWTS-style LDAP к pin’нутым `hdc-*` проще и предсказуемее. Резерв GSSAPI усложнял UI без пользы.

## Отвергнуто

- Dual mode simple + GSSAPI reserve.
- Только panel LDAP, Squid всё ещё GSSAPI.
- Отдельный бинарный helper.

## Риски

- Пароль bind в `spm.db` и в live `squid.conf` (`-p`).
- Ротация пароля учётки в AD — в панели вручную.
