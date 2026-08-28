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
        var loadMoreWrap = root.querySelector('#pw-load-more-wrap');
        var loadMoreBtn = root.querySelector('#pw-load-more');
        var noResults = root.querySelector('#pw-no-results');

        var perPage = parseInt(root.getAttribute('data-pw-per-page') || '24', 10);
        if (!perPage || perPage < 1) {
            perPage = 24;
        }

        var allCards = Array.prototype.slice.call(root.querySelectorAll('.pw-card'));
        var activeFilter = 'all';
        var visibleLimit = perPage;

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

        function applyView() {
            var matched = allCards.filter(cardMatchesFilters);
            var sorted = sortCards(matched);

            allCards.forEach(function (card) {
                card.classList.add('is-hidden');
                hold.appendChild(card);
            });

            sorted.forEach(function (card, index) {
                if (index < visibleLimit) {
                    card.classList.remove('is-hidden');
                    masonry.appendChild(card);
                }
            });

            var shown = Math.min(visibleLimit, sorted.length);
            if (resultsMeta) {
                if (sorted.length === 0) {
                    resultsMeta.textContent = '0 winners match your filters';
                } else if (shown < sorted.length) {
                    resultsMeta.textContent = 'Showing ' + shown + ' of ' + sorted.length + ' winners';
                } else {
                    resultsMeta.textContent = sorted.length + (sorted.length === 1 ? ' winner' : ' winners');
                }
            }

            if (noResults) {
                noResults.hidden = sorted.length > 0;
            }
            masonry.hidden = sorted.length === 0;

            if (loadMoreWrap && loadMoreBtn) {
                var hasMore = shown < sorted.length;
                loadMoreWrap.hidden = !hasMore;
                loadMoreBtn.disabled = !hasMore;
            }
        }

        function resetPagination() {
            visibleLimit = perPage;
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
            if (loadMoreBtn && (event.target === loadMoreBtn || loadMoreBtn.contains(event.target))) {
                event.preventDefault();
                visibleLimit += perPage;
                applyView();
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
