# ADR 0009 — Domain discover (headless, not Squid)

Дата: 2026-09-06, 19:30 МСК.  
Статус: в работе.

## Решение

Меню **Domain discover** → URL → spmd `domain_discover` → headless Chromium/Chrome → список hostname из NetLog.

- Не Squid: не access.log, не squid.conf, не ACL apply.
- SSRF fail-closed: только http/https, DNS → отказ private/loopback/link-local/metadata.
- Один job (`/run/spmd/discover.lock`), timeout ~45s.
- Staging: `/opt/spm/storage/tmp/discover-job.json`.
- В списке только **домены 2-го уровня** (`cdn.example.com` → `example.com`); субдомены отбрасываются.
- Опционально (галка по умолчанию): скрыть ads/analytics из **короткого** встроенного denylist
  (`app/Data/discover_ad_denylist.txt`, 2nd-level; ручное пополнение). Полный Peter Lowe — отменён.

## Почему

Подбор доменов для ACL до политики. Лог Squid не связывает «главный сайт → ресурсы».

## Отвергнуто

- Парсинг access.log.
- HTML-only fetch без браузера как единственный режим.
- Playwright как обязательная зависимость (опционально позже).

## Ops

На хосте нужен `chromium` или `google-chrome`. Без бинарника — явная ошибка в UI.
`install.sh` / `update.sh` ставят `chromium` (`dnf install -y chromium --exclude=openh264`); сбой пакета — WARNING, панель всё равно ставится.

NetLog: только `events[].params.url` / `url_before_redirect` (не regex по всему файлу — иначе шум из constants).
Chromium: `--disable-background-networking` и родственные флаги + свежий `--user-data-dir` под `/run/spmd`.
