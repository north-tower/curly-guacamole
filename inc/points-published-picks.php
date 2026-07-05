<?php
/**
 * Persist Points Engine picks shown on live race cards for audit / published backtest mode.
 */

if (!function_exists('bricks_points_published_picks_table_name')) {
    function bricks_points_published_picks_table_name() {
        global $wpdb;
        static $resolved = null;
        if ($resolved !== null) {
            return $resolved;
        }
        // Prefer smartform-style unprefixed table (filled by SQL cron); fall back to wp install table.
        if ($wpdb->get_var("SHOW TABLES LIKE 'points_engine_published_picks'") === 'points_engine_published_picks') {
            $resolved = 'points_engine_published_picks';
        } else {
            $resolved = $wpdb->prefix . 'points_engine_published_picks';
        }
        return $resolved;
    }
}

if (!function_exists('bricks_points_published_picks_maybe_install')) {
    function bricks_points_published_picks_maybe_install() {
        if (get_option('bricks_points_published_picks_db_version') === '1') {
            return;
        }

        global $wpdb;
        $table = bricks_points_published_picks_table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            race_id bigint(20) unsigned NOT NULL,
            meeting_date date NOT NULL,
            win_horse varchar(255) NOT NULL DEFAULT '',
            place_horses text,
            ew_simple_horse varchar(255) NOT NULL DEFAULT '',
            ew_edge_horse varchar(255) NOT NULL DEFAULT '',
            saved_at datetime NOT NULL,
            source varchar(32) NOT NULL DEFAULT 'race_card',
            PRIMARY KEY  (race_id, meeting_date),
            KEY meeting_date (meeting_date)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        update_option('bricks_points_published_picks_db_version', '1');
    }
}
add_action('init', 'bricks_points_published_picks_maybe_install', 5);

if (!function_exists('bricks_points_published_picks_save_if_changed')) {
    /**
     * Avoid a DB write on every race page view when picks are unchanged.
     */
    function bricks_points_published_picks_save_if_changed($race_id, $meeting_date, $picks, $ew_simple, $ew_edge, $source = 'race_card') {
        $race_id = intval($race_id);
        $meeting_date = (string) $meeting_date;
        if ($race_id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $meeting_date)) {
            return false;
        }

        $win = isset($picks['winner']['horse_name']) ? (string) $picks['winner']['horse_name'] : '';
        if ($win === '') {
            return false;
        }

        $place_names = [];
        if (!empty($picks['place']) && is_array($picks['place'])) {
            foreach ($picks['place'] as $pp) {
                if (!empty($pp['horse_name'])) {
                    $place_names[] = (string) $pp['horse_name'];
                }
            }
        }

        $ew_simple_name = is_array($ew_simple) ? (string) ($ew_simple['horse_name'] ?? '') : '';
        $ew_edge_name = is_array($ew_edge) ? (string) ($ew_edge['horse_name'] ?? '') : '';

        $existing = bricks_points_published_picks_get($race_id, $meeting_date);
        if (is_array($existing)) {
            $existing_places = isset($existing['place_horses']) && is_array($existing['place_horses'])
                ? array_values($existing['place_horses'])
                : [];
            sort($place_names);
            sort($existing_places);
            if (
                ($existing['win_horse'] ?? '') === $win
                && $existing_places === $place_names
                && ($existing['ew_simple_horse'] ?? '') === $ew_simple_name
                && ($existing['ew_edge_horse'] ?? '') === $ew_edge_name
            ) {
                return false;
            }
        }

        return bricks_points_published_picks_save($race_id, $meeting_date, $picks, $ew_simple, $ew_edge, $source);
    }
}

