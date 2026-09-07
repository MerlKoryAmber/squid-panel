# CODEMAP — карта репозитория SPM

Обновлено: 2026-09-07, 13:30 МСК.  
Назначение: ориентир для агента **до** широкого grep. Не замена коду и ADR.

**Правило:** перед каждым `git push` — сверить и при необходимости обновить этот файл (новые маршруты, сервисы, spmd-команды, ADR).

Читать после handoff / вместе с `CLAUDE.md`. Решения — в `docs/adr/`.

---

## Стек и лаба

| Что | Где |
|-----|-----|
| Панель | PHP под `/opt/spm`, nginx **:8443**, PHP-FPM `squidmgr` |
| БД | SQLite `spm.db` (`DB_PATH`, schema `database/schema.sql` + `Database::ensureSchema`) |
| Агент | `agent/spmd.py`, socket `/run/spmd.sock`, лог `/var/log/spmd.log` |
| Live Squid | `/etc/squid/squid.conf` — только parse→backup→write→reconfigure (ADR 0005) |
| Staging | `/opt/spm/storage/tmp/` |
| ACL files | `/opt/spm/storage/acl` → `/etc/squid/acl.d` |
| Лаба | `root@192.168.0.178`, UI `https://192.168.0.178:8443/` |

Выкладка: `scp` → `/opt/spm` → человек смотрит → commit → push отдельно.  
`update.sh` / `systemctl restart squid` — только по явной команде. После `spmd.py`: `systemctl restart spmd`.

---

## Дерево (важное)

```
public/index.php          # маршруты + autoload Controllers/Services
app/Controllers/          # HTTP
app/Services/             # бизнес-логика / Squid / spmd glue
app/Core/                 # Database, Auth, View, Router, Audit
agent/spmd.py             # whitelist привилегированных команд
views/                    # PHP templates (layout.php = меню)
docs/adr/                 # архитектурные решения
docs/agent_reports/       # handoff, deferred, отчёты
docs/CODEMAP.md           # этот файл
CLAUDE.md                 # метод работы (выше дефолта Cursor)
install.sh / update.sh    # установка / переустановка; deps + chromium (ADR 0009)
tests/*_cli.php           # точечные CLI-тесты
```

---

## Политика → live conf (ADR 0005, ПРИНЯТО)

```
Save (ACL / HTTP Access / Cascade / Listen / cache toggle / …)
  → SquidLiveApply::remember()
    → SquidPolicyApply::stageFromDatabase()
         SquidConfigBuilder::generate()
         PanelNet::writeTmp('squid.conf.parse')
    → PrivilegedExecutor::squid_policy_apply
         spmd: parse staging → backup *.spm-policy-* → live → reconfigure
```

Порядок в conf: **extra** → auth_param → external_acl_type → acl → peers → http_access → listen/coredump.

- `extra_conf` — unmanaged (`cache*`, ssl_bump, …) + при флаге managed cache-off (ADR 0008).
- Большие списки сайтов — файл `acl.d`, не тысячи строк в SQLite/conf.
- Каскад: пиры по `name=`. Edit правила маршрута — пока Delete+Add (deferred).

Ключевые файлы: `SquidConfigBuilder`, `SquidPolicyApply`, `SquidLiveApply`, `SquidConfigParser` (импорт).

---

## Меню → контроллер (кратко)

| UI | Путь | Контроллер |
|----|------|------------|
| Dashboard | `/dashboard` | DashboardController |
| ACLs | `/acl` | AclController |
| AD groups | `/acl/ad-groups` | AdGroupController |
| HTTP Access | `/http_access` | HttpAccessController |
| Cascade | `/peers` | CachePeerController |
| Auth | `/auth/*` | AuthConfigController |
| Users / Logs / Stats / Audit | … | User / Log / Stats / Audit |
| Live config | `/live-config` | SquidConfController (read-only) |
| Domain discover | `/discover` | DiscoverController (ADR 0009, не Squid) |
| Settings | `/settings` | SettingsController (admin) |
| Instructions | `/instructions` | InstructionsController |

Сайдбар: `views/layout.php`. Роуты: `public/index.php`.

---

## Settings (карточки)

