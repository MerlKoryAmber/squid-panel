# SPM CLI menu (`spm`)

Дата: 2026-09-09, 17:45 МСК.  
Статус: РЕАЛИЗОВАНО НО НЕ ПРИНЯТО.

## Решение

Команда **`spm`**: без аргументов — меню; с аргументом — сразу действие.

- Репо: `spm.sh` → install копирует в `/usr/bin/spm` (+ `/usr/local/bin`) и `/opt/spm/spm.sh`
- v1: update (keep/drop), uninstall, password, status, restart spmd/web, backup, URL
- **Нет** restart Squid в меню
- Переносимая идея: `docs/patterns/cli-menu-linux.md`

## Hotfix 2026-09-09 ~17:50 МСК

Root PATH без `/usr/local/bin` → `spm: command not found` после install.
Стало: ставить в **`/usr/bin/spm`** (+ local).
