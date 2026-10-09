# Policy map (read-only)

Дата: 2026-10-09, 11:55 МСК.  
Статус: РЕАЛИЗОВАНО НО НЕ ПРИНЯТО.

## Согласовано

- **HTTP Access A:** AND внутри правила → одна стрелка → ALLOW/DENY; first match; крупные чипы.
- **Упаковка 1:** SVG fit-content; &lt;900px — 1 колонка правил; ≥900px — 2 колонки.
- **Cascade 1A:** только реальные пути (peer_access **allow**, always_direct, never_direct без orphan peers); deny → бейдж `denies:N` на пире, не отдельные входы.

## Не делать

Рисовать всех ACL/пиров «на всякий случай» — нет.

## UX 2026-10-09 ~12:20 МСК

- Убран дубль `h2` Policy map (остаётся только top-header).
- Без вложенного scrollbar и без точечного grid под SVG.
- ACL-чип: имя и `src`/type на разных строках (без перекрытия).
- Пиры cascade: один цвет (silver) на все пиры, не палитра по узлам.
- Клик по ACL → тот же tip с содержимым, что в HTTP Access (`View::aclTipText`).

## Проверка

`spm update` → Policy map: один заголовок сверху; без полосы у SVG; `src` читается; клик ACL показывает values.
