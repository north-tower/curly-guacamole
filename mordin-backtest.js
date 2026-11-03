(function () {
    'use strict';

    var root = document.getElementById('fhor-mordin-backtest');
    if (!root || !window.fhorMordinBt) {
        return;
    }

    var runBtn = document.getElementById('mbt-run');
    var statusEl = document.getElementById('mbt-status');
    var resultsEl = document.getElementById('mbt-results');
    var daysEl = document.getElementById('mbt-days');
    var tbody = root.querySelector('#mbt-table tbody');
    var mdEl = document.getElementById('mbt-markdown');
    var jsonEl = document.getElementById('mbt-json');
    var rangeEl = document.getElementById('mbt-range');
    var lastPayload = null;

    function post(action, data) {
        var body = new FormData();
        body.append('action', action);
        body.append('nonce', fhorMordinBt.nonce);
        Object.keys(data || {}).forEach(function (key) {
            body.append(key, data[key]);
        });
        return fetch(fhorMordinBt.ajax, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (r) { return r.json(); });
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function renderTable(rows) {
        if (!tbody) {
            return;
        }
        tbody.innerHTML = '';
        (rows || []).forEach(function (row) {
            var s = row.scenario || {};
            var w = row.with || {};
            var b = row.baseline || {};
            var r = row.removed || {};
            var v = row.verdict || {};
            var tr = document.createElement('tr');
            tr.className = v.verdict === 'Keep badge' ? 'verdict-keep' : 'verdict-drop';
            tr.innerHTML =
                '<td>' + esc(s.key) + '</td>' +
                '<td><code>' + esc(s.rule) + '</code></td>' +
                '<td class="mbt-n">' + esc(w.bets) + '</td>' +
                '<td class="mbt-n">' + esc(w.win_rate) + '</td>' +
                '<td class="mbt-n">' + esc(w.roi) + '</td>' +
                '<td class="mbt-n">' + esc(b.roi) + '</td>' +
                '<td class="mbt-n">' + esc(r.removed_bets) + '</td>' +
                '<td>' + esc(v.verdict) + '</td>' +
                '<td>' + esc(v.reason) + '</td>';
            tbody.appendChild(tr);
        });
    }

    function download(filename, text, mime) {
        var blob = new Blob([text], { type: mime || 'text/plain;charset=utf-8' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = filename;
        a.click();
        URL.revokeObjectURL(a.href);
    }

    function copyText(text, label) {
        if (!text) {
            return;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                if (statusEl) {
                    statusEl.textContent = 'Copied ' + label;
                }
            });
            return;
        }
        window.prompt('Copy ' + label + ':', text);
    }

    if (runBtn) {
        runBtn.addEventListener('click', function () {
            var days = daysEl ? daysEl.value : '1825';
            runBtn.disabled = true;
            if (statusEl) {
                statusEl.textContent = 'Running 22 backtests… this can take several minutes.';
            }
            if (resultsEl) {
                resultsEl.hidden = true;
            }
            post('fhor_mordin_bt_run', { days: days }).then(function (json) {
                runBtn.disabled = false;
                if (!json || !json.success) {
                    if (statusEl) {
                        statusEl.textContent = (json && json.data && json.data.message) || 'Run failed.';
                    }
                    return;
                }
                lastPayload = json.data;
                renderTable(json.data.rows);
                if (mdEl) {
                    mdEl.value = json.data.markdown || '';
                }
                if (jsonEl) {
                    jsonEl.value = JSON.stringify(json.data, null, 2);
                }
                if (rangeEl && json.data.meta) {
                    var m = json.data.meta;
                    rangeEl.textContent = 'Window: ' + (m.from || '') + ' → ' + (m.to || '') +
                        ' · ' + (m.days || '') + ' days · finished ' + (m.finished || '');
                }
                if (resultsEl) {
                    resultsEl.hidden = false;
                }
                if (statusEl) {
                    statusEl.textContent = 'Done — export below or paste JSON in chat.';
                }
            }).catch(function () {
                runBtn.disabled = false;
                if (statusEl) {
                    statusEl.textContent = 'Network error — try again.';
                }
            });
        });
    }

    document.getElementById('mbt-copy-notes') && document.getElementById('mbt-copy-notes').addEventListener('click', function () {
        copyText(lastPayload && lastPayload.notes_table_markdown, 'NOTES table');
    });
    document.getElementById('mbt-copy-full') && document.getElementById('mbt-copy-full').addEventListener('click', function () {
        copyText(lastPayload && lastPayload.markdown, 'full report');
    });
    document.getElementById('mbt-dl-md') && document.getElementById('mbt-dl-md').addEventListener('click', function () {
        if (lastPayload && lastPayload.markdown) {
            download('mordin-task10-report.md', lastPayload.markdown, 'text/markdown;charset=utf-8');
        }
    });
    document.getElementById('mbt-dl-json') && document.getElementById('mbt-dl-json').addEventListener('click', function () {
        if (lastPayload) {
            download('mordin-task10-report.json', JSON.stringify(lastPayload, null, 2), 'application/json;charset=utf-8');
        }
    });
})();
