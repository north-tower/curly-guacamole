<?php
/**
 * Admin-only P&L dashboard for points strategies.
 */

if (!function_exists('bricks_admin_pnl_place_terms')) {
    function bricks_admin_pnl_place_terms($field_size, $override = 0) {
        $override = intval($override);
        if ($override > 0) {
            return $override;
        }
        if (function_exists('bricks_points_place_terms_count')) {
            return bricks_points_place_terms_count($field_size);
        }
        $field_size = intval($field_size);
        if ($field_size >= 16) return 4;
        if ($field_size >= 8) return 3;
        if ($field_size >= 5) return 2;
        return 1;
    }
}

if (!function_exists('bricks_admin_pnl_empty_strategy')) {
    function bricks_admin_pnl_empty_strategy() {
        return [
            'selections' => 0,
            'staked_pts' => 0.0,
            'returns_pts' => 0.0,
            'profit_pts' => 0.0,
            'hits' => 0,
            'win_hits' => 0,
            'place_hits' => 0,
            'strike_rate' => 0.0,
            'roi_pct' => 0.0,
        ];
    }
}

if (!function_exists('bricks_admin_pnl_apply_commission')) {
    function bricks_admin_pnl_apply_commission($returns_pts, $staked_pts, $commission_pct) {
        $gross_profit = $returns_pts - $staked_pts;
        if ($gross_profit <= 0 || $commission_pct <= 0) {
            return $returns_pts;
        }
        $commission = $gross_profit * ($commission_pct / 100.0);
        return max(0.0, $returns_pts - $commission);
    }
}

