<div class="page-header">
    <h2>Statistics</h2>
    <p id="stats-status" style="color:var(--ir-text-muted);margin:0 0 1rem;">Loading last 24h from access.log…</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Requests (24h)</div>
        <div class="stat-value" id="stat-total">—</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Cache Hits</div>
        <div class="stat-value success" id="stat-hits">—</div>
        <div class="stat-meta" id="stat-hit-ratio"></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Cache Misses</div>
        <div class="stat-value warning" id="stat-misses">—</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Errors</div>
        <div class="stat-value danger" id="stat-errors">—</div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Hourly Traffic</h3></div>
    <div class="card-body">
        <div class="chart-wrap">
            <canvas id="hourly-chart"></canvas>
        </div>
    </div>
</div>

<script src="/assets/js/chart.js"></script>
<script>
(function () {
    const statusEl = document.getElementById('stats-status');
    const chartCanvas = document.getElementById('hourly-chart');
    let chart = null;

    function fmt(n) {
        return Number(n || 0).toLocaleString();
    }

    function applyStats(stats) {
        if (stats.error) {
            statusEl.textContent = stats.error;
            return;
        }
        statusEl.textContent = 'Last 24 hours (recent log window)';
        document.getElementById('stat-total').textContent = fmt(stats.total_requests);
        document.getElementById('stat-hits').textContent = fmt(stats.cache_hits);
        document.getElementById('stat-hit-ratio').textContent = (stats.hit_ratio || '0%') + ' hit ratio';
        document.getElementById('stat-misses').textContent = fmt(stats.cache_misses);
        document.getElementById('stat-errors').textContent = fmt(stats.errors);

        const hourly = stats.hourly || [];
        const data = {
            labels: hourly.map(h => h.hour + ':00'),
            datasets: [{
                label: 'Requests',
                data: hourly.map(h => h.count),
                backgroundColor: '#1a2d4a',
                borderRadius: 4
            }]
        };
        const options = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#8a94a3', font: { size: 11 } } },
                y: { grid: { color: '#e2e5e9' }, ticks: { color: '#8a94a3', font: { size: 11 } } }
            }
        };
        if (chart) {
            chart.data = data;
            chart.update();
        } else {
            chart = new Chart(chartCanvas, { type: 'bar', data, options });
        }
    }

    fetch('/stats/api/data?hours=24', { credentials: 'same-origin' })
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(applyStats)
        .catch(err => {
            statusEl.textContent = 'Failed to load statistics: ' + err.message;
        });
})();
</script>
