# Policy map (read-only)

Дата: 2026-10-09, 11:55 МСК.  
Статус: РЕАЛИЗОВАНО НО НЕ ПРИНЯТО.

## Согласовано

- **HTTP Access A:** AND внутри правила → одна стрелка → ALLOW/DENY; first match; крупные чипы.
- **Упаковка 1:** SVG fit-content; &lt;900px — 1 колонка правил; ≥900px — 2 колонки.
- **Cascade 1A:** только реальные пути (peer_access **allow**, always_direct, never_direct без orphan peers); deny → бейдж `denies:N` на пире, не отдельные входы.

## Не делать

Рисовать всех ACL/пиров «на всякий случай» — нет.

## Проверка

`spm update` → Policy map: нет пустого квадрата; Access читается как AND+порядок; Cascade без простыни deny/orphan.