if (!function_exists('bricks_points_published_picks_save')) {
    /**
     * @param array $picks bricks_points_pick_winner_place() result
     */
    function bricks_points_published_picks_save($race_id, $meeting_date, $picks, $ew_simple, $ew_edge, $source = 'race_card') {
        global $wpdb;

        $race_id = intval($race_id);
        $meeting_date = (string) $meeting_date;
        if ($race_id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $meeting_date)) {
            return false;
        }

        $win = isset($picks['winner']['horse_name']) ? (string) $picks['winner']['horse_name'] : '';
        if ($win === '') {
            return false;
        }

        $place_names = [];
        if (!empty($picks['place']) && is_array($picks['place'])) {
            foreach ($picks['place'] as $pp) {
                if (!empty($pp['horse_name'])) {
                    $place_names[] = (string) $pp['horse_name'];
                }
            }
        }

        $table = bricks_points_published_picks_table_name();
        $wpdb->replace(
            $table,
            [
                'race_id' => $race_id,
                'meeting_date' => $meeting_date,
                'win_horse' => $win,
                'place_horses' => wp_json_encode($place_names),
                'ew_simple_horse' => is_array($ew_simple) ? (string) ($ew_simple['horse_name'] ?? '') : '',
                'ew_edge_horse' => is_array($ew_edge) ? (string) ($ew_edge['horse_name'] ?? '') : '',
                'saved_at' => current_time('mysql'),
                'source' => sanitize_key((string) $source),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if (function_exists('bricks_points_published_picks_clear_get_cache')) {
            bricks_points_published_picks_clear_get_cache($race_id, $meeting_date);
        }

        return true;
    }
}

if (!function_exists('bricks_points_published_picks_get')) {
    function bricks_points_published_picks_get($race_id, $meeting_date) {
        global $wpdb;

        $race_id = intval($race_id);
        if ($race_id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $meeting_date)) {
            return null;
        }

        $cache_key = 'bricks_pe_picks_' . $race_id . '_' . $meeting_date;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached === 'none' ? null : $cached;
        }

        $table = bricks_points_published_picks_table_name();
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `$table` WHERE race_id = %d AND meeting_date = %s",
            $race_id,
            $meeting_date
        ));

        if (!$row) {
            set_transient($cache_key, 'none', 10 * MINUTE_IN_SECONDS);
            return null;
        }

        $place = [];
        if (!empty($row->place_horses)) {
            $decoded = json_decode($row->place_horses, true);
            if (is_array($decoded)) {
                $place = $decoded;
            }
        }

        $result = [
            'race_id' => $race_id,
            'meeting_date' => $meeting_date,
            'win_horse' => (string) ($row->win_horse ?? ''),
            'place_horses' => $place,
            'ew_simple_horse' => (string) ($row->ew_simple_horse ?? ''),
            'ew_edge_horse' => (string) ($row->ew_edge_horse ?? ''),
            'saved_at' => (string) ($row->saved_at ?? ''),
            'source' => (string) ($row->source ?? 'race_card'),
        ];
        set_transient($cache_key, $result, 10 * MINUTE_IN_SECONDS);
        return $result;
    }
}

if (!function_exists('bricks_points_published_picks_clear_get_cache')) {
    function bricks_points_published_picks_clear_get_cache($race_id, $meeting_date) {
        delete_transient('bricks_pe_picks_' . intval($race_id) . '_' . (string) $meeting_date);
    }
}

if (!function_exists('bricks_points_published_picks_defer_save_if_changed')) {
    function bricks_points_published_picks_defer_save_if_changed($race_id, $meeting_date, $picks, $ew_simple, $ew_edge, $source = 'race_card') {
        register_shutdown_function(function () use ($race_id, $meeting_date, $picks, $ew_simple, $ew_edge, $source) {
            bricks_points_published_picks_save_if_changed($race_id, $meeting_date, $picks, $ew_simple, $ew_edge, $source);
        });
    }
}

if (!function_exists('bricks_points_published_picks_count_for_range')) {
    function bricks_points_published_picks_count_for_range($from_date, $to_date) {
        global $wpdb;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
            return 0;
        }
        $table = bricks_points_published_picks_table_name();
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `$table` WHERE meeting_date BETWEEN %s AND %s",
            $from_date,
            $to_date
        ));
    }
}

