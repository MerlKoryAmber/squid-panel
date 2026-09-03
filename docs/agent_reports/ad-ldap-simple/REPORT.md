# AD LDAP simple bind (ADR 0006)

Дата: 2026-09-03, 13:05 МСК.  
Статус: РЕАЛИЗОВАНО НО НЕ ПРИНЯТО (лаба).

## Лаба после `-S` (e9770ba)

```bash
sudo bash /opt/update.sh --keep-db
```

Kerberos → LDAP servers `hdc-01` / `hdc-02` → Save.  
Или ждать следующий коммит с AD Groups UI.

## Пункт 2 (этот код, ещё не в git на момент отчёта)

- ADR 0006
- `ad_ldap_config` + UI **AD groups → LDAP directory**
- mode `gssapi` | `simple` (DN+password+servers+port+ssl)
- Save → sync helpers → Apply live
- spmd: staging JSON + simple `ldapsearch -x -y`
- После выкладки: **`systemctl restart spmd`**

## Проверка лабы (simple)

1. AD groups → simple, servers hdc-01/02, bind DN/pass, Save  
2. `grep external_acl_type /etc/squid/squid.conf` → `-u` `-p` `-S` / `-l`  
3. Список групп открывается  
4. cache.log без `ldap_sasl_interactive_bind_s`
