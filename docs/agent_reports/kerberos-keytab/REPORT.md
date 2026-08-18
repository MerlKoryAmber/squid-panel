# Kerberos import + keytab upload

Дата: 2026-08-18, 18:10 МСК.

Статус: РЕАЛИЗОВАНО НО НЕ ПРИНЯТО.

## Импорт live negotiate

Live:

`auth_param negotiate program /usr/lib64/squid/negotiate_kerberos_auth -k /etc/krb5.keytab -s HTTP/hprx-01.hci.interros.ru@HCI.INTERROS.RU`

Было: вся строка в `program`, `keytab_path`/`principal` пустые, realm UI = DOMAIN.LOCAL, `children` резало `startup=`/`idle=`.

Стало: парсятся `-k`, `-s`, realm из `@REALM`, `children` + `children_extra`, `keep_alive`. `/etc/krb5.conf` панель не пишет.

Путь `/etc/krb5.keytab` хранится как импортированный. kinit/spmd — только `/etc/squid/*.keytab`. UI предупреждает, Test disabled пока нет managed keytab.

## Upload

POST `/auth/kerberos/upload`: admin + CSRF, MIT magic 0x0501/0x0502, ≤512 KB.

PHP → `/opt/spm/storage/tmp/<name>.keytab` → spmd `keytab_install` → `/etc/squid/<name>.keytab` chmod 640 squid:squid. Sudo fallback нет.

## Хвост

- Повторный импорт на стенде (spm.db сносится update.sh).
- spmd restart после выкладки.
- Живой squid.conf не трогать.
- UI в браузере не прогнан (нет стенда в этой сессии).
