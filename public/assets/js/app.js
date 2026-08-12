/* Squid Proxy Manager — Frontend */

document.addEventListener('DOMContentLoaded', function() {
    updateStatus();
    setInterval(updateStatus, 10000);
});

function updateStatus() {
    fetch('/api/squid/status')
        .then(r => r.json())
        .then(data => {
            const badge = document.getElementById('squidStatus');
            if (!badge) return;

            badge.className = 'status-badge ' + (data.status || 'unknown');
            badge.innerHTML = '<span class="status-dot"></span>' + (data.status || 'unknown');
        })
        .catch(() => {
            const badge = document.getElementById('squidStatus');
            if (badge) {
                badge.className = 'status-badge error';
                badge.innerHTML = '<span class="status-dot"></span>unreachable';
            }
        });
}

function serviceAction(action) {
    const msg = document.getElementById('serviceMessage');
    if (msg) {
        msg.style.display = 'block';
        msg.className = 'message-box';
        msg.textContent = 'Processing...';
    }

    fetch('/service/' + action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'spm_csrf_token=' + encodeURIComponent(document.querySelector('input[name="spm_csrf_token"]')?.value || '')
    })
    .then(r => r.json())
    .then(data => {
        if (msg) {
            msg.className = 'message-box ' + (data.success ? 'success' : 'error');
            msg.textContent = data.message || (data.success ? 'Success' : 'Failed');
        }
        updateStatus();
    })
    .catch(err => {
        if (msg) {
            msg.className = 'message-box error';
            msg.textContent = 'Error: ' + err.message;
        }
    });
}

function testKerberos() {
    const result = document.getElementById('kerberosTestResult');
    if (result) {
        result.style.display = 'block';
        result.className = 'message-box';
        result.textContent = 'Testing...';
    }

    const form = document.querySelector('form[action="/auth/kerberos/save"]');
    const data = new FormData(form);

    fetch('/auth/kerberos/test', {
        method: 'POST',
        body: data
    })
    .then(r => r.json())
    .then(data => {
        if (result) {
            result.className = 'message-box ' + (data.success ? 'success' : 'error');
            result.textContent = data.output || data.message || 'Test complete';
        }
    })
    .catch(err => {
        if (result) {
            result.className = 'message-box error';
            result.textContent = 'Error: ' + err.message;
        }
    });
}
