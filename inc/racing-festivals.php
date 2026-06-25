<?php
/**
 * UK & Irish racing festival landing hubs (/festivals/{slug}/).
 *
 * Seasonal hubs for Cheltenham, Grand National, Royal Ascot, Galway — timed to
 * the UK/Irish racing calendar with Fhorsite ratings, racecourse links, and live cards.
 *
 * Bricks: [racing_festivals_index] · [racing_festival_hub]
 */

if (!function_exists('bricks_festival_definitions')) {
    /**
     * @return array<string, array<string, mixed>>
     */
    function bricks_festival_definitions() {
        return (array) apply_filters('bricks_festival_definitions', [
            'cheltenham' => [
                'slug' => 'cheltenham',
                'aliases' => ['cheltenham-festival'],
                'name' => 'The Cheltenham Festival',
                'short_name' => 'Cheltenham Festival',
                'course' => 'Cheltenham',
                'course_slug' => 'cheltenham',
                'country' => 'England',
                'region' => 'uk-jumps',
                'season' => 'spring',
                'typical_timing' => 'March (Race Week)',
                'window_start' => '03-10',
                'window_end' => '03-14',
                'lead' => 'National Hunt\'s flagship meeting — Champion Hurdle, Queen Mother Champion Chase, Stayers\' Hurdle, and the Cheltenham Gold Cup. Fhorsite speed ratings and Points Engine picks for every UK jumps festival race.',
                'highlight_races' => [
                    ['name' => 'Champion Hurdle', 'day' => 'Tuesday', 'day_offset' => 0],
                    ['name' => 'Queen Mother Champion Chase', 'day' => 'Wednesday', 'day_offset' => 1],
                    ['name' => 'Stayers\' Hurdle', 'day' => 'Thursday', 'day_offset' => 2],
                    ['name' => 'Cheltenham Gold Cup', 'day' => 'Friday', 'day_offset' => 3],
                ],
            ],
            'grand-national' => [
                'slug' => 'grand-national',
                'aliases' => ['aintree', 'grand-national-festival', 'aintree-grand-national'],
                'name' => 'Grand National Festival',
                'short_name' => 'Grand National',
                'course' => 'Aintree',
                'course_slug' => 'aintree',
                'country' => 'England',
                'region' => 'uk-jumps',
                'season' => 'spring',
                'typical_timing' => 'April (Aintree meeting)',
                'window_start' => '04-03',
                'window_end' => '04-05',
                'lead' => 'Aintree\'s three-day festival culminating in the Randox Grand National — the world\'s most famous steeplechase. Turf and jumps ratings, draw-bias notes, and published Points Engine win picks.',
                'highlight_races' => [
                    ['name' => 'Manifesto Novices\' Chase', 'day' => 'Thursday', 'day_offset' => 0],
                    ['name' => 'Mildmay Novices\' Hurdle', 'day' => 'Friday', 'day_offset' => 1],
                    ['name' => 'Randox Grand National', 'day' => 'Saturday', 'day_offset' => 2],
                ],
            ],
            'royal-ascot' => [
                'slug' => 'royal-ascot',
                'aliases' => ['ascot', 'royal-ascot-festival'],
                'name' => 'Royal Ascot',
                'short_name' => 'Royal Ascot',
                'course' => 'Ascot',
                'course_slug' => 'ascot',
                'country' => 'England',
                'region' => 'uk-flat',
                'season' => 'summer',
                'typical_timing' => 'June (Royal Meeting)',
                'window_start' => '06-17',
                'window_end' => '06-21',
                'lead' => 'Five days of elite flat racing on Ascot\'s turf — from the Gold Cup to the Diamond Jubilee Stakes. Turf speed ratings, draw bias, and Nap of the Day-style Points Engine analysis for every Royal Ascot race.',
                'highlight_races' => [
                    ['name' => 'King\'s Stand Stakes', 'day' => 'Tuesday', 'day_offset' => 0],
                    ['name' => 'Prince of Wales\'s Stakes', 'day' => 'Wednesday', 'day_offset' => 1],
                    ['name' => 'Ascot Gold Cup', 'day' => 'Thursday', 'day_offset' => 2],
                    ['name' => 'Diamond Jubilee Stakes', 'day' => 'Saturday', 'day_offset' => 4],
                ],
            ],
            'galway' => [
                'slug' => 'galway',
                'aliases' => ['galway-festival', 'galway-races'],
                'name' => 'Galway Festival',
                'short_name' => 'Galway Festival',
                'course' => 'Galway',
                'course_slug' => 'galway',
                'country' => 'Ireland',
                'region' => 'ireland',
                'season' => 'summer',
                'typical_timing' => 'July / August',
                'window_start' => '07-28',
                'window_end' => '08-03',
                'lead' => 'Ireland\'s summer racing carnival — seven days of flat and jumps action at Ballybrit. Irish racing speed figures, course form, and Fhorsite Points Engine picks for Galway festival week.',
                'highlight_races' => [
                    ['name' => 'Galway Plate', 'day' => 'Wednesday', 'day_offset' => 0],
                    ['name' => 'Galway Hurdle', 'day' => 'Thursday', 'day_offset' => 1],
                    ['name' => 'Irish St Leger trial races', 'day' => 'Friday–Sunday', 'day_offset' => 2, 'day_span' => 2],
                ],
            ],
        ]);
    }
}

if (!function_exists('bricks_festival_resolve_slug')) {
    function bricks_festival_resolve_slug($slug) {
        $slug = sanitize_title((string) $slug);
        if ($slug === '') {
            return null;
        }

        foreach (bricks_festival_definitions() as $def) {
            if (($def['slug'] ?? '') === $slug) {
                return $def;
            }
            foreach ((array) ($def['aliases'] ?? []) as $alias) {
                if (sanitize_title($alias) === $slug) {
                    return $def;
                }
            }
        }

        return null;
    }
}

