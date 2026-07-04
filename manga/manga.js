(function () {
    const table = document.getElementById('manga-table');
    const searchInput = document.getElementById('manga-search');
    if (!table) return;

    const tbody = table.querySelector('tbody');
    const headers = table.querySelectorAll('thead th.sortable');
    let sortCol = -1;
    let sortAsc = true;

    function getRows() {
        return Array.from(tbody.querySelectorAll('tr'));
    }

    function filterRows() {
        const query = (searchInput?.value || '').trim().toLowerCase();
        getRows().forEach(row => {
            if (!query) {
                row.classList.remove('filtered-out');
                return;
            }
            const text = row.textContent.toLowerCase();
            row.classList.toggle('filtered-out', !text.includes(query));
        });
    }

    function sortRows(colIndex) {
        if (sortCol === colIndex) {
            sortAsc = !sortAsc;
        } else {
            sortCol = colIndex;
            sortAsc = true;
        }

        headers.forEach((th, i) => {
            th.classList.remove('sort-asc', 'sort-desc');
            if (i === colIndex) {
                th.classList.add(sortAsc ? 'sort-asc' : 'sort-desc');
            }
        });

        const rows = getRows();

        rows.sort((a, b) => {
            const aVal = (a.cells[colIndex]?.textContent || '').trim().toLowerCase();
            const bVal = (b.cells[colIndex]?.textContent || '').trim().toLowerCase();
            const cmp = aVal.localeCompare(bVal, 'fr', { sensitivity: 'base' });
            return sortAsc ? cmp : -cmp;
        });

        rows.forEach(r => tbody.appendChild(r));
    }

    headers.forEach((th, index) => {
        th.addEventListener('click', () => sortRows(index));
    });

    searchInput?.addEventListener('input', filterRows);
})();
