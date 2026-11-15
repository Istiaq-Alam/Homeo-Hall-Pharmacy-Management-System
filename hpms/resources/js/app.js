import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import './navigation';
import './pwa';
import './tables';

window.Alpine = Alpine;
window.Chart = Chart;

const themeTokens = () => getComputedStyle(document.documentElement);
const themeColor = (token, fallback) => themeTokens().getPropertyValue(token).trim() || fallback;

// Chart colours come from the active theme's tokens, never from fixed hex, so a
// series picks up the theme accent and every chart repaints when the theme changes.
const chartInstances = [];
const chartPalette = () => ({
    accent: themeColor('--t-accent', '#0066cc'),
    accentSoft: themeColor('--t-accent-soft', '#eaf3fc'),
    positive: themeColor('--t-success-ink', '#27673a'),
    muted: themeColor('--t-muted', '#7a7a7a'),
    line: themeColor('--t-line', '#d2d2d7'),
    grid: themeColor('--t-line-soft', '#f0f0f0'),
    body: themeColor('--t-body', '#555555'),
    font: themeColor('--t-font', "'Inter', system-ui, -apple-system, sans-serif"),
});

const chartSeriesColors = (role, type, palette) => {
    if (role === 'positive') {
        return { borderColor: palette.positive, backgroundColor: palette.positive };
    }
    if (role === 'neutral') {
        return { borderColor: palette.muted, backgroundColor: type === 'bar' ? palette.line : palette.muted };
    }

    return { borderColor: palette.accent, backgroundColor: type === 'bar' ? palette.accent : palette.accentSoft };
};

const paintCharts = () => {
    const palette = chartPalette();
    Chart.defaults.font.family = palette.font;
    Chart.defaults.font.size = 12;
    Chart.defaults.color = palette.muted;

    chartInstances.forEach((chart) => {
        const type = chart.config.type;
        chart.data.datasets.forEach((dataset) => {
            const colors = chartSeriesColors(dataset.role || 'accent', type, palette);
            dataset.borderColor = colors.borderColor;
            dataset.backgroundColor = colors.backgroundColor;
        });
        const { plugins, scales } = chart.options;
        if (plugins?.legend?.labels) {
            plugins.legend.labels.color = palette.body;
        }
        if (scales?.x?.ticks) {
            scales.x.ticks.color = palette.muted;
        }
        if (scales?.y?.ticks) {
            scales.y.ticks.color = palette.muted;
        }
        if (scales?.y?.grid) {
            scales.y.grid.color = palette.grid;
        }
        chart.update('none');
    });
};

Chart.defaults.font.family = "'SF Pro Text', 'Inter', system-ui, -apple-system, sans-serif";
Chart.defaults.font.size = 12;
Chart.defaults.color = themeColor('--t-muted', '#7a7a7a');
document.addEventListener('themechange', paintCharts);
new MutationObserver(paintCharts).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

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
        chartInstances.push(new Chart(canvas, {
            type: canvas.dataset.type || 'line',
            data: {
                labels: data.labels,
                datasets: data.datasets,
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { labels: { usePointStyle: true, boxWidth: 7, color: themeColor('--t-body', '#555') } } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: themeColor('--t-muted', '#7a7a7a') } },
                    y: { beginAtZero: true, grid: { color: themeColor('--t-line-soft', '#f0f0f0') }, ticks: { color: themeColor('--t-muted', '#7a7a7a') } },
                },
            },
        }));
    });

    paintCharts();
});
