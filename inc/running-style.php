<?php
/**
 * Horse Running Style Classification Engine & Competitors Pace Card.
 *
 * Parses historic in-race comments into career running-style rates:
 *   leader, closeup, chaser, heldup, midi, lagger
 * plus net_leader_score = (leader + closeup) - (heldup + midi + lagger).
 *
 * Shortcode: [competitors_pace_card race_id="12345"]
 * (race_id also resolved from the race detail URL / query var)
 */

if (!defined('ABSPATH')) {
    exit;
}

// -----------------------------------------------------------------------------
// Keyword dictionary (case-insensitive; longer phrases matched first)
// -----------------------------------------------------------------------------

if (!function_exists('bricks_running_style_keywords')) {
    /**
     * @return array<string, string[]> category => keyword phrases
     */
    function bricks_running_style_keywords() {
        return (array) apply_filters('running_style_keywords', [
            'leader' => [
                'made all the running',
                'made all',
                'made the running',
                'made running',
                'set a strong pace',
                'set the pace',
                'set pace',
                'dictated',
                'went clear',
                'soon led',
                'quickly led',
                'led throughout',
                'led until',
                'led early',
                'led',
                'ld',
            ],
            'closeup' => [
                'pressed the leader',
                'pressed leader',
                'pressed leaders',
                'pressed pace',
                'tracked the leader',
                'tracked leader',
                'disputed lead',
                'with leaders',
                'close up',
                'closeup',
                'handy',
                'racing prominently',
                'raced prominently',
                'raced prominent',
                'prominent',
                'prom',
            ],
            'chaser' => [
                'chased the leaders',
                'chased leaders',
                'chased the leader',
                'chased leader',
                'tracked the leaders',
                'tracked leaders',
                'in touch with leaders',
                'just behind leaders',
                'chsd lds',
                'chsd ld',
            ],
            'heldup' => [
                'held up in touch',
                'held up behind',
                'held up towards',
                'held up in midfield',
                'settled in midfield',
                'settled midfield',
                'settled towards rear',
                'dropped in',
                'held up',
                'hld up',
                'waited with',
            ],
            'midi' => [
                'raced in mid-division',
                'raced mid-division',
                'in mid-division',
                'mid-division',
                'mid division',
                'raced midfield',
                'in midfield',
                'in mid-pack',
                'mid-pack',
                'mid-div',
                'midfield',
            ],
            'lagger' => [
                'held up in rear',
                'held up last',
                'always in rear',
                'always towards rear',
                'always behind',
                'towards rear',
                'in rear',
                'last away',
                'slowly away',
                'slwly awy',
                'very slowly away',
                'badly away',
                'missed break',
                'lost many lengths',
                'detached',
                'tailed off',
                'dwelt badly',
                'dwelt',
                'outpaced early',
                'reared start',
            ],
        ]);
    }
}

if (!function_exists('bricks_running_style_skip_patterns')) {
    /**
     * Comments matching these (case-insensitive) are excluded from career runs.
     *
     * @return string[]
     */
    function bricks_running_style_skip_patterns() {
        return (array) apply_filters('running_style_skip_patterns', [
            'withdrawn',
            'non-runner',
            'non runner',
            'nonrunner',
            'refused to race',
            'left at start',
            'took no part',
        ]);
    }
}

if (!function_exists('bricks_running_style_negation_patterns')) {
    /**
     * Category => phrases that cancel a positive keyword hit in the same fragment.
     *
     * @return array<string, string[]>
     */
    function bricks_running_style_negation_patterns() {
        return (array) apply_filters('running_style_negation_patterns', [
            'leader' => [
                'never led',
                'not led',
                'unable to lead',
                'failed to lead',
                'could not lead',
                'no chance to lead',
            ],
            'closeup' => [
                'never prominent',
                'not prominent',
                'never close up',
                'never closeup',
            ],
            'chaser' => [
                'never chased',
                'not chased',
            ],
            'heldup' => [
                'never held up',
                'not held up',
            ],
            'midi' => [
                'never midfield',
                'not mid-division',
            ],
            'lagger' => [
                'never in rear',
                'not in rear',
            ],
        ]);
    }
}

