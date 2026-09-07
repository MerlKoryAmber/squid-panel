# AD group member sync (ADR 0010)

Дата: 2026-09-07, 13:35 МСК.  
Статус: РЕАЛИЗОВАНО НО НЕ ПРИНЯТО.

## Сделано

- ADR 0010: members → `ad_group_members` → `acl.d/ad_*.txt` → `proxy_auth`.
- Нет `-p` / `ext_kerberos_ldap_group_acl` в live conf для AD groups.
- spmd `ad_ldap_group_members`; timer `spm-ad-group-sync.timer` (30 мин).
- UI: Sync now + last sync meta; import создаёт file ACL.
- Миграция legacy `external`+`kg_*` → `proxy_auth` file.
- Тесты: `ad_group_member_sync_cli.php`, обновлены fragments / policy_include / ldap_servers.

## Лаба / тест

```bash
sudo bash /opt/update.sh --keep-db
sudo systemctl restart spmd
sudo systemctl enable --now spm-ad-group-sync.timer
```

**Важно после выкладки:** сразу **Sync members** (или дождаться timer). До sync файлы `ad_*.txt` могут быть пустыми → group ACL никого не пустит (fail-closed). Apply мигрирует `kg_*` → `proxy_auth` автоматически.

Save LDAP → Import → Sync members → `grep -iE 'proxy_auth|-p |ext_kerberos_ldap' /etc/squid/squid.conf` — пароля и kerberos ldap group helper быть не должно.

## Хвост

- Приёмка Merl.
- Шифрование `bind_password` в SQLite — отдельно.
- Уточнить формат логина на лабе (оба `user` / `user@REALM`).
