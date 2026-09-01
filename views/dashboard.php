<div class="stats-grid">
    <?php
    $httpPort = trim((string)($httpPort ?? ''));
    $visibleHostname = trim((string)($visibleHostname ?? ''));
    $portFirst = $httpPort !== '' ? (preg_split('/\s+/', $httpPort)[0] ?? $httpPort) : '—';
    ?>
    <div class="stat-card">
        <div class="stat-label">HTTP Access Rules</div>
        <div class="stat-value"><?= $stats['http_access'] ?? 0 ?></div>
        <div class="stat-meta">Active filtering rules</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">ACLs Defined</div>
        <div class="stat-value"><?= $stats['acls'] ?? 0 ?></div>
        <div class="stat-meta">Access control lists</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Cascade peers</div>
        <div class="stat-value"><?= $stats['peers'] ?? 0 ?></div>
        <div class="stat-meta">Upstream proxies</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Peer Access Rules</div>
        <div class="stat-value"><?= $stats['peer_access'] ?? 0 ?></div>
        <div class="stat-meta">Routing decisions</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Auth Methods</div>
        <div class="stat-value"><?= $stats['auth'] ?? 0 ?></div>
        <div class="stat-meta">Configured backends</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Squid http_port</div>
        <div class="stat-value" style="font-size:1.4rem; word-break:break-all;"><?= htmlspecialchars($portFirst) ?></div>
        <div class="stat-meta"><?= htmlspecialchars($httpPort !== '' ? str_replace("\n", ' · ', $httpPort) : 'not imported') ?><?= !empty($visibleHostname) ? ' · ' . htmlspecialchars($visibleHostname) : '' ?></div>
    </div>
</div>

