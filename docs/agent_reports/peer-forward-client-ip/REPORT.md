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

## Hotfix 2026-09-09 ~13:35 МСК

На проде XFF не было на проводе к MB при галке на `MBhproxy-IP`.

Причины в emit:
1. Имя ACL `spm_xff_MBhproxy-IP` (дефис) — плохой token для Squid.
2. `request_header_access … allow peername` не удерживал XFF после `deny all`.

Стало: ACL `spm_xff_MBhproxy_IP peername MBhproxy-IP` +  
`request_header_access X-Forwarded-For deny all` +  
`request_header_add X-Forwarded-For %>a spm_xff_MBhproxy_IP`.

## Проверка

1. Edit peer → галка → Save (Apply).
2. `grep -nE 'spm_xff_|X-Forwarded-For|request_header_add' /etc/squid/squid.conf`
3. tcpdump на peer: есть `X-Forwarded-For:`.
