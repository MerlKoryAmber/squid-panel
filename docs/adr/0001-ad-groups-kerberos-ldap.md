# ADR 0001 — AD groups via Kerberos LDAP helper

Дата: 2026-08-18, 18:40 МСК.  
Статус: в работе (выбор человека в чате; ПРИНЯТО ещё нет).

## Решение

Членство в группе AD Squid проверяет **на запросе** helper’ом  
`/usr/lib64/squid/ext_kerberos_ldap_group_acl` (`-g GROUP@REALM` или `-t` hex, `-m 5`, `-D realm`, `-P` principal).

Список имён групп в UI: LDAP (`ldapsearch` GSSAPI) через spmd, тем же keytab `/etc/squid/*.keytab`. Join/winbind не нужен. Host = поле KDC, иначе имя realm; Base DN из realm. Ручной ввод имени — запасной путь.

Одна группа → `external_acl_type kg_*` + ACL `ad_*` (type `external`). Дальше ACL выбирается в HTTP Access / Cascade как любая другая.

Live `/etc/squid/squid.conf` генератор не переписывает.

## Отвергнуто

- Дамп членов группы в `proxy_auth` (стареет, тысячи строк).
- `ext_wbinfo_group_acl` как проверка в Squid (человек выбрал Kerberos LDAP).
- Join/winbind как обязательный шаг для списка групп.