if (!function_exists('bricks_running_style_abbreviation_map')) {
    /**
     * Expand common Racing Post abbreviations before keyword matching.
     *
     * @return array<string, string> pattern => replacement
     */
    function bricks_running_style_abbreviation_map() {
        return (array) apply_filters('running_style_abbreviation_map', [
            '/\bhld\s*up\b/u' => 'held up',
            '/\bheldup\b/u' => 'held up',
            '/\bchsd\s+lds\b/u' => 'chased leaders',
            '/\bchsd\s+ld\b/u' => 'chased leader',
            '/\btrkd\s+lds\b/u' => 'tracked leaders',
            '/\btrkd\s+ld\b/u' => 'tracked leader',
            '/\btrk\s+ld\b/u' => 'tracked leader',
            '/\bprs\s+ld\b/u' => 'pressed leader',
            '/\bprs\s+lds\b/u' => 'pressed leaders',
            '/\bslwly\s+awy\b/u' => 'slowly away',
            '/\bslowly\s+awy\b/u' => 'slowly away',
            '/\bmid\s*div\b/u' => 'mid-division',
            '/\bmiddiv\b/u' => 'mid-division',
            '/\bmid\s*field\b/u' => 'midfield',
            '/\bin\s+rr\b/u' => 'in rear',
            '/\btwd\s+rr\b/u' => 'towards rear',
            '/\bprom\b/u' => 'prominent',
            '/\bcloseup\b/u' => 'close up',
        ]);
    }
}

// -----------------------------------------------------------------------------
// Classification
// -----------------------------------------------------------------------------

