import {
    Chart, LineController, LineElement, PointElement,
    BarController, BarElement, DoughnutController, ArcElement,
    CategoryScale, LinearScale, Tooltip, Legend,
} from 'chart.js';

// Only what the three chart types need is registered, which keeps the bundle small.
Chart.register(
    LineController, LineElement, PointElement,
    BarController, BarElement,
    DoughnutController, ArcElement,
    CategoryScale, LinearScale, Tooltip, Legend,
);

/*
 * Loaded only on pages that contain <x-chart> (see resources/views/components/chart.blade.php).
 * The component outputs <div data-chart='{"type":"line","labels":[...],"series":[...]}'><canvas></canvas></div>
 * and this file draws it. Types: line, bar (vertical or horizontal) and doughnut.
 */

const root = document.documentElement;
const SELECTOR = '[data-chart]';

// Used only if a Bootstrap variable is missing.
const FALLBACK = {
    primary: '#0d6efd', success: '#198754', warning: '#ffc107',
    danger: '#dc3545', info: '#0dcaf0', secondary: '#6c757d',
};

function cssVar(name, fallback) {
    return getComputedStyle(root).getPropertyValue(name).trim() || fallback;
}

/**
 * A canvas cannot use CSS, so the colours are read from Bootstrap's variables when the chart is drawn.
 * They differ between data-bs-theme="light" and "dark", which is how the chart follows the user's theme.
 */
function palette() {
    return {
        text: cssVar('--bs-body-color', '#212529'),
        grid: cssVar('--bs-border-color', '#dee2e6'),
        surface: cssVar('--bs-body-bg', '#ffffff'),
        font: getComputedStyle(document.body).fontFamily || 'sans-serif',
    };
}

function themeColor(name) {
    return cssVar(`--bs-${name}`, FALLBACK[name] || FALLBACK.primary);
}

function legend(colors, display = true) {
    return { display, position: 'bottom', labels: { color: colors.text, usePointStyle: true, boxWidth: 8, boxHeight: 8 } };
}

function buildDoughnut(spec, colors, number, unit) {
    const series = spec.series[0] || { data: [], color: [] };
    const names = Array.isArray(series.color) ? series.color : [series.color];

    return {
        type: 'doughnut',
        data: {
            labels: spec.labels,
            datasets: [{
                label: series.label,
                data: series.data,
                backgroundColor: spec.labels.map((_, i) => themeColor(names[i % names.length])),
                borderColor: colors.surface,    // a gap between slices, in the card's own colour
                borderWidth: 2,
                hoverOffset: 6,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            font: { family: colors.font },
            color: colors.text,
            plugins: {
                legend: legend(colors),
                tooltip: { callbacks: { label: (item) => `${item.label}: ${number.format(item.parsed)}${unit}` } },
            },
        },
    };
}

function buildBar(spec, colors, number, unit) {
    const horizontal = Boolean(spec.horizontal);
    const value = horizontal ? 'x' : 'y';     // the axis that carries the numbers
    const category = horizontal ? 'y' : 'x';

    return {
        type: 'bar',
        data: {
            labels: spec.labels,
            datasets: spec.series.map((s) => ({
                label: s.label,
                data: s.data,
                backgroundColor: themeColor(s.color),
                borderRadius: 4,
                maxBarThickness: 32,
            })),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: horizontal ? 'y' : 'x',
            font: { family: colors.font },
            color: colors.text,
            plugins: {
                legend: legend(colors, spec.series.length > 1),   // one series needs no legend
                tooltip: { callbacks: { label: (item) => `${item.dataset.label}: ${number.format(item.parsed[value])}${unit}` } },
            },
            scales: {
                [category]: { ticks: { color: colors.text }, grid: { display: false }, border: { color: colors.grid } },
                [value]: {
                    min: spec.min ?? undefined,
                    max: spec.max ?? undefined,
                    ticks: { color: colors.text, callback: (v) => `${number.format(v)}${unit}` },
                    grid: { color: colors.grid },
                    border: { display: false },
                },
            },
        },
    };
}

function buildLine(spec, colors, number, unit) {
    return {
        type: 'line',
        data: {
            labels: spec.labels,
            datasets: spec.series.map((s) => {
                const color = themeColor(s.color);

                return {
                    label: s.label,
                    data: s.data,                       // null = month without data: the line breaks instead of dropping to 0
                    borderColor: color,
                    backgroundColor: color,
                    borderWidth: 2,
                    borderDash: s.dashed ? [6, 4] : [], // dashed vs solid, so the two lines never rely on colour alone
                    pointRadius: s.dashed ? 2 : 4,      // visible even when only one month has data
                    pointHoverRadius: 6,
                    spanGaps: false,
                    tension: 0,
                };
            }),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            font: { family: colors.font },
            color: colors.text,
            plugins: {
                legend: legend(colors),
                tooltip: { callbacks: { label: (item) => `${item.dataset.label}: ${number.format(item.parsed.y)}${unit}` } },
            },
            scales: {
                x: { ticks: { color: colors.text }, grid: { display: false }, border: { color: colors.grid } },
                y: {
                    min: spec.min ?? undefined,
                    max: spec.max ?? undefined,
                    ticks: { color: colors.text, callback: (v) => `${number.format(v)}${unit}` },
                    grid: { color: colors.grid },
                    border: { display: false },
                },
            },
        },
    };
}

const BUILDERS = { line: buildLine, bar: buildBar, doughnut: buildDoughnut };

function buildConfig(spec) {
    const build = BUILDERS[spec.type] || buildLine;
    const number = new Intl.NumberFormat(root.lang || undefined, { maximumFractionDigits: 2 }); // "12,5" in Indonesian

    return build(spec, palette(), number, spec.unit || '');
}

function render(element) {
    const canvas = element.querySelector('canvas');
    let spec;

    try {
        spec = JSON.parse(element.dataset.chart);
    } catch (error) {
        console.error('Chart: invalid data', error);

        return;
    }

    if (!canvas) {
        return;
    }

    Chart.getChart(canvas)?.destroy();
    new Chart(canvas, buildConfig(spec));
}

function renderAll() {
    document.querySelectorAll(SELECTOR).forEach(render);
}

function start() {
    renderAll();

    // Redraw if the theme changes without a page load (a future theme switch).
    new MutationObserver(renderAll).observe(root, { attributes: true, attributeFilter: ['data-bs-theme'] });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
} else {
    start();
}
