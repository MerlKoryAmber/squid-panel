<div class="page-header">
    <h2>Live Logs</h2>
    <a href="/logs" class="btn btn-secondary">← Back to Logs</a>
</div>

<div class="card">
    <div class="card-header">
        <h3>Real-time Stream</h3>
        <span class="subtitle" id="conn-status" style="color:var(--ir-success);">● Connected</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="log-viewer" id="log-container"></div>
    </div>
</div>

<script>
const container = document.getElementById('log-container');
const status = document.getElementById('conn-status');
const evtSource = new EventSource('/logs/api/stream');

evtSource.onmessage = function(e) {
    const line = document.createElement('div');
    line.className = 'log-line';
    line.textContent = e.data;
    container.appendChild(line);
    container.scrollTop = container.scrollHeight;
    if (container.children.length > 500) container.removeChild(container.firstChild);
};

evtSource.onerror = function() {
    status.textContent = '● Reconnecting...';
    status.style.color = 'var(--ir-warning)';
};

evtSource.onopen = function() {
    status.textContent = '● Connected';
    status.style.color = 'var(--ir-success)';
};
</script>