if (!function_exists('bricks_admin_pnl_calculate')) {
    function bricks_admin_pnl_calculate($from_date, $to_date, $race_type_filter, $cfg) {
        if (!function_exists('bricks_points_backtest_fetch_rows') || !function_exists('bricks_points_score_runner')) {
            return [
                'summary' => [],
                'race_count' => 0,
                'runner_count' => 0,
                'error' => 'Points engine functions not available.',
            ];
        }

        $rows = bricks_points_backtest_fetch_rows($from_date, $to_date, $race_type_filter);
        if (empty($rows)) {
            return [
                'summary' => [],
                'race_count' => 0,
                'runner_count' => 0,
                'error' => '',
            ];
        }

        $by_race = [];
        foreach ($rows as $row) {
            $rid = isset($row->race_id) ? intval($row->race_id) : 0;
            if ($rid <= 0) continue;
            if (!isset($by_race[$rid])) {
                $by_race[$rid] = [];
            }
            $by_race[$rid][] = $row;
        }

        $stats = [
            'win' => bricks_admin_pnl_empty_strategy(),
            'place' => bricks_admin_pnl_empty_strategy(),
            'ew_simple' => bricks_admin_pnl_empty_strategy(),
            'ew_edge' => bricks_admin_pnl_empty_strategy(),
        ];

        $win_stake = max(0.0, floatval($cfg['stake_win']));
        $place_stake = max(0.0, floatval($cfg['stake_place']));
        $ew_total_stake = max(0.0, floatval($cfg['stake_ew']));
        $ew_win_stake = $ew_total_stake / 2.0;
        $ew_place_stake = $ew_total_stake / 2.0;
        $ew_fraction = max(0.0, min(1.0, floatval($cfg['ew_fraction'])));
        $commission_pct = max(0.0, min(100.0, floatval($cfg['commission_pct'])));
        $manual_places = intval($cfg['ew_places_override']);

        $enabled = isset($cfg['enabled_strategies']) && is_array($cfg['enabled_strategies']) ? $cfg['enabled_strategies'] : ['win', 'place', 'ew_simple', 'ew_edge'];
        $is_enabled = function($key) use ($enabled) {
            return in_array($key, $enabled, true);
        };

        foreach ($by_race as $race_rows) {
            $field_size = count($race_rows);
            if ($field_size < 2) continue;

            $scored = [];
            foreach ($race_rows as $idx => $rr) {
                $speed_data = (object) [
                    'fhorsite_rating' => (isset($rr->speed_rating) && is_numeric($rr->speed_rating)) ? $rr->speed_rating : null,
                    'fhorsite_rating_reliability' => 60,
                    'SR_LTO' => (isset($rr->wt_speed_rating) && is_numeric($rr->wt_speed_rating)) ? $rr->wt_speed_rating : null,
                    'days_since_ran' => (isset($rr->days_since_ran) && is_numeric($rr->days_since_ran)) ? $rr->days_since_ran : null,
                    'draw_bias_pct' => (isset($rr->draw_bias_pct) && is_numeric($rr->draw_bias_pct)) ? $rr->draw_bias_pct : null,
                    'class_diff' => (isset($rr->class_diff) && is_numeric($rr->class_diff)) ? $rr->class_diff : null,
                    'official_rating_diff' => (isset($rr->official_rating_diff) && is_numeric($rr->official_rating_diff)) ? $rr->official_rating_diff : null,
                    'course_winner' => (isset($rr->course_winner) && is_numeric($rr->course_winner)) ? $rr->course_winner : null,
                    'distance_winner' => (isset($rr->distance_winner) && is_numeric($rr->distance_winner)) ? $rr->distance_winner : null,
                    'candd_winner' => (isset($rr->candd_winner) && is_numeric($rr->candd_winner)) ? $rr->candd_winner : null,
                    'going_prev_wins' => (isset($rr->going_prev_wins) && is_numeric($rr->going_prev_wins)) ? $rr->going_prev_wins : null,
                    'beaten_favourite' => (isset($rr->beaten_favourite) && is_numeric($rr->beaten_favourite)) ? $rr->beaten_favourite : null,
                    'TnrWinPct14d' => (isset($rr->TnrWinPct14d) && is_numeric($rr->TnrWinPct14d)) ? $rr->TnrWinPct14d : null,
                    'TnrJkyPlacePct' => (isset($rr->TnrJkyPlacePct) && is_numeric($rr->TnrJkyPlacePct)) ? $rr->TnrJkyPlacePct : null,
                    'forecast_price_decimal' => (isset($rr->forecast_price_decimal) && is_numeric($rr->forecast_price_decimal)) ? $rr->forecast_price_decimal : null,
                    'forecast_price' => isset($rr->starting_price) ? $rr->starting_price : ''
                ];

                $pts = bricks_points_score_runner($rr, $speed_data, ['is_flat' => true]);
                $odds_decimal = bricks_points_parse_decimal_odds($speed_data->forecast_price_decimal, $speed_data->forecast_price);
                $scored[] = [
                    'runner_key' => $idx,
                    'horse_name' => (string) ($rr->horse_name ?? ''),
                    'model_score' => floatval($pts['score'] ?? 0),
                    'market_prob' => bricks_points_market_implied_rank($odds_decimal),
                    'market_rank' => 0,
                    'model_rank' => 0,
                    'edge_score' => 0,
                    'odds_decimal' => $odds_decimal,
                    'finish_position' => intval($rr->finish_position ?? 999),
                ];
            }

            usort($scored, function($a, $b){ return $b['model_score'] <=> $a['model_score']; });
            $rank = 1;
            foreach ($scored as &$sr) { $sr['model_rank'] = $rank++; }
            unset($sr);

            $market_sorted = $scored;
            usort($market_sorted, function($a, $b){ return ($b['market_prob'] ?? 0) <=> ($a['market_prob'] ?? 0); });
            $mk = 1; $mk_map = [];
            foreach ($market_sorted as $mr) {
                if (($mr['market_prob'] ?? 0) > 0) $mk_map[$mr['runner_key']] = $mk++;
            }
            foreach ($scored as &$sr) {
                $sr['market_rank'] = $mk_map[$sr['runner_key']] ?? 0;
                $rank_edge = ($sr['market_rank'] > 0) ? ($sr['market_rank'] - $sr['model_rank']) : 0;
                $score_edge = max(0.0, (floatval($sr['model_score']) - 55.0) * 0.20);
                $sr['edge_score'] = round(($rank_edge * 4.0) + $score_edge, 2);
            }
            unset($sr);

            $picks = bricks_points_pick_winner_place($scored);
            $ew_simple = bricks_points_pick_each_way_simple($scored);
            $ew_edge = bricks_points_pick_each_way_edge($scored);
            $place_terms = bricks_admin_pnl_place_terms($field_size, $manual_places);

            $settle_win = function($pick) use (&$stats, $win_stake) {
                if (!$pick || !isset($pick['odds_decimal']) || floatval($pick['odds_decimal']) <= 1 || $win_stake <= 0) return;
                $is_win = intval($pick['finish_position'] ?? 999) === 1;
                $stats['win']['selections'] += 1;
                $stats['win']['staked_pts'] += $win_stake;
                $stats['win']['returns_pts'] += $is_win ? ($win_stake * floatval($pick['odds_decimal'])) : 0.0;
                if ($is_win) {
                    $stats['win']['hits'] += 1;
                    $stats['win']['win_hits'] += 1;
                }
            };

            $settle_place = function($pick) use (&$stats, $place_stake, $place_terms, $ew_fraction) {
                if (!$pick || !isset($pick['odds_decimal']) || floatval($pick['odds_decimal']) <= 1 || $place_stake <= 0) return;
                $placed = intval($pick['finish_position'] ?? 999) <= $place_terms;
                $stats['place']['selections'] += 1;
                $stats['place']['staked_pts'] += $place_stake;
                if ($placed) {
                    $place_odds = 1.0 + ((floatval($pick['odds_decimal']) - 1.0) * $ew_fraction);
                    $stats['place']['returns_pts'] += $place_stake * $place_odds;
                    $stats['place']['hits'] += 1;
                    $stats['place']['place_hits'] += 1;
                }
            };

            $settle_ew = function($pick, $key) use (&$stats, $ew_win_stake, $ew_place_stake, $place_terms, $ew_fraction) {
                if (!$pick || !isset($pick['odds_decimal']) || floatval($pick['odds_decimal']) <= 1) return;
                if (($ew_win_stake + $ew_place_stake) <= 0) return;
                $finish = intval($pick['finish_position'] ?? 999);
                $is_win = ($finish === 1);
                $placed = ($finish <= $place_terms);

                $stats[$key]['selections'] += 1;
                $stats[$key]['staked_pts'] += ($ew_win_stake + $ew_place_stake);

                if ($is_win && $ew_win_stake > 0) {
                    $stats[$key]['returns_pts'] += $ew_win_stake * floatval($pick['odds_decimal']);
                    $stats[$key]['win_hits'] += 1;
                }
                if ($placed && $ew_place_stake > 0) {
                    $place_odds = 1.0 + ((floatval($pick['odds_decimal']) - 1.0) * $ew_fraction);
                    $stats[$key]['returns_pts'] += $ew_place_stake * $place_odds;
                    $stats[$key]['place_hits'] += 1;
                }
                if ($placed) {
                    $stats[$key]['hits'] += 1;
                }
            };

            if ($is_enabled('win')) {
                $settle_win($picks['winner'] ?? null);
            }
            if ($is_enabled('place') && !empty($picks['place'])) {
                foreach (array_slice($picks['place'], 0, 3) as $pp) {
                    $settle_place($pp);
                }
            }
            if ($is_enabled('ew_simple')) {
                $settle_ew($ew_simple, 'ew_simple');
            }
            if ($is_enabled('ew_edge')) {
                $settle_ew($ew_edge, 'ew_edge');
            }
        }

        foreach ($stats as $k => $v) {
            $adjusted_returns = bricks_admin_pnl_apply_commission($v['returns_pts'], $v['staked_pts'], $commission_pct);
            $profit = $adjusted_returns - $v['staked_pts'];
            $stats[$k]['returns_pts'] = round($adjusted_returns, 2);
            $stats[$k]['profit_pts'] = round($profit, 2);
            $stats[$k]['strike_rate'] = $v['selections'] > 0 ? round(($v['hits'] / $v['selections']) * 100, 2) : 0.0;
            $stats[$k]['roi_pct'] = $v['staked_pts'] > 0 ? round(($profit / $v['staked_pts']) * 100, 2) : 0.0;
        }

        return [
            'summary' => $stats,
            'race_count' => count($by_race),
            'runner_count' => count($rows),
            'error' => '',
        ];
    }
}

