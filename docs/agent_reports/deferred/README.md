# Отложенное (backlog)

Обновлено: 2026-09-05, 17:45 МСК.  
Не делать без явной команды Merl.

## Domain discover (headless)

**Статус:** ОТЛОЖЕНО (Merl, 2026-09-05).

**Суть:** пункт меню в панели — ввод URL → spmd → headless browser → список hostname’ов, к которым ходит страница.

**Границы (зафиксировано):**

- **Не привязывать к Squid** — не access.log, не squid.conf, не ACL apply, не helpers.
- Только панель + spmd + headless (Chromium/аналог).
- access.log **не** даёт связь «главный сайт → его ресурсы» (нет Referer в native format; эвристики шумные).

**Оценка:** средне-тяжёлый MVP (~1–2 дня + ops Chromium на Rocky/CentOS); SSRF fail-closed обязателен; 1 job, timeout.

**Когда брать:** АУДИТ лабы (есть ли chromium, RAM) → ПЛАН/ADR → реализация → лаба → приёмка. Не «заодно».

**Не путать с:** Instructions/DevTools-сниппет вручную в браузере админа — отдельная дешёвая идея, тоже не начата.

---

## Settings: отключить кэш Squid

**Статус:** ОТЛОЖЕНО (Merl, 2026-09-05). Нужен пункт где-то в Settings.

**Суть:** toggle/кнопка в UI — выключить object cache Squid (прокси только forward, без сохранения объектов на диск/в RAM по политике cache).

### Комментарии агента (до реализации)

**Сейчас в коде:**

- `cache` / `cache_mem` и прочее «чужое» живут в `extra_conf` — Settings прямо пишет, что unmanaged lines stay in extra (см. Settings → Policy).
- `cache_dir` есть в `squid_globals` / `SquidConfigBuilder` / импорт parser; в UI Listen сейчас нет отдельного «выкл. кэш».
- В тестах уже встречается паттерн `cache deny all` + `cache_mem 0` в extra.

**Как обычно гасят кэш в Squid:**

- Минимум: `cache deny all` (не класть ответы в store).
- Часто рядом: `cache_mem 0`; иногда убирают/`null` для `cache_dir` — осторожно: пустой `cache_dir` vs явный null зависит от версии/сборки; лучше один канонический набор директив и ADR.
- Это **не** то же самое, что `offline_mode` / отключение ICP / отключение кэша DNS.

**Где в панели:** Settings (рядом с Listen / Policy), не отдельный тяжёлый раздел. Save → тот же пайплайн ADR 0005 (parse staging → backup → live → reconfigure). Не править live руками.

**Риски / дизайн:**

- Не плодить дубли: если в `extra_conf` уже есть `cache deny all`, toggle не должен вставить второй раз; при «вкл. кэш снова» — снять управляемые строки и вернуть прежний `cache_dir`/`cache_mem` из снимка или дефолта install.
- Хранить флаг в БД (`squid_globals` или settings), чтобы UI показывал фактическое намерение, а не только grep conf.
- Лёгкая фича по UI (~полдня), но нужен dry-run на лабе: `squid -k parse` + проверка, что объекты не HIT (access.log / cache.log).
- Не смешивать с Domain discover.

**Когда брать:** короткий ПЛАН (какие директивы канон) → Settings UI → Apply → лаба → приёмка.