if (!function_exists('bricks_running_style_normalize_comment')) {
    function bricks_running_style_normalize_comment($comment) {
        $text = strtolower(trim((string) $comment));
        $text = str_replace(["\r", "\n", "\t"], ' ', $text);
        // Normalize punctuation so "led," / "led;" still tokenise cleanly.
        $text = str_replace([';', ';', ':', '/', '\\', '(', ')', '[', ']', '"', "'"], ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim((string) $text);

        foreach (bricks_running_style_abbreviation_map() as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }

        return preg_replace('/\s+/', ' ', trim((string) $text));
    }
}

if (!function_exists('bricks_running_style_is_skipped')) {
    function bricks_running_style_is_skipped($comment) {
        $text = bricks_running_style_normalize_comment($comment);
        if ($text === '') {
            return true;
        }
        foreach (bricks_running_style_skip_patterns() as $pattern) {
            $pattern = bricks_running_style_normalize_comment($pattern);
            if ($pattern !== '' && strpos($text, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('bricks_running_style_keyword_matches')) {
    /**
     * Always use word-boundary matching so short tokens stay precise.
     */
    function bricks_running_style_keyword_matches($haystack, $keyword) {
        $keyword = strtolower(trim((string) $keyword));
        if ($keyword === '' || $haystack === '') {
            return false;
        }

        $parts = preg_split('/\s+/', $keyword);
        $escaped = array_map(function ($part) {
            return preg_quote($part, '/');
        }, $parts);

        // Require the full phrase as contiguous tokens.
        $pattern = '/\b' . implode('\s+', $escaped) . '\b/u';
        return (bool) preg_match($pattern, $haystack);
    }
}

if (!function_exists('bricks_running_style_apply_negations')) {
    /**
     * Drop category hits cancelled by negation phrases in the same fragment.
     *
     * @param array<string,bool> $hits
     * @param string $text
     * @return array<string,bool>
     */
    function bricks_running_style_apply_negations(array $hits, $text) {
        if (empty($hits)) {
            return $hits;
        }

        foreach (bricks_running_style_negation_patterns() as $category => $patterns) {
            if (empty($hits[$category])) {
                continue;
            }
            foreach ((array) $patterns as $pattern) {
                $pattern = bricks_running_style_normalize_comment($pattern);
                if ($pattern !== '' && bricks_running_style_keyword_matches($text, $pattern)) {
                    unset($hits[$category]);
                    break;
                }
            }
        }

        return $hits;
    }
}

if (!function_exists('bricks_running_style_classify_fragment')) {
    /**
     * Classify one comment fragment (usually a comma-separated clause).
     *
     * @return array<string,bool>
     */
    function bricks_running_style_classify_fragment($fragment) {
        $original = bricks_running_style_normalize_comment($fragment);
        if ($original === '') {
            return [];
        }

        $text = $original;
        $keywords = bricks_running_style_keywords();

        // Flatten + sort longest-first so "held up in rear" claims before "held up".
        $flat = [];
        foreach ($keywords as $category => $phrases) {
            foreach ((array) $phrases as $phrase) {
                $phrase = bricks_running_style_normalize_comment($phrase);
                if ($phrase === '') {
                    continue;
                }
                $flat[] = [$category, $phrase, strlen($phrase)];
            }
        }
        usort($flat, function ($a, $b) {
            if ($a[2] === $b[2]) {
                return strcmp($a[1], $b[1]);
            }
            return $b[2] <=> $a[2];
        });

        $hits = [];
        foreach ($flat as [$category, $phrase]) {
            if (bricks_running_style_keyword_matches($text, $phrase)) {
                $hits[$category] = true;
                // Remove matched phrase so shorter overlapping keywords don't also fire.
                $parts = preg_split('/\s+/', $phrase);
                $escaped = array_map(function ($part) {
                    return preg_quote($part, '/');
                }, $parts);
                $text = preg_replace(
                    '/\b' . implode('\s+', $escaped) . '\b/u',
                    ' ',
                    $text
                );
                $text = preg_replace('/\s+/', ' ', trim((string) $text));
            }
        }

        return bricks_running_style_apply_negations($hits, $original);
    }
}

if (!function_exists('bricks_running_style_early_window')) {
    /**
     * Prefer the early part of a comment (where running style usually lives).
     * Keeps enough text for multi-clause early descriptions.
     */
    function bricks_running_style_early_window($comment) {
        $text = bricks_running_style_normalize_comment($comment);
        if ($text === '') {
            return '';
        }

        // Cut at common late-race outcome markers if they appear later.
        $cutters = [
            ' weakened',
            ' faded',
            ' no extra',
            ' one paced',
            ' kept on',
            ' stayed on',
            ' no impression',
            ' never dangerous',
            ' finished',
        ];
        $cut_at = null;
        foreach ($cutters as $cutter) {
            $pos = strpos($text, $cutter);
            if ($pos !== false && $pos > 12) {
                $cut_at = ($cut_at === null) ? $pos : min($cut_at, $pos);
            }
        }
        if ($cut_at !== null) {
            $text = trim(substr($text, 0, $cut_at));
        }

        // Soft cap: first ~18 words still covers most early-position notes.
        $words = preg_split('/\s+/', $text);
        if (count($words) > 18) {
            $text = implode(' ', array_slice($words, 0, 18));
        }

        return $text;
    }
}

if (!function_exists('bricks_running_style_classify_comment')) {
    /**
     * Classify a single in-race comment.
     *
     * Splits on commas (Racing Post style) so each clause can contribute
     * independently — matching the Bucklow Hill multi-tag test case.
     *
     * @return array<string,bool>|null  hit map, or null if the row is skipped
     */
    function bricks_running_style_classify_comment($comment) {
        if (bricks_running_style_is_skipped($comment)) {
            return null;
        }

        $window = bricks_running_style_early_window($comment);
        if ($window === '') {
            return [];
        }

        // Split into clauses, but also classify the joined early window once
        // so multi-word phrases spanning soft punctuation still match.
        $fragments = preg_split('/\s*(?:,| and )\s*/u', $window);
        $fragments[] = $window;

        $hits = [];
        foreach ($fragments as $fragment) {
            $fragment = trim((string) $fragment);
            if ($fragment === '') {
                continue;
            }
            foreach (bricks_running_style_classify_fragment($fragment) as $category => $on) {
                if ($on) {
                    $hits[$category] = true;
                }
            }
        }

        // Mutual exclusion sharpening: rear/lagger should not also count as
        // generic held-up when the more specific rear language won.
        if (!empty($hits['lagger']) && !empty($hits['heldup'])) {
            unset($hits['heldup']);
        }
        // Midfield settlement phrases that also tripped midi stay as heldup+midi
        // only when both genuinely appear; no extra pruning needed.

        return $hits;
    }
}

if (!function_exists('bricks_running_style_empty_counts')) {
    function bricks_running_style_empty_counts() {
        return [
            'runs'    => 0,
            'leader'  => 0,
            'closeup' => 0,
            'chaser'  => 0,
            'heldup'  => 0,
            'midi'    => 0,
            'lagger'  => 0,
        ];
    }
}

if (!function_exists('bricks_running_style_run_weight')) {
    /**
     * Recency weight for the Nth most-recent completed run (0 = latest).
     * Last 5 runs carry the most influence; older form still counts.
     */
    function bricks_running_style_run_weight($index) {
        $index = max(0, intval($index));
        if ($index < 5) {
            return 3.0;
        }
        if ($index < 10) {
            return 2.0;
        }
        return 1.0;
    }
}

if (!function_exists('bricks_running_style_aggregate_comments')) {
    /**
     * Aggregate category totals from comments (newest-first preferred).
     *
     * Returns both integer hit counts (for transparency / runs) and weighted
     * sums used for sharper rate calculation.
     *
     * @param string[] $comments newest first when available
     * @return array{runs:int,leader:int,closeup:int,chaser:int,heldup:int,midi:int,lagger:int,weight_sum:float,w_leader:float,w_closeup:float,w_chaser:float,w_heldup:float,w_midi:float,w_lagger:float}
     */
    function bricks_running_style_aggregate_comments(array $comments) {
        $counts = bricks_running_style_empty_counts();
        $counts['weight_sum'] = 0.0;
        foreach (['leader', 'closeup', 'chaser', 'heldup', 'midi', 'lagger'] as $cat) {
            $counts['w_' . $cat] = 0.0;
        }

        $completed_index = 0;
        foreach ($comments as $comment) {
            $hits = bricks_running_style_classify_comment($comment);
            if ($hits === null) {
                continue;
            }

            $counts['runs']++;
            $weight = bricks_running_style_run_weight($completed_index);
            $counts['weight_sum'] += $weight;
            $completed_index++;

            foreach (['leader', 'closeup', 'chaser', 'heldup', 'midi', 'lagger'] as $cat) {
                if (!empty($hits[$cat])) {
                    $counts[$cat]++;
                    $counts['w_' . $cat] += $weight;
                }
            }
        }

        return $counts;
    }
}

if (!function_exists('bricks_running_style_rates_from_counts')) {
    /**
     * Convert aggregates into career rates + net_leader_score.
     *
     * Rates use recency-weighted sums when available; falls back to raw counts.
     *
     * @param array $counts
     * @return array{
     *   runs:int,
     *   leader:float|null, closeup:float|null, chaser:float|null,
     *   heldup:float|null, midi:float|null, lagger:float|null,
     *   net_leader_score:float|null,
     *   available:bool
     * }
     */
    function bricks_running_style_rates_from_counts(array $counts) {
        $runs = intval($counts['runs'] ?? 0);
        $cats = ['leader', 'closeup', 'chaser', 'heldup', 'midi', 'lagger'];

        if ($runs <= 0) {
            $out = ['runs' => 0, 'net_leader_score' => null, 'available' => false];
            foreach ($cats as $cat) {
                $out[$cat] = null;
            }
            return $out;
        }

        $weight_sum = floatval($counts['weight_sum'] ?? 0);
        $use_weighted = $weight_sum > 0;

        $rates = ['runs' => $runs, 'available' => true];
        foreach ($cats as $cat) {
            if ($use_weighted) {
                $rates[$cat] = round(floatval($counts['w_' . $cat] ?? 0) / $weight_sum, 2);
            } else {
                $rates[$cat] = round(intval($counts[$cat] ?? 0) / $runs, 2);
            }
        }
        $rates['net_leader_score'] = round(
            ($rates['leader'] + $rates['closeup'])
            - ($rates['heldup'] + $rates['midi'] + $rates['lagger']),
            2
        );

        return $rates;
    }
}

// -----------------------------------------------------------------------------
// Historic comment fetch (career)
// -----------------------------------------------------------------------------

if (!function_exists('bricks_running_style_fetch_comments_by_runners')) {
    /**
     * Fetch historic in-race comments for a set of runner_ids.
     *
     * Prefers historic_runners_beta; falls back to daily_comment_history.
     * Comments are returned newest-first for recency weighting.
     *
     * @param int[] $runner_ids
     * @return array<int, array{comments:string[], avg_btn:float|null}>
     */
    function bricks_running_style_fetch_comments_by_runners(array $runner_ids) {
        global $wpdb;

        $runner_ids = array_values(array_unique(array_filter(array_map('intval', $runner_ids))));
        if (empty($runner_ids)) {
            return [];
        }

        sort($runner_ids);
        // Bump version whenever classification / weighting rules change.
        $cache_key = 'bricks_runstyle_cmt_v3_' . md5(implode(',', $runner_ids));
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $placeholders = implode(',', array_fill(0, count($runner_ids), '%d'));
        $out = [];
        foreach ($runner_ids as $rid) {
            $out[$rid] = ['comments' => [], 'avg_btn' => null];
        }

        $rows = [];
        $table = 'historic_runners_beta';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists === $table) {
            $races_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', 'historic_races_beta'));
            if ($races_exists === 'historic_races_beta') {
                $sql = "SELECT hrunb.runner_id, hrunb.in_race_comment, hrunb.distance_beaten,
                               hracb.meeting_date
                        FROM `historic_runners_beta` hrunb
                        LEFT JOIN `historic_races_beta` hracb ON hracb.race_id = hrunb.race_id
                        WHERE hrunb.runner_id IN ($placeholders)
                          AND hrunb.in_race_comment IS NOT NULL
                          AND hrunb.in_race_comment != ''
                        ORDER BY hrunb.runner_id ASC, hracb.meeting_date DESC, hrunb.race_id DESC";
            } else {
                $sql = "SELECT runner_id, in_race_comment, distance_beaten, NULL AS meeting_date
                        FROM `$table`
                        WHERE runner_id IN ($placeholders)
                          AND in_race_comment IS NOT NULL
                          AND in_race_comment != ''";
            }
            $rows = $wpdb->get_results($wpdb->prepare($sql, ...$runner_ids));
        } else {
            $table = 'daily_comment_history';
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($exists !== $table) {
                set_transient($cache_key, $out, 15 * MINUTE_IN_SECONDS);
                return $out;
            }
            $sql = "SELECT runner_id, in_race_comment, distance_beaten, meeting_date
                    FROM `$table`
                    WHERE runner_id IN ($placeholders)
                      AND in_race_comment IS NOT NULL
                      AND in_race_comment != ''
                    ORDER BY runner_id ASC, meeting_date DESC, race_id DESC";
            $rows = $wpdb->get_results($wpdb->prepare($sql, ...$runner_ids));
        }

        $btn_sums = [];
        $btn_ns = [];
        foreach ((array) $rows as $row) {
            $rid = intval($row->runner_id ?? 0);
            if ($rid <= 0 || !isset($out[$rid])) {
                continue;
            }
            $comment = (string) ($row->in_race_comment ?? '');
            if ($comment === '') {
                continue;
            }
            $out[$rid]['comments'][] = $comment;

            if (isset($row->distance_beaten) && $row->distance_beaten !== null && $row->distance_beaten !== '' && is_numeric($row->distance_beaten)) {
                $btn_sums[$rid] = ($btn_sums[$rid] ?? 0.0) + floatval($row->distance_beaten);
                $btn_ns[$rid] = ($btn_ns[$rid] ?? 0) + 1;
            }
        }

        foreach ($btn_ns as $rid => $n) {
            if ($n > 0) {
                $out[$rid]['avg_btn'] = round($btn_sums[$rid] / $n, 2);
            }
        }

        set_transient($cache_key, $out, 30 * MINUTE_IN_SECONDS);
        return $out;
    }
}

if (!function_exists('bricks_running_style_profiles_for_runners')) {
    /**
     * Build style rate profiles keyed by runner_id.
     *
     * @param int[] $runner_ids
     * @return array<int, array>
     */
    function bricks_running_style_profiles_for_runners(array $runner_ids) {
        $by_runner = bricks_running_style_fetch_comments_by_runners($runner_ids);
        $profiles = [];

        foreach ($by_runner as $rid => $payload) {
            $counts = bricks_running_style_aggregate_comments($payload['comments']);
            $rates = bricks_running_style_rates_from_counts($counts);
            $rates['avg_btn'] = $payload['avg_btn'];
            $rates['counts'] = $counts;
            $profiles[intval($rid)] = $rates;
        }

        return $profiles;
    }
}

if (!function_exists('bricks_running_style_attach_to_runners')) {
    /**
     * Attach style_* properties onto runner objects (mutates in place).
     *
     * @param array<int,object> $runners
     * @return array<int,object>
     */
    function bricks_running_style_attach_to_runners(array $runners) {
        $ids = [];
        foreach ($runners as $r) {
            $rid = intval($r->runner_id ?? 0);
            if ($rid > 0) {
                $ids[] = $rid;
            }
        }

        $profiles = bricks_running_style_profiles_for_runners($ids);

        foreach ($runners as $r) {
            $rid = intval($r->runner_id ?? 0);
            $profile = $profiles[$rid] ?? bricks_running_style_rates_from_counts(bricks_running_style_empty_counts());
            $r->style_runs = intval($profile['runs'] ?? 0);
            $r->style_leader = $profile['leader'];
            $r->style_closeup = $profile['closeup'];
            $r->style_chaser = $profile['chaser'];
            $r->style_heldup = $profile['heldup'];
            $r->style_midi = $profile['midi'];
            $r->style_lagger = $profile['lagger'];
            $r->style_net_leader_score = $profile['net_leader_score'];
            $r->style_available = !empty($profile['available']);
            $r->style_avg_btn = $profile['avg_btn'] ?? null;
        }

        return $runners;
    }
}

// -----------------------------------------------------------------------------
// Competitors Pace Card — data + render
// -----------------------------------------------------------------------------

if (!function_exists('bricks_competitors_pace_card_get_runners')) {
    /**
     * @return array<int,object>
     */
    function bricks_competitors_pace_card_get_runners($race_id) {
        if (!function_exists('bricks_pace_map_resolve_race') || !function_exists('bricks_pace_map_get_runners')) {
            return [];
        }

        $resolved = bricks_pace_map_resolve_race($race_id);
        if (!$resolved) {
            return [];
        }

        $runners = bricks_pace_map_get_runners($race_id, $resolved['speed_table']);
        if (empty($runners)) {
            return [];
        }

        // Pull gender / jockey / trainer / weight / age from speed table if present.
        // pace_map_get_runners already selects a subset; enrich if columns missing.
        global $wpdb;
        $speed_table = $resolved['speed_table'];
        $ids = array_map(function ($r) {
            return intval($r->runner_id ?? 0);
        }, $runners);
        $ids = array_values(array_filter($ids));
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $extra = $wpdb->get_results($wpdb->prepare(
                "SELECT `runner_id`, `gender`, `age`, `jockey_name`, `trainer_name`,
                        `weight_pounds`, `forecast_price`, `forecast_price_decimal`,
                        `days_since_ran`, `cloth_number`, `stall_number`, `name`
                 FROM `$speed_table`
                 WHERE `race_id` = %d AND `runner_id` IN ($placeholders)",
                $race_id,
                ...$ids
            ));
            $lookup = [];
            foreach ((array) $extra as $row) {
                $lookup[intval($row->runner_id)] = $row;
            }
            foreach ($runners as $r) {
                $rid = intval($r->runner_id ?? 0);
                if (!isset($lookup[$rid])) {
                    continue;
                }
                $src = $lookup[$rid];
                foreach (['gender', 'age', 'jockey_name', 'trainer_name', 'weight_pounds', 'forecast_price', 'forecast_price_decimal', 'days_since_ran', 'cloth_number', 'stall_number', 'name'] as $field) {
                    if ((!isset($r->{$field}) || $r->{$field} === null || $r->{$field} === '') && isset($src->{$field})) {
                        $r->{$field} = $src->{$field};
                    }
                }
            }
        }

        bricks_running_style_attach_to_runners($runners);

        usort($runners, function ($a, $b) {
            return intval($a->cloth_number ?? 0) <=> intval($b->cloth_number ?? 0);
        });

        return $runners;
    }
}

if (!function_exists('bricks_running_style_format_rate')) {
    function bricks_running_style_format_rate($value, $runs) {
        if (intval($runs) <= 0 || $value === null) {
            return 'n/a';
        }
        return number_format((float) $value, 2, '.', '');
    }
}

if (!function_exists('bricks_running_style_rate_cell_class')) {
    function bricks_running_style_rate_cell_class($value, $runs) {
        if (intval($runs) <= 0 || $value === null) {
            return 'cpc-na';
        }
        if ((float) $value >= 0.40) {
            return 'cpc-hot';
        }
        return '';
    }
}

if (!function_exists('bricks_running_style_format_weight')) {
    function bricks_running_style_format_weight($weight_pounds) {
        if ($weight_pounds === null || $weight_pounds === '' || !is_numeric($weight_pounds)) {
            return '—';
        }
        $lbs = intval($weight_pounds);
        $st = intdiv($lbs, 14);
        $rem = $lbs % 14;
        return $st . '-' . $rem;
    }
}

if (!function_exists('bricks_competitors_pace_card_styles')) {
    function bricks_competitors_pace_card_styles() {
        static $printed = false;
        if ($printed) {
            return '';
        }
        $printed = true;

        return '<style id="competitors-pace-card-styles">
        .cpc{margin:1.5rem 0 2rem;color:#0f172a;font-family:inherit}
        .cpc-head{margin:0 0 .75rem}
        .cpc-eyebrow{display:inline-block;font-size:.75rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:#64748b}
        .cpc-title{margin:.2rem 0 0;font-size:clamp(1.2rem,2.4vw,1.5rem);line-height:1.25}
        .cpc-sub{margin:.25rem 0 0;color:#64748b;font-size:.9rem}
        .cpc-wrap{overflow-x:auto;border:1px solid #e2e8f0;border-radius:12px;background:#fff}
        .cpc-table{width:100%;border-collapse:collapse;font-size:.82rem;min-width:980px}
        .cpc-table th,.cpc-table td{padding:.55rem .5rem;border-bottom:1px solid #e2e8f0;text-align:center;white-space:nowrap}
        .cpc-table th{background:#0f172a;color:#fff;font-weight:700;font-size:.72rem;text-transform:uppercase;letter-spacing:.03em;position:sticky;top:0;z-index:1}
        .cpc-table td.cpc-name,.cpc-table th.cpc-name{text-align:left;font-weight:700}
        .cpc-table tbody tr:nth-child(even){background:#f8fafc}
        .cpc-table tbody tr:hover{background:#f1f5f9}
        .cpc-table td.cpc-hot{font-weight:800;color:#166534;background:#dcfce7}
        .cpc-table td.cpc-na{color:#94a3b8;font-style:italic}
        .cpc-table td.cpc-net{font-weight:700}
        .cpc-table td.cpc-net.cpc-hot{background:#fef9c3;color:#854d0e}
        .cpc-legend{margin:.65rem 0 0;font-size:.75rem;color:#64748b;display:flex;flex-wrap:wrap;gap:.5rem 1.1rem}
        .cpc-legend strong{color:#334155}
        .cpc-swatch{display:inline-block;width:10px;height:10px;border-radius:2px;margin-right:.3rem;vertical-align:middle;background:#dcfce7;border:1px solid #86efac}
        </style>';
    }
}

if (!function_exists('bricks_competitors_pace_card_render')) {
    /**
     * @param array<int,object> $runners
     * @param object|null $race
     */
    function bricks_competitors_pace_card_render(array $runners, $race = null) {
        $course = $race->course ?? ($runners[0]->course ?? '');
        $time = $race->scheduled_time ?? ($runners[0]->Time ?? '');
        $title = $race->race_title ?? ($runners[0]->race_title ?? '');

        $style_cols = ['leader', 'closeup', 'chaser', 'heldup', 'midi', 'lagger'];

        ob_start();
        ?>
        <section class="cpc" aria-label="Competitors pace card">
            <header class="cpc-head">
                <span class="cpc-eyebrow">Competitors Pace Card</span>
                <h2 class="cpc-title">Running Style Classification</h2>
                <p class="cpc-sub">
                    <?php echo esc_html(trim($course . ($time ? ' · ' . $time : '') . ($title ? ' · ' . $title : ''))); ?>
                    — career rates from historic in-race comments
                </p>
            </header>

            <div class="cpc-wrap">
                <table class="cpc-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th class="cpc-name">Name</th>
                            <th>Forecast</th>
                            <th>Av Btn</th>
                            <th>Gender</th>
                            <th>Days</th>
                            <th>Weight</th>
                            <th>Age</th>
                            <th>Jockey</th>
                            <th>Trainer</th>
                            <th>Runs</th>
                            <th>Leader</th>
                            <th>Closeup</th>
                            <th>Chaser</th>
                            <th>Heldup</th>
                            <th>Midi</th>
                            <th>Lagger</th>
                            <th>Net Leader</th>
                            <th>Stall</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($runners as $r): ?>
                            <?php
                            $runs = intval($r->style_runs ?? 0);
                            $forecast = trim((string) ($r->forecast_price ?? ''));
                            if ($forecast === '' && isset($r->forecast_price_decimal) && is_numeric($r->forecast_price_decimal)) {
                                $forecast = number_format((float) $r->forecast_price_decimal, 2);
                            }
                            $avg_btn = $r->style_avg_btn;
                            $net = $r->style_net_leader_score;
                            $net_class = 'cpc-net ' . bricks_running_style_rate_cell_class($net, $runs);
                            // Net leader can be negative; highlight when strongly positive (>= 0.40).
                            ?>
                            <tr>
                                <td><?php echo esc_html((string) ($r->cloth_number ?: '—')); ?></td>
                                <td class="cpc-name"><?php echo esc_html((string) ($r->name ?? '')); ?></td>
                                <td><?php echo esc_html($forecast !== '' ? $forecast : '—'); ?></td>
                                <td><?php echo $avg_btn !== null ? esc_html(number_format((float) $avg_btn, 2)) : '—'; ?></td>
                                <td><?php echo esc_html((string) ($r->gender ?: '—')); ?></td>
                                <td><?php echo isset($r->days_since_ran) && $r->days_since_ran !== '' && $r->days_since_ran !== null ? esc_html((string) intval($r->days_since_ran)) : '—'; ?></td>
                                <td><?php echo esc_html(bricks_running_style_format_weight($r->weight_pounds ?? null)); ?></td>
                                <td><?php echo esc_html((string) ($r->age ?: '—')); ?></td>
                                <td><?php echo esc_html((string) ($r->jockey_name ?: '—')); ?></td>
                                <td><?php echo esc_html((string) ($r->trainer_name ?: '—')); ?></td>
                                <td><?php echo $runs > 0 ? esc_html((string) $runs) : '<span class="cpc-na">n/a</span>'; ?></td>
                                <?php foreach ($style_cols as $col): ?>
                                    <?php
                                    $prop = 'style_' . $col;
                                    $val = $r->{$prop} ?? null;
                                    $cls = bricks_running_style_rate_cell_class($val, $runs);
                                    ?>
                                    <td class="<?php echo esc_attr($cls); ?>"><?php echo esc_html(bricks_running_style_format_rate($val, $runs)); ?></td>
                                <?php endforeach; ?>
                                <td class="<?php echo esc_attr(trim($net_class)); ?>"><?php echo esc_html(bricks_running_style_format_rate($net, $runs)); ?></td>
                                <td><?php echo intval($r->stall_number ?? 0) > 0 ? esc_html((string) intval($r->stall_number)) : '—'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="cpc-legend">
                <span><span class="cpc-swatch"></span> Highlighted cells are ≥ 0.40</span>
                <span><strong>Net Leader</strong> = (leader + closeup) − (heldup + midi + lagger)</span>
                <span>Rates weight the last 5 runs heaviest · Withdrawn / non-runners excluded</span>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('bricks_competitors_pace_card_shortcode')) {
    function bricks_competitors_pace_card_shortcode($atts = []) {
        $atts = shortcode_atts([
            'race_id' => '',
        ], $atts, 'competitors_pace_card');

        $race_id = 0;
        if (function_exists('bricks_pace_map_resolve_race_id')) {
            $race_id = bricks_pace_map_resolve_race_id($atts);
        } else {
            $race_id = intval($atts['race_id'] ?: get_query_var('race_id'));
        }

        if ($race_id <= 0) {
            return '<div class="pace-map-error" style="padding:1rem;border:1px solid #fecaca;background:#fef2f2;border-radius:8px;color:#991b1b;">Competitors Pace Card: a <code>race_id</code> is required.</div>';
        }

        $race = null;
        if (function_exists('bricks_pace_map_resolve_race')) {
            $resolved = bricks_pace_map_resolve_race($race_id);
            $race = $resolved['race'] ?? null;
        }

        $runners = bricks_competitors_pace_card_get_runners($race_id);
        if (empty($runners)) {
            return '<div class="pace-map-error" style="padding:1rem;border:1px solid #e2e8f0;background:#f8fafc;border-radius:8px;color:#475569;">Competitors Pace Card: no runners found for this race.</div>';
        }

        return bricks_competitors_pace_card_styles() . bricks_competitors_pace_card_render($runners, $race);
    }
}
add_shortcode('competitors_pace_card', 'bricks_competitors_pace_card_shortcode');
