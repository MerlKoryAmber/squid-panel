# Отложенное (backlog)

Обновлено: 2026-09-05, 17:30 МСК.  
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
