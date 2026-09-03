# LDAP servers (-S) for AD group helpers

Дата: 2026-09-03, 12:35 МСК.  
Статус: РЕАЛИЗОВАНО НО НЕ ПРИНЯТО.

## Что

Kerberos → поле **LDAP servers** → `auth_config.ldap_servers` → флаг `-S` у `ext_kerberos_ldap_group_acl` (без DNS SRV).

## Поведение

- UI: Authentication → Kerberos, textarea (FQDN по строке).
- Save: пишет БД, синхронизирует `external_acl_types.options`, **Apply live squid.conf**.
- Пустое поле: `-S` снимается, снова SRV.
- Новые AD groups из панели тоже получают `-S`.

## Прод (после выкладки)

1. `update.sh --keep-db` или scp + schema auto.
2. Kerberos → LDAP servers:
   ```
   hdc-01.hci.interros.ru
   hdc-02.hci.interros.ru
   ```
3. Save → проверить `grep external_acl_type /etc/squid/squid.conf` на `-S hdc-01...:hdc-02...`.
4. Смотреть cache.log: ушли ли `Can't contact LDAP server`.

## Файлы

`Database.php`, `schema.sql`, `AdGroupAcl.php`, `SquidConfigBuilder.php`, `AuthConfigController.php`, `views/auth/kerberos.php`, `tests/ad_ldap_servers_cli.php`, `tests/squid_fragments_cli.php`.