if (!function_exists('bricks_festival_url')) {
    function bricks_festival_url($slug = '') {
        $base = home_url('/festivals/');
        $slug = sanitize_title((string) $slug);
        return $slug === '' ? $base : $base . $slug . '/';
    }
}

if (!function_exists('bricks_festival_year_window')) {
    /**
     * @return array{start:string,end:string,year:int}
     */
    function bricks_festival_year_window(array $festival, $year = null) {
        $year = $year !== null ? intval($year) : intval(wp_date('Y'));
        return [
            'year' => $year,
            'start' => sprintf('%04d-%s', $year, $festival['window_start']),
            'end' => sprintf('%04d-%s', $year, $festival['window_end']),
        ];
    }
}

if (!function_exists('bricks_festival_resolve_occurrence')) {
    /**
     * Next or current festival occurrence relative to today.
     *
     * @return array{year:int,start:string,end:string,status:string,days_until:int|null,days_since:int|null}
     */
    function bricks_festival_resolve_occurrence(array $festival) {
        $today = wp_date('Y-m-d');
        $year = intval(wp_date('Y'));
        $window = bricks_festival_year_window($festival, $year);

        if ($today >= $window['start'] && $today <= $window['end']) {
            return array_merge($window, [
                'status' => 'live',
                'days_until' => 0,
                'days_since' => null,
            ]);
        }

        if ($today < $window['start']) {
            $days_until = max(0, (int) floor((strtotime($window['start']) - strtotime($today)) / 86400));
            return array_merge($window, [
                'status' => 'upcoming',
                'days_until' => $days_until,
                'days_since' => null,
            ]);
        }

        $next = bricks_festival_year_window($festival, $year + 1);
        $days_until = max(0, (int) floor((strtotime($next['start']) - strtotime($today)) / 86400));
        $days_since = max(0, (int) floor((strtotime($today) - strtotime($window['end'])) / 86400));

        return array_merge($next, [
            'status' => 'upcoming',
            'days_until' => $days_until,
            'days_since' => $days_since,
            'between_seasons' => true,
        ]);
    }
}

if (!function_exists('bricks_festival_sort_by_next_start')) {
    /**
     * Soonest next occurrence first; live festivals pinned to top by caller.
     *
     * @param array<string, array<string, mixed>> $festivals
     * @return array<int, array<string, mixed>>
     */
    function bricks_festival_sort_by_next_start(array $festivals) {
        $items = array_values($festivals);
        usort($items, function ($a, $b) {
            $oa = bricks_festival_resolve_occurrence($a);
            $ob = bricks_festival_resolve_occurrence($b);
            if (($oa['status'] ?? '') === 'live' && ($ob['status'] ?? '') !== 'live') {
                return -1;
            }
            if (($ob['status'] ?? '') === 'live' && ($oa['status'] ?? '') !== 'live') {
                return 1;
            }
            return strcmp((string) ($oa['start'] ?? ''), (string) ($ob['start'] ?? ''));
        });
        return $items;
    }
}

if (!function_exists('bricks_festival_get_image_url')) {
    function bricks_festival_get_image_url(array $festival, $context = 'card') {
        $context = $context === 'hero' ? 'hero' : 'card';
        if ($context === 'hero' && !empty($festival['hero_image_url'])) {
            return (string) $festival['hero_image_url'];
        }
        if (!empty($festival['card_image_url'])) {
            return (string) $festival['card_image_url'];
        }
        if ($context === 'hero' && !empty($festival['card_image_url'])) {
            return (string) $festival['card_image_url'];
        }
        if (!empty($festival['image_url'])) {
            return (string) $festival['image_url'];
        }
        return '';
    }
}

if (!function_exists('bricks_festival_countdown_label')) {
    function bricks_festival_countdown_label(array $occurrence) {
        if (($occurrence['status'] ?? '') === 'live') {
            return 'Live now';
        }

        $days = max(0, intval($occurrence['days_until'] ?? 0));
        if ($days === 0) {
            return 'Starts today';
        }
        if ($days === 1) {
            return 'Starts tomorrow';
        }
        if ($days <= 42) {
            return 'Starts in ' . $days . ' days';
        }
        if ($days <= 365) {
            $months = max(1, (int) round($days / 30));
            if ($months <= 11) {
                return 'Starts in ' . $months . ' month' . ($months === 1 ? '' : 's');
            }
        }

        $start = (string) ($occurrence['start'] ?? '');
        if ($start !== '') {
            return 'Starts ' . wp_date('j M Y', strtotime($start));
        }

        return 'Upcoming';
    }
}

if (!function_exists('bricks_festival_secondary_status_label')) {
    /**
     * Shown alongside countdown when the last meeting has ended (archive / no live card).
     */
    function bricks_festival_secondary_status_label(array $occurrence) {
        if (($occurrence['status'] ?? '') === 'live') {
            return '';
        }
        if (!empty($occurrence['between_seasons']) && intval($occurrence['days_since'] ?? 0) > 0) {
            return 'Off season';
        }
        return '';
    }
}

if (!function_exists('bricks_festival_format_window_label')) {
    function bricks_festival_format_window_label(array $occurrence) {
        $start = wp_date('j M', strtotime($occurrence['start']));
        $end = wp_date('j M Y', strtotime($occurrence['end']));
        return $start . ' – ' . $end;
    }
}

if (!function_exists('bricks_festival_status_label')) {
    function bricks_festival_status_label(array $occurrence) {
        return bricks_festival_countdown_label($occurrence);
    }
}

