(function () {
    'use strict';

    function boot() {
        var root = document.getElementById('fhor-system-builder-qa');
        if (!root || !window.fhorSbQa) {
            return;
        }

        var storageKey = 'fhor_sb_qa_checklist_v1';

        function post(action) {
            var body = new window.FormData();
            body.append('action', action);
            body.append('nonce', fhorSbQa.nonce);
            return fetch(fhorSbQa.ajax, { method: 'POST', credentials: 'same-origin', body: body })
                .then(function (res) { return res.json(); });
        }

        var logEl = document.getElementById('sb-qa-self-test-log');
        var runBtn = document.getElementById('sb-qa-run-self-tests');
        if (runBtn && logEl) {
            runBtn.addEventListener('click', function () {
                runBtn.disabled = true;
                logEl.hidden = false;
                logEl.textContent = 'Running…';
                post('fhor_sb_qa_self_tests').then(function (json) {
                    runBtn.disabled = false;
                    if (!json || !json.success) {
                        logEl.textContent = (json && json.data && json.data.message) || 'Self-tests failed.';
                        return;
                    }
                    var data = json.data || {};
                    var lines = [(data.ok ? 'PASS' : 'FAIL') + ' — all server checks'];
                    (data.tests || []).forEach(function (t) {
                        lines.push((t.ok ? '  ok ' : '  FAIL ') + t.name);
                        if (!t.ok && t.detail) {
                            lines.push('       ' + JSON.stringify(t.detail));
                        }
                    });
                    logEl.textContent = lines.join('\n');
                }).catch(function () {
                    runBtn.disabled = false;
                    logEl.textContent = 'Network error.';
                });
            });
        }

        var listEl = document.getElementById('sb-qa-checklist');
        var resetBtn = document.getElementById('sb-qa-checklist-reset');
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
            (fhorSbQa.checklist || []).forEach(function (item) {
                var li = document.createElement('li');
                var id = 'sb-qa-chk-' + item.id;
                li.innerHTML = '<label for="' + id + '"><input type="checkbox" id="' + id + '" data-id="' + item.id + '"' +
                    (state[item.id] ? ' checked' : '') + '><span><strong>' + escapeHtml(item.label) + '</strong><small>' +
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

        var cardsEl = document.getElementById('sb-qa-scenario-cards');
        var scenarios = fhorSbQa.scenarios || {};
        if (cardsEl) {
            Object.keys(scenarios).forEach(function (key) {
                var s = scenarios[key];
                var card = document.createElement('div');
                card.className = 'sb-qa-card';
                var steps = (s.steps || []).map(function (step, i) {
                    return '<li>' + escapeHtml(step) + '</li>';
                }).join('');
                card.innerHTML = '<h3>' + escapeHtml(s.title || key) + '</h3>' +
                    '<p>' + escapeHtml(s.summary || '') + '</p>' +
                    '<ol>' + steps + '</ol>' +
                    '<button type="button" class="sb-btn sb-btn-primary sb-qa-load" data-scenario="' + escapeHtml(key) + '">Load into builder</button>';
                cardsEl.appendChild(card);
            });
            cardsEl.addEventListener('click', function (event) {
                var btn = event.target.closest('.sb-qa-load');
                if (!btn) {
                    return;
                }
                var key = btn.getAttribute('data-scenario');
                var scenario = scenarios[key];
                if (!scenario || !scenario.filters) {
                    return;
                }
                if (typeof window.fhorSbApplyFilters !== 'function') {
                    window.alert('System Builder is still loading — wait a moment and try again.');
                    return;
                }
                window.fhorSbApplyFilters(scenario.filters);
                var builder = document.getElementById('fhor-system-builder');
                if (builder) {
                    builder.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
                btn.textContent = 'Loaded — run test';
                window.setTimeout(function () {
                    btn.textContent = 'Load into builder';
                }, 2500);
            });
        }
    }

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, function (ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
