const enhanceTables = () => {
    document.querySelectorAll('table.table').forEach((table) => {
        const headers = Array.from(table.tHead?.rows?.[0]?.cells ?? [])
            .map((header) => header.textContent.trim());

        if (!headers.length) {
            return;
        }

        table.querySelectorAll('tbody tr').forEach((row) => {
            Array.from(row.cells).forEach((cell, index) => {
                if (cell.hasAttribute('colspan') || cell.dataset.label !== undefined) {
                    return;
                }

                cell.dataset.label = headers[index] ?? '';
            });
        });

        table.classList.add('is-card-table');
        table.closest('.table-wrap')?.classList.add('is-card-wrap');
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', enhanceTables, { once: true });
} else {
    enhanceTables();
}