if (!function_exists('bricks_festival_format_race_date_label')) {
    function bricks_festival_format_race_date_label(array $race, array $occurrence) {
        $start_ts = strtotime((string) ($occurrence['start'] ?? ''));
        if (!$start_ts) {
            return (string) ($race['day'] ?? '');
        }

        $offset = isset($race['day_offset']) ? intval($race['day_offset']) : 0;
        $span = isset($race['day_span']) ? max(0, intval($race['day_span'])) : 0;
        $race_ts = strtotime('+' . $offset . ' days', $start_ts);

        $day_name = trim((string) ($race['day'] ?? ''));
        if ($span > 0) {
            $end_ts = strtotime('+' . $span . ' days', $race_ts);
            return $day_name . ' · ' . wp_date('j M', $race_ts) . ' – ' . wp_date('j M Y', $end_ts);
        }

        $date_label = wp_date('l j M Y', $race_ts);
        if ($day_name !== '') {
            return $day_name . ' · ' . wp_date('j M Y', $race_ts);
        }
        return $date_label;
    }
}

if (!function_exists('bricks_festival_highlight_race_url')) {
    function bricks_festival_highlight_race_url(array $race, array $festival, array $occurrence) {
        if (!empty($race['url'])) {
            return (string) $race['url'];
        }

        $course_url = function_exists('bricks_track_url')
            ? bricks_track_url($festival['course_slug'] ?? $festival['course'])
            : home_url('/racecourses/' . sanitize_title($festival['course_slug'] ?? '') . '/');

        if (($occurrence['status'] ?? '') === 'live') {
            return home_url('/daily/');
        }

        if (($occurrence['status'] ?? '') === 'upcoming' && intval($occurrence['days_until'] ?? 999) <= 21) {
            return home_url('/daily/');
        }

        return $course_url;
    }
}

if (!function_exists('bricks_festival_race_cards_cta')) {
    /**
     * @return array{show:bool,label:string,url:string}|null
     */
    function bricks_festival_race_cards_cta(array $festival, array $occurrence, $has_winners = false) {
        $status = $occurrence['status'] ?? '';
        $course_url = function_exists('bricks_track_url')
            ? bricks_track_url($festival['course_slug'] ?? $festival['course'])
            : home_url('/racecourses/' . sanitize_title($festival['course_slug'] ?? '') . '/');

        if ($status === 'live') {
            return [
                'show' => true,
                'label' => 'Today\'s race cards',
                'url' => home_url('/daily/'),
            ];
        }

        if ($status === 'upcoming' && intval($occurrence['days_until'] ?? 999) <= 14) {
            return [
                'show' => true,
                'label' => 'Today\'s race cards',
                'url' => home_url('/daily/'),
            ];
        }

        if ($has_winners) {
            return [
                'show' => true,
                'label' => 'View past winners',
                'url' => '#rf-winners',
            ];
        }

        return null;
    }
}

if (!function_exists('bricks_festival_get_venue_winners')) {
    function bricks_festival_get_venue_winners(array $festival, $limit = 8) {
        if (!function_exists('bricks_track_get_venue_winners')) {
            return [];
        }
        return bricks_track_get_venue_winners($festival['course'] ?? '', max(1, min(20, intval($limit))));
    }
}

if (!function_exists('bricks_festival_is_request')) {
    function bricks_festival_is_request() {
        if (get_query_var('festivals_index') || get_query_var('festival_slug')) {
            return true;
        }
        return (bool) preg_match('#/festivals(?:/|$)#i', (string) ($_SERVER['REQUEST_URI'] ?? ''));
    }
}

