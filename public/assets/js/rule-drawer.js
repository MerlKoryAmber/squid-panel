(function () {
    const root = document.getElementById('spm-rule-drawer');
    if (!root) {
        return;
    }
    const form = document.getElementById('spm-rule-form');
    const titleEl = document.getElementById('spm-rule-drawer-title');
    const idInput = form.querySelector('input[name="id"]');
    const nameInput = form.querySelector('input[name="name"]');
    const actionSelect = form.querySelector('select[name="action"]');
    const enabledInput = form.querySelector('input[name="enabled"]');
    const fromSelect = form.querySelector('select[name="from[]"]');
    const toSelect = form.querySelector('select[name="to[]"]');
    const extra = document.getElementById('spm-rule-drawer-extra');
    const toggleForm = document.getElementById('spm-rule-toggle');
    const deleteForm = document.getElementById('spm-rule-delete');
    const toggleId = toggleForm ? toggleForm.querySelector('input[name="id"]') : null;
    const deleteId = deleteForm ? deleteForm.querySelector('input[name="id"]') : null;
    const toggleBtn = toggleForm ? toggleForm.querySelector('button') : null;

    function setMulti(select, names) {
        const set = {};
        (names || []).forEach(function (n) { set[n] = true; });
        Array.from(select.options).forEach(function (opt) {
            opt.selected = !!set[opt.value];
        });
    }

    function openDrawer() {
        root.hidden = false;
        document.body.classList.add('spm-drawer-open');
        nameInput.focus();
    }

    function closeDrawer() {
        root.hidden = true;
        document.body.classList.remove('spm-drawer-open');
        document.querySelectorAll('#rulesTable tbody tr.is-selected').forEach(function (tr) {
            tr.classList.remove('is-selected');
        });
        if (window.history && window.history.replaceState) {
            window.history.replaceState({}, '', '/http_access');
        }
    }

    function openAdd() {
        titleEl.textContent = 'Add rule';
        form.action = '/http_access/store';
        idInput.value = '';
        nameInput.value = '';
        actionSelect.value = 'allow';
        enabledInput.checked = true;
        setMulti(fromSelect, []);
        setMulti(toSelect, []);
        if (extra) {
            extra.hidden = true;
        }
        document.querySelectorAll('#rulesTable tbody tr.is-selected').forEach(function (tr) {
            tr.classList.remove('is-selected');
        });
        openDrawer();
        if (window.history && window.history.replaceState) {
            window.history.replaceState({}, '', '/http_access?add=1');
        }
    }

    function openEdit(tr) {
        let data;
        try {
            data = JSON.parse(tr.getAttribute('data-rule') || '{}');
        } catch (e) {
            return;
        }
        if (!data.simple) {
            const expert = tr.querySelector('form[action="/ui/policy-mode"]');
            if (expert) {
                expert.submit();
            }
            return;
        }
        titleEl.textContent = 'Edit rule';
        form.action = '/http_access/update';
        idInput.value = String(data.id || '');
        nameInput.value = data.name || '';
        actionSelect.value = data.action === 'deny' ? 'deny' : 'allow';
        enabledInput.checked = !!data.enabled;
        setMulti(fromSelect, data.from || []);
        setMulti(toSelect, data.to || []);
        if (extra) {
            extra.hidden = !toggleForm;
        }
        if (toggleId) {
            toggleId.value = String(data.id || '');
        }
        if (deleteId) {
            deleteId.value = String(data.id || '');
        }
        if (toggleBtn) {
            toggleBtn.textContent = data.enabled ? 'Disable' : 'Enable';
        }
        document.querySelectorAll('#rulesTable tbody tr.is-selected').forEach(function (row) {
            row.classList.remove('is-selected');
        });
        tr.classList.add('is-selected');
        openDrawer();
        if (window.history && window.history.replaceState) {
            window.history.replaceState({}, '', '/http_access?edit=' + encodeURIComponent(data.id));
        }
    }

    root.querySelectorAll('[data-drawer-close]').forEach(function (el) {
        el.addEventListener('click', closeDrawer);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' || root.hidden) {
            return;
        }
        const confirmBox = document.getElementById('spm-confirm');
        if (confirmBox && !confirmBox.hidden) {
            return;
        }
        closeDrawer();
    }, true);

    const addBtn = document.getElementById('spm-rule-add');
    if (addBtn) {
        addBtn.addEventListener('click', function (e) {
            e.preventDefault();
            openAdd();
        });
    }

    const table = document.getElementById('rulesTable');
    if (table) {
        table.querySelector('tbody').addEventListener('click', function (e) {
            if (e.target.closest('.drag-handle')) {
                return;
            }
            if (e.target.closest('button')) {
                return;
            }
            const tr = e.target.closest('tr[data-rule]');
            if (tr) {
                e.preventDefault();
                openEdit(tr);
            }
        });
    }

    window.spmRuleDrawer = { openAdd: openAdd, openEdit: openEdit, close: closeDrawer };
})();
