# Peer forward client IP (X-Forwarded-For)

Дата: 2026-09-09, 15:20 МСК.  
Статус: РЕАЛИЗОВАНО НО НЕ ПРИНЯТО.

## Решение

Галка на пире Cascade: **Forward client IP (X-Forwarded-For) to this peer only**.

- БД: `cache_peers.forward_client_ip` (0/1).
- Conf:
  - `request_header_access X-Forwarded-For deny all` (fail-closed / Settings)
  - `request_header_add X-Forwarded-For "%>a" <ACL…>` для каждой **allow**-строки `cache_peer_access` у пира с галкой
- Остальные peer/origin без галки — без XFF.

MB trust XFF — отдельно (не в этой задаче).

## Hotfix timeline (прод)

1. **~12:55** — allow `peername` выше deny all → на проводе нет XFF.
2. **~13:35** — `acl spm_xff_* peername` + `request_header_add %>a spm_xff_*` (sanitize `-`) → **на проде уже так**, tcpdump `vk.com` без `X-Forwarded-For`. Вердикт: **`peername` в `request_header_*` не матчится** на этом Squid.
3. **~15:20** — убрать peername; add по ACL из `cache_peer_access allow` (напр. `LinuxToMBProxy`), значение `"%>a"` в кавычках.

## Проверка после выкладки

1. Edit peer → галка → Save (Apply).
2. `grep -nE 'X-Forwarded-For|request_header_add' /etc/squid/squid.conf`  
   Ожидание: `deny all` + `request_header_add X-Forwarded-For "%>a" LinuxToMBProxy` (и/или auth ACL), **без** `spm_xff_` / `peername`.
3. tcpdump к MB: есть `X-Forwarded-For:`.
