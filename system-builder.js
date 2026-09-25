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

        function reveal(id) {
            var el = document.getElementById(id);
            if (el && window.matchMedia('(max-width: 980px)').matches) {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
            profit.textContent = ((book.profit || 0) >= 0 ? '+' : '') + (book.profit || 0) + ' pts';
            profit.className = cls(book.profit || 0);
            bets.textContent = String(book.bets || 0);
            wr.textContent = (book.win_rate || 0) + '%';
        }

        function renderBacktest(data) {
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
            document.getElementById('sb-range').textContent =
                data.from + ' – ' + data.to + ' · ' + data.qualifiers + ' qualifiers from ' + data.runners_scanned +
                ' runners scanned · ISP place rate ' + (data.isp && data.isp.place_rate != null ? data.isp.place_rate : data.place_rate) + '%' + extra;
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
            var diTh = document.getElementById('sb-sample-di-th');
            var cdTh = document.getElementById('sb-sample-cd-th');
            if (diTh) {
                diTh.hidden = !showDosage;
            }
            if (cdTh) {
                cdTh.hidden = !showDosage;
            }
            var tb = document.querySelector('#sb-sample-table tbody');
            tb.innerHTML = '';
            (data.samples || []).forEach(function (row) {
                var tr = document.createElement('tr');
                var pl = (row.profit >= 0 ? '+' : '') + row.profit;
                var isp = row.isp != null ? Number(row.isp).toFixed(2) : (row.sp || '–');
                var bsp = row.bsp != null ? Number(row.bsp).toFixed(2) : '–';
                var race = escapeHtml(row.course)
                    + (row.country ? ' · ' + escapeHtml(row.country) : '')
                    + ' · ' + escapeHtml(row.date)
                    + (row.field ? ' · ' + escapeHtml(row.field) + ' ran' : '');
                var dosageCells = showDosage
                    ? '<td>' + (row.di == null ? '–' : escapeHtml(row.di)) + '</td><td>' + (row.cd == null ? '–' : escapeHtml(row.cd)) + '</td>'
                    : '';
                tr.innerHTML = '<td>' + escapeHtml(row.horse) + '</td><td>' + race + '</td>' + dosageCells + '<td>' + escapeHtml(isp) + '</td><td>' + escapeHtml(bsp) + '</td><td>' + escapeHtml(row.pos) + '</td><td>' + pl + '</td>';
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
                var alertBtn = '';
                if (fhorSb.canAlert) {
                    var alertClass = sys.alerts ? 'sb-btn sb-alert is-on' : 'sb-btn sb-alert';
                    var alertLabel = sys.alerts ? 'Alerts on' : 'Alerts off';
                    alertBtn = '<button type="button" class="' + alertClass + '">' + alertLabel + '</button>';
                }
                return '<div class="sb-saved-item" data-id="' + escapeHtml(sys.id) + '"><b>' + escapeHtml(sys.name) + '</b>' +
                    alertBtn +
                    '<button type="button" class="sb-btn sb-load">Load</button>' +
                    '<button type="button" class="sb-btn sb-del">Delete</button></div>';
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

        try {
            if (localStorage.getItem('fhor_sb_guide_seen') === '1') {
                var guide = document.getElementById('sb-guide');
                if (guide) {
                    guide.open = false;
                }
            } else {
                localStorage.setItem('fhor_sb_guide_seen', '1');
            }
        } catch (ignore) {
            /* storage blocked */
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var btn = document.getElementById('sb-run');
            withBusy(btn, function () {
                return post('fhor_sb_backtest').then(function (json) {
                    if (json && json.success) {
                        renderBacktest(json.data);
                    } else {
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

        function saveSystem(alerts, btn) {
            var nameEl = document.getElementById('sb-name');
            var name = nameEl ? nameEl.value.trim() : '';
            withBusy(btn, function () {
                return post('fhor_sb_save', { name: name, alerts: alerts ? '1' : '' }).then(function (json) {
                    if (json && json.success) {
                        renderSaved(json.data.systems);
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

        document.getElementById('sb-saved').addEventListener('click', function (event) {
            var item = event.target.closest('.sb-saved-item');
            if (!item) {
                return;
            }
            var id = item.getAttribute('data-id');
            var sys = saved.filter(function (s) { return s.id === id; })[0];
            if (event.target.classList.contains('sb-load') && sys) {
                applyFilters(sys.filters || {});
                document.getElementById('sb-name').value = sys.name || '';
                document.getElementById('sb-alerts').checked = !!sys.alerts;
            }
            if (event.target.classList.contains('sb-del') && id) {
                post('fhor_sb_delete', { id: id }).then(function (json) {
                    if (json && json.success) {
                        renderSaved(json.data.systems);
                    }
                });
            }
            if (event.target.classList.contains('sb-alert') && id && sys) {
                var on = sys.alerts ? '' : '1';
                post('fhor_sb_toggle_alerts', { id: id, alerts: on }).then(function (json) {
                    if (json && json.success) {
                        renderSaved(json.data.systems);
                    }
                });
            }
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
