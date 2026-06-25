(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    ready(function () {
        var root = document.querySelector('.racecourse-guide-index-page');
        if (!root) {
            return;
        }

        var searchInput = root.querySelector('#rcg-index-search');
        var chips = root.querySelectorAll('.rcg-index-chip');
        var cards = root.querySelectorAll('.rcg-index-card');
        var sections = root.querySelectorAll('.rcg-index-letter-section');
        var azLinks = root.querySelectorAll('.rcg-index-az a');
        var emptyState = root.querySelector('.rcg-index-empty');
        var resultsCount = root.querySelector('.rcg-index-results-count');
        var lockedRegion = root.getAttribute('data-rcg-region') || '';

        var activeCountry = 'all';
        var activeRegion = lockedRegion || 'all';
        var searchTerm = '';
        var debounceTimer = null;

        function normalize(str) {
            return (str || '').toLowerCase().trim();
        }

        function cardMatches(card) {
            var name = normalize(card.getAttribute('data-name'));
            var country = card.getAttribute('data-country') || '';
            var region = card.getAttribute('data-region') || '';

            if (activeCountry !== 'all' && country !== activeCountry) {
                return false;
            }

            if (activeRegion !== 'all' && region !== activeRegion) {
                return false;
            }

            if (searchTerm !== '' && name.indexOf(searchTerm) === -1) {
                return false;
            }

            return true;
        }

        function applyFilters() {
            var visibleCount = 0;
            var lettersWithVisible = {};

            cards.forEach(function (card) {
                var show = cardMatches(card);
                card.classList.toggle('is-hidden', !show);
                card.setAttribute('aria-hidden', show ? 'false' : 'true');
                if (show) {
                    visibleCount += 1;
                    var letter = card.closest('.rcg-index-letter-section');
                    if (letter) {
                        lettersWithVisible[letter.getAttribute('data-letter')] = true;
                    }
                }
            });

            sections.forEach(function (section) {
                var letter = section.getAttribute('data-letter');
                var hasVisible = !!lettersWithVisible[letter];
                section.classList.toggle('is-hidden', !hasVisible);
                section.setAttribute('aria-hidden', hasVisible ? 'false' : 'true');
            });

            azLinks.forEach(function (link) {
                var letter = link.getAttribute('data-letter');
                var enabled = !!lettersWithVisible[letter];
                link.classList.toggle('is-disabled', !enabled);
                link.setAttribute('aria-disabled', enabled ? 'false' : 'true');
                if (!enabled) {
                    link.setAttribute('tabindex', '-1');
                } else {
                    link.removeAttribute('tabindex');
                }
            });

            if (emptyState) {
                emptyState.hidden = visibleCount > 0;
            }

            if (resultsCount) {
                resultsCount.textContent = visibleCount + ' racecourse' + (visibleCount === 1 ? '' : 's');
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () {
                    searchTerm = normalize(searchInput.value);
                    applyFilters();
                }, 250);
            });
        }

        chips.forEach(function (chip) {
            chip.addEventListener('click', function () {
                var filterType = chip.getAttribute('data-filter-type') || 'country';
                var filterValue = chip.getAttribute('data-filter') || 'all';

                root.querySelectorAll('.rcg-index-chip[data-filter-type="' + filterType + '"]').forEach(function (c) {
                    c.classList.remove('is-active');
                    c.setAttribute('aria-selected', 'false');
                });
                chip.classList.add('is-active');
                chip.setAttribute('aria-selected', 'true');

                if (filterType === 'region') {
                    activeRegion = filterValue;
                } else {
                    activeCountry = filterValue;
                }

                applyFilters();
            });
        });

        azLinks.forEach(function (link) {
            link.addEventListener('click', function (e) {
                if (link.classList.contains('is-disabled')) {
                    e.preventDefault();
                    return;
                }
                var targetId = link.getAttribute('href');
                if (!targetId || targetId.charAt(0) !== '#') {
                    return;
                }
                var target = document.querySelector(targetId);
                if (!target || target.classList.contains('is-hidden')) {
                    e.preventDefault();
                    return;
                }
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                var firstCard = target.querySelector('.rcg-index-card:not(.is-hidden)');
                if (firstCard) {
                    firstCard.focus();
                }
            });
        });

        applyFilters();
    });
})();
