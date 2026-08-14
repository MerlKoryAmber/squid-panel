<div class="page-header">
    <h2>Statistics</h2>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Requests (24h)</div>
        <div class="stat-value"><?= number_format($stats['total_requests'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Cache Hits</div>
        <div class="stat-value success"><?= number_format($stats['cache_hits'] ?? 0) ?></div>
        <div class="stat-meta"><?= $stats['hit_ratio'] ?? '0%' ?> hit ratio</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Cache Misses</div>
        <div class="stat-value warning"><?= number_format($stats['cache_misses'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Errors</div>
        <div class="stat-value danger"><?= number_format($stats['errors'] ?? 0) ?></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Hourly Traffic</h3></div>
    <div class="card-body">
        <div id="hourly-chart" style="width:100%; height:300px;"></div>
    </div>
</div>

<script src="/assets/js/chart.js"></script>
<script>
const hourly = <?= json_encode($stats['hourly'] ?? []) ?>;
new Chart(document.getElementById('hourly-chart'), {
    type: 'bar',
    data: {
        labels: hourly.map(h => h.hour + ':00'),
        datasets: [{
            label: 'Requests',
            data: hourly.map(h => h.count),
            backgroundColor: '#1a2d4a',
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, ticks: { color: '#8a94a3', font: { size: 11 } } },
            y: { grid: { color: '#e2e5e9' }, ticks: { color: '#8a94a3', font: { size: 11 } } }
        }
    }
});
</script>
