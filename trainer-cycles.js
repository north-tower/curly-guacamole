/**
 * Trainer Cycles profile delta chart (Chart.js 4).
 */
(function () {
    'use strict';

    function readCssVar(el, name, fallback) {
        if (!el || !window.getComputedStyle) {
            return fallback;
        }
        var value = window.getComputedStyle(el).getPropertyValue(name);
        return value && String(value).trim() ? String(value).trim() : fallback;
    }

    function parseBootstrap(root) {
        var raw = root.getAttribute('data-fhor-tc-profile');
        if (!raw) {
            return null;
        }
        try {
            return JSON.parse(raw);
        } catch (err) {
            return null;
        }
    }

    function monthLabel(data, index) {
        var months = data.months || [];
        var row = months[index];
        if (row && data.monthLabels && data.monthLabels[row.cal_month - 1]) {
            return data.monthLabels[row.cal_month - 1];
        }
        if (row && row.cal_month) {
            return String(row.cal_month);
        }
        return '';
    }

    function segmentColor(ctx, hot, cold, neutral) {
        var y0 = ctx.p0.parsed.y;
        var y1 = ctx.p1.parsed.y;
        if (y0 === null || y1 === null || y0 === undefined || y1 === undefined) {
            return neutral;
        }
        if (y0 >= 0 && y1 >= 0) {
            return hot;
        }
        if (y0 <= 0 && y1 <= 0) {
            return cold;
        }
        return neutral;
    }

    function initProfileChart(root) {
        if (root.getAttribute('data-fhor-tc-chart-ready') === '1') {
            return;
        }

        var data = parseBootstrap(root);
        if (!data || !data.months || !data.months.length) {
            return;
        }

        var canvas = root.querySelector('.fhor-tc-profile-chart');
        if (!canvas || typeof window.Chart === 'undefined') {
            return;
        }

        var hot = readCssVar(root, '--fhor-tc-hot', '#2fd08a');
        var cold = readCssVar(root, '--fhor-tc-cold', '#4c8dff');
        var small = readCssVar(root, '--fhor-tc-small', '#94a3b8');
        var muted = readCssVar(root, '--fhor-tc-muted', '#64748b');

        var labels = [];
        var deltas = [];
        var smallSamples = [];

        data.months.forEach(function (row, index) {
            labels.push(monthLabel(data, index));
            if (row.eligible && row.delta !== null && row.delta !== undefined) {
                deltas.push(row.delta);
                smallSamples.push(null);
            } else if (row.runs > 0) {
                deltas.push(null);
                smallSamples.push(0);
            } else {
                deltas.push(null);
                smallSamples.push(null);
            }
        });

        var reducedMotion = window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        var chart = new window.Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Delta vs baseline (pts)',
                        data: deltas,
                        spanGaps: false,
                        borderWidth: 2.5,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: 0.28,
                        borderColor: hot,
                        backgroundColor: 'transparent',
                        segment: {
                            borderColor: function (ctx) {
                                return segmentColor(ctx, hot, cold, muted);
                            },
                        },
                        pointBackgroundColor: function (ctx) {
                            var value = ctx.parsed.y;
                            if (value === null || value === undefined) {
                                return muted;
                            }
                            if (value > 0) {
                                return hot;
                            }
                            if (value < 0) {
                                return cold;
                            }
                            return muted;
                        },
                        pointBorderColor: '#fff',
                        pointBorderWidth: 1,
                    },
                    {
                        label: 'Small sample',
                        data: smallSamples,
                        type: 'scatter',
                        showLine: false,
                        pointStyle: 'rectRot',
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        backgroundColor: small,
                        borderColor: small,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: reducedMotion ? false : undefined,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var idx = ctx.dataIndex;
                                var row = data.months[idx];
                                if (!row) {
                                    return '';
                                }
                                if (ctx.datasetIndex === 1 || !row.eligible) {
                                    return 'Small sample (' + row.runs + ' runs, min ' + data.minMonthRunners + ')';
                                }
                                var delta = row.delta;
                                var sign = delta > 0 ? '+' : '';
                                var strike = row.strike_pct !== null && row.strike_pct !== undefined
                                    ? row.strike_pct.toFixed(1) + '% win'
                                    : 'win % n/a';
                                return sign + delta.toFixed(1) + ' pts · ' + row.runs + ' runs · ' + strike;
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(148, 163, 184, 0.25)',
                        },
                        ticks: {
                            color: muted,
                            font: { size: 11, weight: '600' },
                        },
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Points vs baseline',
                            color: muted,
                            font: { size: 11, weight: '600' },
                        },
                        grid: {
                            color: 'rgba(148, 163, 184, 0.25)',
                        },
                        ticks: {
                            color: muted,
                            callback: function (value) {
                                var num = Number(value);
                                if (Number.isNaN(num)) {
                                    return value;
                                }
                                return (num > 0 ? '+' : '') + num;
                            },
                        },
                    },
                },
            },
        });

        root.setAttribute('data-fhor-tc-chart-ready', '1');
        root.fhorTcChart = chart;
    }

    function boot() {
        var roots = document.querySelectorAll('.fhor-tc-profile');
        if (!roots.length) {
            return;
        }

        if (typeof window.Chart === 'undefined') {
            window.setTimeout(boot, 120);
            return;
        }

        roots.forEach(initProfileChart);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    window.fhorTcInitProfileCharts = boot;
})();