if (!function_exists('bricks_points_published_picks_race_ids_for_range')) {
    /**
     * @return int[]
     */
    function bricks_points_published_picks_race_ids_for_range($from_date, $to_date) {
        global $wpdb;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
            return [];
        }

        $ids = [];
        if ($wpdb->get_var("SHOW TABLES LIKE 'historic_races_beta'") === 'historic_races_beta') {
            $historic = $wpdb->get_col($wpdb->prepare(
                "SELECT race_id FROM historic_races_beta
                 WHERE meeting_date BETWEEN %s AND %s
                 ORDER BY meeting_date ASC, scheduled_time ASC, race_id ASC",
                $from_date,
                $to_date
            ));
            if (is_array($historic)) {
                $ids = array_merge($ids, $historic);
            }
        }

        if ($wpdb->get_var("SHOW TABLES LIKE 'advance_daily_races_beta'") === 'advance_daily_races_beta') {
            $daily = $wpdb->get_col($wpdb->prepare(
                "SELECT race_id FROM advance_daily_races_beta
                 WHERE meeting_date BETWEEN %s AND %s
                 ORDER BY meeting_date ASC, scheduled_time ASC, race_id ASC",
                $from_date,
                $to_date
            ));
            if (is_array($daily)) {
                $ids = array_merge($ids, $daily);
            }
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        return $ids;
    }
}

if (!function_exists('bricks_points_engine_race_is_national_hunt')) {
    function bricks_points_engine_race_is_national_hunt($race_type) {
        if (function_exists('bricks_points_race_type_is_national_hunt')) {
            return bricks_points_race_type_is_national_hunt($race_type);
        }
        $race_type_lower = strtolower((string) $race_type);
        return (
            strpos($race_type_lower, 'hurdle') !== false ||
            strpos($race_type_lower, 'chase') !== false ||
            strpos($race_type_lower, 'n_h_flat') !== false ||
            strpos($race_type_lower, 'nh_flat') !== false ||
            strpos($race_type_lower, 'national hunt') !== false
        );
    }
}

if (!function_exists('bricks_points_engine_trainer_course_lookup')) {
    function bricks_points_engine_trainer_course_lookup($course, $meeting_date, array $trainer_names) {
        global $wpdb;

        $lookup = [];
        $trainer_names = array_values(array_unique(array_filter(array_map('trim', $trainer_names))));
        if ($course === '' || empty($trainer_names) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $meeting_date)) {
            return $lookup;
        }
        if ($wpdb->get_var("SHOW TABLES LIKE 'historic_runners_beta'") !== 'historic_runners_beta') {
            return $lookup;
        }

        $tfc_from = date('Y-m-d', strtotime('-5 years', strtotime($meeting_date)));
        $tfc_to = date('Y-m-d', strtotime('-1 day', strtotime($meeting_date)));
        $placeholders = implode(',', array_fill(0, count($trainer_names), '%s'));
        $sql = "
            SELECT hrunb.trainer_name AS trainer_name,
                   COUNT(*) AS runs,
                   SUM(CASE WHEN CAST(hrunb.finish_position AS UNSIGNED) = 1 THEN 1 ELSE 0 END) AS wins
            FROM historic_runners_beta hrunb
            INNER JOIN historic_races_beta hracb ON hracb.race_id = hrunb.race_id
            WHERE hrunb.trainer_name IN ($placeholders)
              AND hracb.course = %s
              AND hracb.meeting_date BETWEEN %s AND %s
              AND hrunb.finish_position REGEXP '^[0-9]+$'
            GROUP BY hrunb.trainer_name";
        $params = array_merge($trainer_names, [$course, $tfc_from, $tfc_to]);
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        foreach ($rows as $row) {
            $tn = trim((string) ($row->trainer_name ?? ''));
            $runs = intval($row->runs ?? 0);
            $wins = intval($row->wins ?? 0);
            if ($tn === '' || $runs <= 0) {
                continue;
            }
            $lookup[$tn] = [
                'runs' => $runs,
                'wins' => $wins,
                'win_pct' => round(($wins / $runs) * 100, 1),
            ];
        }
        return $lookup;
    }
}

if (!function_exists('bricks_points_engine_build_speed_lookups')) {
    /**
     * @return array{0: array, 1: array}
     */
    function bricks_points_engine_build_speed_lookups($speed_ratings) {
        $by_name = [];
        $by_runner_id = [];
        if (empty($speed_ratings)) {
            return [$by_name, $by_runner_id];
        }
        foreach ($speed_ratings as $rating) {
            $horse_name = ($rating->horse_name ?? '') !== '' ? $rating->horse_name : ($rating->name ?? '');
            if ($horse_name) {
                $by_name[$horse_name] = $rating;
            }
            if (isset($rating->runner_id) && $rating->runner_id !== null && $rating->runner_id !== '') {
                $by_runner_id[(string) $rating->runner_id] = $rating;
            }
        }
        return [$by_name, $by_runner_id];
    }
}