if (!function_exists('bricks_festival_enqueue_styles')) {
    function bricks_festival_enqueue_styles() {
        $css = '
        .racing-festivals-page{--rf-green:#16a34a}
        .rf-hero{margin-bottom:1.5rem}
        .rf-title{margin:0 0 .5rem;font-size:clamp(1.75rem,3vw,2.35rem);line-height:1.15}
        .rf-lead{margin:0;color:#475569;font-size:1.05rem;line-height:1.6;max-width:760px}
        .rf-badge{display:inline-block;margin:0 0 .75rem;padding:.25rem .65rem;border-radius:999px;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
        .rf-badge.is-live{background:#dcfce7;color:#15803d}
        .rf-badge.is-upcoming{background:#eff6ff;color:#1d4ed8}
        .rf-badge.is-off{background:#f1f5f9;color:#64748b}
        .rf-badge-row{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem;margin:0 0 .75rem}
        .rf-badge-row .rf-badge{margin:0}
        .rf-meta{display:flex;flex-wrap:wrap;gap:.75rem;margin:1rem 0 1.5rem;font-size:.875rem;color:#64748b}
        .rf-meta span{display:inline-flex;align-items:center;gap:.35rem}
        .rf-actions{display:flex;flex-wrap:wrap;gap:.65rem;margin:0 0 1.75rem}
        .rf-btn{display:inline-block;padding:.55rem 1rem;border-radius:8px;font-size:.875rem;font-weight:700;text-decoration:none}
        .rf-btn-primary{background:var(--rf-green);color:#fff}
        .rf-btn-secondary{background:#fff;border:1px solid #e2e8f0;color:#334155}
        .rf-section{margin:2rem 0}
        .rf-section h2{margin:0 0 .75rem;font-size:1.3rem}
        .rf-race-list{margin:0;padding:0;list-style:none;display:grid;gap:.5rem}
        .rf-race-list a.rf-race-row{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.65rem .85rem;border:1px solid #e2e8f0;border-radius:8px;background:#fff;font-size:.9rem;text-decoration:none;color:inherit;transition:border-color .2s,box-shadow .2s,transform .2s}
        .rf-race-list a.rf-race-row:hover,.rf-race-list a.rf-race-row:focus-visible{border-color:var(--rf-green);box-shadow:0 4px 14px rgba(15,23,42,.08);transform:translateY(-1px);outline:none}
        .rf-race-row-body{display:flex;flex-direction:column;gap:.15rem;min-width:0}
        .rf-race-row strong{color:#111827;font-size:.95rem}
        .rf-race-row-date{font-size:.8rem;color:#64748b}
        .rf-race-row-chevron{font-size:1.2rem;line-height:1;color:#94a3b8;flex-shrink:0;transition:transform .2s,color .2s}
        .rf-race-list a.rf-race-row:hover .rf-race-row-chevron,.rf-race-list a.rf-race-row:focus-visible .rf-race-row-chevron{color:var(--rf-green);transform:translateX(2px)}
        .rf-grid--index{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;margin:0 0 2rem}
        .rf-card{display:flex;flex-direction:column;gap:.4rem;padding:0;border:1px solid #e2e8f0;border-radius:12px;background:#fff;text-decoration:none;color:inherit;overflow:hidden;transition:border-color .2s,box-shadow .2s,transform .2s}
        .rf-card:hover,.rf-card:focus-visible{border-color:var(--rf-green);box-shadow:0 6px 18px rgba(15,23,42,.08);transform:translateY(-2px);outline:none}
        .rf-card.is-live{border-color:#86efac;background:#fff}
        .rf-card--featured{grid-column:1/-1;display:grid;grid-template-columns:minmax(220px,340px) 1fr;border-width:2px;border-color:#22c55e;box-shadow:0 8px 24px rgba(22,163,74,.12)}
        .rf-card--featured:hover,.rf-card--featured:focus-visible{border-color:#16a34a;box-shadow:0 10px 28px rgba(22,163,74,.16)}
        .rf-card-media{display:block;width:100%;aspect-ratio:16/10;background:linear-gradient(135deg,#ecfdf5 0%,#f1f5f9 100%);overflow:hidden}
        .rf-card-media img{display:block;width:100%;height:100%;object-fit:cover}
        .rf-card-body{padding:1rem 1.1rem;display:flex;flex-direction:column;gap:.35rem;flex:1}
        .rf-card--featured .rf-card-body{padding:1.25rem 1.35rem;justify-content:center}
        .rf-card-title{margin:0;font-size:1.05rem;font-weight:800;color:#111827}
        .rf-card--featured .rf-card-title{font-size:clamp(1.15rem,2vw,1.45rem)}
        .rf-card-timing{margin:0;font-size:.85rem;color:#64748b}
        .rf-card-desc{margin:0;font-size:.82rem;line-height:1.45;color:#475569}
        .rf-card--compact .rf-card-desc{display:none}
        .rf-hero-banner{display:block;width:100%;max-height:280px;border-radius:12px;margin:0 0 1.25rem;overflow:hidden;background:linear-gradient(135deg,#ecfdf5 0%,#f1f5f9 100%)}
        .rf-hero-banner img{display:block;width:100%;height:100%;max-height:280px;object-fit:cover}
        .rf-related-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:.75rem}
        .rf-winners-table{width:100%;border-collapse:collapse;font-size:.875rem}
        .rf-winners-table th,.rf-winners-table td{padding:.6rem .75rem;border-bottom:1px solid #e2e8f0;text-align:left}
        .rf-winners-table th{background:#f8fafc;font-weight:600}
        .rf-breadcrumb{margin:0 0 1rem;font-size:.875rem;color:#64748b}
        .rf-breadcrumb a{color:#15803d;font-weight:600;text-decoration:none}
        .rf-breadcrumb a:hover{text-decoration:underline}
        @media (max-width:900px){.rf-grid--index{grid-template-columns:1fr}.rf-card--featured{grid-template-columns:1fr}}
        @media (min-width:901px) and (max-width:1100px){.rf-grid--index{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media (min-width:1100px){.rf-grid--index:not(:has(.rf-card--featured)){grid-template-columns:repeat(4,minmax(0,1fr))}}
        ';
        wp_register_style('bricks-racing-festivals', false);
        wp_enqueue_style('bricks-racing-festivals');
        wp_add_inline_style('bricks-racing-festivals', $css);
    }
}

if (!function_exists('bricks_festival_render_status_badge')) {
    function bricks_festival_render_status_badge(array $occurrence, $include_secondary = true) {
        $status = $occurrence['status'] ?? 'upcoming';
        $cls = $status === 'live' ? 'is-live' : 'is-upcoming';
        $html = '<span class="rf-badge ' . esc_attr($cls) . '">' . esc_html(bricks_festival_countdown_label($occurrence)) . '</span>';

        if ($include_secondary) {
            $secondary = bricks_festival_secondary_status_label($occurrence);
            if ($secondary !== '') {
                $html .= '<span class="rf-badge is-off">' . esc_html($secondary) . '</span>';
            }
        }

        return $html;
    }
}

if (!function_exists('bricks_festival_render_card')) {
    /**
     * Shared festival card for index and related hubs.
     *
     * @param array<string, mixed> $def
     * @param array{featured?:bool,compact?:bool} $opts
     */
    function bricks_festival_render_card(array $def, array $opts = []) {
        $occ = bricks_festival_resolve_occurrence($def);
        $is_live = ($occ['status'] ?? '') === 'live';
        $featured = !empty($opts['featured']) || $is_live;
        $compact = !empty($opts['compact']);
        $image = bricks_festival_get_image_url($def, 'card');

        $classes = ['rf-card'];
        if ($is_live) {
            $classes[] = 'is-live';
        }
        if ($featured) {
            $classes[] = 'rf-card--featured';
        }
        if ($compact) {
            $classes[] = 'rf-card--compact';
        }

        ob_start();
        ?>
        <a href="<?php echo esc_url(bricks_festival_url($def['slug'])); ?>" class="<?php echo esc_attr(implode(' ', $classes)); ?>">
            <?php if ($image !== ''): ?>
                <span class="rf-card-media">
                    <img src="<?php echo esc_url($image); ?>" alt="" loading="lazy" />
                </span>
            <?php elseif ($featured): ?>
                <span class="rf-card-media" aria-hidden="true"></span>
            <?php endif; ?>
            <span class="rf-card-body">
                <span class="rf-badge-row"><?php echo bricks_festival_render_status_badge($occ); ?></span>
                <h3 class="rf-card-title"><?php echo esc_html($def['name']); ?></h3>
                <p class="rf-card-timing"><?php echo esc_html($def['typical_timing'] ?? ''); ?> · <?php echo esc_html(bricks_festival_format_window_label($occ)); ?></p>
                <?php if (!$compact): ?>
                    <p class="rf-card-desc"><?php echo esc_html(wp_trim_words($def['lead'] ?? '', $featured ? 28 : 18)); ?></p>
                <?php endif; ?>
            </span>
        </a>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('bricks_racing_festivals_index_shortcode')) {
    function bricks_racing_festivals_index_shortcode() {
        bricks_festival_enqueue_styles();

        $festivals = bricks_festival_sort_by_next_start(bricks_festival_definitions());
        $live_slug = '';
        foreach ($festivals as $def) {
            $occ = bricks_festival_resolve_occurrence($def);
            if (($occ['status'] ?? '') === 'live') {
                $live_slug = $def['slug'] ?? '';
                break;
            }
        }

        ob_start();
        ?>
        <div class="racing-festivals-page" id="racing-festivals-index">
            <header class="rf-hero">
                <h1 class="rf-title">UK &amp; Irish Racing Festivals</h1>
                <p class="rf-lead">
                    Seasonal Fhorsite ratings hubs for the biggest meetings on the calendar — Cheltenham Festival, Grand National, Royal Ascot, and Galway — with racecourse guides, key races, and Points Engine history.
                </p>
            </header>

            <div class="rf-grid rf-grid--index">
                <?php foreach ($festivals as $def): ?>
                    <?php
                    $is_live = ($def['slug'] ?? '') === $live_slug && $live_slug !== '';
                    echo bricks_festival_render_card($def, ['featured' => $is_live]);
                    ?>
                <?php endforeach; ?>
            </div>

            <section class="rf-section">
                <h2>Racecourses directory</h2>
                <p class="rf-lead" style="margin-bottom:.75rem;">Permanent course guides with draw bias, speed ratings, and live cards.</p>
                <a class="rf-btn rf-btn-secondary" href="<?php echo esc_url(function_exists('bricks_track_directory_url') ? bricks_track_directory_url() : home_url('/racecourses/')); ?>">Browse all racecourses</a>
            </section>
        </div>
        <?php
        return ob_get_clean();
    }
}
add_shortcode('racing_festivals_index', 'bricks_racing_festivals_index_shortcode');

if (!function_exists('bricks_racing_festival_hub_shortcode')) {
    function bricks_racing_festival_hub_shortcode($atts = []) {
        $atts = shortcode_atts(['slug' => ''], $atts, 'racing_festival_hub');

        bricks_festival_enqueue_styles();
        if (function_exists('bricks_track_enqueue_styles')) {
            bricks_track_enqueue_styles();
        }

        $slug = sanitize_title((string) $atts['slug']);
        if ($slug === '' && get_query_var('festival_slug')) {
            $slug = sanitize_title((string) get_query_var('festival_slug'));
        }

        $festival = bricks_festival_resolve_slug($slug);
        if (!$festival) {
            return '<div class="racecourse-guide-error">Festival hub not found.</div>';
        }

        $occ = bricks_festival_resolve_occurrence($festival);
        $winners = bricks_festival_get_venue_winners($festival, 10);
        $course_url = function_exists('bricks_track_url')
            ? bricks_track_url($festival['course_slug'] ?? $festival['course'])
            : home_url('/racecourses/' . sanitize_title($festival['course_slug'] ?? '') . '/');
        $region_url = function_exists('bricks_track_region_url') && !empty($festival['region'])
            ? bricks_track_region_url($festival['region'])
            : '';
        $show_live_card = ($occ['status'] === 'live' || ($occ['status'] === 'upcoming' && intval($occ['days_until'] ?? 999) <= 7))
            && function_exists('bricks_track_has_meeting_today')
            && bricks_track_has_meeting_today($festival['course'] ?? '');
        $race_cards_cta = bricks_festival_race_cards_cta($festival, $occ, !empty($winners));
        $hero_image = bricks_festival_get_image_url($festival, 'hero');

        ob_start();
        ?>
        <div class="racing-festivals-page" id="racing-festival-hub">
            <nav class="rf-breadcrumb" aria-label="Breadcrumb">
                <a href="<?php echo esc_url(bricks_festival_url()); ?>">Racing festivals</a>
                <span aria-hidden="true"> › </span>
                <span><?php echo esc_html($festival['short_name'] ?? $festival['name']); ?></span>
            </nav>

            <?php if ($hero_image !== ''): ?>
                <div class="rf-hero-banner">
                    <img src="<?php echo esc_url($hero_image); ?>" alt="" loading="eager" />
                </div>
            <?php endif; ?>

            <header class="rf-hero">
                <div class="rf-badge-row"><?php echo bricks_festival_render_status_badge($occ); ?></div>
                <h1 class="rf-title"><?php echo esc_html($festival['name']); ?> — Fhorsite Ratings Hub</h1>
                <p class="rf-lead"><?php echo esc_html($festival['lead'] ?? ''); ?></p>
                <div class="rf-meta">
                    <span>📅 <?php echo esc_html(bricks_festival_format_window_label($occ)); ?></span>
                    <span>📍 <?php echo esc_html($festival['course'] ?? ''); ?><?php echo !empty($festival['country']) ? ', ' . esc_html($festival['country']) : ''; ?></span>
                    <?php if (($occ['status'] ?? '') !== 'live' && intval($occ['days_until'] ?? 0) > 0): ?>
                        <span>⏳ <?php echo esc_html(bricks_festival_countdown_label($occ)); ?></span>
                    <?php endif; ?>
                </div>
                <div class="rf-actions">
                    <a class="rf-btn rf-btn-primary" href="<?php echo esc_url($course_url); ?>"><?php echo esc_html($festival['course']); ?> racecourse guide</a>
                    <?php if ($race_cards_cta && !empty($race_cards_cta['show'])): ?>
                        <?php $cta_url = (string) $race_cards_cta['url']; ?>
                        <a class="rf-btn rf-btn-secondary" href="<?php echo ($cta_url !== '' && $cta_url[0] === '#') ? esc_attr($cta_url) : esc_url($cta_url); ?>"><?php echo esc_html($race_cards_cta['label']); ?></a>
                    <?php endif; ?>
                    <?php if ($region_url !== ''): ?>
                        <a class="rf-btn rf-btn-secondary" href="<?php echo esc_url($region_url); ?>">More <?php echo esc_html($festival['region'] === 'ireland' ? 'Irish' : 'UK'); ?> racecourses</a>
                    <?php endif; ?>
                    <?php if (!empty($festival['case_study_url'])): ?>
                        <a class="rf-btn rf-btn-secondary" href="<?php echo esc_url($festival['case_study_url']); ?>"><?php echo esc_html($festival['case_study_label'] ?? 'Festival case study'); ?></a>
                    <?php endif; ?>
                </div>
            </header>

            <?php if (!empty($festival['highlight_races'])): ?>
            <section class="rf-section" aria-labelledby="rf-key-races">
                <h2 id="rf-key-races">Key races</h2>
                <ul class="rf-race-list">
                    <?php foreach ($festival['highlight_races'] as $race): ?>
                        <?php
                        $race_label = bricks_festival_format_race_date_label($race, $occ);
                        $race_url = bricks_festival_highlight_race_url($race, $festival, $occ);
                        ?>
                        <li>
                            <a class="rf-race-row" href="<?php echo esc_url($race_url); ?>">
                                <span class="rf-race-row-body">
                                    <strong><?php echo esc_html($race['name'] ?? ''); ?></strong>
                                    <span class="rf-race-row-date"><?php echo esc_html($race_label); ?></span>
                                </span>
                                <span class="rf-race-row-chevron" aria-hidden="true">›</span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>

            <?php if ($show_live_card): ?>
                <?php echo do_shortcode('[racecourse_guide_card course="' . esc_attr($festival['course']) . '" slug="' . esc_attr($festival['course_slug'] ?? '') . '" lock_course="1" hide_filters="1"]'); ?>
            <?php elseif (($occ['status'] ?? '') === 'live'): ?>
                <section class="rf-section">
                    <p class="rf-lead">Check <a href="<?php echo esc_url(home_url('/daily/')); ?>">today's race cards</a> for live Fhorsite ratings during <?php echo esc_html($festival['short_name']); ?> week.</p>
                </section>
            <?php endif; ?>

            <?php if (!empty($winners)): ?>
            <section class="rf-section" id="rf-winners" aria-labelledby="rf-winners-heading">
                <h2 id="rf-winners-heading">Fhorsite Points Engine winners at <?php echo esc_html($festival['course']); ?></h2>
                <div class="racecourse-guide-table-wrap">
                    <table class="rf-winners-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Horse</th>
                                <th>Race</th>
                                <th>SP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($winners as $row): ?>
                                <?php
                                $race_url = function_exists('bricks_race_url') && !empty($row->race_id)
                                    ? bricks_race_url(intval($row->race_id))
                                    : '';
                                $date_label = !empty($row->meeting_date)
                                    ? wp_date('j M Y', strtotime((string) $row->meeting_date))
                                    : '';
                                ?>
                                <tr>
                                    <td><?php echo esc_html($date_label); ?></td>
                                    <td><?php echo esc_html($row->win_horse ?? ''); ?></td>
                                    <td>
                                        <?php if ($race_url !== ''): ?>
                                            <a href="<?php echo esc_url($race_url); ?>"><?php echo esc_html($row->race_title ?? 'Race'); ?></a>
                                        <?php else: ?>
                                            <?php echo esc_html($row->race_title ?? 'Race'); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($row->starting_price ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php endif; ?>

            <section class="rf-section" aria-labelledby="rf-related">
                <h2 id="rf-related">Other festival hubs</h2>
                <div class="rf-related-grid">
                    <?php foreach (bricks_festival_definitions() as $other): ?>
                        <?php if (($other['slug'] ?? '') === ($festival['slug'] ?? '')) { continue; } ?>
                        <?php echo bricks_festival_render_card($other, ['compact' => true]); ?>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
        <?php
        return ob_get_clean();
    }
}
add_shortcode('racing_festival_hub', 'bricks_racing_festival_hub_shortcode');

if (!function_exists('bricks_festival_maybe_enqueue_styles')) {
    function bricks_festival_maybe_enqueue_styles() {
        if (
            get_query_var('festivals_index')
            || get_query_var('festival_slug')
            || (function_exists('bricks_current_post_has_shortcode') && bricks_current_post_has_shortcode(['racing_festivals_index', 'racing_festival_hub']))
        ) {
            bricks_festival_enqueue_styles();
        }
    }
}
add_action('wp_enqueue_scripts', 'bricks_festival_maybe_enqueue_styles', 26);

if (!function_exists('bricks_add_festival_rewrite_rules')) {
    function bricks_add_festival_rewrite_rules() {
        add_rewrite_tag('%festivals_index%', '([0-9]+)');
        add_rewrite_tag('%festival_slug%', '([a-z0-9-]+)');
        add_rewrite_rule('^festivals/?$', 'index.php?festivals_index=1', 'top');
        add_rewrite_rule('^festivals/([a-z0-9-]+)/?$', 'index.php?festival_slug=$matches[1]', 'top');
    }
}
add_action('init', 'bricks_add_festival_rewrite_rules', 20);

if (!function_exists('bricks_add_festival_query_vars')) {
    function bricks_add_festival_query_vars($vars) {
        $vars[] = 'festivals_index';
        $vars[] = 'festival_slug';
        return $vars;
    }
}
add_filter('query_vars', 'bricks_add_festival_query_vars');

if (!function_exists('bricks_festival_customize_virtual_post')) {
    function bricks_festival_customize_virtual_post() {
        if (!bricks_festival_is_request()) {
            return;
        }

        global $post, $wp_query;

        if (get_query_var('festivals_index')) {
            $title = 'UK & Irish Racing Festivals';
            $slug = 'festivals';
        } else {
            $fslug = sanitize_title((string) get_query_var('festival_slug'));
            $def = bricks_festival_resolve_slug($fslug);
            $title = $def ? ($def['name'] . ' — Fhorsite Ratings Hub') : 'Racing Festival';
            $slug = $def ? ($def['slug'] ?? $fslug) : $fslug;
        }

        $post = new WP_Post((object) [
            'ID' => 0,
            'post_title' => $title,
            'post_content' => '',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_name' => $slug,
        ]);
        $wp_query->post = $post;
        $wp_query->posts = [$post];
        $wp_query->post_count = 1;
        $wp_query->is_404 = false;
        $wp_query->is_page = true;
        $wp_query->is_singular = true;
        setup_postdata($post);
    }
}
add_action('wp', 'bricks_festival_customize_virtual_post', 10);

if (!function_exists('bricks_festival_get_bricks_content_template_id')) {
    function bricks_festival_get_bricks_content_template_id() {
        if (defined('BRICKS_FESTIVALS_TEMPLATE_CONTENT')) {
            return max(0, intval(constant('BRICKS_FESTIVALS_TEMPLATE_CONTENT')));
        }
        return max(0, intval(get_option('bricks_festivals_tpl_content', 0)));
    }
}

if (!function_exists('bricks_festival_filter_active_templates')) {
    function bricks_festival_filter_active_templates($active_templates, $post_id, $content_type) {
        if (!bricks_festival_is_request() || !is_array($active_templates)) {
            return $active_templates;
        }
        $tpl = bricks_festival_get_bricks_content_template_id();
        if ($tpl > 0 && get_post_status($tpl) === 'publish') {
            $active_templates['content'] = $tpl;
        }
        return $active_templates;
    }
}
add_filter('bricks/active_templates', 'bricks_festival_filter_active_templates', 23, 3);

if (!function_exists('bricks_festival_render_main_content')) {
    function bricks_festival_render_main_content() {
        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;

        $content_template_id = bricks_festival_get_bricks_content_template_id();
        if ($content_template_id > 0 && class_exists('\Bricks\Frontend')) {
            \Bricks\Frontend::render_content(get_the_ID(), 'content');
            return;
        }

        if (get_query_var('festivals_index')) {
            echo do_shortcode('[racing_festivals_index]');
            return;
        }

        $slug = sanitize_title((string) get_query_var('festival_slug'));
        if ($slug !== '' && bricks_festival_resolve_slug($slug)) {
            echo do_shortcode('[racing_festival_hub slug="' . esc_attr($slug) . '"]');
            return;
        }

        echo do_shortcode('[racing_festivals_index]');
    }
}

if (!function_exists('bricks_festival_template')) {
    function bricks_festival_template($template) {
        if (is_admin()) {
            return $template;
        }
        if (get_query_var('festivals_index') || get_query_var('festival_slug')) {
            $custom = get_stylesheet_directory() . '/festival-hub.php';
            if (file_exists($custom)) {
                return $custom;
            }
        }
        return $template;
    }
}
add_filter('template_include', 'bricks_festival_template');

if (!function_exists('bricks_flush_festival_rewrite_rules_if_needed')) {
    function bricks_flush_festival_rewrite_rules_if_needed() {
        if (get_option('bricks_festival_rewrite_flushed') !== '1') {
            flush_rewrite_rules();
            update_option('bricks_festival_rewrite_flushed', '1');
        }
    }
}
add_action('init', 'bricks_flush_festival_rewrite_rules_if_needed', 999);

if (!function_exists('bricks_festival_register_settings_page')) {
    function bricks_festival_register_settings_page() {
        add_options_page(
            'Racing Festivals',
            'Racing Festivals',
            'manage_options',
            'bricks-racing-festivals',
            'bricks_festival_render_settings_page'
        );
    }
}
add_action('admin_menu', 'bricks_festival_register_settings_page');

if (!function_exists('bricks_festival_render_settings_page')) {
    function bricks_festival_render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (isset($_POST['bricks_festivals_tpl_content']) && check_admin_referer('bricks_festival_settings')) {
            update_option('bricks_festivals_tpl_content', absint($_POST['bricks_festivals_tpl_content']));
            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }
        $tpl = bricks_festival_get_bricks_content_template_id();
        ?>
        <div class="wrap">
            <h1>Racing Festival Hubs</h1>
            <p>Virtual URLs: <code>/festivals/</code>, <code>/festivals/cheltenham/</code>, <code>/festivals/grand-national/</code>, <code>/festivals/royal-ascot/</code>, <code>/festivals/galway/</code></p>
            <form method="post">
                <?php wp_nonce_field('bricks_festival_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="bricks_festivals_tpl_content">Bricks content template ID</label></th>
                        <td>
                            <input type="number" name="bricks_festivals_tpl_content" id="bricks_festivals_tpl_content" value="<?php echo esc_attr($tpl); ?>" min="0" class="small-text" />
                            <p class="description">Optional. Use <code>[racing_festivals_index]</code> on index or <code>[racing_festival_hub]</code> on single hubs (slug auto-detected).</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save'); ?>
            </form>
            <h2>Link a blog case study or images</h2>
            <pre style="background:#f6f7f7;padding:12px;max-width:760px;overflow:auto;">add_filter('bricks_festival_definitions', function ($festivals) {
    $festivals['cheltenham']['case_study_url'] = 'https://fhor.site/blog/cheltenham-festival-2026-preview/';
    $festivals['cheltenham']['case_study_label'] = '2026 Cheltenham Festival preview';
    $festivals['cheltenham']['card_image_url'] = 'https://yoursite.com/wp-content/uploads/cheltenham-festival-card.jpg';
    $festivals['cheltenham']['hero_image_url'] = 'https://yoursite.com/wp-content/uploads/cheltenham-festival-hero.jpg';
    return $festivals;
});</pre>
        </div>
        <?php
    }
}

// -----------------------------------------------------------------------------
// Festival SEO
// -----------------------------------------------------------------------------

if (!function_exists('bricks_festival_build_meta_title')) {
    function bricks_festival_build_meta_title() {
        if (get_query_var('festivals_index')) {
            return 'UK & Irish Racing Festivals | Cheltenham, Grand National, Ascot, Galway | Fhorsite';
        }
        $def = bricks_festival_resolve_slug((string) get_query_var('festival_slug'));
        if (!$def) {
            return 'Racing Festival Hub | Fhorsite';
        }
        return sprintf('%s Ratings & Tips Hub | Fhorsite Speed Figures', $def['name']);
    }
}

if (!function_exists('bricks_festival_build_meta_description')) {
    function bricks_festival_build_meta_description() {
        if (get_query_var('festivals_index')) {
            return 'Seasonal Fhorsite ratings hubs for Cheltenham Festival, Grand National, Royal Ascot, and Galway — key races, racecourse guides, and Points Engine winners timed to the UK & Irish racing calendar.';
        }
        $def = bricks_festival_resolve_slug((string) get_query_var('festival_slug'));
        if (!$def) {
            return '';
        }
        $occ = bricks_festival_resolve_occurrence($def);
        return sprintf(
            '%s hub — %s at %s. Fhorsite speed ratings, key races, %s racecourse guide, and published Points Engine winners. %s.',
            $def['name'],
            bricks_festival_format_window_label($occ),
            $def['course'],
            $def['course'],
            wp_trim_words($def['lead'] ?? '', 28, '…')
        );
    }
}

if (!function_exists('bricks_festival_output_json_ld')) {
    function bricks_festival_output_json_ld() {
        static $done = false;
        if ($done || !bricks_festival_is_request()) {
            return;
        }
        $done = true;

        if (!function_exists('bricks_seo_print_json_ld')) {
            return;
        }

        if (get_query_var('festivals_index')) {
            $url = bricks_festival_url();
            $items = [];
            $pos = 1;
            foreach (bricks_festival_definitions() as $def) {
                $items[] = [
                    '@type' => 'ListItem',
                    'position' => $pos++,
                    'name' => $def['name'],
                    'url' => bricks_festival_url($def['slug']),
                ];
            }
            bricks_seo_print_json_ld([
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                '@id' => $url . '#collection',
                'name' => 'UK & Irish Racing Festivals',
                'description' => bricks_festival_build_meta_description(),
                'url' => $url,
                'mainEntity' => [
                    '@type' => 'ItemList',
                    'itemListElement' => $items,
                ],
            ]);
            return;
        }

        $def = bricks_festival_resolve_slug((string) get_query_var('festival_slug'));
        if (!$def) {
            return;
        }

        $url = bricks_festival_url($def['slug']);
        $occ = bricks_festival_resolve_occurrence($def);
        $course_url = function_exists('bricks_track_url')
            ? bricks_track_url($def['course_slug'] ?? '')
            : home_url('/racecourses/' . sanitize_title($def['course_slug'] ?? '') . '/');

        bricks_seo_print_json_ld([
            [
                '@context' => 'https://schema.org',
                '@type' => 'Event',
                '@id' => $url . '#event',
                'name' => $def['name'],
                'description' => $def['lead'] ?? '',
                'url' => $url,
                'startDate' => $occ['start'],
                'endDate' => $occ['end'],
                'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                'eventStatus' => ($occ['status'] === 'live')
                    ? 'https://schema.org/EventScheduled'
                    : 'https://schema.org/EventScheduled',
                'location' => [
                    '@type' => 'SportsActivityLocation',
                    'name' => $def['course'],
                    'url' => $course_url,
                ],
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Racing festivals', 'item' => bricks_festival_url()],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => $def['name'], 'item' => $url],
                ],
            ],
        ]);
    }
}
add_action('wp_head', 'bricks_festival_output_json_ld', 6);

add_filter('slim_seo_meta_title', function ($title) {
    if (!bricks_festival_is_request()) {
        return $title;
    }
    return bricks_festival_build_meta_title();
}, 27);

add_filter('slim_seo_meta_description', function ($description) {
    if (!bricks_festival_is_request()) {
        return $description;
    }
    $built = bricks_festival_build_meta_description();
    return $built !== '' ? $built : $description;
}, 27);

add_filter('pre_get_document_title', function ($title) {
    if (!bricks_festival_is_request()) {
        return $title;
    }
    return bricks_festival_build_meta_title();
}, 27);

if (!function_exists('bricks_add_festivals_menu_item')) {
    function bricks_add_festivals_menu_item($items, $args) {
        if (is_admin()) {
            return $items;
        }
        $url = bricks_festival_url();
        if (strpos($items, $url) !== false || strpos($items, '/festivals') !== false) {
            return $items;
        }
        $current = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $active = (strpos($current, '/festivals') !== false) ? ' current-menu-item current_page_item' : '';
        $items .= '<li class="menu-item menu-item-type-custom menu-item-racing-festivals' . esc_attr($active) . '">'
            . '<a href="' . esc_url($url) . '">Racing Festivals</a>'
            . '</li>';
        return $items;
    }
}
add_filter('wp_nav_menu_items', 'bricks_add_festivals_menu_item', 22, 2);
