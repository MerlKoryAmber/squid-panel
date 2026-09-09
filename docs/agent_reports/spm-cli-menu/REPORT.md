# SPM CLI menu (`spm`)

Дата: 2026-09-09, 17:45 МСК.  
Статус: РЕАЛИЗОВАНО НО НЕ ПРИНЯТО.

## Решение

Команда **`spm`** (как s-ui): без аргументов — меню; с аргументом — сразу действие.

- Репо: `spm.sh` → install копирует в `/usr/local/bin/spm` и `/opt/spm/spm.sh`
- v1: update (keep/drop), uninstall, password, status, restart spmd/web, backup, URL
- **Нет** restart Squid в меню

## Проверка

```bash
sudo bash /opt/update.sh --keep-db
spm
# или: spm status | spm backup | spm help
```