if (!function_exists('bricks_points_engine_score_runners')) {
    /**
     * Same scoring path as the live race card (without HTML).
     *
     * @return array<int, array<string, mixed>>
     */
    function bricks_points_engine_score_runners($race, array $runners, array $speed_by_name, array $speed_by_runner_id, array $non_runner_lookup, array $trainer_course_lookup) {
        if (empty($runners) || !$race) {
            return [];
        }

        $race_id = intval($race->race_id ?? 0);
        $is_national_hunt = bricks_points_engine_race_is_national_hunt($race->race_type ?? '');
        $show_maturity_edge = function_exists('bricks_is_2yo_flat_race_for_maturity')
            && bricks_is_2yo_flat_race_for_maturity($race, $is_national_hunt);

        $scored = [];
        foreach ($runners as $idx => $runner_item) {
            $runner_name = isset($runner_item->name) ? (string) $runner_item->name : '';
            $runner_id_int = isset($runner_item->runner_id) ? intval($runner_item->runner_id) : 0;
            $runner_key = ($runner_id_int > 0 ? (string) $runner_id_int : 'idx_' . $idx);

            $is_non_runner = false;
            if (isset($runner_item->non_runner) && intval($runner_item->non_runner) === 1) {
                $is_non_runner = true;
            }
            if (!$is_non_runner && $runner_id_int > 0) {
                $lookup_key = $race_id . ':' . $runner_id_int;
                if (!empty($non_runner_lookup[$lookup_key])) {
                    $is_non_runner = true;
                }
            }

            $speed_data = null;
            if ($runner_name !== '' && isset($speed_by_name[$runner_name])) {
                $speed_data = $speed_by_name[$runner_name];
            } elseif ($runner_id_int > 0 && isset($speed_by_runner_id[(string) $runner_id_int])) {
                $speed_data = $speed_by_runner_id[(string) $runner_id_int];
            }

            $trainer_name_key = isset($runner_item->trainer_name) ? trim((string) $runner_item->trainer_name) : '';
            $trainer_course = ($trainer_name_key !== '' && isset($trainer_course_lookup[$trainer_name_key]))
                ? $trainer_course_lookup[$trainer_name_key]
                : null;
            $trainer_course_pct = ($trainer_course && isset($trainer_course['win_pct'])) ? floatval($trainer_course['win_pct']) : null;

            $maturity_edge_score = null;
            if ($show_maturity_edge) {
                $runner_foaling_date = '';
                if ($speed_data && isset($speed_data->foaling_date)) {
                    $runner_foaling_date = $speed_data->foaling_date;
                } elseif (isset($runner_item->foaling_date)) {
                    $runner_foaling_date = $runner_item->foaling_date;
                }
                if (function_exists('bricks_calculate_maturity_edge')) {
                    $maturity_edge = bricks_calculate_maturity_edge($runner_foaling_date, $race->meeting_date);
                    if (is_array($maturity_edge) && isset($maturity_edge['score'])) {
                        $maturity_edge_score = floatval($maturity_edge['score']);
                    }
                }
            }

            $points_result = bricks_points_score_runner($runner_item, $speed_data, [
                'is_flat' => !$is_national_hunt,
                'trainer_course_pct' => $trainer_course_pct,
                'maturity_edge_score' => $maturity_edge_score,
            ]);

            $forecast_decimal = null;
            $forecast_fractional = '';
            if ($speed_data) {
                $forecast_decimal = $speed_data->forecast_price_decimal ?? null;
                $forecast_fractional = (string) ($speed_data->forecast_price ?? '');
            }
            if (($forecast_decimal === null || $forecast_decimal === '') && isset($runner_item->forecast_price_decimal)) {
                $forecast_decimal = $runner_item->forecast_price_decimal;
            }
            if ($forecast_fractional === '' && isset($runner_item->forecast_price)) {
                $forecast_fractional = (string) $runner_item->forecast_price;
            }

            $odds_decimal = bricks_points_parse_decimal_odds($forecast_decimal, $forecast_fractional);
            $scored[] = [
                'runner_key' => $runner_key,
                'runner_id' => $runner_id_int,
                'horse_name' => $runner_name,
                'model_score' => floatval($points_result['score'] ?? 0),
                'model_reasons' => $points_result['reasons'] ?? [],
                'market_prob' => bricks_points_market_implied_rank($odds_decimal),
                'market_rank' => 0,
                'model_rank' => 0,
                'edge_score' => 0.0,
                'odds_decimal' => $odds_decimal,
                'odds_fractional' => $forecast_fractional,
                'is_non_runner' => $is_non_runner,
                'has_form' => !empty($points_result['has_form']),
                'fsr' => ($speed_data && isset($speed_data->fhorsite_rating) && is_numeric($speed_data->fhorsite_rating)) ? floatval($speed_data->fhorsite_rating) : null,
                'fsrr' => ($speed_data && isset($speed_data->fhorsite_rating_reliability) && is_numeric($speed_data->fhorsite_rating_reliability)) ? floatval($speed_data->fhorsite_rating_reliability) : null,
            ];
        }

        $model_sorted = $scored;
        usort($model_sorted, function ($a, $b) {
            return ($b['model_score'] ?? 0) <=> ($a['model_score'] ?? 0);
        });
        $model_rank = 1;
        $model_rank_map = [];
        foreach ($model_sorted as $ms) {
            if (!empty($ms['is_non_runner'])) {
                continue;
            }
            $model_rank_map[$ms['runner_key']] = $model_rank++;
        }

        $market_sorted = $scored;
        usort($market_sorted, function ($a, $b) {
            return ($b['market_prob'] ?? 0) <=> ($a['market_prob'] ?? 0);
        });
        $market_rank = 1;
        $market_rank_map = [];
        foreach ($market_sorted as $mk) {
            if (!empty($mk['is_non_runner'])) {
                continue;
            }
            if (($mk['market_prob'] ?? 0) <= 0) {
                continue;
            }
            $market_rank_map[$mk['runner_key']] = $market_rank++;
        }

        foreach ($scored as &$row) {
            $rk = $row['runner_key'];
            $row['model_rank'] = $model_rank_map[$rk] ?? 0;
            $row['market_rank'] = $market_rank_map[$rk] ?? 0;
            $rank_edge = ($row['market_rank'] > 0 && $row['model_rank'] > 0)
                ? ($row['market_rank'] - $row['model_rank'])
                : 0;
            $score_edge = max(0.0, (floatval($row['model_score']) - 55.0) * 0.20);
            $row['edge_score'] = round(($rank_edge * 4.0) + $score_edge, 2);
        }
        unset($row);

        return $scored;
    }
}

