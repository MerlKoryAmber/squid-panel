<div class="page-header"><h2>Live Log Tail</h2></div>

<div class="panel">
    <div class="log-container" id="logContainer">
        <div class="log-empty">Connecting to log stream...</div>
    </div>
</div>

<script>
let lastSize = 0;
let container = document.getElementById('logContainer');

function fetchLogs() {
    fetch('/logs/api/stream?last_size=' + lastSize)
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                container.innerHTML = '<div class="log-empty">' + data.error + '</div>';
                return;
            }
            lastSize = data.size;
            if (data.lines.length > 0) {
                const empty = container.querySelector('.log-empty');
                if (empty) empty.remove();

                data.lines.forEach(line => {
                    const div = document.createElement('div');
                    div.className = 'log-line';
                    div.innerHTML = `<span class="log-time">${line.timestamp}</span> <span class="log-ip">${line.client_ip}</span> <span class="log-status">${line.status.split('/')[0]}</span> ${line.method} ${line.url}`;
                    container.appendChild(div);
                });

                while (container.children.length > 500) {
                    container.removeChild(container.firstChild);
                }
                container.scrollTop = container.scrollHeight;
            }
        })
        .catch(e => console.error(e));
}

setInterval(fetchLogs, 2000);
fetchLogs();
</script>
