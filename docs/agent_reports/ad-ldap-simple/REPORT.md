# AD LDAP simple bind only

Дата: 2026-09-03, 13:40 МСК.

## Статус

`9a14fe8` — группы только через LDAP simple bind. GSSAPI для групп убран.

## Лаба

```bash
sudo bash /opt/update.sh --keep-db
sudo systemctl restart spmd
```

AD groups → LDAP: servers hdc-01/02, bind DN, password → Save.

Handoff дальше: `docs/agent_reports/handoff/2026-09-03.md`. GSSAPI для групп не возвращать.
