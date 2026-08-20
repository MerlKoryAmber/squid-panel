# dual-ui-kwts — отчёт реализации

Дата: 2026-08-20, ~20:15 МСК.  
Статус: **реализовано, не принято**. Автор кода себе «принято» не ставит.  
PHP на этой Windows-машине в PATH нет — `php -l` / CLI-тест здесь не гонялись.

## Сделано по плану

- `users.policy_ui` default `expert`, таблица `cascade_routes`, переключатель Simple/Expert на HTTP rules и Cascade.
- Классификатор `PolicyAclKind`, простой HTTP: откуда/куда/действие, без слова ACL в тексте экрана.
- Компилятор `CascadeRouteCompiler`: пир → allow + deny других + never_direct; Direct → always_direct. Save simple пересобирает Squid-таблицы.
- Правка access/routing в эксперте сносит проекцию `cascade_routes` (не затирает сами squid-строки).
- CLI-проверки: `tests/policy_ui_cli.php` (гонять на сервере: `php tests/policy_ui_cli.php`).

## Не проверено здесь

- UI в браузере (вход → Simple → HTTP rules / Cascade).
- Импорт live conf + сложные правила «Open in expert».
- Запись в живую `spm.db` на CentOS.

## Хвост

Приёмка человеком. Коммит/push — по команде. На сервере: `php tests/policy_ui_cli.php` и сценарий UI.
