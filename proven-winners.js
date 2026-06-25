(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('proven-winners-archive');
        if (!root) {
            return;
        }

        var masonry = document.getElementById('pw-masonry');
        if (!masonry) {
            return;
        }

        var chips = root.querySelectorAll('.pw-chip');
        var searchInput = document.getElementById('pw-search');
        var sortSelect = document.getElementById('pw-sort');
        var trackSelect = document.getElementById('pw-track');
        var dateSelect = document.getElementById('pw-date');
        var resultsMeta = document.getElementById('pw-results-meta');
        var loadMoreWrap = document.getElementById('pw-load-more-wrap');
        var loadMoreBtn = document.getElementById('pw-load-more');
        var noResults = document.getElementById('pw-no-results');

        var perPage = parseInt(root.getAttribute('data-pw-per-page') || '24', 10);
        if (!perPage || perPage < 1) {
            perPage = 24;
        }

        var allCards = Array.prototype.slice.call(masonry.querySelectorAll('.pw-card'));
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

        function cardMatchesFilters(card) {
            var featured = card.getAttribute('data-pw-featured') === '1';
            if (activeFilter === 'featured' && !featured) {
                return false;
            }

            var query = searchInput ? searchInput.value.trim().toLowerCase() : '';
            if (query) {
                var horse = (card.getAttribute('data-pw-horse') || '').toLowerCase();
                if (horse.indexOf(query) === -1) {
                    return false;
                }
            }

            if (trackSelect && trackSelect.value) {
                var course = card.getAttribute('data-pw-course') || '';
                if (course !== trackSelect.value) {
                    return false;
                }
            }

            if (dateSelect && dateSelect.value) {
                var cardDate = parseDate(card.getAttribute('data-pw-date') || '');
                var val = dateSelect.value;
                if (val.indexOf('year-') === 0) {
                    var year = val.slice(5);
                    var cardYear = (card.getAttribute('data-pw-date') || '').slice(0, 4);
                    if (cardYear !== year) {
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
            });

            sorted.forEach(function (card, index) {
                if (index < visibleLimit) {
                    card.classList.remove('is-hidden');
                }
                masonry.appendChild(card);
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

        chips.forEach(function (chip) {
            chip.addEventListener('click', function () {
                chips.forEach(function (c) {
                    c.classList.remove('is-active');
                });
                chip.classList.add('is-active');
                activeFilter = chip.getAttribute('data-pw-filter') || 'all';
                resetPagination();
                applyView();
            });
        });

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                resetPagination();
                applyView();
            });
        }

        [sortSelect, trackSelect, dateSelect].forEach(function (el) {
            if (!el) {
                return;
            }
            el.addEventListener('change', function () {
                resetPagination();
                applyView();
            });
        });

        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', function () {
                visibleLimit += perPage;
                applyView();
            });
        }

        applyView();
    });
})();
