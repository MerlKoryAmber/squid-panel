# ADR — Squid Proxy Manager

Реестр архитектурных решений. Не записано здесь — решения нет.

| ID | Дата (МСК) | Заголовок | Статус |
|----|------------|-----------|--------|
| 0001 | 2026-08-18 | AD groups via Kerberos LDAP helper | в работе |
| 0002 | 2026-08-18 | Squid listen include + nginx panel allowlist | в работе |
| 0003 | 2026-08-20 | Два UI политики: эксперт Squid и простой KWTS-стиль | ОТМЕНЕНО 2026-08-22 |
| 0004 | 2026-08-21 | Выкладка политики в Squid через include + явный Apply | **заменено 0005** 2026-08-22 |
| 0005 | 2026-08-22 | Панель форматирует и пишет live squid.conf | **ПРИНЯТО** 2026-09-06 |
| 0006 | 2026-09-03 | AD groups через отдельный LDAP(S) bind | в работе |
| 0007 | 2026-09-03 | LDAP CA trust + замена TLS панели | в работе |
| 0008 | 2026-09-06 | Disable Squid object cache (Settings) | в работе |
| 0009 | 2026-09-06 | Domain discover (headless, не Squid) | в работе |
| 0010 | 2026-09-07 | AD groups: sync members → DB → proxy_auth file | в работе |

Новый ADR: `docs/adr/NNNN-slug.md` (NNNN — следующий свободный номер).
В файле: что решили, почему, что отвергли.