- Listen (`/settings/squid`) — http_port, hostname, coredump, request_header_access → Apply.
- Policy Apply (`/settings/apply-policy`) — тот же пайплайн без правок Listen.
- **Object cache** (`/settings/cache`) — `disable_cache` (ADR 0008).
- nginx allowlist (`/settings/allow`) — spmd `nginx_allow_apply`.
- Panel TLS (`/settings/tls`) — spmd `panel_tls_install` (ADR 0007).
- Theme / language / password.

---

## AD / LDAP / TLS (ADR 0006–0007, 0010)

| Тема | Сервисы / UI |
|------|----------------|
| Groups = sync members → DB → file | `AdGroupMemberSync`, `AdGroupAcl`, `/acl/ad-groups` |
| Squid ACL | `proxy_auth` + `/etc/squid/acl.d/ad_*.txt` — **нет** `-p` в conf |
| List groups | spmd `ad_ldap_groups` |
| List members | spmd `ad_ldap_group_members` |
| Timer | `spm-ad-group-sync.timer` → `install/ad_group_sync.php` |
| Root CA | `/acl/ad-groups/ca` → `ca_trust_install` |
| Panel TLS | Settings → `PanelTls` + `panel_tls_install` |
| Kerberos SSO | `/auth/kerberos` + keytab_install (не для groups) |

---

## Domain discover (ADR 0009)

- **Не** Squid / access.log / conf.
- Staging `discover-job.json` → spmd `domain_discover` → headless Chromium NetLog → **second-level domains only**.
- NetLog: только request URL из events (не constants); флаги без background networking; `CHROME_NOISE_SLD` режет google/googleapis/… кроме seed URL.
- Галка Hide ads/analytics: короткий curated denylist → `app/Data/discover_ad_denylist.txt` (ручное пополнение).
- SSRF: http(s), public IP only; lock `/run/spmd/discover.lock`.
- Нужен пакет Chromium/Chrome на хосте (`install.sh` ставит).
- Тест NetLog: `tests/domain_discover_netlog_cli.py`.

---

## spmd whitelist (ориентир)

Сверять с `ALLOWED_COMMANDS` в `agent/spmd.py` и `PrivilegedExecutor.php` (должны совпадать по смыслу):

| command | Суть |
|---------|------|
| squid_* | status/start/stop/restart/reconfigure/syntax/version |
| acl_file_install | storage/acl → acl.d |
| keytab_install | staging → `/etc/squid/*.keytab` |
| ca_trust_install | LDAP CA → trust + squid copy |
| panel_tls_install | nginx cert/key |
| ad_ldap_groups | ldapsearch simple (names) |
| ad_ldap_group_members | ldapsearch nested members |
| squid_listen_apply / squid_policy_apply | live conf |
| nginx_allow_apply | allowlist include |
| domain_discover | headless hosts |
| kinit_test / wbinfo_* / net_ads_info | диагностика |

PHP live conf **не** пишет. Fail-closed: нет в whitelist → отказ.

---

## Документы процесса

| Файл | Зачем |
|------|--------|
| `CLAUDE.md` | метод, запреты, caveman |
| `docs/agent_reports/handoff/*.md` | актуальный контекст сессии |
| `docs/agent_reports/deferred/README.md` | backlog |
| `docs/adr/README.md` | реестр решений |
| `docs/CODEMAP.md` | эта карта |

ADR статусы (сверять README): 0005 ПРИНЯТО; 0006–0009 в работе / ждут приёмки на момент обновления карты.

---

## Куда класть новое

| Задача | Куда |
|--------|------|
| Новый экран | Controller + `views/` + route + пункт в `layout.php` |
| Привилегия root | `spmd.py` + `PrivilegedExecutor` (+ staging в `storage/tmp`) |
| Директива Squid из БД | Builder fragment + колонка/`extra` + Apply 0005; при необходимости ADR |
| Решение «как делаем» | `docs/adr/NNNN-….md` + строка в `docs/adr/README.md` |
| Отложить | `docs/agent_reports/deferred/README.md` |

После крупного изменения: CODEMAP + handoff/ADR по факту → затем commit → **обновить CODEMAP ещё раз если забыли** → push.
