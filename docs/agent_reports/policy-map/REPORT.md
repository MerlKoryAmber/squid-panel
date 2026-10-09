# Policy map (read-only)

Дата: 2026-10-09, 10:35 МСК.  
Статус: РЕАЛИЗОВАНО НО НЕ ПРИНЯТО.

## Решение

Пункт меню **Policy map** (`/policy-map`): схема из `spm.db` (не live conf).

- Слой HTTP Access — порядок top→bottom, allow/deny, disabled
- Слой Cascade — `routing_rules` + peers / `cache_peer_access`
- Клик по ACL — подсветка на обоих слоях

Файлы: `PolicyMapController`, `PolicyMapBuilder`, `views/policy_map/index.php`, CSS, route, sidebar.

## Проверка

1. `update.sh --keep-db` / `spm update`
2. Сайдбар → Policy map
3. Клик ACL на Access — подсветка и на Cascade (если ACL там есть)
