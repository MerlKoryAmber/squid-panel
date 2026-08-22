(function () {
    document.querySelectorAll('table.js-col-sort').forEach(function (table) {
        const tbody = table.tBodies[0];
        if (!tbody) {
            return;
        }
        table.querySelectorAll('th[data-col]').forEach(function (th) {
            th.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' && e.key !== ' ') {
                    return;
                }
                e.preventDefault();
                th.click();
            });
            th.addEventListener('click', function () {
                const col = th.getAttribute('data-col');
                const cur = table.getAttribute('data-sort-col');
                const dir = (cur === col && table.getAttribute('data-sort-dir') === 'asc') ? 'desc' : 'asc';
                table.setAttribute('data-sort-col', col);
                table.setAttribute('data-sort-dir', dir);
                table.querySelectorAll('th[data-col]').forEach(function (h) {
                    h.setAttribute('aria-sort', h === th ? (dir === 'asc' ? 'ascending' : 'descending') : 'none');
                });
                const mul = dir === 'asc' ? 1 : -1;
                const rows = Array.prototype.slice.call(tbody.rows);
                rows.sort(function (a, b) {
                    const av = (a.querySelector('[data-col="' + col + '"]') || a).getAttribute('data-sort') || '';
                    const bv = (b.querySelector('[data-col="' + col + '"]') || b).getAttribute('data-sort') || '';
                    return av.localeCompare(bv, undefined, { numeric: true, sensitivity: 'base' }) * mul;
                });
                rows.forEach(function (row) {
                    tbody.appendChild(row);
                });
            });
        });
    });
})();
