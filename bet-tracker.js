(function () {
    'use strict';

    if (!window.fhorBt) {
        return;
    }

    var state = {};
    var range = 'all';
    var chart = null;
    var quoteTimer = null;
    var showingDemo = false;
    var preferSample = true;

    function boot() {
        document.addEventListener('click', onLogClick);
        var modal = document.getElementById('fhor-bt-modal');
        if (modal) {
            bindModal(modal);
        }
        if (document.getElementById('fhor-bet-tracker') && Number(fhorBt.premium)) {
            bindApp();
            refreshNonce().then(function () {
                loadBook();
            });
        }
        if (fhorBt.prefill && fhorBt.prefill.horse && Number(fhorBt.premium)) {
            openFromPrefill(fhorBt.prefill);
        }
    }

    function parseBody(text) {
        var trimmed = String(text || '').replace(/^\uFEFF/, '').trim();
        try {
            return JSON.parse(trimmed);
        } catch (e) {
            if (trimmed === '-1' || trimmed === '0') {
                return { success: false, data: { message: 'Your session needs a refresh.', code: 'nonce' } };
            }
            return { success: false, data: { message: 'Request was rejected. Refresh the page and try again.' } };
        }
    }

    function refreshNonce() {
        var url = fhorBt.ajax + (String(fhorBt.ajax).indexOf('?') === -1 ? '?' : '&') + 'action=fhor_bt_nonce';
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) { return res.text(); }).then(function (text) {
            var json = parseBody(text);
            if (json && json.success && json.data && json.data.nonce) {
                fhorBt.nonce = json.data.nonce;
                return true;
            }
            return false;
        }).catch(function () {
            return false;
        });
    }

    function post(action, fields, retried) {
        var body = new window.FormData();
        body.append('action', action);
        body.append('nonce', fhorBt.nonce || '');
        Object.keys(fields || {}).forEach(function (key) {
            if (fields[key] === undefined || fields[key] === null) {
                return;
            }
            body.append(key, fields[key]);
        });
        return fetch(fhorBt.ajax, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: body
        })
            .then(function (res) { return res.text(); })
            .then(function (text) {
                var json = parseBody(text);
                var stale = json && json.data && json.data.code === 'nonce';
                if (!retried && stale) {
                    return refreshNonce().then(function (ok) {
                        if (!ok) {
                            return json;
                        }
                        return post(action, fields, true);
                    });
                }
                return json;
            })
            .catch(function () {
                return { success: false, data: { message: 'Network error. Try again.' } };
            });
    }

    function errMsg(json) {
        return (json && json.data && json.data.message) || 'Something went wrong.';
    }

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, function (ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
        });
    }

    function money(n) {
        var v = Math.round((Number(n) || 0) * 100) / 100;
        return (v < 0 ? '-' : '') + '£' + Math.abs(v).toFixed(2);
    }

    function shiftDate(iso, daysBack) {
        var p = String(iso || '').split('-');
        var dt = new Date(Date.UTC(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10)));
        if (isNaN(dt.getTime())) {
            return iso;
        }
        dt.setUTCDate(dt.getUTCDate() - daysBack);
        var m = ('0' + (dt.getUTCMonth() + 1)).slice(-2);
        var d = ('0' + dt.getUTCDate()).slice(-2);
        return dt.getUTCFullYear() + '-' + m + '-' + d;
    }

    function book() {
        if (showingDemo && state.demo) {
            return state.demo;
        }
        return state;
    }

    function inRange(date) {
        if (range === 'all') {
            return true;
        }
        var today = book().today || state.today || String(fhorBt.now || '').slice(0, 10);
        var start = shiftDate(today, (parseInt(range, 10) || 7) - 1);
        return String(date || '') >= start;
    }

    function formatWhen(placed) {
        var m = String(placed || '').match(/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})/);
        if (!m) {
            return placed || '';
        }
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return parseInt(m[3], 10) + ' ' + months[parseInt(m[2], 10) - 1] + ' ' + m[1] + ', ' + m[4] + ':' + m[5];
    }

    function toLocalInput(placed) {
        var m = String(placed || '').match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})/);
        return m ? (m[1] + 'T' + m[2]) : (fhorBt.now || '');
    }

    function combineWhen(date, time) {
        var d = String(date || '').slice(0, 10);
        if (!/^\d{4}-\d{2}-\d{2}$/.test(d)) {
            return fhorBt.now || '';
        }
        var m = String(time || '').match(/(\d{1,2})[:.](\d{2})/);
        var hh = '12';
        var mm = '00';
        if (m) {
            hh = ('0' + m[1]).slice(-2);
            mm = m[2];
        }
        return d + 'T' + hh + ':' + mm;
    }

    function onLogClick(event) {
        var btn = event.target.closest ? event.target.closest('.fhor-bt-log') : null;
        if (!btn) {
            return;
        }
        event.preventDefault();
        if (!Number(fhorBt.premium)) {
            window.location.href = fhorBt.url || '/bet-tracker/';
            return;
        }
        var system = btn.getAttribute('data-system') || '';
        var builder = document.getElementById('fhor-system-builder');
        if (builder && builder.contains(btn)) {
            var nameEl = document.getElementById('sb-name');
            if (nameEl) {
                system = nameEl.value.trim();
            }
        }
        openModal({
            when: combineWhen(btn.getAttribute('data-date'), btn.getAttribute('data-time')),
            horse: btn.getAttribute('data-horse') || '',
            course: btn.getAttribute('data-course') || '',
            odds: btn.getAttribute('data-odds') || '',
            system_name: system,
            system_id: btn.getAttribute('data-system-id') || '',
            bet_type: 'single',
            each_way: false,
            stake_mode: 'auto'
        });
    }

    function openFromPrefill(prefill) {
        openModal({
            when: combineWhen(prefill.date, prefill.time),
            horse: prefill.horse || '',
            course: prefill.course || '',
            odds: prefill.odds || '',
            system_name: prefill.system || '',
            system_id: prefill.system_id || '',
            bet_type: 'single',
            each_way: false,
            stake_mode: 'auto'
        });
    }

    function typeLimits() {
        var sel = document.getElementById('bt-type');
        var opt = sel.options[sel.selectedIndex];
        return {
            min: parseInt(opt.getAttribute('data-min'), 10) || 1,
            max: parseInt(opt.getAttribute('data-max'), 10) || 1
        };
    }

    function resultOptions(selected) {
        var options = [
            ['pending', 'Pending'],
            ['won', 'Won'],
            ['placed', 'Placed'],
            ['lost', 'Lost'],
            ['void', 'Void']
        ];
        return options.map(function (pair) {
            var on = pair[0] === (selected || 'pending') ? ' selected' : '';
            return '<option value="' + pair[0] + '"' + on + '>' + pair[1] + '</option>';
        }).join('');
    }

    function legRow(leg) {
        var wrap = document.createElement('div');
        wrap.className = 'bt-leg';
        wrap.innerHTML = '<input class="bt-leg-horse" type="text" maxlength="190" placeholder="Horse" value="' + escapeHtml(leg.horse || '') + '">'
            + '<input class="bt-leg-course" type="text" maxlength="190" placeholder="Course" value="' + escapeHtml(leg.course || '') + '">'
            + '<input class="bt-leg-odds" type="text" maxlength="32" placeholder="3.50 or 5/2" value="' + escapeHtml(leg.odds_input || leg.odds || '') + '">'
            + '<select class="bt-leg-result" aria-label="Result">' + resultOptions(leg.result) + '</select>'
            + '<button type="button" class="bt-icon is-danger bt-leg-remove">Remove</button>';
        return wrap;
    }

    function readLegs() {
        var rows = document.querySelectorAll('#bt-legs .bt-leg');
        var out = [];
        Array.prototype.forEach.call(rows, function (row) {
            out.push({
                horse: row.querySelector('.bt-leg-horse').value,
                course: row.querySelector('.bt-leg-course').value,
                odds_input: row.querySelector('.bt-leg-odds').value,
                result: row.querySelector('.bt-leg-result').value
            });
        });
        return out;
    }

    function renderLegs(legs) {
        var limits = typeLimits();
        var list = (legs || []).slice();
        while (list.length < limits.min) {
            list.push({});
        }
        if (list.length > limits.max) {
            list = list.slice(0, limits.max);
        }
        var box = document.getElementById('bt-legs');
        box.innerHTML = '';
        list.forEach(function (leg) {
            box.appendChild(legRow(leg));
        });
        var add = document.getElementById('bt-add-leg');
        add.hidden = list.length >= limits.max;
        Array.prototype.forEach.call(box.querySelectorAll('.bt-leg-remove'), function (btn) {
            btn.hidden = list.length <= limits.min;
        });
    }

    function syncEw() {
        var on = document.getElementById('bt-ew').checked;
        document.getElementById('bt-ew-wrap').style.display = on ? '' : 'none';
    }

    function showModalError(message) {
        var el = document.getElementById('bt-modal-error');
        if (!message) {
            el.hidden = true;
            el.textContent = '';
            return;
        }
        el.hidden = false;
        el.textContent = message;
    }

    function openModal(seed) {
        seed = seed || {};
        var modal = document.getElementById('fhor-bt-modal');
        if (!modal) {
            return;
        }
        showModalError('');
        document.getElementById('bt-id').value = seed.id ? String(seed.id) : '';
        document.getElementById('bt-modal-title').textContent = seed.id ? 'Edit bet' : 'Log bet';
        document.getElementById('bt-when').value = seed.when || toLocalInput(seed.placed_at) || fhorBt.now || '';
        document.getElementById('bt-type').value = seed.bet_type || 'single';
        document.getElementById('bt-system').value = seed.system_name || '';
        document.getElementById('bt-system-id').value = seed.system_id || '';
        document.getElementById('bt-ew').checked = !!seed.each_way;
        document.getElementById('bt-ew-terms').value = seed.ew_key || '1/4';
        document.getElementById('bt-note').value = seed.note || '';
        var auto = seed.stake_mode !== 'manual';
        var autoBox = document.getElementById('bt-stake-auto');
        var stake = document.getElementById('bt-stake');
        autoBox.checked = auto;
        stake.readOnly = auto;
        stake.value = seed.stake_mode === 'manual' && seed.manual_total ? Number(seed.manual_total).toFixed(2) : '';
        var legs = (seed.legs && seed.legs.length) ? seed.legs : [{
            horse: seed.horse || '',
            course: seed.course || '',
            odds_input: seed.odds || '',
            result: 'pending'
        }];
        renderLegs(legs);
        syncEw();
        modal.hidden = false;
        modal.classList.add('is-open');
        scheduleQuote();
    }

    function closeModal() {
        var modal = document.getElementById('fhor-bt-modal');
        if (!modal) {
            return;
        }
        modal.classList.remove('is-open');
        modal.hidden = true;
    }

    function scheduleQuote() {
        window.clearTimeout(quoteTimer);
        quoteTimer = window.setTimeout(requestQuote, 200);
    }

    function requestQuote() {
        var modal = document.getElementById('fhor-bt-modal');
        if (!modal || !modal.classList.contains('is-open')) {
            return;
        }
        post('fhor_bt_quote', {
            placed_at: document.getElementById('bt-when').value,
            bet_type: document.getElementById('bt-type').value,
            each_way: document.getElementById('bt-ew').checked ? '1' : '',
            leg_count: String(readLegs().length),
            exclude_id: document.getElementById('bt-id').value || '0'
        }).then(function (json) {
            var hint = document.getElementById('bt-stake-hint');
            if (!hint || !json || !json.success) {
                return;
            }
            var data = json.data || {};
            if (!data.valid) {
                hint.textContent = 'Add the selections this bet type needs.';
                return;
            }
            var lines = Number(data.lines) || 0;
            hint.textContent = (data.label || 'Recommended stake') + ' · ' + lines + ' line' + (lines === 1 ? '' : 's')
                + ' · ' + money(data.per_line) + ' per line · recommended total ' + money(data.total) + '.';
            var stake = document.getElementById('bt-stake');
            if (document.getElementById('bt-stake-auto').checked) {
                stake.value = Number(data.total || 0).toFixed(2);
                stake.readOnly = true;
            }
        });
    }

    function bindModal(modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });
        document.getElementById('bt-cancel').addEventListener('click', closeModal);
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                closeModal();
            }
        });
        document.getElementById('bt-type').addEventListener('change', function () {
            renderLegs(readLegs());
            scheduleQuote();
        });
        document.getElementById('bt-ew').addEventListener('change', function () {
            syncEw();
            scheduleQuote();
        });
        document.getElementById('bt-ew-terms').addEventListener('change', scheduleQuote);
        document.getElementById('bt-when').addEventListener('change', scheduleQuote);
        document.getElementById('bt-add-leg').addEventListener('click', function () {
            var legs = readLegs();
            legs.push({});
            renderLegs(legs);
            scheduleQuote();
        });
        document.getElementById('bt-legs').addEventListener('click', function (event) {
            var btn = event.target.closest ? event.target.closest('.bt-leg-remove') : null;
            if (!btn) {
                return;
            }
            var limits = typeLimits();
            var legs = readLegs();
            if (legs.length <= limits.min) {
                return;
            }
            var row = btn.closest('.bt-leg');
            var rows = document.querySelectorAll('#bt-legs .bt-leg');
            var index = Array.prototype.indexOf.call(rows, row);
            if (index >= 0) {
                legs.splice(index, 1);
                renderLegs(legs);
                scheduleQuote();
            }
        });
        document.getElementById('bt-legs').addEventListener('input', scheduleQuote);
        var autoBox = document.getElementById('bt-stake-auto');
        var stake = document.getElementById('bt-stake');
        autoBox.addEventListener('change', function () {
            stake.readOnly = autoBox.checked;
            if (autoBox.checked) {
                requestQuote();
            }
        });
        document.getElementById('bt-form').addEventListener('submit', function (event) {
            event.preventDefault();
            saveBet();
        });
    }

    function saveBet() {
        var button = document.getElementById('bt-save');
        var legs = readLegs().map(function (leg) {
            return {
                horse: leg.horse.trim(),
                course: leg.course.trim(),
                odds: leg.odds_input.trim(),
                result: leg.result
            };
        });
        var payload = {
            id: document.getElementById('bt-id').value,
            placed_at: document.getElementById('bt-when').value,
            bet_type: document.getElementById('bt-type').value,
            each_way: document.getElementById('bt-ew').checked,
            ew_terms: document.getElementById('bt-ew-terms').value,
            system_name: document.getElementById('bt-system').value.trim(),
            system_id: document.getElementById('bt-system-id').value,
            stake_mode: document.getElementById('bt-stake-auto').checked ? 'auto' : 'manual',
            manual_total: document.getElementById('bt-stake').value,
            note: document.getElementById('bt-note').value,
            legs: legs
        };
        button.disabled = true;
        showModalError('');
        post('fhor_bt_save_bet', { bet: JSON.stringify(payload) }).then(function (json) {
            button.disabled = false;
            if (!json || !json.success) {
                showModalError(errMsg(json));
                return;
            }
            closeModal();
            if (document.getElementById('fhor-bet-tracker')) {
                applyPayload(json.data);
            } else {
                window.alert('Bet saved to your tracker.');
            }
        });
    }

    function bindApp() {
        document.getElementById('bt-add').addEventListener('click', function () {
            openModal({ when: fhorBt.now, stake_mode: 'auto', bet_type: 'single' });
        });
        document.getElementById('bt-ranges').addEventListener('click', function (event) {
            var tab = event.target.closest ? event.target.closest('.bt-tab') : null;
            if (!tab) {
                return;
            }
            range = tab.getAttribute('data-range') || 'all';
            Array.prototype.forEach.call(document.querySelectorAll('#bt-ranges .bt-tab'), function (el) {
                el.classList.toggle('is-on', el === tab);
            });
            render();
        });
        document.getElementById('bt-system-filter').addEventListener('change', render);
        [document.getElementById('bt-demo-toggle'), document.getElementById('bt-demo-peek')].forEach(function (demoToggle) {
            if (!demoToggle) {
                return;
            }
            demoToggle.addEventListener('click', toggleDemo);
        });
        document.getElementById('bt-settings-form').addEventListener('submit', function (event) {
            event.preventDefault();
            saveSettings();
        });
        document.getElementById('bt-table-body').addEventListener('click', function (event) {
            var edit = event.target.closest ? event.target.closest('.bt-edit') : null;
            var del = event.target.closest ? event.target.closest('.bt-del') : null;
            if (edit) {
                editBet(edit.getAttribute('data-id'));
            }
            if (del) {
                deleteBet(del.getAttribute('data-id'));
            }
        });
    }

    function loadBook() {
        post('fhor_bt_bootstrap', {}).then(function (json) {
            if (!json || !json.success) {
                showBanner(errMsg(json));
                return;
            }
            applyPayload(json.data);
        });
    }

    function saveSettings() {
        var button = document.getElementById('bt-save-settings');
        button.disabled = true;
        post('fhor_bt_save_settings', {
            mode: document.getElementById('bt-mode').value,
            starting_bankroll: document.getElementById('bt-bankroll').value,
            percentage: document.getElementById('bt-percentage').value,
            point_value: document.getElementById('bt-point-value').value,
            points_per_bet: document.getElementById('bt-points').value
        }).then(function (json) {
            button.disabled = false;
            if (!json || !json.success) {
                showBanner(errMsg(json));
                return;
            }
            showBanner('');
            applyPayload(json.data);
        });
    }

    function showBanner(message) {
        var el = document.getElementById('bt-banner');
        if (!el) {
            return;
        }
        if (!message) {
            el.hidden = true;
            el.textContent = '';
            return;
        }
        el.hidden = false;
        el.textContent = message;
    }

    function applyPayload(data) {
        var previousCount = (state.bets || []).length;
        state = data || {};
        state.bets = state.bets || [];
        if (!state.bets.length) {
            showingDemo = preferSample && !!state.demo;
        } else if (state.bets.length > previousCount) {
            showingDemo = false;
            preferSample = false;
        }
        fillSettings(state.settings || {});
        fillSystems(book().bets || []);
        syncDemo();
        render();
    }

    function toggleDemo() {
        if (!state.demo) {
            return;
        }
        showingDemo = !showingDemo;
        if (!(state.bets || []).length) {
            preferSample = showingDemo;
        }
        fillSystems(book().bets || []);
        syncDemo();
        render();
    }

    function syncDemo() {
        var box = document.getElementById('bt-demo');
        var title = document.getElementById('bt-demo-title');
        var copy = document.getElementById('bt-demo-copy');
        var toggle = document.getElementById('bt-demo-toggle');
        var peek = document.getElementById('bt-demo-peek');
        var history = document.getElementById('bt-history-title');
        var extra = document.getElementById('bt-extra-head');
        var add = document.getElementById('bt-add');
        var hasOwn = (state.bets || []).length > 0;
        if (box) {
            box.hidden = !(showingDemo || !hasOwn);
            box.classList.toggle('is-mine', !showingDemo);
        }
        if (showingDemo) {
            if (title) {
                title.textContent = 'This is a sample book';
            }
            if (copy) {
                copy.textContent = hasOwn
                    ? 'You are looking at the example Over 5/1 each-way sheet from 2–16 September, not your saved bets. Your own bets are unchanged.'
                    : 'The chart, totals, and history below are an example: the Over 5/1 each-way sheet from 2–16 September. Nothing here is saved to your account. Add a bet and this sample is replaced by your own book.';
            }
            if (toggle) {
                toggle.textContent = hasOwn ? 'Show my bets' : 'Hide sample';
            }
        } else if (!hasOwn) {
            if (title) {
                title.textContent = 'Your book is empty';
            }
            if (copy) {
                copy.textContent = 'Add a bet whenever you are ready. It is saved to your account, and the sample stays out of the way. You can bring the sample back to see the layout again.';
            }
            if (toggle) {
                toggle.textContent = 'Show sample';
            }
        }
        if (peek) {
            peek.hidden = !state.demo || !hasOwn || showingDemo;
        }
        if (history) {
            history.textContent = showingDemo ? 'Sample history' : 'History';
        }
        if (extra) {
            extra.textContent = showingDemo ? 'Note' : '';
        }
        if (add) {
            add.textContent = (!hasOwn && showingDemo) ? 'Add your first bet' : 'Add bet';
        }
    }

    function fillSettings(settings) {
        var mode = document.getElementById('bt-mode');
        if (!mode) {
            return;
        }
        mode.value = settings.mode === 'flat' ? 'flat' : 'percentage';
        document.getElementById('bt-bankroll').value = settings.starting_bankroll;
        document.getElementById('bt-percentage').value = settings.percentage;
        document.getElementById('bt-point-value').value = settings.point_value;
        document.getElementById('bt-points').value = settings.points_per_bet;
    }

    function fillSystems(bets) {
        var sel = document.getElementById('bt-system-filter');
        if (!sel) {
            return;
        }
        var current = sel.value;
        var names = [];
        bets.forEach(function (bet) {
            if (bet.system_name && names.indexOf(bet.system_name) === -1) {
                names.push(bet.system_name);
            }
        });
        names.sort();
        sel.innerHTML = '<option value="">All systems</option>' + names.map(function (name) {
            return '<option value="' + escapeHtml(name) + '">' + escapeHtml(name) + '</option>';
        }).join('');
        sel.value = current;
    }

    function visibleBets() {
        var system = '';
        var sel = document.getElementById('bt-system-filter');
        if (sel) {
            system = sel.value;
        }
        return (book().bets || []).filter(function (bet) {
            var date = bet.date || String(bet.placed_at || '').slice(0, 10);
            if (system && bet.system_name !== system) {
                return false;
            }
            return inRange(date);
        });
    }

    function render() {
        var view = book();
        var bets = visibleBets();
        var settings = view.settings || state.settings || {};
        var profit = 0;
        var shadow = 0;
        var staked = 0;
        var hits = 0;
        var decided = 0;
        var byDay = {};
        var days = [];
        bets.forEach(function (bet) {
            if (bet.result === 'pending') {
                return;
            }
            profit += Number(bet.profit) || 0;
            shadow += Number(bet.shadow_profit) || 0;
            if (bet.result !== 'void') {
                staked += Number(bet.total_stake) || 0;
                decided += 1;
                if (bet.result === 'won' || bet.result === 'placed') {
                    hits += 1;
                }
            }
            var day = bet.date || String(bet.placed_at || '').slice(0, 10);
            if (!byDay[day]) {
                byDay[day] = { a: 0, s: 0 };
                days.push(day);
            }
            byDay[day].a += Number(bet.profit) || 0;
            byDay[day].s += Number(bet.shadow_profit) || 0;
        });
        days.sort();
        var labels = [];
        var actualSeries = [];
        var shadowSeries = [];
        var cumA = 0;
        var cumS = 0;
        days.forEach(function (day) {
            cumA += byDay[day].a;
            cumS += byDay[day].s;
            labels.push(day);
            actualSeries.push(Math.round(cumA * 100) / 100);
            shadowSeries.push(Math.round(cumS * 100) / 100);
        });
        paintStats(profit, shadow, staked, hits, decided, settings);
        paintWhatIf(profit, shadow, decided);
        drawChart(labels, actualSeries, shadowSeries, view.actual_label || 'Your staking', view.shadow_label || 'Shadow');
        paintTable(bets);
    }

    function paintStats(profit, shadow, staked, hits, decided, settings) {
        var bank = document.getElementById('bt-bankroll-stat');
        if (!bank) {
            return;
        }
        var view = book();
        bank.textContent = money(view.bankroll);
        document.getElementById('bt-bankroll-sub').textContent = showingDemo
            ? 'Sample book, opened at ' + money(settings.starting_bankroll)
            : 'Opened at ' + money(settings.starting_bankroll);
        var today = document.getElementById('bt-today-stat');
        var todaySub = document.getElementById('bt-today-sub');
        if (settings.mode === 'flat') {
            today.textContent = money(view.flat_line) + ' / line';
            todaySub.textContent = String(settings.points_per_bet) + ' pt × ' + money(settings.point_value);
        } else {
            today.textContent = money(view.today_budget);
            todaySub.textContent = showingDemo
                ? 'Locked for 17 Sept, from ' + money(view.today_opening)
                : 'Per bet from ' + money(view.today_opening);
        }
        var profitEl = document.getElementById('bt-view-profit');
        profitEl.textContent = money(profit);
        profitEl.className = profit > 0 ? 'bt-pos' : (profit < 0 ? 'bt-neg' : '');
        var profitNote = profitEl.nextElementSibling;
        if (profitNote) {
            if (settings.mode === 'flat' && Number(settings.point_value) > 0) {
                var pts = profit / Number(settings.point_value);
                profitNote.textContent = (pts >= 0 ? '+' : '') + pts.toFixed(2) + ' pts in this view';
            } else {
                profitNote.textContent = 'In this view';
            }
        }
        document.getElementById('bt-view-staked').textContent = money(staked);
        var yieldPct = staked ? (profit / staked) * 100 : 0;
        var yieldEl = document.getElementById('bt-view-yield');
        yieldEl.textContent = (Math.round(yieldPct * 10) / 10).toFixed(1) + '%';
        yieldEl.className = yieldPct > 0 ? 'bt-pos' : (yieldPct < 0 ? 'bt-neg' : '');
        var rate = decided ? (hits / decided) * 100 : 0;
        document.getElementById('bt-view-strike').textContent = (Math.round(rate * 10) / 10).toFixed(1) + '%';
        document.getElementById('bt-view-count').textContent = hits + ' of ' + decided + ' settled';
    }

    function paintWhatIf(profit, shadow, decided) {
        var box = document.getElementById('bt-whatif');
        if (!box) {
            return;
        }
        var view = book();
        var actualLabel = view.actual_label || 'your staking';
        var shadowLabel = view.shadow_label || 'the other method';
        var text;
        if (!decided) {
            text = 'Settle a bet in this view to compare staking methods.';
        } else {
            var diff = Math.round((profit - shadow) * 100) / 100;
            if (Math.abs(diff) < 0.005) {
                text = 'Using ' + actualLabel + ' returned the same as ' + shadowLabel + ' on these bets (' + money(profit) + ').';
            } else if (diff > 0) {
                text = 'Using a ' + actualLabel + ' strategy on these bets made you ' + money(diff) + ' more than ' + shadowLabel + ' would have.';
            } else {
                text = 'Using a ' + shadowLabel + ' strategy on these bets would have made you ' + money(-diff) + ' more than ' + actualLabel + '.';
            }
        }
        box.innerHTML = '<h3>What-if</h3><p>' + escapeHtml(text) + '</p>';
    }

    function drawChart(labels, actualSeries, shadowSeries, actualLabel, shadowLabel) {
        var canvas = document.getElementById('bt-chart');
        if (!canvas || !window.Chart) {
            return;
        }
        if (chart) {
            chart.destroy();
        }
        chart = new window.Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: actualLabel,
                        data: actualSeries,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37,99,235,0.08)',
                        fill: true,
                        tension: 0.25,
                        pointRadius: labels.length > 40 ? 0 : 3,
                        borderWidth: 2
                    },
                    {
                        label: shadowLabel,
                        data: shadowSeries,
                        borderColor: '#94a3b8',
                        borderDash: [5, 4],
                        fill: false,
                        tension: 0.25,
                        pointRadius: 0,
                        borderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ctx.dataset.label + ': ' + money(ctx.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        ticks: {
                            callback: function (value) {
                                return money(value);
                            }
                        }
                    }
                }
            }
        });
    }

    function paintTable(bets) {
        var empty = document.getElementById('bt-empty');
        var wrap = document.getElementById('bt-table-wrap');
        var body = document.getElementById('bt-table-body');
        if (!body) {
            return;
        }
        if (!bets.length) {
            empty.hidden = false;
            empty.textContent = showingDemo
                ? 'No sample bets in this view.'
                : ((state.bets || []).length
                    ? 'No bets in this view.'
                    : 'No bets yet. Add one, or use Log Bet on a daily qualifier.');
            wrap.hidden = true;
            body.innerHTML = '';
            return;
        }
        empty.hidden = true;
        wrap.hidden = false;
        var rows = bets.slice().reverse();
        body.innerHTML = rows.map(function (bet) {
            var pnl = bet.result === 'pending' ? '—' : money(bet.profit);
            var pnlClass = bet.result === 'pending' ? '' : (Number(bet.profit) > 0 ? 'bt-pos' : (Number(bet.profit) < 0 ? 'bt-neg' : ''));
            var action = showingDemo
                ? escapeHtml(bet.note || '')
                : '<button type="button" class="bt-icon bt-edit" data-id="' + escapeHtml(bet.id) + '">Edit</button>'
                    + '<button type="button" class="bt-icon is-danger bt-del" data-id="' + escapeHtml(bet.id) + '">Delete</button>';
            var actionLabel = showingDemo ? 'Note' : '';
            return '<tr>'
                + '<td data-label="Date"><span>' + escapeHtml(formatWhen(bet.placed_at)) + '</span></td>'
                + '<td data-label="Course"><span>' + escapeHtml(bet.course || '') + '</span></td>'
                + '<td data-label="Selection"><span>' + escapeHtml(bet.selection_label || '') + '</span></td>'
                + '<td data-label="Type"><span>' + escapeHtml(bet.type_label || bet.bet_type || '') + '</span></td>'
                + '<td data-label="Odds"><span>' + escapeHtml(bet.odds_display || '') + '</span></td>'
                + '<td data-label="Stake"><span>' + money(bet.total_stake) + '</span></td>'
                + '<td data-label="Result"><span class="bt-badge is-' + escapeHtml(bet.result || 'pending') + '">' + escapeHtml(labelResult(bet.result)) + '</span></td>'
                + '<td data-label="P/L" class="' + pnlClass + '"><span>' + pnl + '</span></td>'
                + '<td data-label="System"><span>' + escapeHtml(bet.system_name || '—') + '</span></td>'
                + '<td class="bt-td-actions" data-label="' + actionLabel + '"><span>' + action + '</span></td>'
                + '</tr>';
        }).join('');
    }

    function labelResult(result) {
        if (result === 'won') return 'Won';
        if (result === 'placed') return 'Placed';
        if (result === 'lost') return 'Lost';
        if (result === 'void') return 'Void';
        return 'Pending';
    }

    function findBet(id) {
        var found = null;
        (state.bets || []).forEach(function (bet) {
            if (String(bet.id) === String(id)) {
                found = bet;
            }
        });
        return found;
    }

    function editBet(id) {
        var bet = findBet(id);
        if (!bet) {
            return;
        }
        openModal(bet);
    }

    function deleteBet(id) {
        var bet = findBet(id);
        var name = bet ? (bet.selection_label || 'this bet') : 'this bet';
        if (!window.confirm('Delete ' + name + '? Later percentage stakes will be rebuilt.')) {
            return;
        }
        post('fhor_bt_delete_bet', { id: id }).then(function (json) {
            if (!json || !json.success) {
                showBanner(errMsg(json));
                return;
            }
            showBanner('');
            applyPayload(json.data);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
