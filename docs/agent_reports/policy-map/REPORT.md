# Policy map (read-only)

Дата: 2026-10-09, 10:50 МСК.  
Статус: РЕАЛИЗОВАНО НО НЕ ПРИНЯТО.

## Решение

Пункт меню **Policy map** (`/policy-map`): **схема** (узлы + стрелки) из `spm.db`, не таблица/список карточек.

- HTTP Access: ACL(+ACL) → ALLOW/DENY, вертикальный поток #1→#N
- Cascade: ACL → never_direct/always_direct → peer/DIRECT; peer_access к пирам
- Пустой cascade — заглушка → DIRECT
- Клик ACL — подсветка на обоих слоях

Файлы: `PolicyMapController`, `PolicyMapBuilder`, `views/policy_map/index.php`, CSS.

## Исправление UX 10:50 МСК

Первая выкладка была списками — не совпала с согласованным макетом. Перерисовано под schematic.

## Проверка

1. `spm update` / `update.sh --keep-db`
2. Policy map: видны узлы и стрелки, не «таблица правил»
3. Только HTTP Access — верхний поток полный; Cascade показывает пустую схему → DIRECT
