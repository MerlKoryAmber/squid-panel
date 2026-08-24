# Squid Proxy Manager (SPM)

Веб-панель для уже работающего Squid на **CentOS Stream 9 / Rocky 9 / AlmaLinux 9**.  
Стек: PHP (пользователь `squidmgr`) + агент **spmd** (Python, root, UNIX-сокет) + SQLite `spm.db` + nginx **:8443** (TLS).

Репозиторий: [MerlKoryAmber/squid-panel](https://github.com/MerlKoryAmber/squid-panel).

## Что панель делает

- Импорт живого `/etc/squid/squid.conf` в БД (ACL, http_access, каскад, auth, listen, `coredump_dir`, `request_header_access`, прочие директивы в `extra_conf`).
- ACL, HTTP Access, каскад (`cache_peer` по `name=`), Basic / Negotiate / NTLM.
- Kerberos: разбор `-k`/`-s`, загрузка keytab в `/etc/squid/*.keytab` через spmd.
- Группы AD: список через LDAP+GSSAPI (тот же keytab), в Squid — `ext_kerberos_ldap_group_acl`; join/winbind не обязателен.
- Большие списки сайтов — файлы `/etc/squid/acl.d/<acl>.txt`, не тысячи строк в conf.
- Settings: `http_port`, hostname, `coredump_dir`, `request_header_access`; IP-whitelist панели на nginx.
- **Save политики** (ACL / Access / Cascade / Listen) пишет live `/etc/squid/squid.conf` после `squid -k parse`, backup `*.spm-policy-*`. Install делает то же после импорта.
- Статус Squid: `/proc` и `systemctl is-active squid` (не полный `systemctl status`).

## Чего панель не делает

- **Не ставит Squid.** Сначала должен быть рабочий прокси и непустой `squid.conf`.
- **Не делает `systemctl restart squid`.** После формата — `squid -k reconfigure`.
- Kerberos Save в UI пока только `spm.db`; в live `auth_param` попадает при Save политики / install format.
- `ssl_bump` / `refresh_pattern` — только если были в исходном conf (extra). Из UI не редактируются.

## Установка

Нужны root, установленный Squid, заполненный `/etc/squid/squid.conf`.

```bash
# из клона репозитория
chmod +x install.sh uninstall.sh
sudo ./install.sh
```

- Панель: `https://<IP>:8443/` (порт: `PANEL_PORT`, по умолчанию 8443).
- Логин: `admin`. Пароль — введённый при установке или сгенерированный (печатается один раз).
- Сертификат самоподписанный.
- Если есть `/etc/krb5.keytab` и ещё нет `/etc/squid/krb5.keytab` — install копирует (640, `squid:squid`).
- Импорт conf в новую `spm.db`, затем формат live conf после parse. Копии: `/etc/squid/squid.conf.spm-install-<timestamp>`, на лабе также `squid.conf.spm-lab-baseline`.

## Обновление (внедрение)

`/opt/update.sh` клонирует GitHub и гоняет `install.sh`. Спросит, сносить ли `spm.db` (по умолчанию нет). `--drop-db` / `--keep-db`. При drop — импорт live conf; `squid.conf` всё равно формат после parse. `systemctl restart spmd`.

## Снятие панели

```bash
sudo /opt/spm/uninstall.sh
```

Удаляет панель, nginx vhost, allowlist, php-fpm pool, spmd, sudoers. **Squid не останавливает.**

Не удаляет (на них может ссылаться текущий Squid): `spm-listen.conf`, `acl.d`, `/etc/squid/*.keytab`, правки/include в `squid.conf`, бэкапы `*.spm-install-*` / `*.spm-listen-*`. `/etc/krb5.keytab` не трогает.

## Права и секреты

- Привилегированные команды только через **spmd** (whitelist) или узкий sudoers (`/etc/sudoers.d/spm`).
- Keytab для kinit/LDAP панели: только `/etc/squid/*.keytab`.
- nginx не в группе `squidmgr` (сокет spmd).
- Пароли, `.env`, keytab, дампы — не в git.

## Каталоги

| Путь | Назначение |
|------|------------|
| `/opt/spm` | Панель, `database/spm.db` |
| `/run/spmd.sock` | Агент |
| `agent/selinux/` | Политика php-fpm→spmd (инсталлер; skip если SELinux Disabled) |
| `/etc/nginx/conf.d/spm.conf` | Vhost панели |
| `/etc/nginx/conf.d/spm-allow.inc` | IP allowlist панели |
| `/etc/squid/squid.conf` | Live conf панели (после install/Save) |
| `/etc/squid/acl.d/` | Файловые ACL |

Решения: [`docs/adr/`](docs/adr/README.md).
