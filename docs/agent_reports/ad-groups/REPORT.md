# AD groups → ACL

Дата: 2026-08-18, 18:40 МСК.  
Статус: РЕАЛИЗОВАНО НО НЕ ПРИНЯТО.

Человек выбрал `ext_kerberos_ldap_group_acl`.

Сделано:

- UI `/acl/ad-groups`: список LDAP+keytab (join не нужен), чекбоксы + ручное имя.
- На группу: helper `kg_*` (`-g`/`-t`, `-m 5`, `-D`, `-P`) и ACL `ad_*` type `external`.
- Импорт/генерация `external_acl_type`. ACL `external` — одна строка.
- spmd/sudoers: `wbinfo -g`.
- Применение: выбрать ACL в HTTP Access / Cascade. Live squid.conf не трогаем.

Хвост: UI/wbinfo на стенде не гонял; после update.sh — restart spmd, visudo sudoers. Squid: keytab читаемый (`KRB5_KTNAME` если не default).
