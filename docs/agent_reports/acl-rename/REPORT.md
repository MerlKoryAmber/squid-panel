# ACL rename Save

Дата: 2026-08-24, 12:05 МСК.

Статус: реализовано, **не ПРИНЯТО**.

## Баг

`AclController::saveFromPost` на update писал `entries/storage/description/group_name`, **не `name`**. Save после правки имени возвращал ту же форму.

## Фикс

- `UPDATE acls SET name = ? …`
- Имя: `[A-Za-z0-9._-]` (как файл ACL), иначе точка в имени съедалась бы при Save.
- Дубль имени → flash, форма не молчит.
- Ссылки: `http_access_rules`, `cascade_routes`, `cache_peer_access_rules`, `routing_rules`, `cache_peers.access_acl` (`AclNameRefs`, `!name` и без prefix-match).
- File-list: work-файл под новым именем, старый work unlink.

Тест: `php tests/acl_name_refs_cli.php`

## Хвост

- Лаба `scp` → `/opt/spm` (192.168.0.178) — сделать при доступе; `update.sh` не запускать.
- Тип ACL по-прежнему не меняется на update (как было).
- Старый live `/etc/squid/acl.d/<old>.txt` после rename не удаляется (нет команды spmd); conf после parse смотрит на новое имя.
