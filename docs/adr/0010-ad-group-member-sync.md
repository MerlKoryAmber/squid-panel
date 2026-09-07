# ADR 0010 — AD group membership via DB sync + proxy_auth files

Дата: 2026-09-07, 13:25 МСК.  
Статус: в работе (согласовано в чате: вариант A).

## Решение

Членство AD для Squid **не** через live LDAP-helper.

1. Sync (~30 мин + Sync now): LDAP simple bind → таблица `ad_group_members` в `spm.db`.
2. Экспорт в `/etc/squid/acl.d/ad_*.txt`.
3. Squid: `acl ad_* proxy_auth "/etc/squid/acl.d/ad_*.txt"`.
4. В conf **нет** bind-пароля и **нет** `ext_kerberos_ldap_group_acl` для групп.
5. Логины в БД/файле: `user` и `user@REALM`.
6. LDAP fail: не затирать прежних членов.

Пароль bind только у sync (staging `-y` / secret), не в squid.conf.  
`ad_ldap_config.bind_password` в БД пока для UI (шифрование — отдельно).

## Почему

Человек: никаких паролей в открытом виде в conf. Live helper
`ext_kerberos_ldap_group_acl` умеет только `-p` в argv.  
Sync + файл — штатный `proxy_auth` и уже принятый паттерн больших ACL-файлов.

## Отвергнуто

- `ext_ldap_group_acl` + `-W` (live LDAP) — отложено в пользу A.
- Только файл без БД; только БД + helper к SQLite.
- GSSAPI для групп (ADR 0006).

## Supersedes

- ADR 0001: отказ от dump в `proxy_auth` — снят для AD groups.
- ADR 0006: `-p` в live helper для Squid — снят; simple bind остаётся для sync/list.