if (!function_exists('bricks_admin_pnl_dashboard_shortcode')) {
    function bricks_admin_pnl_dashboard_shortcode($atts = []) {
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return '<div style="max-width:760px;margin:40px auto;padding:24px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;">
                <h2 style="margin:0 0 10px 0;color:#111827;">Admin P&amp;L Dashboard</h2>
                <p style="margin:0;color:#6b7280;">This page is available to admins only.</p>
            </div>';
        }

        if (!function_exists('bricks_points_backtest_fetch_rows')) {
            return '<div style="max-width:760px;margin:40px auto;padding:24px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;color:#991b1b;">
                Points backtest engine is not available.
            </div>';
        }

        $default_to = date('Y-m-d', strtotime('-1 day'));
        $default_from = date('Y-m-d', strtotime('-365 days', strtotime($default_to)));

        $from_date = isset($_GET['ap_from']) ? sanitize_text_field($_GET['ap_from']) : $default_from;
        $to_date = isset($_GET['ap_to']) ? sanitize_text_field($_GET['ap_to']) : $default_to;
        $race_type_filter = isset($_GET['ap_race_type']) ? sanitize_text_field($_GET['ap_race_type']) : '';
        $all_labels = [
            'win' => 'Win Pick',
            'place' => 'Place Shortlist',
            'ew_simple' => 'EW Simple',
            'ew_edge' => 'EW Edge',
        ];
        $selected_strategies = isset($_GET['ap_strategies']) ? (array) $_GET['ap_strategies'] : array_keys($all_labels);
        $selected_strategies = array_values(array_intersect(array_map('sanitize_key', $selected_strategies), array_keys($all_labels)));
        if (empty($selected_strategies)) {
            $selected_strategies = array_keys($all_labels);
        }
        $stake_win = isset($_GET['ap_stake_win']) ? floatval($_GET['ap_stake_win']) : 1.0;
        $stake_place = isset($_GET['ap_stake_place']) ? floatval($_GET['ap_stake_place']) : 1.0;
        $stake_ew = isset($_GET['ap_stake_ew']) ? floatval($_GET['ap_stake_ew']) : 1.0;
        $ew_fraction = isset($_GET['ap_ew_fraction']) ? floatval($_GET['ap_ew_fraction']) : 0.25;
        $ew_places_override = isset($_GET['ap_ew_places']) ? intval($_GET['ap_ew_places']) : 0;
        $commission_pct = isset($_GET['ap_commission']) ? floatval($_GET['ap_commission']) : 0.0;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) $from_date = $default_from;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) $to_date = $default_to;
        if ($from_date > $to_date) { $tmp = $from_date; $from_date = $to_date; $to_date = $tmp; }
        if ($stake_win < 0) $stake_win = 1.0;
        if ($stake_place < 0) $stake_place = 1.0;
        if ($stake_ew < 0) $stake_ew = 1.0;
        if ($ew_fraction <= 0 || $ew_fraction > 1) $ew_fraction = 0.25;
        if ($ew_places_override < 0 || $ew_places_override > 8) $ew_places_override = 0;
        if ($commission_pct < 0 || $commission_pct > 100) $commission_pct = 0.0;

        $result = bricks_admin_pnl_calculate($from_date, $to_date, $race_type_filter, [
            'stake_win' => $stake_win,
            'stake_place' => $stake_place,
            'stake_ew' => $stake_ew,
            'ew_fraction' => $ew_fraction,
            'ew_places_override' => $ew_places_override,
            'commission_pct' => $commission_pct,
            'enabled_strategies' => $selected_strategies,
        ]);
        $summary = $result['summary'] ?? [];

        ob_start();
        ?>
        <div style="max-width:1200px;margin:24px auto;padding:0 16px 30px;">
            <h1 style="margin:0 0 6px;color:#111827;font-size:30px;font-weight:800;">Admin P&amp;L Dashboard</h1>
            <p style="margin:0 0 14px;color:#6b7280;">Audit-grade settlement: configurable stakes, EW fraction, optional fixed place terms, and optional commission on positive net profit. 1pt = £1.</p>

            <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:12px;margin-bottom:14px;">
                <input type="hidden" name="my_admin_pnl_page" value="1" />
                <label style="font-size:12px;color:#374151;">From<br><input type="date" name="ap_from" value="<?php echo esc_attr($from_date); ?>" style="padding:8px;border:1px solid #d1d5db;border-radius:8px;"></label>
                <label style="font-size:12px;color:#374151;">To<br><input type="date" name="ap_to" value="<?php echo esc_attr($to_date); ?>" style="padding:8px;border:1px solid #d1d5db;border-radius:8px;"></label>
                <label style="font-size:12px;color:#374151;">Race Type<br><input type="text" name="ap_race_type" value="<?php echo esc_attr($race_type_filter); ?>" placeholder="Optional exact match" style="padding:8px;border:1px solid #d1d5db;border-radius:8px;"></label>
                <fieldset style="border:1px solid #e5e7eb;border-radius:8px;padding:8px 10px;min-width:230px;">
                    <legend style="font-size:12px;color:#374151;padding:0 4px;">Indicators</legend>
                    <?php foreach ($all_labels as $strategy_key => $strategy_label): ?>
                        <label style="display:block;font-size:12px;color:#111827;line-height:1.6;">
                            <input type="checkbox" name="ap_strategies[]" value="<?php echo esc_attr($strategy_key); ?>" <?php checked(in_array($strategy_key, $selected_strategies, true)); ?>>
                            <?php echo esc_html($strategy_label); ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <label style="font-size:12px;color:#374151;">Win Stake (pt)<br><input type="number" step="0.01" min="0" name="ap_stake_win" value="<?php echo esc_attr($stake_win); ?>" style="width:100px;padding:8px;border:1px solid #d1d5db;border-radius:8px;"></label>
                <label style="font-size:12px;color:#374151;">Place Stake (pt)<br><input type="number" step="0.01" min="0" name="ap_stake_place" value="<?php echo esc_attr($stake_place); ?>" style="width:100px;padding:8px;border:1px solid #d1d5db;border-radius:8px;"></label>
                <label style="font-size:12px;color:#374151;">EW Total Stake (pt)<br><input type="number" step="0.01" min="0" name="ap_stake_ew" value="<?php echo esc_attr($stake_ew); ?>" style="width:120px;padding:8px;border:1px solid #d1d5db;border-radius:8px;"></label>
                <label style="font-size:12px;color:#374151;">EW Fraction<br><input type="number" step="0.01" min="0.01" max="1" name="ap_ew_fraction" value="<?php echo esc_attr($ew_fraction); ?>" style="width:95px;padding:8px;border:1px solid #d1d5db;border-radius:8px;"></label>
                <label style="font-size:12px;color:#374151;">Fixed Places (0=auto)<br><input type="number" step="1" min="0" max="8" name="ap_ew_places" value="<?php echo esc_attr($ew_places_override); ?>" style="width:120px;padding:8px;border:1px solid #d1d5db;border-radius:8px;"></label>
                <label style="font-size:12px;color:#374151;">Commission %<br><input type="number" step="0.1" min="0" max="100" name="ap_commission" value="<?php echo esc_attr($commission_pct); ?>" style="width:100px;padding:8px;border:1px solid #d1d5db;border-radius:8px;"></label>
                <div style="align-self:flex-end;"><button type="submit" style="padding:9px 14px;border:none;border-radius:8px;background:#2563eb;color:#fff;font-weight:700;cursor:pointer;">Run P&amp;L</button></div>
            </form>

            <?php if (!empty($result['error'])): ?>
                <div style="margin-bottom:12px;padding:10px 12px;border:1px solid #fecaca;background:#fef2f2;border-radius:8px;color:#991b1b;"><?php echo esc_html($result['error']); ?></div>
            <?php endif; ?>

            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
                <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;font-size:12px;color:#374151;">Races: <strong><?php echo esc_html($result['race_count'] ?? 0); ?></strong></div>
                <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;font-size:12px;color:#374151;">Runners: <strong><?php echo esc_html($result['runner_count'] ?? 0); ?></strong></div>
            </div>

            <div style="overflow:auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;margin-bottom:16px;">
                <table style="width:100%;border-collapse:collapse;min-width:900px;">
                    <thead>
                        <tr>
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">Strategy</th>
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">Selections</th>
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">Staked (pts)</th>
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">Returns (pts)</th>
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">Hits</th>
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">Win/Place Hits</th>
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">Strike %</th>
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">Profit (pts)</th>
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">Profit (£)</th>
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">ROI %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($all_labels as $k => $base_label):
                            if (!in_array($k, $selected_strategies, true)) {
                                continue;
                            }
                            $label = $base_label;
                            if ($k === 'ew_simple' || $k === 'ew_edge') {
                                $label .= ' (0.5w/0.5p)';
                            }
                            $r = $summary[$k] ?? bricks_admin_pnl_empty_strategy();
                            $roi_class = floatval($r['roi_pct']) >= 0 ? '#065f46' : '#991b1b';
                            $profit_pts = floatval($r['profit_pts']);
                            $profit_gbp = $profit_pts; // 1pt = £1
                        ?>
                        <tr>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;font-weight:700;"><?php echo esc_html($label); ?></td>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;"><?php echo esc_html($r['selections']); ?></td>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;"><?php echo esc_html(number_format(floatval($r['staked_pts']), 2)); ?></td>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;"><?php echo esc_html(number_format(floatval($r['returns_pts']), 2)); ?></td>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;"><?php echo esc_html($r['hits']); ?></td>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;"><?php echo esc_html(intval($r['win_hits']) . '/' . intval($r['place_hits'])); ?></td>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;"><?php echo esc_html(number_format(floatval($r['strike_rate']), 2)); ?>%</td>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;color:<?php echo esc_attr($roi_class); ?>;"><?php echo esc_html(number_format($profit_pts, 2)); ?></td>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;color:<?php echo esc_attr($roi_class); ?>;">£<?php echo esc_html(number_format($profit_gbp, 2)); ?></td>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;color:<?php echo esc_attr($roi_class); ?>;font-weight:700;"><?php echo esc_html(number_format(floatval($r['roi_pct']), 2)); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
add_shortcode('admin_pnl_dashboard', 'bricks_admin_pnl_dashboard_shortcode');
