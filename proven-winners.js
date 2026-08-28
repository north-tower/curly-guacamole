(function () {
    'use strict';

    function initProvenWinnersArchive(root) {
        if (!root || root.getAttribute('data-pw-ready') === '1') {
            return;
        }
        root.setAttribute('data-pw-ready', '1');

        var masonry = root.querySelector('#pw-masonry') || root.querySelector('.pw-masonry');
        if (!masonry) {
            return;
        }

        var hold = root.querySelector('#pw-card-hold');
        if (!hold) {
            hold = document.createElement('div');
            hold.id = 'pw-card-hold';
            hold.className = 'pw-card-hold';
            hold.hidden = true;
            hold.setAttribute('aria-hidden', 'true');
            root.appendChild(hold);
        }

        var chips = root.querySelectorAll('.pw-chip');
        var searchInput = root.querySelector('#pw-search') || root.querySelector('.pw-search');
        var sortSelect = root.querySelector('#pw-sort');
        var trackSelect = root.querySelector('#pw-track');
        var dateSelect = root.querySelector('#pw-date');
        var resultsMeta = root.querySelector('#pw-results-meta');
        var pagination = root.querySelector('#pw-pagination');
        var noResults = root.querySelector('#pw-no-results');

        var perPage = parseInt(root.getAttribute('data-pw-per-page') || '24', 10);
        if (!perPage || perPage < 1) {
            perPage = 24;
        }

        var allCards = Array.prototype.slice.call(root.querySelectorAll('.pw-card'));
        var activeFilter = 'all';
        var currentPage = 1;

        function parseDate(str) {
            if (!str) {
                return 0;
            }
            var t = Date.parse(str + 'T00:00:00');
            return isNaN(t) ? 0 : t;
        }

        function daysAgoMs(days) {
            var d = new Date();
            d.setHours(0, 0, 0, 0);
            d.setDate(d.getDate() - days);
            return d.getTime();
        }

        function norm(str) {
            return String(str || '')
                .toLowerCase()
                .replace(/[_-]+/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
        }

        function cardMatchesFilters(card) {
            var featured = card.getAttribute('data-pw-featured') === '1';
            var ewHit = card.getAttribute('data-pw-ew-hit') === '1';
            if (activeFilter === 'featured' && !featured) {
                return false;
            }
            if (activeFilter === 'ew-big' && !(featured && ewHit)) {
                return false;
            }

            var query = searchInput ? norm(searchInput.value) : '';
            if (query) {
                var haystack = norm(
                    card.getAttribute('data-pw-search') ||
                        card.getAttribute('data-pw-horse') ||
                        ''
                );
                if (haystack.indexOf(query) === -1) {
                    return false;
                }
            }

            if (trackSelect && trackSelect.value) {
                if (norm(card.getAttribute('data-pw-course')) !== norm(trackSelect.value)) {
                    return false;
                }
            }

            if (dateSelect && dateSelect.value) {
                var cardDateStr = card.getAttribute('data-pw-date') || '';
                var cardDate = parseDate(cardDateStr);
                var val = dateSelect.value;
                if (val.indexOf('year-') === 0) {
                    if (cardDateStr.slice(0, 4) !== val.slice(5)) {
                        return false;
                    }
                } else if (val.indexOf('month-') === 0) {
                    if (cardDateStr.slice(0, 7) !== val.slice(6)) {
                        return false;
                    }
                } else if (val === 'this-month' || val === 'last-month') {
                    var ref = new Date();
                    ref.setHours(0, 0, 0, 0);
                    ref.setDate(1);
                    if (val === 'last-month') {
                        ref.setMonth(ref.getMonth() - 1);
                    }
                    var month = ref.getMonth() + 1;
                    var key = ref.getFullYear() + '-' + (month < 10 ? '0' : '') + month;
                    if (cardDateStr.slice(0, 7) !== key) {
                        return false;
                    }
                } else {
                    var days = parseInt(val, 10);
                    if (days > 0 && cardDate < daysAgoMs(days)) {
                        return false;
                    }
                }
            }

            return true;
        }

        function sortCards(cards) {
            var mode = sortSelect ? sortSelect.value : 'recent';
            return cards.slice().sort(function (a, b) {
                if (mode === 'roi-desc') {
                    var roiA = parseFloat(a.getAttribute('data-pw-best-roi') || '0');
                    var roiB = parseFloat(b.getAttribute('data-pw-best-roi') || '0');
                    if (roiB !== roiA) {
                        return roiB - roiA;
                    }
                } else if (mode === 'ew-desc') {
                    var ewA = parseFloat(a.getAttribute('data-pw-ew-roi') || '0');
                    var ewB = parseFloat(b.getAttribute('data-pw-ew-roi') || '0');
                    if (ewB !== ewA) {
                        return ewB - ewA;
                    }
                } else if (mode === 'price-desc') {
                    var spA = parseFloat(a.getAttribute('data-pw-sp') || '0');
                    var spB = parseFloat(b.getAttribute('data-pw-sp') || '0');
                    if (spB !== spA) {
                        return spB - spA;
                    }
                }
                var dateA = parseDate(a.getAttribute('data-pw-date') || '');
                var dateB = parseDate(b.getAttribute('data-pw-date') || '');
                return dateB - dateA;
            });
        }

        function pageItems(current, total) {
            if (total <= 7) {
                var all = [];
                for (var i = 1; i <= total; i++) {
                    all.push(i);
                }
                return all;
            }
            var items = [1];
            var start = Math.max(2, current - 1);
            var end = Math.min(total - 1, current + 1);
            if (current <= 3) {
                start = 2;
                end = 4;
            }
            if (current >= total - 2) {
                start = total - 3;
                end = total - 1;
            }
            if (start > 2) {
                items.push('…');
            }
            for (var n = start; n <= end; n++) {
                items.push(n);
            }
            if (end < total - 1) {
                items.push('…');
            }
            items.push(total);
            return items;
        }

        function renderPagination(total, totalPages) {
            if (!pagination) {
                return;
            }
            pagination.innerHTML = '';
            if (total === 0 || totalPages <= 1) {
                pagination.hidden = true;
                return;
            }
            pagination.hidden = false;

            var status = document.createElement('div');
            status.className = 'pw-page-status';
            status.textContent = 'Page ' + currentPage + ' of ' + totalPages;
            pagination.appendChild(status);

            var prev = document.createElement('button');
            prev.type = 'button';
            prev.className = 'pw-page-btn is-nav';
            prev.setAttribute('data-pw-page', String(currentPage - 1));
            prev.textContent = 'Prev';
            prev.disabled = currentPage <= 1;
            pagination.appendChild(prev);

            pageItems(currentPage, totalPages).forEach(function (item) {
                if (item === '…') {
                    var dots = document.createElement('span');
                    dots.className = 'pw-page-ellipsis';
                    dots.setAttribute('aria-hidden', 'true');
                    dots.textContent = '…';
                    pagination.appendChild(dots);
                    return;
                }
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'pw-page-btn' + (item === currentPage ? ' is-active' : '');
                btn.setAttribute('data-pw-page', String(item));
                btn.setAttribute('aria-label', 'Page ' + item);
                if (item === currentPage) {
                    btn.setAttribute('aria-current', 'page');
                }
                btn.textContent = String(item);
                pagination.appendChild(btn);
            });

            var next = document.createElement('button');
            next.type = 'button';
            next.className = 'pw-page-btn is-nav';
            next.setAttribute('data-pw-page', String(currentPage + 1));
            next.textContent = 'Next';
            next.disabled = currentPage >= totalPages;
            pagination.appendChild(next);
        }

        function applyView(opts) {
            var matched = allCards.filter(cardMatchesFilters);
            var sorted = sortCards(matched);
            var totalPages = Math.max(1, Math.ceil(sorted.length / perPage) || 1);
            if (currentPage > totalPages) {
                currentPage = totalPages;
            }
            if (currentPage < 1) {
                currentPage = 1;
            }

            var start = sorted.length === 0 ? 0 : (currentPage - 1) * perPage;
            var end = start + perPage;

            allCards.forEach(function (card) {
                card.classList.add('is-hidden');
                hold.appendChild(card);
            });

            sorted.slice(start, end).forEach(function (card) {
                card.classList.remove('is-hidden');
                masonry.appendChild(card);
            });

            var shownFrom = sorted.length === 0 ? 0 : start + 1;
            var shownTo = Math.min(end, sorted.length);
            if (resultsMeta) {
                if (sorted.length === 0) {
                    resultsMeta.textContent = '0 winners match your filters';
                } else if (totalPages > 1) {
                    resultsMeta.textContent = 'Showing ' + shownFrom + '–' + shownTo + ' of ' + sorted.length + ' winners';
                } else {
                    resultsMeta.textContent = sorted.length + (sorted.length === 1 ? ' winner' : ' winners');
                }
            }

            if (noResults) {
                noResults.hidden = sorted.length > 0;
            }
            masonry.hidden = sorted.length === 0;
            renderPagination(sorted.length, sorted.length === 0 ? 0 : totalPages);

            if (opts && opts.scroll && sorted.length > 0) {
                var target = resultsMeta || masonry;
                if (target && target.scrollIntoView) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        }

        function resetPagination() {
            currentPage = 1;
        }

        root.addEventListener('click', function (event) {
            var chip = event.target.closest ? event.target.closest('.pw-chip') : null;
            if (chip && root.contains(chip)) {
                event.preventDefault();
                chips.forEach(function (c) {
                    c.classList.remove('is-active');
                });
                chip.classList.add('is-active');
                activeFilter = chip.getAttribute('data-pw-filter') || 'all';
                resetPagination();
                applyView();
                return;
            }
            var pageBtn = event.target.closest ? event.target.closest('[data-pw-page]') : null;
            if (pageBtn && pagination && pagination.contains(pageBtn) && !pageBtn.disabled) {
                event.preventDefault();
                var nextPage = parseInt(pageBtn.getAttribute('data-pw-page') || '1', 10);
                if (!nextPage || nextPage === currentPage) {
                    return;
                }
                currentPage = nextPage;
                applyView({ scroll: true });
            }
        });

        root.addEventListener('input', function (event) {
            if (searchInput && event.target === searchInput) {
                resetPagination();
                applyView();
            }
        });

        root.addEventListener('change', function (event) {
            var target = event.target;
            if (!target) {
                return;
            }
            if (target === sortSelect || target === trackSelect || target === dateSelect) {
                resetPagination();
                applyView();
            }
        });

        applyView();
    }

    function boot() {
        var roots = document.querySelectorAll('.proven-winners-page, #proven-winners-archive');
        if (!roots.length) {
            return;
        }
        Array.prototype.forEach.call(roots, initProvenWinnersArchive);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
