(function () {
    'use strict';

    function boot() {
        var root = document.getElementById('fhor-system-builder');
        if (!root || !window.fhorSb) {
            return;
        }

        var form = document.getElementById('sb-form');
        var savedJson = document.getElementById('sb-saved-json');
        var saved = [];
        try {
            saved = savedJson ? JSON.parse(savedJson.textContent || '[]') : [];
        } catch (e) {
            saved = [];
        }

        function val(name) {
            var el = form.querySelector('[name="' + name + '"]');
            return el ? String(el.value || '').trim() : '';
        }

        function checked(name) {
            return Array.prototype.map.call(form.querySelectorAll('[name="' + name + '"]:checked'), function (el) {
                return el.value;
            });
        }

        function setVal(name, value) {
            var el = form.querySelector('[name="' + name + '"]');
            if (!el || value === undefined || value === null) {
                return;
            }
            el.value = value;
        }

        function setChecked(name, values) {
            values = values || [];
            Array.prototype.forEach.call(form.querySelectorAll('[name="' + name + '"]'), function (el) {
                el.checked = values.indexOf(el.value) !== -1;
            });
        }

        function collectFilters() {
            return {
                days: val('days') || '90',
                bet: val('bet') || 'win',
                pick_mode: val('pick_mode') || 'all',
                country: checked('country[]'),
                race_type: checked('race_type[]'),
                going: checked('going[]'),
                flags: checked('flags[]'),
                course: val('course'),
                class: val('class'),
                track_type: val('track_type'),
                handicap: val('handicap'),
                age_range: val('age_range'),
                dist_f_min: val('dist_f_min'),
                dist_f_max: val('dist_f_max'),
                field_min: val('field_min'),
                field_max: val('field_max'),
                fsr_min: val('fsr_min'),
                fsr_max: val('fsr_max'),
                fsr_rank_max: val('fsr_rank_max'),
                sr_min: val('sr_min'),
                sr_max: val('sr_max'),
                sr_rank_max: val('sr_rank_max'),
                or_min: val('or_min'),
                or_max: val('or_max'),
                or_diff_min: val('or_diff_min'),
                or_diff_max: val('or_diff_max'),
                cls_min: val('cls_min'),
                cls_max: val('cls_max'),
                dslr_min: val('dslr_min'),
                dslr_max: val('dslr_max'),
                db_min: val('db_min'),
                db_max: val('db_max'),
                tnr_min: val('tnr_min'),
                comb_min: val('comb_min'),
                win_strike_min: val('win_strike_min'),
                place_strike_min: val('place_strike_min'),
                odds_min: val('odds_min'),
                odds_max: val('odds_max'),
                sp_min: val('sp_min'),
                sp_max: val('sp_max'),
                fsrr_min: val('fsrr_min'),
                sr_lto_min: val('sr_lto_min'),
                age_min: val('age_min'),
                age_max: val('age_max'),
                stall_min: val('stall_min'),
                stall_max: val('stall_max'),
                pts_rank_max: val('pts_rank_max'),
                di_min: val('di_min'),
                di_max: val('di_max'),
                cd_min: val('cd_min'),
                cd_max: val('cd_max'),
                pace_zone: val('pace_zone'),
                style: val('style'),
                pms_min: val('pms_min'),
                trainer: val('trainer'),
                jockey: val('jockey')
            };
        }

        function syncFsrRankLoadNote() {
            var note = document.getElementById('sb-saved-fsr-note');
            if (!note) {
                return;
            }
            var rank = val('fsr_rank_max');
            var banner = document.getElementById('sb-fsr-backtest-banner');
            if (rank !== '') {
                note.hidden = false;
                note.textContent = 'Run System skips FSr rank on historic data until CR backtest is loaded; ROI uses your other rules. Find qualifiers still uses your saved FSr rank.';
                if (banner) {
                    banner.hidden = false;
                }
            } else {
                note.hidden = true;
                note.textContent = '';
                if (banner) {
                    banner.hidden = true;
                }
            }
            syncChips();
        }

        function applyFilters(filters) {
            if (!filters) {
                return;
            }
            [
                'days', 'bet', 'pick_mode', 'course', 'class', 'track_type', 'handicap', 'age_range',
                'dist_f_min', 'dist_f_max', 'field_min', 'field_max', 'fsr_min', 'fsr_max', 'fsr_rank_max',
                'sr_min', 'sr_max', 'sr_rank_max', 'or_min', 'or_max', 'or_diff_min', 'or_diff_max',
                'cls_min', 'cls_max', 'dslr_min', 'dslr_max', 'db_min', 'db_max', 'tnr_min', 'comb_min',
                'win_strike_min', 'place_strike_min', 'odds_min', 'odds_max', 'sp_min', 'sp_max',
                'fsrr_min', 'sr_lto_min', 'age_min', 'age_max', 'stall_min', 'stall_max',
                'pts_rank_max', 'di_min', 'di_max', 'cd_min', 'cd_max', 'pace_zone', 'style', 'pms_min', 'trainer', 'jockey'
            ].forEach(function (key) {
                var value = filters[key];
                setVal(key, value === undefined || value === null ? '' : value);
            });
            setChecked('country[]', filters.country);
            setChecked('race_type[]', filters.race_type);
            setChecked('going[]', filters.going);
            setChecked('flags[]', filters.flags);
            syncFsrRankLoadNote();
            syncChips();
        }

        function post(action, extra) {
            var body = new window.FormData();
            body.append('action', action);
            body.append('nonce', fhorSb.nonce);
            body.append('filters', JSON.stringify(collectFilters()));
            if (extra) {
                Object.keys(extra).forEach(function (k) {
                    body.append(k, extra[k]);
                });
            }
            return fetch(fhorSb.ajax, { method: 'POST', credentials: 'same-origin', body: body })
                .then(function (res) { return res.json(); })
                .catch(function () {
                    return { success: false, data: { message: 'Network error. Try again.' } };
                });
        }

        function cls(n) {
            return n > 0 ? 'is-pos' : (n < 0 ? 'is-neg' : '');
        }

        function toast(msg) {
            var t = document.getElementById('sb-toast');
            if (!t) {
                return;
            }
            t.textContent = msg;
            t.classList.add('on');
            clearTimeout(toast._tm);
            toast._tm = setTimeout(function () {
                t.classList.remove('on');
            }, 2200);
        }

        function setMobileView(view) {
            if (!root) {
                return;
            }
            root.setAttribute('data-view', view);
            Array.prototype.forEach.call(root.querySelectorAll('.sb-seg button'), function (btn) {
                btn.setAttribute('aria-pressed', btn.getAttribute('data-sb-view') === view ? 'true' : 'false');
            });
            if (view === 'results') {
                window.scrollTo(0, 0);
            }
        }

        function switchMainPane(pane) {
            var tabs = document.getElementById('sb-main-tabs');
            if (!tabs) {
                return;
            }
            Array.prototype.forEach.call(tabs.querySelectorAll('button'), function (btn) {
                var on = btn.getAttribute('data-pane') === pane;
                btn.setAttribute('aria-selected', on ? 'true' : 'false');
                btn.classList.toggle('is-on', on);
            });
            Array.prototype.forEach.call(root.querySelectorAll('.sb-pane[data-pane]'), function (el) {
                el.hidden = el.getAttribute('data-pane') !== pane;
            });
        }

        function reveal(id) {
            setMobileView('results');
            if (window.matchMedia('(max-width: 900px)').matches) {
                var col = root.querySelector('.sb-results-col');
                if (col && col.scrollIntoView) {
                    col.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        }

        function selectLabel(el) {
            if (!el || el.tagName !== 'SELECT') {
                return '';
            }
            var opt = el.options[el.selectedIndex];
            return opt ? opt.textContent : '';
        }

        function syncChips() {
            var chipsEl = document.getElementById('sb-chips');
            if (!chipsEl || !form) {
                return;
            }
            var chips = [];
            var daysEl = form.querySelector('[name="days"]');
            var betEl = form.querySelector('[name="bet"]');
            if (daysEl) {
                chips.push(selectLabel(daysEl));
            }
            if (betEl) {
                chips.push(selectLabel(betEl));
            }
            checked('country[]').forEach(function (c) { chips.push(c); });
            checked('race_type[]').forEach(function (c) { chips.push(c); });
            if (val('course')) {
                chips.push(val('course'));
            }
            if (val('class')) {
                chips.push('Class ' + val('class'));
            }
            if (val('track_type')) {
                chips.push(val('track_type') === 'AW' ? 'All-weather' : val('track_type'));
            }
            checked('going[]').forEach(function (g) { chips.push(g); });
            if (val('dist_f_min') || val('dist_f_max')) {
                chips.push((val('dist_f_min') || '0') + '–' + (val('dist_f_max') || '+') + 'f');
            }
            if (val('field_min') || val('field_max')) {
                chips.push('Field ' + (val('field_min') || '0') + '–' + (val('field_max') || '+'));
            }
            if (val('handicap') === 'yes') {
                chips.push('Handicaps');
            } else if (val('handicap') === 'no') {
                chips.push('Non-handicaps');
            }
            if (val('pts_rank_max')) {
                chips.push('Points rank ≤ ' + val('pts_rank_max'));
            }
            if (val('sr_rank_max')) {
                chips.push('Speed rank ≤ ' + val('sr_rank_max'));
            }
            chipsEl.innerHTML = chips.length
                ? chips.map(function (x) { return '<span class="sb-chip">' + escapeHtml(x) + '</span>'; }).join('')
                : '<span class="sb-none">No rules set</span>';
            var sampleSum = root.querySelector('[data-sum="sample"]');
            if (sampleSum) {
                sampleSum.textContent = selectLabel(daysEl) + ' · ' + selectLabel(betEl) + ' · ' + selectLabel(form.querySelector('[name="pick_mode"]'));
            }
            var raceSum = root.querySelector('[data-sum="race"]');
            if (raceSum) {
                var countries = checked('country[]');
                var types = checked('race_type[]');
                var going = checked('going[]');
                raceSum.textContent = [
                    countries.length ? countries.join(', ') : 'Any country',
                    types.length ? types.join(', ') : 'Any type',
                    (val('dist_f_min') || '0') + '–' + (val('dist_f_max') || '+') + 'f',
                    going.length ? going.join('/') : 'Any going'
                ].join(' · ');
            }
            var rateSum = root.querySelector('[data-sum="rate"]');
            if (rateSum) {
                var spd = form.querySelector('[name="sr_rank_max"]');
                rateSum.textContent = 'Speed rank ' + (selectLabel(spd) || 'any').toLowerCase() + ' · FSR rank applies to live card';
            }
        }

        function fillBook(prefix, book) {
            book = book || {};
            var roi = document.getElementById(prefix === 'sb' ? 'sb-roi' : 'sb-bsp-roi');
            var profit = document.getElementById(prefix === 'sb' ? 'sb-profit' : 'sb-bsp-profit');
            var bets = document.getElementById(prefix === 'sb' ? 'sb-bets' : 'sb-bsp-bets');
            var wr = document.getElementById(prefix === 'sb' ? 'sb-wr' : 'sb-bsp-wr');
            if (!roi) {
                return;
            }
            roi.textContent = (book.roi != null ? book.roi : 0) + '%';
            roi.className = cls(book.roi || 0);
            var roiStat = roi.closest('.sb-stat');
            if (roiStat) {
                roiStat.classList.toggle('good', (book.roi || 0) > 0);
            }
            profit.textContent = ((book.profit || 0) >= 0 ? '+' : '') + (book.profit || 0) + ' pts';
            profit.className = cls(book.profit || 0);
            var profitStat = profit.closest('.sb-stat');
            if (profitStat) {
                profitStat.classList.toggle('good', (book.profit || 0) > 0);
            }
            bets.textContent = String(book.bets || 0);
            wr.textContent = (book.win_rate || 0) + '%';
        }

        function profitCurveSvg(samples, totalBets) {
            samples = samples || [];
            if (!samples.length) {
                return '';
            }
            var t = 0;
            var pts = [0];
            samples.slice().reverse().forEach(function (row) {
                t += row.profit || 0;
                pts.push(t);
            });
            var mn = Math.min.apply(null, pts);
            var mx = Math.max.apply(null, pts);
            var W = 600;
            var H = 120;
            var p = 8;
            var x = function (i) { return p + i * (W - 2 * p) / (pts.length - 1); };
            var y = function (v) { return H - p - (v - mn) * (H - 2 * p) / ((mx - mn) || 1); };
            var d = pts.map(function (v, i) { return (i ? 'L' : 'M') + x(i).toFixed(1) + ' ' + y(v).toFixed(1); }).join(' ');
            var n = samples.length;
            var label = 'Profit over the latest ' + n + ' bet' + (n === 1 ? '' : 's');
            return '<h3><span>' + label + '</span><span style="color:var(--sb-mute,#5d6659);font-weight:500">of ' + (totalBets || n) + ' total</span></h3>' +
                '<svg viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="none" role="img" aria-label="Cumulative profit and loss">' +
                '<line x1="0" x2="' + W + '" y1="' + y(0) + '" y2="' + y(0) + '" stroke="currentColor" stroke-opacity=".25" stroke-dasharray="4 4"/>' +
                '<path d="' + d + ' L' + x(pts.length - 1) + ' ' + (H - p) + ' L' + x(0) + ' ' + (H - p) + 'Z" fill="#a4e51c" fill-opacity=".25"/>' +
                '<path d="' + d + '" fill="none" stroke="#17703a" stroke-width="2.5" vector-effect="non-scaling-stroke"/></svg>';
        }

        function fmtPl(n) {
            return (n > 0 ? '+' : '') + (Math.round(n * 10) / 10);
        }

        function renderBacktest(data) {
            switchMainPane('back');
            document.getElementById('sb-results-empty').hidden = true;
            document.getElementById('sb-results-body').hidden = false;
            fillBook('sb', data.isp || data);
            fillBook('bsp', data.bsp);
            var bspBook = document.getElementById('sb-bsp-book');
            if (bspBook) {
                bspBook.style.opacity = data.has_bsp ? '1' : '.55';
            }
            var extra = data.has_bsp ? '' : ' · Betfair SP not present in this historic sample';
            if (data.capped) {
                extra += ' · sample hit the scan cap — add more race filters or shorten lookback';
            }
            var fsrNote = '';
            if (data.historic_fsr_runners != null && data.runners_scanned > 0) {
                fsrNote = ' · historic FSr on ' + data.historic_fsr_runners + ' / ' + data.runners_scanned + ' runners';
            }
            document.getElementById('sb-range').textContent =
                data.from + ' – ' + data.to + ' · ' + data.qualifiers + ' qualifiers from ' + data.runners_scanned +
                ' runners scanned' + fsrNote + ' · ISP place rate ' + (data.isp && data.isp.place_rate != null ? data.isp.place_rate : data.place_rate) + '%' + extra;
            var zeroHint = document.getElementById('sb-zero-hint');
            if (zeroHint) {
                if (data.fsr_rank_backtest_skipped && data.fsr_rank_backtest_note) {
                    zeroHint.hidden = false;
                    zeroHint.textContent = data.fsr_rank_backtest_note;
                } else if (data.zero_hint && (data.qualifiers === 0 || !data.qualifiers)) {
                    zeroHint.hidden = false;
                    zeroHint.textContent = data.zero_hint;
                } else {
                    zeroHint.hidden = true;
                    zeroHint.textContent = '';
                }
            }
            var paceNote = document.getElementById('sb-pace-note');
            if (paceNote) {
                if (data.pace_live_only) {
                    paceNote.hidden = false;
                    paceNote.textContent = 'Pace zone, PMS, and lone-leader / swooper flags are applied to the live card and email alerts, not this historic ROI sample.';
                } else {
                    paceNote.hidden = true;
                    paceNote.textContent = '';
                }
            }
            var dosageNote = document.getElementById('sb-dosage-note');
            var showDosage = !!data.dosage_filtered;
            if (dosageNote) {
                if (showDosage) {
                    dosageNote.hidden = false;
                    dosageNote.textContent = 'Historic ROI includes only horses whose Chefs-de-Race DI/CD fall inside your dosage band. Sample rows show the figures that passed.';
                } else {
                    dosageNote.hidden = true;
                    dosageNote.textContent = '';
                }
            }
            var curveWrap = document.getElementById('sb-curve-wrap');
            if (curveWrap) {
                if ((data.samples || []).length) {
                    curveWrap.hidden = false;
                    curveWrap.innerHTML = profitCurveSvg(data.samples, data.isp && data.isp.bets != null ? data.isp.bets : data.bets);
                } else {
                    curveWrap.hidden = true;
                    curveWrap.innerHTML = '';
                }
            }
            var foot = document.getElementById('sb-sample-footnote');
            if (foot) {
                var sampleN = (data.samples || []).length;
                var totalN = data.isp && data.isp.bets != null ? data.isp.bets : data.bets;
                if (sampleN && totalN > sampleN) {
                    foot.hidden = false;
                    foot.textContent = 'Showing the latest ' + sampleN + ' of ' + totalN + ' bets. A sample this small can swing a long way, so treat ROI with care.';
                } else {
                    foot.hidden = true;
                    foot.textContent = '';
                }
            }
            var tb = document.querySelector('#sb-sample-table tbody');
            tb.innerHTML = '';
            (data.samples || []).forEach(function (row) {
                var tr = document.createElement('tr');
                var win = (row.profit || 0) > 0;
                var isp = row.isp != null ? Number(row.isp).toFixed(2) : (row.sp || '–');
                var raceMeta = escapeHtml(row.course) + ' · ' + escapeHtml(row.date)
                    + (row.field ? ' · ' + escapeHtml(row.field) + ' ran' : '');
                var posNum = parseInt(row.pos, 10);
                var posClass = 'sb-pos' + (posNum === 1 ? ' first' : '');
                tr.innerHTML = '<td>' + escapeHtml(row.horse) + '<small>' + raceMeta + '</small></td>' +
                    '<td class="sb-n">' + escapeHtml(isp) + '</td>' +
                    '<td class="sb-n"><span class="' + posClass + '">' + escapeHtml(row.pos) + '</span></td>' +
                    '<td class="sb-n"><span class="sb-pill ' + (win ? 'w' : 'l') + '">' + fmtPl(row.profit || 0) + '</span></td>';
                tb.appendChild(tr);
            });
            reveal('sb-results');
        }

        var qualifierSets = { today: null, tomorrow: null };

        function renderQualifierRows(block) {
            document.getElementById('sb-today-empty').hidden = true;
            document.getElementById('sb-today-body').hidden = false;
            block = block || { count: 0, date: '', rows: [] };
            document.getElementById('sb-today-meta').textContent = (block.count || 0) + ' qualifier(s) on ' + (block.date || '');
            var tb = document.querySelector('#sb-today-table tbody');
            tb.innerHTML = '';
            (block.rows || []).forEach(function (row) {
                var tr = document.createElement('tr');
                var name = row.race_url
                    ? '<a href="' + escapeHtml(row.race_url) + '">' + escapeHtml(row.horse) + '</a>'
                    : escapeHtml(row.horse);
                var log = '<button type="button" class="sb-btn fhor-bt-log" data-horse="' + escapeHtml(row.horse) + '" data-course="' + escapeHtml(row.course) + '" data-time="' + escapeHtml(row.time) + '" data-date="' + escapeHtml(block.date || '') + '" data-odds="' + escapeHtml(row.forecast || '') + '" data-system="">⚡ Log Bet</button>';
                tr.innerHTML = '<td>' + escapeHtml(row.time) + '</td><td>' + name + '</td><td>' + escapeHtml(row.course) + '</td><td>' + (row.fsr == null ? '–' : row.fsr) + '</td><td>' + (row.fsr_rank || '–') + '</td><td>' + (row.pts == null ? '–' : row.pts) + '</td><td>' + (row.di == null ? '–' : escapeHtml(row.di)) + '</td><td>' + (row.cd == null ? '–' : escapeHtml(row.cd)) + '</td><td>' + escapeHtml(row.forecast || '') + '</td><td>' + log + '</td>';
                tb.appendChild(tr);
            });
        }

        function renderToday(data) {
            switchMainPane('qual');
            qualifierSets.today = data.today || data;
            qualifierSets.tomorrow = data.tomorrow || null;
            var day = qualifierSets.tomorrow ? 'tomorrow' : 'today';
            var tabs = document.querySelectorAll('#sb-q-tabs .sb-tab');
            Array.prototype.forEach.call(tabs, function (tab) {
                tab.classList.toggle('is-on', tab.getAttribute('data-day') === day);
            });
            renderQualifierRows(qualifierSets[day] || qualifierSets.today);
            reveal('sb-today-panel');
        }

        function escapeHtml(str) {
            return String(str || '').replace(/[&<>"']/g, function (ch) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
            });
        }

        function renderSaved(list) {
            saved = list || [];
            var wrap = document.getElementById('sb-saved');
            if (!saved.length) {
                wrap.innerHTML = '<p class="sb-empty">No saved systems yet.</p>';
                return;
            }
            wrap.innerHTML = saved.map(function (sys) {
                var alertSw = '';
                if (fhorSb.canAlert) {
                    alertSw = '<label class="sb-sw sb-alert-wrap"><input type="checkbox" class="sb-alert-toggle"' +
                        (sys.alerts ? ' checked' : '') + '><i></i>Email alerts</label>';
                }
                return '<div class="sb-saved-item" data-id="' + escapeHtml(sys.id) + '">' +
                    '<div class="sb-saved-t"><b>' + escapeHtml(sys.name) + '</b><small>Saved system</small></div>' +
                    alertSw +
                    '<button type="button" class="sb-btn sb-btn-sm sb-load">Load</button>' +
                    '<button type="button" class="sb-btn sb-btn-sm sb-del sb-btn-del">Delete</button></div>';
            }).join('');
        }

        function withBusy(btn, fn) {
            if (!btn) {
                return fn();
            }
            btn.disabled = true;
            return fn().finally(function () {
                btn.disabled = false;
            });
        }

        var dosagePresets = {
            sprinter: {
                di_min: '2.00',
                di_max: '',
                cd_min: '0.50',
                cd_max: '',
                dist_f_min: '',
                dist_f_max: '6'
            },
            stamina: {
                di_min: '',
                di_max: '1.40',
                cd_min: '',
                cd_max: '0.50',
                dist_f_min: '12',
                dist_f_max: ''
            }
        };

        Array.prototype.forEach.call(document.querySelectorAll('.sb-dosage-preset'), function (btn) {
            btn.addEventListener('click', function () {
                var preset = dosagePresets[btn.getAttribute('data-preset')];
                if (!preset) {
                    return;
                }
                Object.keys(preset).forEach(function (key) {
                    setVal(key, preset[key]);
                });
            });
        });

        var startersWrap = document.getElementById('sb-starters');
        var starters = (window.fhorSb && fhorSb.starters) ? fhorSb.starters : {};
        if (startersWrap && starters) {
            Object.keys(starters).forEach(function (key) {
                var s = starters[key];
                if (!s || !s.filters) {
                    return;
                }
                var card = document.createElement('div');
                card.className = 'sb-starter';
                card.innerHTML = '<h3>' + escapeHtml(s.title || key) + '</h3>' +
                    '<p>' + escapeHtml(s.summary || '') + '</p>' +
                    '<button type="button" class="sb-btn sb-btn-primary sb-starter-load" data-starter="' + escapeHtml(key) + '">' +
                    escapeHtml(s.cta || 'Load') + '</button>';
                startersWrap.appendChild(card);
            });
            startersWrap.addEventListener('click', function (event) {
                var btn = event.target.closest('.sb-starter-load');
                if (!btn) {
                    return;
                }
                var key = btn.getAttribute('data-starter');
                var starter = starters[key];
                if (!starter || !starter.filters) {
                    return;
                }
                applyFilters(starter.filters);
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                if (starter.action === 'qualifiers') {
                    var qBtn = document.getElementById('sb-today');
                    if (qBtn) {
                        qBtn.click();
                    }
                } else {
                    var runBtn = document.getElementById('sb-run');
                    if (runBtn) {
                        form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                    }
                }
            });
        }

        var guide = document.getElementById('sb-guide');
        if (guide) {
            try {
                if (localStorage.getItem('fhor_sb_guide_seen') === '1') {
                    guide.open = false;
                }
            } catch (ignore) {
                /* storage blocked */
            }
            guide.addEventListener('toggle', function () {
                if (!guide.open) {
                    try {
                        localStorage.setItem('fhor_sb_guide_seen', '1');
                    } catch (ignore2) {
                        /* storage blocked */
                    }
                }
            });
        }

        form.addEventListener('input', syncChips);
        form.addEventListener('change', function (event) {
            syncChips();
            if (event.target && event.target.getAttribute('name') === 'fsr_rank_max') {
                syncFsrRankLoadNote();
            }
        });
        syncChips();

        var mainTabs = document.getElementById('sb-main-tabs');
        if (mainTabs) {
            mainTabs.addEventListener('click', function (event) {
                var btn = event.target.closest('button[data-pane]');
                if (!btn) {
                    return;
                }
                switchMainPane(btn.getAttribute('data-pane'));
            });
        }

        Array.prototype.forEach.call(root.querySelectorAll('.sb-seg button'), function (btn) {
            btn.addEventListener('click', function () {
                setMobileView(btn.getAttribute('data-sb-view') || 'rules');
            });
        });

        var findEmpty = document.getElementById('sb-today-empty-find');
        if (findEmpty) {
            findEmpty.addEventListener('click', function () {
                var q = document.getElementById('sb-today');
                if (q) {
                    q.click();
                }
            });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var btn = document.getElementById('sb-run');
            var empty = document.getElementById('sb-results-empty');
            var body = document.getElementById('sb-results-body');
            if (empty) {
                empty.innerHTML = '<b>Running…</b>Testing your rules on past races.';
                empty.hidden = false;
            }
            if (body) {
                body.hidden = true;
            }
            switchMainPane('back');
            setMobileView('results');
            withBusy(btn, function () {
                return post('fhor_sb_backtest').then(function (json) {
                    if (json && json.success) {
                        renderBacktest(json.data);
                    } else {
                        if (empty) {
                            empty.innerHTML = '<b>Run your system</b>See how these rules would have performed on past races.';
                            empty.hidden = false;
                        }
                        if (body) {
                            body.hidden = true;
                        }
                        window.alert((json && json.data && json.data.message) || 'Backtest failed.');
                    }
                });
            });
        });

        document.getElementById('sb-today').addEventListener('click', function () {
            var btn = this;
            withBusy(btn, function () {
                return post('fhor_sb_qualifiers').then(function (json) {
                    if (json && json.success) {
                        renderToday(json.data);
                    } else {
                        window.alert((json && json.data && json.data.message) || 'Could not load qualifiers.');
                    }
                });
            });
        });

        function betTrackerBookUrl(systemId) {
            var base = fhorSb.betTrackerUrl || '';
            if (!base || !systemId) {
                return '';
            }
            var id = String(systemId).replace(/\D/g, '');
            if (!id) {
                return '';
            }
            return base + (base.indexOf('?') === -1 ? '?' : '&') + 'book=' + encodeURIComponent(id);
        }

        function afterSystemSaved(newId, wasFirstSave) {
            if (!newId || !Number(fhorSb.betTrackerPremium) || !fhorSb.betTrackerUrl) {
                return;
            }
            var url = betTrackerBookUrl(newId);
            if (!url) {
                return;
            }
            if (wasFirstSave) {
                window.location.href = url;
                return;
            }
            if (window.confirm('System saved. Open Bet Tracker to set this system’s starting bankroll and staking?')) {
                window.location.href = url;
            }
        }

        function saveSystem(alerts, btn) {
            var nameEl = document.getElementById('sb-name');
            var name = nameEl ? nameEl.value.trim() : '';
            if (!name) {
                if (nameEl) {
                    nameEl.focus();
                }
                toast('Name your system first');
                return;
            }
            var wasFirstSave = saved.length === 0;
            withBusy(btn, function () {
                return post('fhor_sb_save', { name: name, alerts: alerts ? '1' : '' }).then(function (json) {
                    if (json && json.success) {
                        var newId = json.data && json.data.id;
                        renderSaved(json.data.systems);
                        toast(alerts ? 'Saved with alerts: ' + name : 'Saved: ' + name);
                        switchMainPane('saved');
                        setMobileView('results');
                        afterSystemSaved(newId, wasFirstSave);
                    } else {
                        window.alert((json && json.data && json.data.message) || 'Could not save.');
                    }
                });
            });
        }

        var saveBtn = document.getElementById('sb-save');
        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                saveSystem(false, this);
            });
        }
        var saveAlertsBtn = document.getElementById('sb-save-alerts');
        if (saveAlertsBtn) {
            saveAlertsBtn.addEventListener('click', function () {
                saveSystem(true, this);
            });
        }

        var tabs = document.getElementById('sb-q-tabs');
        if (tabs) {
            tabs.addEventListener('click', function (event) {
                var tab = event.target.closest('.sb-tab');
                if (!tab) {
                    return;
                }
                var day = tab.getAttribute('data-day');
                Array.prototype.forEach.call(tabs.querySelectorAll('.sb-tab'), function (el) {
                    el.classList.toggle('is-on', el === tab);
                });
                if (qualifierSets[day]) {
                    renderQualifierRows(qualifierSets[day]);
                }
            });
        }

        function loadSystemIntoBuilder(sys) {
            if (!sys) {
                return;
            }
            applyFilters(sys.filters || {});
            var nameEl = document.getElementById('sb-name');
            if (nameEl) {
                nameEl.value = sys.name || '';
            }
            var alertsEl = document.getElementById('sb-alerts');
            if (alertsEl) {
                alertsEl.checked = !!sys.alerts;
            }
            setMobileView('rules');
            var bar = root.querySelector('.sb-bar');
            if (bar && bar.scrollIntoView) {
                bar.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            toast('Loaded into the builder');
        }

        var adminSystems = [];
        var adminSearch = '';

        function adminHaystack(sys) {
            return [
                sys.name,
                sys.owner_label,
                sys.user_login,
                sys.user_email,
                sys.user_id,
                sys.saved_at
            ].join(' ').toLowerCase();
        }

        function renderAdminSystems(list) {
            adminSystems = list || [];
            var wrap = document.getElementById('sb-admin-systems');
            if (!wrap || !fhorSb.isAdmin) {
                return;
            }
            var q = (adminSearch || '').trim().toLowerCase();
            var visible = adminSystems.filter(function (sys) {
                return !q || adminHaystack(sys).indexOf(q) !== -1;
            });
            if (!adminSystems.length) {
                wrap.innerHTML = '<p class="sb-empty">No member systems in the database yet.</p>';
                return;
            }
            if (!visible.length) {
                wrap.innerHTML = '<p class="sb-empty">No systems match this filter.</p>';
                return;
            }
            wrap.innerHTML = visible.map(function (sys) {
                var owner = escapeHtml(sys.owner_label || sys.user_login || 'User #' + sys.user_id);
                var login = sys.user_login && sys.user_login !== sys.owner_label
                    ? ' · @' + escapeHtml(sys.user_login)
                    : '';
                var when = sys.saved_at ? escapeHtml(sys.saved_at) : '';
                var alertTag = sys.alerts ? ' · alerts on' : '';
                return '<div class="sb-saved-item sb-admin-item" data-id="' + escapeHtml(sys.id) + '">' +
                    '<div class="sb-saved-meta"><b>' + escapeHtml(sys.name) + '</b>' +
                    '<small>' + owner + login + alertTag + (when ? ' · ' + when : '') + '</small></div>' +
                    '<button type="button" class="sb-btn sb-admin-load">Load</button></div>';
            }).join('');
        }

        if (fhorSb.isAdmin) {
            var adminJson = document.getElementById('sb-admin-systems-json');
            try {
                renderAdminSystems(adminJson ? JSON.parse(adminJson.textContent || '[]') : []);
            } catch (eAdmin) {
                renderAdminSystems([]);
            }
            var adminSearchEl = document.getElementById('sb-admin-systems-search');
            if (adminSearchEl) {
                adminSearchEl.addEventListener('input', function () {
                    adminSearch = adminSearchEl.value || '';
                    renderAdminSystems(adminSystems);
                });
            }
            var adminWrap = document.getElementById('sb-admin-systems');
            if (adminWrap) {
                adminWrap.addEventListener('click', function (event) {
                    if (!event.target.classList.contains('sb-admin-load')) {
                        return;
                    }
                    var item = event.target.closest('.sb-admin-item');
                    if (!item) {
                        return;
                    }
                    var id = item.getAttribute('data-id');
                    var sys = adminSystems.filter(function (s) { return s.id === id; })[0];
                    loadSystemIntoBuilder(sys);
                });
            }
        }

        document.getElementById('sb-saved').addEventListener('click', function (event) {
            var item = event.target.closest('.sb-saved-item');
            if (!item) {
                return;
            }
            var id = item.getAttribute('data-id');
            var sys = saved.filter(function (s) { return s.id === id; })[0];
            if (event.target.classList.contains('sb-load') && sys) {
                loadSystemIntoBuilder(sys);
            }
            if (event.target.classList.contains('sb-del') && id) {
                var delBtn = event.target;
                if (!delBtn.classList.contains('arm')) {
                    delBtn.classList.add('arm');
                    delBtn.textContent = 'Confirm delete';
                    setTimeout(function () {
                        delBtn.classList.remove('arm');
                        delBtn.textContent = 'Delete';
                    }, 3000);
                    return;
                }
                post('fhor_sb_delete', { id: id }).then(function (json) {
                    if (json && json.success) {
                        renderSaved(json.data.systems);
                        toast('Deleted');
                    }
                });
            }
        });

        document.getElementById('sb-saved').addEventListener('change', function (event) {
            if (!event.target.classList.contains('sb-alert-toggle')) {
                return;
            }
            var item = event.target.closest('.sb-saved-item');
            if (!item) {
                return;
            }
            var id = item.getAttribute('data-id');
            var sys = saved.filter(function (s) { return s.id === id; })[0];
            if (!id || !sys) {
                return;
            }
            var wantOn = event.target.checked;
            if (!!sys.alerts === wantOn) {
                return;
            }
            post('fhor_sb_toggle_alerts', { id: id, alerts: wantOn ? '1' : '' }).then(function (json) {
                if (json && json.success) {
                    renderSaved(json.data.systems);
                } else {
                    event.target.checked = !!sys.alerts;
                }
            });
        });

        window.fhorSbApplyFilters = applyFilters;
        window.fhorSbCollectFilters = collectFilters;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
