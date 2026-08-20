# Squid Proxy Manager (SPM)

Веб-панель для уже работающего Squid на **CentOS Stream 9 / Rocky 9 / AlmaLinux 9**.  
Стек: PHP (пользователь `squidmgr`) + агент **spmd** (Python, root, UNIX-сокет) + SQLite `spm.db` + nginx **:8443** (TLS).

Репозиторий: [MerlKoryAmber/squid-panel](https://github.com/MerlKoryAmber/squid-panel).

## Что панель делает

- Импорт живого `/etc/squid/squid.conf` в БД (ACL, http_access, каскад, auth, `http_port` / `visible_hostname`).
- ACL, HTTP Access, каскад (`cache_peer` по `name=`), Basic / Negotiate / NTLM.
- Kerberos: разбор `-k`/`-s`, загрузка keytab в `/etc/squid/*.keytab` через spmd.
- Группы AD: список через LDAP+GSSAPI (тот же keytab), в Squid — `ext_kerberos_ldap_group_acl`; join/winbind не обязателен.
- Большие списки сайтов — файлы `/etc/squid/acl.d/<acl>.txt`, не тысячи строк в conf.
- Settings: listen (`http_port`, hostname → `/etc/squid/spm-listen.conf` + include в `squid.conf`), IP-whitelist панели на nginx.
- **Apply to Squid**: ACL / access / cascade → `/etc/squid/spm-acl.conf`, `spm-peers.conf`, `spm-http_access.conf` + include в live, backup `*.spm-policy-*`, parse, reconfigure. Save без этой кнопки на Squid не влияет.
- Статус Squid: `/proc` и `systemctl is-active squid` (не полный `systemctl status`).

## Чего панель не делает

- **Не ставит и не заменяет Squid.** Сначала должен быть рабочий прокси и непустой `squid.conf`.
- **Install / update.sh не переписывают** живой `squid.conf` и **не рестартят Squid**.
- Генератор не перезаписывает `squid.conf` целиком. Auth / ssl_bump / refresh_pattern в v1 не выкладываются из БД.
- Listen: отдельная кнопка **Save and apply to Squid** → `spm-listen.conf`.

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
- Если есть `/etc/krb5.keytab` и ещё нет `/etc/squid/krb5.keytab` — install копирует (640, `squid:squid`). Живой helper в conf не меняется.
- Импорт conf в новую `spm.db`. Копия conf: `/etc/squid/squid.conf.spm-install-<timestamp>`.

## Обновление (внедрение)

`/opt/update.sh` клонирует GitHub и снова гоняет `install.sh`.

**Сносит `/opt/spm`, включая `spm.db`.** Squid и `squid.conf` не трогает. После обновления — повторный импорт, `systemctl restart spmd`.

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
| `/etc/nginx/conf.d/spm.conf` | Vhost панели |
| `/etc/nginx/conf.d/spm-allow.inc` | IP allowlist панели |
| `/etc/squid/acl.d/` | Файловые ACL |
| `/etc/squid/spm-listen.conf` | Listen/hostname с панели |
| `/etc/squid/spm-acl.conf` | ACL с панели (после Apply) |
| `/etc/squid/spm-peers.conf` | Каскад с панели (после Apply) |
| `/etc/squid/spm-http_access.conf` | http_access с панели (после Apply) |

Решения: [`docs/adr/`](docs/adr/README.md).
