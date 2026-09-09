# Pattern: Linux CLI management menu

Дата: 2026-09-09, 18:00 МСК.  
Назначение: **переносимая идея** для соседних проектов (панель + агент на Linux).  
Не ADR продукта и не замена `install.sh` конкретного репо.

Скопируй этот файл в другой репозиторий и адаптируй имена (`spm` → своё).

---

## Зачем

Оператор на сервере запускает **одну команду** и получает нумерованное меню:

- install / update / uninstall  
- смена пароля админа  
- status сервисов  
- restart безопасных юнитов  
- backup БД + конфигов  
- показать URL панели  

Плюс те же действия как **подкоманды** без меню (`tool status`, `tool update`).

UX такого типа встречается у многих Linux-панелей (нумерованное меню + те же глаголы CLI) — это общий приём, не «фича чужого продукта».

---

## Контракт

| Правило | Деталь |
|---------|--------|
| Entry | `/usr/bin/<name>` (обязательно). Дополнительно `/usr/local/bin/<name>` ок, но **не вместо** `/usr/bin` — у root `secure_path` часто без `/usr/local/bin` → `command not found`. |
| Источник | Скрипт в дереве продукта, напр. `/opt/<app>/<name>.sh`; `install` копирует в `/usr/bin/<name>`. |
| Root | Только root / `sudo <name>`. |
| Без аргументов | Интерактивное меню (цикл → Enter → снова меню). |
| С аргументом | Сразу действие, код выхода ≠ 0 при ошибке. |
| Опасное | Confirm `[y/N]`; уничтожение данных — **два** confirm. |
| Не трогать молча | Сервисы «ядра» (у SPM — Squid restart) **не** в меню без отдельного явного пункта и confirm. |
| Обновление | Вызывать уже существующий `update.sh` / installer, не дублировать логику. |
| Язык меню | Как UI продукта (часто English labels). |
| Выкладка на прод | Только штатный update/install проекта — без ручного `cp` на сервере. |
| Cwd при update | Не из каталога, который update сносит; CLI/`update.sh` сами `cd /tmp` перед clone. |

---

## Каркас меню (шаблон)

```
1. Update (keep DB)
2. Update + DROP DB
3. Uninstall panel
4. Reset admin password
5. Status
6. Restart agent
7. Restart web (nginx / php-fpm / …)
8. Backup DB + main config
9. Show panel URL
0. Exit
```

Подкоманды-зеркало: `update`, `update-drop`, `uninstall`, `password`, `status`, `restart-agent`, `restart-web`, `backup`, `url`, `help`.

---

## Минимальный скелет bash

```bash
#!/bin/bash
# /opt/<app>/<name>.sh → install -m 755 … /usr/bin/<name>

need_root() { [ "$(id -u)" -eq 0 ] || { echo "run as root"; exit 1; }; }

confirm() {
  read -r -p "${1:-Continue?} [y/N] " r
  case "$r" in y|Y|yes|YES) return 0 ;; *) return 1 ;; esac
}

show_menu() {
  echo "1. Update (keep DB)"
  echo "2. …"
  echo "0. Exit"
}

run_menu() {
  while true; do
    show_menu
    read -r -p "Select: " c
    case "$c" in
      1) do_update_keep; read -r -p "Enter…" _ ;;
      0) exit 0 ;;
      *) echo "Invalid" ;;
    esac
  done
}

need_root
case "${1:-}" in
  "") run_menu ;;
  help|-h|--help) echo "…" ;;
  update) do_update_keep ;;
  *) echo "Unknown: $1"; exit 1 ;;
esac
```

Функции `do_*` — тонкие обёртки над существующими скриптами проекта.

---

## Install / uninstall

**Install (фрагмент):**

```bash
if [ -f "$APP_DIR/${NAME}.sh" ]; then
  chmod 755 "$APP_DIR/${NAME}.sh"
  install -m 755 "$APP_DIR/${NAME}.sh" "/usr/bin/${NAME}"
  install -m 755 "$APP_DIR/${NAME}.sh" "/usr/local/bin/${NAME}" 2>/dev/null || true
  echo "CLI menu installed: /usr/bin/${NAME}"
else
  echo "WARNING: ${NAME}.sh missing — CLI not installed"
fi
```

В конце install всегда печатать полный путь (`/usr/bin/…`), не только короткое имя.

**Uninstall:** `rm -f /usr/bin/<name> /usr/local/bin/<name>`.

---

## Backup (минимум)

Каталог вроде `/opt/<app>/storage/backup/<stamp>/`:

- файл БД панели  
- главный live-конфиг, которым управляет панель  

Не сжимать секреты в git; права на каталог — только root / сервисный пользователь.

---

## Референс в SPM

Реализация: `spm.sh`, проводка в `install.sh` / `uninstall.sh` / `update.sh`.  
Отчёт: `docs/agent_reports/spm-cli-menu/REPORT.md`.

Соседний проект: скопировать **этот** файл + переименовать команды под свой стек.
