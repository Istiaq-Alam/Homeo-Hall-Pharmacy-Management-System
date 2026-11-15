import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import './navigation';
import './pwa';
import './tables';

window.Alpine = Alpine;
window.Chart = Chart;

Chart.defaults.font.family = "'SF Pro Text', 'Inter', system-ui, -apple-system, sans-serif";
Chart.defaults.font.size = 12;
Chart.defaults.color = '#7a7a7a';

document.addEventListener('alpine:init', () => {
    Alpine.data('lineItems', () => ({
        rows: [{ medicine_id: '', quantity: 1, unit_price: '' }],
        medicines: [],

        init() {
            const data = document.getElementById('medicine-data');
            this.medicines = data ? JSON.parse(data.textContent) : [];
            const oldRows = document.getElementById('old-line-items');
            if (oldRows && oldRows.textContent.trim() !== '') {
                try {
                    const rows = JSON.parse(oldRows.textContent);
                    if (Array.isArray(rows) && rows.length) {
                        this.rows = rows;
                    }
                } catch (error) {
                    // Keep the blank row when saved input is not valid JSON.
                }
            }
        },

        addRow() {
            this.rows.push({ medicine_id: '', quantity: 1, unit_price: '' });
        },

        removeRow(index) {
            if (this.rows.length > 1) {
                this.rows.splice(index, 1);
            }
        },

        medicine(index) {
            return this.medicines.find((item) => String(item.id) === String(this.rows[index].medicine_id));
        },

        chooseMedicine(index) {
            const medicine = this.medicine(index);
            if (medicine) {
                this.rows[index].unit_price = medicine.price;
            }
        },

        stockLabel(index) {
            const medicine = this.medicine(index);
            return medicine ? `${medicine.stock} ${medicine.unit} in stock` : '';
        },

        lineTotal(index) {
            return (Number(this.rows[index].quantity) || 0) * (Number(this.rows[index].unit_price) || 0);
        },

        grandTotal() {
            return this.rows.reduce((total, row, index) => total + this.lineTotal(index), 0);
        },

        format(value) {
            return '৳' + Number(value || 0).toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
    }));
});

Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-chart]').forEach((canvas) => {
        const chartData = document.getElementById(canvas.dataset.chart);
        if (!chartData) {
            return;
        }

        const data = JSON.parse(chartData.textContent);
        new Chart(canvas, {
            type: canvas.dataset.type || 'line',
            data: {
                labels: data.labels,
                datasets: data.datasets,
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { labels: { usePointStyle: true, boxWidth: 7, color: '#555' } } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#7a7a7a' } },
                    y: { beginAtZero: true, grid: { color: '#f0f0f0' }, ticks: { color: '#7a7a7a' } },
                },
            },
        });
    });
});
