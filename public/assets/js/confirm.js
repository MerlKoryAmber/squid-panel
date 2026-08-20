(function () {
    const box = document.getElementById('spm-confirm');
    if (!box) return;
    const textEl = document.getElementById('spm-confirm-text');
    const okBtn = document.getElementById('spm-confirm-ok');
    const cancelBtn = document.getElementById('spm-confirm-cancel');
    let pending = null;

    function close() {
        box.hidden = true;
        pending = null;
    }

    function open(message, onOk) {
        textEl.textContent = message;
        pending = onOk;
        box.hidden = false;
        okBtn.focus();
    }

    okBtn.addEventListener('click', function () {
        const fn = pending;
        close();
        if (fn) fn();
    });
    cancelBtn.addEventListener('click', close);
    box.querySelector('.spm-confirm-backdrop').addEventListener('click', close);
    document.addEventListener('keydown', function (e) {
        if (box.hidden) return;
        if (e.key === 'Escape') {
            e.preventDefault();
            close();
        }
    });

    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        const msg = form.getAttribute('data-confirm');
        if (!msg || form.getAttribute('data-confirm-ok') === '1') return;
        e.preventDefault();
        open(msg, function () {
            form.setAttribute('data-confirm-ok', '1');
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });
    });

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-confirm]');
        if (!btn || btn.tagName !== 'BUTTON') return;
        if (btn.form && btn.form.hasAttribute('data-confirm')) return;
        const msg = btn.getAttribute('data-confirm');
        if (!msg) return;
        const formId = btn.getAttribute('form');
        const form = btn.form || (formId ? document.getElementById(formId) : null);
        if (!form) return;
        e.preventDefault();
        open(msg, function () {
            form.setAttribute('data-confirm-ok', '1');
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });
    });
})();
