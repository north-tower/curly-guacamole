(function () {
    'use strict';

    function boot() {
        var root = document.getElementById('fhor-trainer-cycles-qa');
        if (!root || !window.fhorTcQa) {
            return;
        }

        var storageKey = 'fhor_tc_qa_checklist_v1';

        function post(action, extra) {
            var body = new window.FormData();
            body.append('action', action);
            body.append('nonce', fhorTcQa.nonce);
            if (extra) {
                Object.keys(extra).forEach(function (key) {
                    body.append(key, extra[key]);
                });
            }
            return fetch(fhorTcQa.ajax, { method: 'POST', credentials: 'same-origin', body: body })
                .then(function (res) { return res.json(); });
        }

        var logEl = document.getElementById('tc-qa-self-test-log');
        var runBtn = document.getElementById('tc-qa-run-self-tests');

        function showLog(text) {
            if (!logEl) {
                return;
            }
            logEl.hidden = false;
            logEl.textContent = text;
        }

        if (runBtn && logEl) {
            runBtn.addEventListener('click', function () {
                runBtn.disabled = true;
                showLog('Running…');
                post('fhor_tc_qa_self_tests').then(function (json) {
                    runBtn.disabled = false;
                    if (!json || !json.success) {
                        showLog((json && json.data && json.data.message) || 'Self-tests failed.');
                        return;
                    }
                    var data = json.data || {};
                    var lines = [(data.ok ? 'PASS' : 'FAIL') + ' — server checks'];
                    (data.tests || []).forEach(function (t) {
                        lines.push((t.ok ? '  ok ' : '  FAIL ') + t.name);
                        if (t.detail) {
                            lines.push('       ' + JSON.stringify(t.detail));
                        }
                    });
                    showLog(lines.join('\n'));
                }).catch(function () {
                    runBtn.disabled = false;
                    showLog('Network error.');
                });
            });
        }

        function bindProbe(btn) {
            if (!btn || !logEl) {
                return;
            }
            btn.addEventListener('click', function () {
                var code = btn.getAttribute('data-code') || 'jumps';
                btn.disabled = true;
                showLog('Macro probe (' + code + ')…');
                post('fhor_tc_qa_macro_probe', { code: code }).then(function (json) {
                    btn.disabled = false;
                    if (!json || !json.success) {
                        showLog((json && json.data && json.data.message) || 'Probe failed.');
                        return;
                    }
                    var d = json.data || {};
                    var lines = [
                        (d.pass ? 'PASS' : 'WARN') + ' — macro cache (' + d.code + ')',
                        '  trainers: ' + (d.trainerCount != null ? d.trainerCount : '?'),
                        '  first:  ' + JSON.stringify(d.first || {}),
                        '  second: ' + JSON.stringify(d.second || {}),
                    ];
                    if (!d.pass) {
                        lines.push('  (Second request should show cache=hit; flush transients if both miss.)');
                    }
                    showLog(lines.join('\n'));
                }).catch(function () {
                    btn.disabled = false;
                    showLog('Network error.');
                });
            });
        }

        bindProbe(document.getElementById('tc-qa-macro-probe-jumps'));
        bindProbe(document.getElementById('tc-qa-macro-probe-flat'));

        var slugBtn = document.getElementById('tc-qa-slug-samples');
        var slugList = document.getElementById('tc-qa-slug-list');
        if (slugBtn && slugList) {
            slugBtn.addEventListener('click', function () {
                slugBtn.disabled = true;
                post('fhor_tc_qa_slug_samples').then(function (json) {
                    slugBtn.disabled = false;
                    if (!json || !json.success) {
                        showLog('Slug samples failed.');
                        return;
                    }
                    var samples = (json.data && json.data.samples) || [];
                    slugList.hidden = false;
                    slugList.innerHTML = '';
                    if (!samples.length) {
                        slugList.innerHTML = '<li>No samples (tables missing or empty index).</li>';
                        return;
                    }
                    samples.forEach(function (s) {
                        var li = document.createElement('li');
                        li.innerHTML = '<a href="' + escapeAttr(s.url) + '" target="_blank" rel="noopener">' +
                            escapeHtml(s.name) + '</a> <code>' + escapeHtml(s.slug) + '</code>' +
                            ' · <a href="' + escapeAttr(s.profile_url_flat) + '" target="_blank" rel="noopener">flat</a>';
                        slugList.appendChild(li);
                    });
                }).catch(function () {
                    slugBtn.disabled = false;
                    showLog('Network error loading slugs.');
                });
            });
        }

        var listEl = document.getElementById('tc-qa-checklist');
        var resetBtn = document.getElementById('tc-qa-checklist-reset');

        function loadChecks() {
            try {
                return JSON.parse(localStorage.getItem(storageKey) || '{}');
            } catch (e) {
                return {};
            }
        }

        function saveChecks(state) {
            try {
                localStorage.setItem(storageKey, JSON.stringify(state));
            } catch (e) {
                /* ignore */
            }
        }

        function renderChecklist() {
            if (!listEl) {
                return;
            }
            var state = loadChecks();
            listEl.innerHTML = '';
            (fhorTcQa.checklist || []).forEach(function (item) {
                var li = document.createElement('li');
                var id = 'tc-qa-chk-' + item.id;
                li.innerHTML = '<label for="' + id + '"><input type="checkbox" id="' + id + '" data-id="' + item.id + '"' +
                    (state[item.id] ? ' checked' : '') + '><span><strong>' + item.label + '</strong><small>' +
                    escapeHtml(item.hint || '') + '</small></span></label>';
                listEl.appendChild(li);
            });
            listEl.querySelectorAll('input[type=checkbox]').forEach(function (el) {
                el.addEventListener('change', function () {
                    var st = loadChecks();
                    st[el.getAttribute('data-id')] = el.checked;
                    saveChecks(st);
                });
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                localStorage.removeItem(storageKey);
                renderChecklist();
            });
        }
        renderChecklist();

        var cardsEl = document.getElementById('tc-qa-scenario-cards');
        var scenarios = fhorTcQa.scenarios || {};
        if (cardsEl) {
            Object.keys(scenarios).forEach(function (key) {
                var s = scenarios[key];
                var card = document.createElement('div');
                card.className = 'tc-qa-card';
                var steps = (s.steps || []).map(function (step) {
                    return '<li>' + escapeHtml(step) + '</li>';
                }).join('');
                var links = s.links || {};
                var linkHtml = '';
                if (links.dashboard) {
                    linkHtml += '<a href="' + escapeAttr(links.dashboard) + '" target="_blank" rel="noopener">Dashboard</a>';
                }
                if (links.dashboard_flat) {
                    linkHtml += '<a href="' + escapeAttr(links.dashboard_flat) + '" target="_blank" rel="noopener">Flat</a>';
                }
                card.innerHTML = '<h3>' + escapeHtml(s.title || key) + '</h3>' +
                    '<p>' + escapeHtml(s.summary || '') + '</p>' +
                    '<ol>' + steps + '</ol>' +
                    (linkHtml ? '<div class="tc-qa-card-links">' + linkHtml + '</div>' : '');
                cardsEl.appendChild(card);
            });
        }
    }

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, function (ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
        });
    }

    function escapeAttr(str) {
        return escapeHtml(str).replace(/`/g, '&#96;');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
