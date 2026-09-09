# Peer forward client IP (X-Forwarded-For)

Дата: 2026-09-09, 12:55 МСК.  
Статус: РЕАЛИЗОВАНО НО НЕ ПРИНЯТО.

## Решение

Галка на пире Cascade: **Forward client IP (X-Forwarded-For) to this peer only**.

- БД: `cache_peers.forward_client_ip` (0/1).
- Conf: `acl spm_xff_<name> peername <name>` +  
  `request_header_access X-Forwarded-For allow spm_xff_<name>`  
  **выше** Settings `X-Forwarded-For deny all`.
- Остальные peer/origin без галки — без XFF.

MB trust XFF — отдельно (не в этой задаче).

## Проверка

1. Edit peer → галка → Save (Apply).
2. `grep -nE 'spm_xff_|X-Forwarded-For' /etc/squid/squid.conf`
3. Allow для пира выше deny all.
