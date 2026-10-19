/**
 * Trainer Cycles profile delta chart (Chart.js 4) + Jumps / Flat AJAX toggle (Phase 2.4–2.5).
 * Macro dashboard hot/cold (Phase 3.2), pattern spotlights (3.3), volatility rail (3.4), AJAX code toggle (3.5).
 * Phase 4.1: aria-live dashboard updates, keyboard tablists, reduced-motion charts.
 * Phase 4.2: dashboard bootstrap carries server meta (cache, ms, sql_ms on cold load).
 */
(function () {
    'use strict';

    var DELTA_CLASSES = ['fhor-tc-delta--hot', 'fhor-tc-delta--cold', 'fhor-tc-delta--neutral'];

    function prefersReducedMotion() {
        return window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function tablistButtons(toggle) {
        return Array.prototype.slice.call(
            toggle.querySelectorAll('.fhor-tc-code-toggle__btn:not(:disabled)')
        );
    }

    function focusTablistButton(toggle, btn) {
        tablistButtons(toggle).forEach(function (b) {
            b.tabIndex = b === btn ? 0 : -1;
        });
        btn.focus();
    }

    function bindTablistKeyboard(toggle) {
        if (!toggle || toggle.getAttribute('data-fhor-tc-tablist-bound') === '1') {
            return;
        }
        toggle.setAttribute('data-fhor-tc-tablist-bound', '1');

        toggle.addEventListener('keydown', function (event) {
            var btn = event.target.closest('.fhor-tc-code-toggle__btn');
            if (!btn || !toggle.contains(btn)) {
                return;
            }

            var buttons = tablistButtons(toggle);
            var index = buttons.indexOf(btn);
            if (index < 0) {
                return;
            }

            var next = null;
            if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                next = buttons[(index + 1) % buttons.length];
            } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                next = buttons[(index - 1 + buttons.length) % buttons.length];
            } else if (event.key === 'Home') {
                next = buttons[0];
            } else if (event.key === 'End') {
                next = buttons[buttons.length - 1];
            } else if (event.key === ' ' || event.key === 'Enter') {
                if (!btn.disabled && !btn.classList.contains('is-active')) {
                    event.preventDefault();
                    btn.click();
                }
                return;
            } else {
                return;
            }

            event.preventDefault();
            focusTablistButton(toggle, next);
        });
    }

    function announceDashboardLive(root, message) {
        if (!root || !message) {
            return;
        }
        var live = root.querySelector('[data-fhor-tc-dashboard-live]');
        if (!live) {
            return;
        }
        live.textContent = '';
        window.setTimeout(function () {
            live.textContent = message;
        }, 40);
    }

    function codeLabelFromData(data, ui) {
        if (ui && ui.heroMeta) {
            var parts = String(ui.heroMeta).split('·');
            if (parts[0]) {
                return parts[0].trim();
            }
        }
        if (data && data.code === 'flat') {
            return 'Flat';
        }
        return 'Jumps';
    }

    function dashboardFilterAnnouncement(data, ui) {
        var label = codeLabelFromData(data, ui);
        var count = data && data.trainers ? data.trainers.length : 0;
        var yardsWord = count === 1 ? 'yard' : 'yards';
        return 'Dashboard updated for ' + label + '. ' + count + ' ' + yardsWord + ' in macro pool.';
    }

    function volatilityModeLabel(mode) {
        return mode === 'wavemakers' ? 'Wavemakers' : 'Flatliners';
    }

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

    function setBootstrap(root, data) {
        if (!root || !data) {
            return;
        }
        try {
            root.setAttribute('data-fhor-tc-profile', JSON.stringify(data));
        } catch (err) {
            /* ignore */
        }
        root.setAttribute('data-code', data.code || '');
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

    function destroyProfileChart(root) {
        if (root && root.fhorTcChart) {
            root.fhorTcChart.destroy();
            root.fhorTcChart = null;
        }
        if (root) {
            root.removeAttribute('data-fhor-tc-chart-ready');
        }
    }

    function initProfileChart(root, dataOverride) {
        var data = dataOverride || parseBootstrap(root);
        if (!data || !data.months || !data.months.length) {
            return;
        }

        var canvas = root.querySelector('.fhor-tc-profile-chart');
        if (!canvas || typeof window.Chart === 'undefined') {
            return;
        }

        destroyProfileChart(root);

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

        var reducedMotion = prefersReducedMotion();

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

    function applyDeltaClass(el, className) {
        if (!el) {
            return;
        }
        DELTA_CLASSES.forEach(function (cls) {
            el.classList.remove(cls);
        });
        if (className) {
            el.classList.add(className);
        }
    }

    function applyUiSnapshot(root, ui) {
        if (!root || !ui) {
            return;
        }

        root.querySelectorAll('[data-fhor-tc-ui]').forEach(function (el) {
            var key = el.getAttribute('data-fhor-tc-ui');
            if (!key || ui[key] === undefined || ui[key] === null) {
                return;
            }
            if (key === 'currentMonthDelta') {
                applyDeltaClass(el, ui.currentMonthDeltaClass || 'fhor-tc-delta--neutral');
            }
            if (key === 'bestMonth') {
                applyDeltaClass(el, ui.bestMonthClass || 'fhor-tc-delta--neutral');
            }
            if (key === 'worstMonth') {
                applyDeltaClass(el, ui.worstMonthClass || 'fhor-tc-delta--neutral');
            }
            if (key === 'chartAriaLabel') {
                el.setAttribute('aria-label', String(ui.chartAriaLabel));
                return;
            }
            el.textContent = String(ui[key]);
        });

        var profileLink = root.querySelector('[data-fhor-tc-ui-link="profileUrl"]');
        if (profileLink && ui.profileUrl) {
            profileLink.setAttribute('href', ui.profileUrl);
        }

        if (ui.summaryKind) {
            var summaryBlock = root.querySelector('.fhor-tc-profile-summary');
            if (summaryBlock) {
                var kind = String(ui.summaryKind).replace(/[^a-z0-9_-]/gi, '') || 'none';
                summaryBlock.setAttribute('data-fhor-tc-summary-kind', kind);
                summaryBlock.className = summaryBlock.className.replace(/fhor-tc-profile-summary--[a-z0-9_-]+/g, '').trim();
                summaryBlock.classList.add('fhor-tc-profile-summary--' + kind);
            }
        }
    }

    function setToggleActive(root, code) {
        var toggle = root.querySelector('[data-fhor-tc-code-toggle]');
        if (!toggle) {
            return;
        }
        toggle.querySelectorAll('.fhor-tc-code-toggle__btn').forEach(function (btn) {
            var isActive = btn.getAttribute('data-code') === code;
            btn.classList.toggle('is-active', isActive);
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
            btn.tabIndex = isActive ? 0 : -1;
            btn.disabled = false;
        });
    }

    function syncProfileUrl(root, code, slug) {
        if (root.classList.contains('fhor-tc-profile--embed') || !slug || !window.history || !window.history.replaceState) {
            return;
        }
        var path = '/trainers/' + slug + '/';
        var next = path;
        if (code === 'flat') {
            next = path + '?code=flat';
        }
        window.history.replaceState(null, '', next);
    }

    function fetchProfile(root, code) {
        var ajax = window.fhorTcAjax || {};
        if (!ajax.url || !ajax.action || !ajax.nonce) {
            return Promise.reject(new Error('ajax_unconfigured'));
        }

        var bootstrap = parseBootstrap(root) || {};
        var body = new URLSearchParams();
        body.set('action', ajax.action);
        body.set('nonce', ajax.nonce);
        body.set('code', code);
        body.set('instance_id', bootstrap.instanceId || '');

        var trainerName = root.getAttribute('data-trainer-name') || bootstrap.trainerName || '';
        var slug = root.getAttribute('data-trainer-slug') || bootstrap.trainerSlug || '';
        if (trainerName) {
            body.set('trainer', trainerName);
        }
        if (slug) {
            body.set('slug', slug);
        }

        return fetch(ajax.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: body.toString(),
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload || payload.success !== true) {
                    var err = new Error('profile_fetch_failed');
                    err.payload = payload;
                    throw err;
                }
                return payload.data;
            });
        });
    }

    function bindCodeToggle(root) {
        var toggle = root.querySelector('[data-fhor-tc-code-toggle]');
        if (!toggle || toggle.getAttribute('data-fhor-tc-bound') === '1') {
            return;
        }
        toggle.setAttribute('data-fhor-tc-bound', '1');
        bindTablistKeyboard(toggle);

        toggle.addEventListener('click', function (event) {
            var btn = event.target.closest('.fhor-tc-code-toggle__btn');
            if (!btn || btn.disabled || btn.classList.contains('is-active')) {
                return;
            }

            var code = btn.getAttribute('data-code');
            if (!code) {
                return;
            }

            var bootstrap = parseBootstrap(root) || {};
            if (bootstrap.code === code) {
                return;
            }

            root.classList.add('is-loading');
            toggle.querySelectorAll('.fhor-tc-code-toggle__btn').forEach(function (b) {
                b.disabled = true;
            });

            fetchProfile(root, code)
                .then(function (data) {
                    var nextBootstrap = data.bootstrap;
                    var ui = data.ui || {};
                    setBootstrap(root, nextBootstrap);
                    applyUiSnapshot(root, ui);
                    setToggleActive(root, code);
                    initProfileChart(root, nextBootstrap);
                    syncProfileUrl(root, code, nextBootstrap.trainerSlug || root.getAttribute('data-trainer-slug'));
                })
                .catch(function () {
                    setToggleActive(root, bootstrap.code || 'jumps');
                })
                .finally(function () {
                    root.classList.remove('is-loading');
                    toggle.querySelectorAll('.fhor-tc-code-toggle__btn').forEach(function (b) {
                        b.disabled = false;
                    });
                });
        });
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatDeltaPts(delta) {
        if (delta === null || delta === undefined) {
            return '—';
        }
        var num = Number(delta);
        if (Number.isNaN(num)) {
            return '—';
        }
        var sign = num > 0 ? '+' : '';
        return sign + num.toFixed(1) + ' pts';
    }

    function deltaClassFor(value) {
        if (value === null || value === undefined) {
            return 'fhor-tc-delta--neutral';
        }
        var num = Number(value);
        if (Number.isNaN(num) || num === 0) {
            return 'fhor-tc-delta--neutral';
        }
        return num > 0 ? 'fhor-tc-delta--hot' : 'fhor-tc-delta--cold';
    }

    function sortedMonthRows(trainer) {
        var months = trainer.months || [];
        return months.slice().sort(function (a, b) {
            return (a.cal_month || 0) - (b.cal_month || 0);
        });
    }

    function buildSparklineHtml(trainer, referenceMonth, minMonthRunners) {
        var rows = sortedMonthRows(trainer);
        if (!rows.length) {
            return '<div class="fhor-tc-dashboard-sparkline fhor-tc-dashboard-sparkline--empty" aria-hidden="true"></div>';
        }

        var maxAbs = 0;
        rows.forEach(function (row) {
            if (row.eligible && row.delta !== null && row.delta !== undefined) {
                maxAbs = Math.max(maxAbs, Math.abs(Number(row.delta)));
            }
        });
        if (maxAbs < 0.05) {
            maxAbs = 1;
        }

        var bars = rows.map(function (row) {
            var cal = row.cal_month || 0;
            var isRef = cal === referenceMonth;
            var classes = ['fhor-tc-dashboard-sparkline__bar'];
            var heightPct = 8;
            var titleParts = ['Month ' + cal];

            if (!row.eligible) {
                classes.push('fhor-tc-dashboard-sparkline__bar--small');
                if (row.runs > 0) {
                    titleParts.push('Small sample (' + row.runs + ' runs, min ' + minMonthRunners + ')');
                } else {
                    titleParts.push('No runs');
                }
            } else if (row.delta === null || row.delta === undefined) {
                classes.push('fhor-tc-dashboard-sparkline__bar--neutral');
                titleParts.push('Delta n/a');
            } else {
                var delta = Number(row.delta);
                if (delta > 0) {
                    classes.push('fhor-tc-dashboard-sparkline__bar--hot');
                } else if (delta < 0) {
                    classes.push('fhor-tc-dashboard-sparkline__bar--cold');
                } else {
                    classes.push('fhor-tc-dashboard-sparkline__bar--neutral');
                }
                heightPct = Math.max(14, Math.round((Math.abs(delta) / maxAbs) * 100));
                titleParts.push(formatDeltaPts(delta));
            }

            if (isRef) {
                classes.push('fhor-tc-dashboard-sparkline__bar--ref');
            }

            return (
                '<span class="' + classes.join(' ') + '" style="--fhor-tc-spark-h:' + heightPct + '%" title="'
                + escapeHtml(titleParts.join(' · '))
                + '" aria-hidden="true"></span>'
            );
        });

        return (
            '<div class="fhor-tc-dashboard-sparkline" role="img" aria-label="12-month delta sparkline">'
            + bars.join('')
            + '</div>'
        );
    }

    function buildDashboardRowHtml(trainer, data, tone) {
        var refMonth = data.referenceMonth || 0;
        var minRuns = data.minMonthRunners || 0;
        var delta = trainer.currentMonthDelta;
        var deltaHtml;
        var badgeHtml = '';

        if (trainer.currentMonthEligible === false) {
            badgeHtml = '<span class="fhor-tc-dashboard-badge">Small sample</span>';
            deltaHtml = '<span class="fhor-tc-dashboard-row__delta fhor-tc-delta--neutral">—</span>';
        } else {
            deltaHtml = (
                '<span class="fhor-tc-dashboard-row__delta '
                + deltaClassFor(delta)
                + '">'
                + escapeHtml(formatDeltaPts(delta))
                + '</span>'
            );
        }

        var name = trainer.trainerName || 'Trainer';
        var url = trainer.url || '#';
        var spark = buildSparklineHtml(trainer, refMonth, minRuns);

        return (
            '<li class="fhor-tc-dashboard-row fhor-tc-dashboard-row--' + tone + '">'
            + '<a class="fhor-tc-dashboard-row__name" href="' + escapeHtml(url) + '">'
            + escapeHtml(name)
            + '</a>'
            + spark
            + deltaHtml
            + badgeHtml
            + '</li>'
        );
    }

    function pickHotColdLists(trainers) {
        var eligible = (trainers || []).filter(function (trainer) {
            return trainer.currentMonthDelta !== null && trainer.currentMonthDelta !== undefined;
        });

        var hot = eligible
            .filter(function (trainer) {
                return Number(trainer.currentMonthDelta) > 0;
            })
            .sort(function (a, b) {
                return Number(b.currentMonthDelta) - Number(a.currentMonthDelta);
            })
            .slice(0, 5);

        var cold = eligible
            .filter(function (trainer) {
                return Number(trainer.currentMonthDelta) < 0;
            })
            .sort(function (a, b) {
                return Number(a.currentMonthDelta) - Number(b.currentMonthDelta);
            })
            .slice(0, 5);

        return { hot: hot, cold: cold };
    }

    function renderDashboardListRegion(container, trainers, data, tone, emptyCopy) {
        if (!container) {
            return;
        }

        if (!trainers.length) {
            container.innerHTML = '<p class="fhor-tc-dashboard-empty">' + escapeHtml(emptyCopy) + '</p>';
            return;
        }

        var items = trainers.map(function (trainer) {
            return buildDashboardRowHtml(trainer, data, tone);
        });

        container.innerHTML = '<ul class="fhor-tc-dashboard-list">' + items.join('') + '</ul>';
    }

    function renderDashboardHotCold(root, data) {
        if (!root || !data || !data.trainers) {
            return;
        }

        var lists = pickHotColdLists(data.trainers);
        var refLabel = data.referenceMonthLabel || 'this month';

        renderDashboardListRegion(
            root.querySelector('[data-fhor-tc-dashboard-region="hot"]'),
            lists.hot,
            data,
            'hot',
            'No hot yards with enough ' + refLabel + ' runs yet.'
        );

        renderDashboardListRegion(
            root.querySelector('[data-fhor-tc-dashboard-region="cold"]'),
            lists.cold,
            data,
            'cold',
            'No cold yards with enough ' + refLabel + ' runs yet.'
        );
    }

    var SPOTLIGHT_REGIONS = [
        {
            key: 'early_birds',
            tone: 'hot',
            emptyCopy: 'No Early Bird yards match this window yet.',
        },
        {
            key: 'late_surge',
            tone: 'hot',
            emptyCopy: 'No Late Surge yards match this window yet.',
        },
        {
            key: 'cold_start',
            tone: 'cold',
            emptyCopy: 'No Cold Start yards match this window yet.',
        },
    ];

    function spotlightWindowMonths(data, key) {
        var windows = data && data.spotlightWindows;
        if (!windows || !windows[key] || !windows[key].length) {
            if (key === 'late_surge') {
                return [11, 12];
            }
            return (data && data.code) === 'flat' ? [4, 5, 6] : [10, 11, 12];
        }
        return windows[key].map(function (m) {
            return Number(m);
        });
    }

    function spotlightWindowAvg(trainer, windowMonths) {
        var lookup = {};
        windowMonths.forEach(function (m) {
            lookup[m] = true;
        });

        var deltas = [];
        (trainer.months || []).forEach(function (row) {
            if (!row || !row.eligible) {
                return;
            }
            var cal = Number(row.cal_month);
            if (!lookup[cal]) {
                return;
            }
            if (row.delta === null || row.delta === undefined) {
                return;
            }
            deltas.push(Number(row.delta));
        });

        if (!deltas.length) {
            return null;
        }
        return deltas.reduce(function (sum, value) {
            return sum + value;
        }, 0) / deltas.length;
    }

    function pickSpotlightList(trainers, data, key, tone) {
        return (trainers || [])
            .filter(function (trainer) {
                var flags = trainer.spotlights || {};
                return !!flags[key];
            })
            .map(function (trainer) {
                return {
                    trainer: trainer,
                    windowAvg: spotlightWindowAvg(trainer, spotlightWindowMonths(data, key)),
                };
            })
            .sort(function (a, b) {
                var left = a.windowAvg;
                var right = b.windowAvg;
                if (left === null && right === null) {
                    return (a.trainer.trainerName || '').localeCompare(b.trainer.trainerName || '');
                }
                if (left === null) {
                    return 1;
                }
                if (right === null) {
                    return -1;
                }
                if (tone === 'cold') {
                    return left - right;
                }
                return right - left;
            })
            .map(function (entry) {
                return entry.trainer;
            });
    }

    function renderDashboardSpotlights(root, data) {
        if (!root || !data || !data.trainers) {
            return;
        }

        SPOTLIGHT_REGIONS.forEach(function (section) {
            var trainers = pickSpotlightList(data.trainers, data, section.key, section.tone);
            renderDashboardListRegion(
                root.querySelector('[data-fhor-tc-dashboard-region="' + section.key + '"]'),
                trainers,
                data,
                section.tone,
                section.emptyCopy
            );
        });
    }

    var VOLATILITY_LIST_LIMIT = 10;

    function dashboardMonthLabel(data, calMonth) {
        var labels = data && data.monthLabels;
        var idx = Number(calMonth) - 1;
        if (labels && labels[idx]) {
            return labels[idx];
        }
        return calMonth ? String(calMonth) : '';
    }

    function formatStrikePct(value) {
        if (value === null || value === undefined) {
            return '—';
        }
        var num = Number(value);
        if (Number.isNaN(num)) {
            return '—';
        }
        return num.toFixed(1) + '%';
    }

    function volatilityBaselineTickPct(trainer) {
        var worst = trainer.worstMonth;
        var best = trainer.bestMonth;
        if (!worst || !best) {
            return 50;
        }
        var minStrike = Number(worst.strikePct);
        var maxStrike = Number(best.strikePct);
        var baseline = Number(trainer.baselinePct);
        if (Number.isNaN(minStrike) || Number.isNaN(maxStrike)) {
            return 50;
        }
        var span = maxStrike - minStrike;
        if (span < 0.05) {
            return 50;
        }
        var pct = ((baseline - minStrike) / span) * 100;
        return Math.max(0, Math.min(100, pct));
    }

    function buildVolatilityRailHtml(trainer, data) {
        var worst = trainer.worstMonth;
        var best = trainer.bestMonth;
        var gap = trainer.gapPts;

        if (!worst || !best || gap === null || gap === undefined) {
            return '<div class="fhor-tc-volatility-rail fhor-tc-volatility-rail--empty" aria-hidden="true"></div>';
        }

        var minLabel = dashboardMonthLabel(data, worst.calMonth) + ' ' + formatStrikePct(worst.strikePct);
        var maxLabel = dashboardMonthLabel(data, best.calMonth) + ' ' + formatStrikePct(best.strikePct);
        var tickPct = volatilityBaselineTickPct(trainer);
        var gapLabel = Number(gap).toFixed(1) + ' pts gap';
        var aria = 'Win rate from ' + minLabel + ' to ' + maxLabel + ', baseline ' + formatStrikePct(trainer.baselinePct);

        return (
            '<div class="fhor-tc-volatility-rail" role="img" aria-label="' + escapeHtml(aria) + '">'
            + '<div class="fhor-tc-volatility-rail__track">'
            + '<span class="fhor-tc-volatility-rail__span"></span>'
            + '<span class="fhor-tc-volatility-rail__tick" style="--fhor-tc-vol-tick:' + tickPct.toFixed(2) + '%" title="Lifetime baseline ' + escapeHtml(formatStrikePct(trainer.baselinePct)) + '"></span>'
            + '</div>'
            + '<div class="fhor-tc-volatility-rail__labels">'
            + '<span class="fhor-tc-volatility-rail__min">' + escapeHtml(minLabel) + '</span>'
            + '<span class="fhor-tc-volatility-rail__gap">' + escapeHtml(gapLabel) + '</span>'
            + '<span class="fhor-tc-volatility-rail__max">' + escapeHtml(maxLabel) + '</span>'
            + '</div>'
            + '</div>'
        );
    }

    function buildVolatilityRowHtml(trainer, data) {
        var name = trainer.trainerName || 'Trainer';
        var url = trainer.url || '#';
        var gap = trainer.gapPts;
        var gapHtml = (
            '<span class="fhor-tc-dashboard-row__gap">'
            + escapeHtml(gap === null || gap === undefined ? '—' : Number(gap).toFixed(1) + ' pts')
            + '</span>'
        );

        return (
            '<li class="fhor-tc-dashboard-row fhor-tc-dashboard-row--volatility">'
            + '<a class="fhor-tc-dashboard-row__name" href="' + escapeHtml(url) + '">'
            + escapeHtml(name)
            + '</a>'
            + buildVolatilityRailHtml(trainer, data)
            + gapHtml
            + '</li>'
        );
    }

    function pickVolatilityList(trainers, mode) {
        var eligible = (trainers || []).filter(function (trainer) {
            return trainer.gapPts !== null && trainer.gapPts !== undefined
                && trainer.bestMonth && trainer.worstMonth;
        });

        eligible.sort(function (a, b) {
            var left = Number(a.gapPts);
            var right = Number(b.gapPts);
            if (mode === 'wavemakers') {
                return right - left;
            }
            return left - right;
        });

        return eligible.slice(0, VOLATILITY_LIST_LIMIT);
    }

    function renderDashboardVolatility(root, data, mode) {
        if (!root || !data || !data.trainers) {
            return;
        }

        var container = root.querySelector('[data-fhor-tc-dashboard-region="volatility"]');
        if (!container) {
            return;
        }

        var sortMode = mode || root.getAttribute('data-volatility-mode') || 'flatliners';
        var trainers = pickVolatilityList(data.trainers, sortMode);
        var emptyCopy = sortMode === 'wavemakers'
            ? 'No yards with enough eligible months to rank wavemakers yet.'
            : 'No yards with enough eligible months to rank flatliners yet.';

        if (!trainers.length) {
            container.innerHTML = '<p class="fhor-tc-dashboard-empty">' + escapeHtml(emptyCopy) + '</p>';
            return;
        }

        var items = trainers.map(function (trainer) {
            return buildVolatilityRowHtml(trainer, data);
        });

        container.innerHTML = '<ul class="fhor-tc-dashboard-list fhor-tc-dashboard-list--volatility">' + items.join('') + '</ul>';
    }

    function setVolatilityToggleActive(root, mode) {
        var toggle = root.querySelector('[data-fhor-tc-volatility-toggle]');
        if (!toggle) {
            return;
        }
        toggle.querySelectorAll('.fhor-tc-code-toggle__btn').forEach(function (btn) {
            var isActive = btn.getAttribute('data-volatility-mode') === mode;
            btn.classList.toggle('is-active', isActive);
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
            btn.tabIndex = isActive ? 0 : -1;
        });
        root.setAttribute('data-volatility-mode', mode);
    }

    function bindVolatilityToggle(root) {
        var toggle = root.querySelector('[data-fhor-tc-volatility-toggle]');
        if (!toggle || toggle.getAttribute('data-fhor-tc-bound') === '1') {
            return;
        }
        toggle.setAttribute('data-fhor-tc-bound', '1');
        bindTablistKeyboard(toggle);

        toggle.addEventListener('click', function (event) {
            var btn = event.target.closest('.fhor-tc-code-toggle__btn');
            if (!btn || btn.classList.contains('is-active')) {
                return;
            }
            var mode = btn.getAttribute('data-volatility-mode');
            if (!mode) {
                return;
            }

            var data = readDashboardData(root);
            setVolatilityToggleActive(root, mode);
            renderDashboardVolatility(root, data, mode);
            announceDashboardLive(
                root,
                'Volatility list sorted by ' + volatilityModeLabel(mode) + '.'
            );
        });
    }

    function readDashboardData(root) {
        var data = window.fhorTcDashboard;
        if (!data && root && root.getAttribute('data-fhor-tc-dashboard')) {
            try {
                data = JSON.parse(root.getAttribute('data-fhor-tc-dashboard'));
            } catch (err) {
                data = null;
            }
        }
        return data;
    }

    function setDashboardData(root, data) {
        if (!root || !data) {
            return;
        }
        window.fhorTcDashboard = data;
        try {
            root.setAttribute('data-fhor-tc-dashboard', JSON.stringify(data));
        } catch (err) {
            /* ignore */
        }
        if (data.code) {
            root.setAttribute('data-code', data.code);
        }
    }

    function applyDashboardUi(root, ui) {
        if (!root || !ui) {
            return;
        }

        var heroMeta = root.querySelector('[data-fhor-tc-ui="heroMeta"]');
        if (heroMeta && ui.heroMeta) {
            heroMeta.textContent = ui.heroMeta;
        }

        if (ui.referenceMonthLabel) {
            root.querySelectorAll('[data-fhor-tc-ui="referenceMonthLabel"]').forEach(function (el) {
                el.textContent = ui.referenceMonthLabel;
            });
        }

        var trainerCount = root.querySelector('[data-fhor-tc-ui="trainerCount"]');
        if (trainerCount && ui.trainerCount) {
            trainerCount.textContent = ui.trainerCount;
        }

        var macroRule = root.querySelector('[data-fhor-tc-ui="macroPoolRule"]');
        if (macroRule && ui.macroPoolRule) {
            macroRule.textContent = ui.macroPoolRule;
        }

        if (ui.spotlightHints) {
            Object.keys(ui.spotlightHints).forEach(function (key) {
                var region = root.querySelector('[data-fhor-tc-dashboard-region="' + key + '"]');
                if (!region) {
                    return;
                }
                var spotlight = region.closest('.fhor-tc-dashboard-spotlight');
                if (!spotlight) {
                    return;
                }
                var hint = spotlight.querySelector('.fhor-tc-dashboard-spotlight__hint');
                if (hint) {
                    hint.textContent = ui.spotlightHints[key];
                }
            });
        }
    }

    function setDashboardToggleActive(root, code) {
        var toggle = root.querySelector('[data-fhor-tc-dashboard-code-toggle]');
        if (!toggle) {
            return;
        }
        toggle.querySelectorAll('.fhor-tc-code-toggle__btn').forEach(function (btn) {
            var isActive = btn.getAttribute('data-code') === code;
            btn.classList.toggle('is-active', isActive);
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
            btn.tabIndex = isActive ? 0 : -1;
            btn.disabled = false;
        });
    }

    function syncDashboardUrl(code) {
        if (!window.history || !window.history.replaceState) {
            return;
        }
        var next = '/trainer-cycles/';
        if (code === 'flat') {
            next = '/trainer-cycles/?code=flat';
        }
        window.history.replaceState(null, '', next);
    }

    function fetchDashboard(code) {
        var ajax = window.fhorTcAjax || {};
        var action = ajax.dashboardAction || 'fhor_tc_dashboard';
        var nonce = ajax.dashboardNonce || ajax.nonce;
        if (!ajax.url || !action || !nonce) {
            return Promise.reject(new Error('ajax_unconfigured'));
        }

        var body = new URLSearchParams();
        body.set('action', action);
        body.set('nonce', nonce);
        body.set('code', code);

        return fetch(ajax.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: body.toString(),
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload || payload.success !== true) {
                    var err = new Error('dashboard_fetch_failed');
                    err.payload = payload;
                    throw err;
                }
                return payload.data;
            });
        });
    }

    function refreshDashboardPanels(root, data) {
        if (!root || !data) {
            return;
        }
        renderDashboardHotCold(root, data);
        renderDashboardSpotlights(root, data);
        var volMode = root.getAttribute('data-volatility-mode') || 'flatliners';
        renderDashboardVolatility(root, data, volMode);
    }

    function bindDashboardCodeToggle(root) {
        var toggle = root.querySelector('[data-fhor-tc-dashboard-code-toggle]');
        if (!toggle || toggle.getAttribute('data-fhor-tc-bound') === '1') {
            return;
        }
        toggle.setAttribute('data-fhor-tc-bound', '1');
        bindTablistKeyboard(toggle);

        toggle.addEventListener('click', function (event) {
            var btn = event.target.closest('.fhor-tc-code-toggle__btn');
            if (!btn || btn.disabled || btn.classList.contains('is-active')) {
                return;
            }

            var code = btn.getAttribute('data-code');
            if (!code) {
                return;
            }

            var data = readDashboardData(root);
            if (data && data.code === code) {
                return;
            }

            var sections = root.querySelector('[data-fhor-tc-dashboard-sections]');
            root.classList.add('is-loading');
            announceDashboardLive(root, 'Loading dashboard data.');
            if (sections) {
                sections.setAttribute('aria-busy', 'true');
            }
            toggle.querySelectorAll('.fhor-tc-code-toggle__btn').forEach(function (b) {
                b.disabled = true;
            });

            fetchDashboard(code)
                .then(function (payload) {
                    var nextData = payload.bootstrap || payload;
                    var ui = payload.ui || {};
                    setDashboardData(root, nextData);
                    applyDashboardUi(root, ui);
                    setDashboardToggleActive(root, code);
                    refreshDashboardPanels(root, nextData);
                    syncDashboardUrl(code);
                    announceDashboardLive(root, dashboardFilterAnnouncement(nextData, ui));
                })
                .catch(function () {
                    if (data && data.code) {
                        setDashboardToggleActive(root, data.code);
                    }
                    announceDashboardLive(root, 'Could not update dashboard. Showing previous data.');
                })
                .finally(function () {
                    root.classList.remove('is-loading');
                    if (sections) {
                        sections.setAttribute('aria-busy', 'false');
                    }
                    toggle.querySelectorAll('.fhor-tc-code-toggle__btn').forEach(function (b) {
                        b.disabled = false;
                    });
                });
        });
    }

    function initDashboard(root) {
        if (!root) {
            return;
        }
        var data = readDashboardData(root);
        if (data) {
            setDashboardData(root, data);
            setVolatilityToggleActive(root, root.getAttribute('data-volatility-mode') || 'flatliners');
            refreshDashboardPanels(root, data);
        }
        setDashboardToggleActive(root, (data && data.code) || root.getAttribute('data-code') || 'jumps');
        bindVolatilityToggle(root);
        bindDashboardCodeToggle(root);
        root.classList.remove('is-bootstrapping');
        var sections = root.querySelector('[data-fhor-tc-dashboard-sections]');
        if (sections) {
            sections.setAttribute('aria-busy', 'false');
        }
    }

    function bootDashboard() {
        var root = document.querySelector('.fhor-tc-dashboard');
        if (!root) {
            return;
        }
        initDashboard(root);
    }

    function boot() {
        bootDashboard();

        var roots = document.querySelectorAll('.fhor-tc-profile');
        if (!roots.length) {
            return;
        }

        if (typeof window.Chart === 'undefined') {
            window.setTimeout(boot, 120);
            return;
        }

        roots.forEach(function (root) {
            initProfileChart(root);
            bindCodeToggle(root);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    window.fhorTcInitDashboard = bootDashboard;
    window.fhorTcRenderDashboardHotCold = renderDashboardHotCold;
    window.fhorTcRenderDashboardSpotlights = renderDashboardSpotlights;
    window.fhorTcRenderDashboardVolatility = renderDashboardVolatility;
    window.fhorTcInitProfileCharts = boot;
    window.fhorTcDestroyProfileChart = destroyProfileChart;
    window.fhorTcInitProfileChart = initProfileChart;
})();