if (!function_exists('bricks_points_engine_picks_from_scored')) {
    function bricks_points_engine_picks_from_scored(array $scored) {
        return [
            'picks' => bricks_points_pick_winner_place($scored),
            'ew_simple' => bricks_points_pick_each_way_simple($scored),
            'ew_edge' => bricks_points_pick_each_way_edge($scored),
        ];
    }
}

if (!function_exists('bricks_points_engine_compute_picks_for_race')) {
    /**
     * Compute Points Engine picks for one race (live speed table when present, else historic replay).
     *
     * @return array{ok: bool, error?: string, meeting_date?: string, source?: string, picks?: array, ew_simple?: mixed, ew_edge?: mixed}
     */
    function bricks_points_engine_compute_picks_for_race($race_id) {
        global $wpdb;

        $race_id = intval($race_id);
        if ($race_id <= 0) {
            return ['ok' => false, 'error' => 'Invalid race ID'];
        }

        $today = wp_date('Y-m-d', current_time('timestamp'));
        $tomorrow = wp_date('Y-m-d', strtotime('+1 day', current_time('timestamp')));

        $race_probe = $wpdb->get_row($wpdb->prepare(
            "SELECT race_id, meeting_date, course, race_type FROM advance_daily_races_beta WHERE race_id = %d",
            $race_id
        ));
        if (!$race_probe) {
            $race_probe = $wpdb->get_row($wpdb->prepare(
                "SELECT race_id, meeting_date, course, race_type FROM advance_daily_races WHERE race_id = %d",
                $race_id
            ));
        }
        if (!$race_probe) {
            $race_probe = $wpdb->get_row($wpdb->prepare(
                "SELECT race_id, meeting_date, course, race_type FROM historic_races_beta WHERE race_id = %d",
                $race_id
            ));
        }
        if (!$race_probe) {
            return ['ok' => false, 'error' => 'Race not found'];
        }

        $meeting_date = (string) $race_probe->meeting_date;
        $source = 'admin_bulk_replay';

        if ($meeting_date === $tomorrow) {
            $races_table = 'advance_daily_races';
            $runners_table = 'advance_daily_runners';
            $speed_table = 'adv_speed&performance_table';
        } else {
            $races_table = 'advance_daily_races_beta';
            $runners_table = 'advance_daily_runners_beta';
            $speed_table = 'speed&performance_table';
        }

        $race = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$races_table` WHERE race_id = %d", $race_id));
        $runners_table_used = $runners_table;
        if (!$race) {
            $race = $wpdb->get_row($wpdb->prepare("SELECT * FROM historic_races_beta WHERE race_id = %d", $race_id));
            $runners_table_used = 'historic_runners_beta';
            $runners = $wpdb->get_results($wpdb->prepare(
                "SELECT hrunb.*, hrunb.name AS name FROM historic_runners_beta hrunb WHERE hrunb.race_id = %d",
                $race_id
            ));
        } else {
            $runners = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM `$runners_table` WHERE race_id = %d",
                $race_id
            ));
        }

        if (!$race || empty($runners) || count($runners) < 2) {
            return ['ok' => false, 'error' => 'Insufficient runners'];
        }

        $speed_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `$speed_table` WHERE race_id = %d",
            $race_id
        ));

        if ($speed_count > 0) {
            $speed_ratings = $wpdb->get_results($wpdb->prepare(
                "SELECT sp.*, r.name AS horse_name
                 FROM `$speed_table` sp
                 LEFT JOIN `$runners_table_used` r ON sp.race_id = r.race_id AND sp.runner_id = r.runner_id
                 WHERE sp.race_id = %d",
                $race_id
            ));
            $source = 'admin_bulk_live';
        } else {
            $speed_ratings = [];
        }

        if (!empty($speed_ratings)) {
            list($speed_by_name, $speed_by_runner_id) = bricks_points_engine_build_speed_lookups($speed_ratings);

            $non_runner_lookup = [];
            if ($wpdb->get_var("SHOW TABLES LIKE 'non_runners'") === 'non_runners') {
                $nr_rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT race_id, runner_id FROM non_runners WHERE race_id = %d",
                    $race_id
                ));
                foreach ($nr_rows as $nr) {
                    $non_runner_lookup[intval($nr->race_id) . ':' . intval($nr->runner_id)] = true;
                }
            }

            $trainer_names = [];
            foreach ($runners as $runner_item) {
                if (!empty($runner_item->trainer_name)) {
                    $trainer_names[] = (string) $runner_item->trainer_name;
                }
            }
            $trainer_course_lookup = bricks_points_engine_trainer_course_lookup(
                (string) ($race->course ?? ''),
                $meeting_date,
                $trainer_names
            );

            $scored = bricks_points_engine_score_runners(
                $race,
                $runners,
                $speed_by_name,
                $speed_by_runner_id,
                $non_runner_lookup,
                $trainer_course_lookup
            );
        } elseif (function_exists('bricks_points_backtest_fetch_rows') && function_exists('bricks_points_backtest_score_race')) {
            $rows = bricks_points_backtest_fetch_rows($meeting_date, $meeting_date);
            $race_rows = [];
            foreach ($rows as $row) {
                if (intval($row->race_id ?? 0) === $race_id) {
                    $race_rows[] = $row;
                }
            }
            if (count($race_rows) < 2) {
                return ['ok' => false, 'error' => 'No historic replay rows'];
            }
            $scored = bricks_points_backtest_score_race($race_rows);
        } else {
            return ['ok' => false, 'error' => 'Scoring functions unavailable'];
        }

        if (empty($scored)) {
            return ['ok' => false, 'error' => 'No scored runners'];
        }

        $bundle = bricks_points_engine_picks_from_scored($scored);
        $picks = $bundle['picks'];
        if (empty($picks['winner']['horse_name'])) {
            return ['ok' => false, 'error' => 'No win pick'];
        }

        return [
            'ok' => true,
            'meeting_date' => $meeting_date,
            'source' => $source,
            'picks' => $picks,
            'ew_simple' => $bundle['ew_simple'],
            'ew_edge' => $bundle['ew_edge'],
        ];
    }
}

if (!function_exists('bricks_points_published_picks_bulk_snapshot')) {
    /**
     * @return array{saved: int, skipped: int, failed: int, errors: string[], sources: array<string, int>}
     */
    function bricks_points_published_picks_bulk_snapshot($from_date, $to_date, $overwrite = false, $limit = 400) {
        global $wpdb;

        $result = [
            'saved' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
            'sources' => [
                'admin_bulk_live' => 0,
                'admin_bulk_replay' => 0,
            ],
        ];

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
            $result['errors'][] = 'Invalid date range';
            return $result;
        }

        $race_ids = bricks_points_published_picks_race_ids_for_range($from_date, $to_date);
        if (empty($race_ids)) {
            $result['errors'][] = 'No races found in range';
            return $result;
        }

        $table = bricks_points_published_picks_table_name();
        $limit = max(1, min(1000, intval($limit)));
        $processed = 0;

        foreach ($race_ids as $race_id) {
            if ($processed >= $limit) {
                $result['errors'][] = 'Stopped at limit of ' . $limit . ' races — run again for the rest.';
                break;
            }
            $processed++;

            $computed = bricks_points_engine_compute_picks_for_race($race_id);
            if (empty($computed['ok'])) {
                $result['failed']++;
                if (count($result['errors']) < 15) {
                    $result['errors'][] = 'Race ' . $race_id . ': ' . ($computed['error'] ?? 'unknown');
                }
                continue;
            }

            $meeting_date = (string) $computed['meeting_date'];
            if (!$overwrite) {
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT race_id FROM `$table` WHERE race_id = %d AND meeting_date = %s",
                    $race_id,
                    $meeting_date
                ));
                if ($exists) {
                    $result['skipped']++;
                    continue;
                }
            }

            $source = (string) ($computed['source'] ?? 'admin_bulk_replay');
            $saved = bricks_points_published_picks_save(
                $race_id,
                $meeting_date,
                $computed['picks'],
                $computed['ew_simple'],
                $computed['ew_edge'],
                $source
            );
            if ($saved) {
                $result['saved']++;
                if (isset($result['sources'][$source])) {
                    $result['sources'][$source]++;
                }
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }
}

if (!function_exists('bricks_points_pick_runner_by_horse_name')) {
  /**
   * Match a published horse name to a scored runner row.
   */
    function bricks_points_pick_runner_by_horse_name(array $scored, $horse_name) {
        $horse_name = trim((string) $horse_name);
        if ($horse_name === '') {
            return null;
        }
        $target = bricks_points_engine_normalize_horse_name($horse_name);
        foreach ($scored as $row) {
            if (bricks_points_engine_normalize_horse_name($row['horse_name'] ?? '') === $target) {
                return $row;
            }
        }
        return null;
    }
}

if (!function_exists('bricks_points_picks_from_published_snapshot')) {
    /**
     * Build pick arrays from a published snapshot + scored runners (for settlement fields).
     */
    function bricks_points_picks_from_published_snapshot(array $snapshot, array $scored) {
        $winner = bricks_points_pick_runner_by_horse_name($scored, $snapshot['win_horse'] ?? '');
        $place = [];
        foreach (($snapshot['place_horses'] ?? []) as $name) {
            $p = bricks_points_pick_runner_by_horse_name($scored, $name);
            if ($p) {
                $place[] = $p;
            }
        }
        return [
            'winner' => $winner,
            'place' => $place,
            'ew_simple' => bricks_points_pick_runner_by_horse_name($scored, $snapshot['ew_simple_horse'] ?? ''),
            'ew_edge' => bricks_points_pick_runner_by_horse_name($scored, $snapshot['ew_edge_horse'] ?? ''),
        ];
    }
}