<?php
$nh = $negotiateHelpers ?? [];
$nhOk = !empty($nh['ok']);
$nhReadable = !empty($nh['readable']);
?>
<div class="card" style="margin-bottom: var(--space-lg);">
    <div class="card-header">
        <h3>Negotiate helpers</h3>
        <span class="subtitle">hourly cron + cache.log · <?= (int)($nh['sample_count'] ?? 0) ?> samples / <?= (int)($nh['window_h'] ?? 168) ?>h</span>
    </div>
    <div class="card-body">
        <?php if (!$nhReadable): ?>
        <p style="color:var(--ir-text-muted); margin:0;">Cannot read <code>/var/log/squid/cache.log</code>. Panel user needs ACL like access.log.</p>
        <?php else: ?>
        <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:12px;">
            <span class="badge <?= $nhOk ? 'badge-success' : 'badge-danger' ?>">
                <?= $nhOk ? 'No helper queue' : 'Queue / overload' ?>
            </span>
            <?php if (!empty($nh['configured'])): ?>
            <span style="color:var(--ir-text-muted); font-size:0.85rem;">
                children <?= (int)$nh['children'] ?>
                · startup <?= (int)$nh['startup'] ?>
                · idle <?= (int)$nh['idle'] ?>
            </span>
            <?php else: ?>
            <span style="color:var(--ir-text-muted); font-size:0.85rem;">No negotiate scheme in DB</span>
            <?php endif; ?>
        </div>
        <?php if ($nhOk): ?>
        <p style="color:var(--ir-text-secondary); margin:0; font-size:0.9rem;">
            <?php if (!empty($nh['from_hourly'])): ?>
            No negotiate helper queue in stored hourly samples (<?= (int)($nh['window_h'] ?? 168) ?>h).
            <?php if (!empty($nh['last_sample_hour'])): ?>
            Last sample hour: <?= htmlspecialchars($nh['last_sample_hour']) ?>.
            <?php endif; ?>
            Peak queued (7d): <?= (int)($nh['peak_queued_7d'] ?? 0) ?>.
            <?php else: ?>
            No hourly samples yet — after <code>update.sh</code> or next Dashboard visit (auto, hourly). Live log (24h): no busy/FATAL.
            <?php endif; ?>
        </p>
        <?php else: ?>
        <p style="color:var(--ir-text-secondary); margin:0; font-size:0.9rem;">
            Busy warnings: <strong><?= (int)$nh['busy_count'] ?></strong>
            <?php if ($nh['busy_ratio'] !== ''): ?> (<?= htmlspecialchars($nh['busy_ratio']) ?>)<?php endif; ?>
            · max queued: <strong><?= (int)$nh['max_queued'] ?></strong>
            · FATAL: <strong><?= (int)$nh['fatal_count'] ?></strong>
            <?php if (!empty($nh['last_busy'])): ?>
            · last: <?= htmlspecialchars($nh['last_busy']) ?>
            <?php endif; ?>
            <br>Raise <strong>Children</strong> (Kerberos). FATAL = chronic overload.
        </p>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="dashboard-grid">
    <div class="dashboard-main">
        <div class="card">
            <div class="card-header">
                <h3>Squid Service Status</h3>
                <span class="subtitle" id="status-time">Updating...</span>
            </div>
            <div class="card-body">
                <div id="status-content">
                    <div class="empty-state">Loading status...</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Traffic Overview (24h)</h3>
                <span class="subtitle">Requests per hour</span>
            </div>
            <div class="card-body">
                <div class="chart-wrap">
                    <canvas id="traffic-chart"></canvas>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Top Domains (24h)</h3>
                <span class="subtitle">Most requested destinations</span>
            </div>
            <div class="card-body">
                <div class="chart-wrap">
                    <canvas id="domains-chart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-side">
        <div class="card">
            <div class="card-header">
                <h3>Quick Actions</h3>
            </div>
            <div class="card-body">
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <a href="/http_access" class="btn btn-secondary">Manage HTTP Access</a>
                    <a href="/acl" class="btn btn-secondary">Manage ACLs</a>
                    <a href="/peers" class="btn btn-secondary">Cascade</a>
                    <a href="/users" class="btn btn-secondary">Users & Password</a>
                    <?php if (!empty($isAdmin)): ?>
                    <a href="/settings" class="btn btn-secondary">Settings</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Recent Audit Events</h3>
            </div>
            <div class="card-body">
                <?php if (empty($auditLogs)): ?>
                <div class="empty-state" style="padding: var(--space-lg);">
                    <p>No recent events</p>
                </div>
                <?php else: ?>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <?php foreach (array_slice($auditLogs, 0, 5) as $event): ?>
                    <div style="padding: 10px; background: var(--ir-bg); border-radius: var(--radius-sm); font-size: 0.82rem;">
                        <div style="font-weight: 600; color: var(--ir-text);"><?= htmlspecialchars($event['action']) ?></div>
                        <div style="color: var(--ir-text-muted); font-size: 0.75rem; margin-top: 2px;">
                            <?= htmlspecialchars($event['user'] ?? 'system') ?> · <?= $event['created_at'] ?>
                        </div>
                        <?php if ($event['details']): ?>
                        <div style="color: var(--ir-text-secondary); margin-top: 4px;"><?= htmlspecialchars($event['details']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <a href="/audit" class="btn btn-sm btn-secondary" style="margin-top: var(--space-md); width:100%;">View All Events</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/chart.js"></script>
<script>
function fmt(value, suffix) {
    if (value === null || value === undefined || value === '') return 'N/A';
    return suffix ? (value + suffix) : String(value);
}

function updateStatus() {
    fetch('/api/squid/status', { credentials: 'same-origin' })
        .then(r => r.text().then(text => ({ r, text })))
        .then(({ r, text }) => {
            if (r.status === 401) throw new Error('session');
            if (!r.ok) throw new Error('status');
            const data = JSON.parse(text);
            const isRunning = !!data.running;
            const html = `
                <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
                    <span class="badge ${isRunning ? 'badge-success' : (data.status === 'error' ? 'badge-danger' : 'badge-danger')}" style="font-size:0.85rem; padding:6px 14px;">
                        ${isRunning ? '● Running' : (data.status === 'error' ? '● Unavailable' : '● Stopped')}
                    </span>
                    <span style="color:var(--ir-text-muted); font-size:0.85rem;">
                        PID: ${fmt(data.pid)} · Uptime: ${fmt(data.uptime)}
                    </span>
                </div>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap:12px;">
                    <div style="padding:12px; background:var(--ir-bg); border-radius:var(--radius-sm);">
                        <div style="font-size:0.75rem; color:var(--ir-text-muted); text-transform:uppercase; letter-spacing:0.05em;">CPU</div>
                        <div style="font-size:1.2rem; font-weight:700; color:var(--ir-text); margin-top:4px;">${fmt(data.cpu, '%')}</div>
                    </div>
                    <div style="padding:12px; background:var(--ir-bg); border-radius:var(--radius-sm);">
                        <div style="font-size:0.75rem; color:var(--ir-text-muted); text-transform:uppercase; letter-spacing:0.05em;">Memory</div>
                        <div style="font-size:1.2rem; font-weight:700; color:var(--ir-text); margin-top:4px;">${fmt(data.memory)}</div>
                    </div>
                    <div style="padding:12px; background:var(--ir-bg); border-radius:var(--radius-sm);">
                        <div style="font-size:0.75rem; color:var(--ir-text-muted); text-transform:uppercase; letter-spacing:0.05em;">Connections</div>
                        <div style="font-size:1.2rem; font-weight:700; color:var(--ir-text); margin-top:4px;">${fmt(data.connections)}</div>
                    </div>
                </div>
            `;
            document.getElementById('status-content').innerHTML = html;
            document.getElementById('status-time').textContent = 'Updated: ' + new Date().toLocaleTimeString();
        })
        .catch(err => {
            const msg = err && err.message === 'session'
                ? 'Session expired. Refresh the page and sign in again.'
                : 'Failed to load status.';
            document.getElementById('status-content').innerHTML = '<div class="alert alert-danger">' + msg + '</div>';
        });
}

function loadCharts() {
    const css = getComputedStyle(document.documentElement);
    const accent = (css.getPropertyValue('--ir-accent') || '#c9a96e').trim();
    const accentHover = (css.getPropertyValue('--ir-accent-hover') || '#b89a60').trim();
    const accentFill = (css.getPropertyValue('--ir-accent-light') || 'rgba(201,169,110,0.16)').trim();
    fetch('/api/squid/stats', { credentials: 'same-origin' })
        .then(r => {
            if (r.status === 401) throw new Error('session');
            if (!r.ok) throw new Error('status');
            return r.json();
        })
        .then(data => {
            if (data.error) {
                const note = document.createElement('div');
                note.className = 'alert alert-danger';
                note.textContent = data.error;
                const trafficCard = document.getElementById('traffic-chart').closest('.card-body');
                if (trafficCard) trafficCard.prepend(note);
            }

            const traffic = Array.isArray(data.hourly) ? data.hourly : [];
            const trafficEl = document.getElementById('traffic-chart');
            new Chart(trafficEl, {
                type: 'line',
                data: {
                    labels: traffic.map(h => (h.hour || '00') + ':00'),
                    datasets: [{
                        label: 'Requests',
                        data: traffic.map(h => h.count || 0),
                        borderColor: accent,
                        backgroundColor: accentFill,
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3,
                        pointBackgroundColor: accent,
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: '#8a94a3', font: { size: 11 } } },
                        y: { grid: { color: '#2a3548' }, ticks: { color: '#8a94a3', font: { size: 11 } } }
                    }
                }
            });

            const domains = Array.isArray(data.topDomains) ? data.topDomains : [];
            new Chart(document.getElementById('domains-chart'), {
                type: 'bar',
                data: {
                    labels: domains.map(d => {
                        const name = (d && d.domain) ? String(d.domain) : '';
                        return name.length > 20 ? name.substring(0, 20) + '...' : name;
                    }),
                    datasets: [{
                        label: 'Requests',
                        data: domains.map(d => d.count || 0),
                        backgroundColor: accent,
                        borderRadius: 4,
                        barThickness: 20
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { color: '#2a3548' }, ticks: { color: '#8a94a3', font: { size: 11 } } },
                        y: { grid: { display: false }, ticks: { color: accentHover, font: { size: 11 } } }
                    }
                }
            });
        })
        .catch(err => {
            const msg = err && err.message === 'session'
                ? 'Session expired. Refresh the page and sign in again.'
                : 'Failed to load traffic stats. The panel user needs read access to /var/log/squid/access.log.';
            document.getElementById('traffic-chart').parentNode.innerHTML = '<div class="alert alert-danger">' + msg + '</div>';
        });
}

updateStatus();
loadCharts();
setInterval(updateStatus, 30000);
</script>
