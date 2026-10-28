<?php
/**
 * Wide runner card body (Runners tab) — layout aligned with Race Page (Cards) mock.
 * Included from race detail shortcode; relies on variables prepared in the foreach loop.
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
                        <td class="runner-card-cell" colspan="24">
                            <div class="runner-card-wide<?php echo $is_top_fsr_card ? ' runner-card-wide--top' : ''; ?><?php echo $is_non_runner ? ' runner-card-wide--nr' : ''; ?><?php echo $sr_highlight ? ' runner-card-wide--sr-hi' : ''; ?>">
                                <div class="runner-card-wide__id">
                                    <div class="runner-card-wide__head">
                                        <span class="cloth-badge"><?php echo esc_html($runner->cloth_number ?: ''); ?></span>
                                        <div class="runner-card-wide__title">
                                            <?php if ($runner->runner_id): ?>
                                                <a href="<?php echo esc_url(bricks_horse_history_url($runner->runner_id)); ?>"
                                                   class="horse-name-link"
                                                   title="<?php echo esc_attr('View ' . ($runner->name ?: '') . ' racing history'); ?>">
                                                    <?php echo esc_html($runner->name ?: 'N/A'); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="runner-card-wide__name"><?php echo esc_html($runner->name ?: 'N/A'); ?></span>
                                            <?php endif; ?>
                                            <?php if ($is_non_runner): ?>
                                                <span class="non-runner-badge">NR</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <p class="runner-card-wide__sub"><?php echo esc_html($runner_origin_line); ?></p>
                                    <div class="runner-card-wide__pills">
                                        <?php if ($is_win_candidate): ?>
                                            <span class="fhor-pick-pill fhor-pick-pill--w" title="Top overall model score for this race.">Win</span>
                                        <?php endif; ?>
                                        <?php if ($is_place_candidate): ?>
                                            <span class="fhor-pick-pill fhor-pick-pill--p" title="Top 3 model-ranked runner for place profile.">Place</span>
                                        <?php endif; ?>
                                        <?php if ($is_ew_simple): ?>
                                            <span class="fhor-pick-pill fhor-pick-pill--ew" title="Simple EW pick.">EW</span>
                                        <?php endif; ?>
                                        <?php if ($is_ew_edge): ?>
                                            <span class="fhor-pick-pill fhor-pick-pill--ew" title="Edge EW pick.">EW Edge</span>
                                        <?php endif; ?>
                                        <?php if ($points_edge > 0): ?>
                                            <span class="fhor-pick-pill fhor-pick-pill--edge" title="Model vs market edge.">Edge <?php echo esc_html(number_format($points_edge, 1)); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="runner-card-wide__act">
                                        <?php
                                        echo bricks_tracker_render_horse_widget(
                                            $runner->name ?: '',
                                            [
                                                'race_id' => $race_id,
                                                'race_date' => $race_date,
                                                'race_time' => isset($race->Time) ? $race->Time : '',
                                                'course' => isset($race->course) ? $race->course : '',
                                                'forecast_odds' => is_string($odds ?? null) ? $odds : (isset($odds) ? (string) $odds : ''),
                                            ],
                                            [
                                                'show_latest_flag' => true,
                                                'show_bet_tracker' => true,
                                                'wrapper_class' => 'tracker-race-row',
                                            ]
                                        );
                                        ?>
                                    </div>
                                </div>
                                <div class="runner-card-wide__body">
                                    <div class="runner-card-wide__rate">
                                        <div class="runner-card-wide__metric runner-card-wide__metric--hi">
                                            <small>FSr</small>
                                            <b class="<?php echo (floatval($fsr) >= 80) ? 'green-text' : ''; ?>"><?php echo esc_html($fsr); ?></b>
                                        </div>
                                        <div class="runner-card-wide__metric">
                                            <small>FSRr</small>
                                            <b><?php echo esc_html($fsrr); ?></b>
                                        </div>
                                        <div class="runner-card-wide__metric">
                                            <small>Pts</small>
                                            <b title="<?php echo esc_attr('Model score 0-100. Reasons: ' . $points_reasons_text); ?>"><?php echo esc_html(number_format($points_score, 1)); ?></b>
                                        </div>
                                        <div class="runner-card-wide__metric">
                                            <small>Comb</small>
                                            <b class="<?php echo ($comb_num >= 70) ? 'green-text' : ''; ?>"><?php echo esc_html($comb); ?></b>
                                            <?php if ($comb_width > 0): ?>
                                                <span class="fhor-meter" aria-hidden="true"><i style="width:<?php echo esc_attr((string) $comb_width); ?>%"></i></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="runner-card-wide__metric">
                                            <small>Forecast</small>
                                            <b><?php echo esc_html($odds ?: 'N/A'); ?></b>
                                        </div>
                                    </div>
                                    <div class="runner-card-wide__groups">
                                        <section>
                                            <h4>Record</h4>
                                            <?php if (!$is_national_hunt): ?>
                                            <div class="runner-card-kv"><span>Stall</span><b><?php echo esc_html($stall_number ?: '—'); ?></b></div>
                                            <div class="runner-card-kv"><span>DB%</span><b class="<?php echo (floatval(str_replace('%', '', $draw_bias_pct)) >= 12) ? 'green-text' : ''; ?>"><?php echo esc_html($draw_bias_pct); ?></b></div>
                                            <?php endif; ?>
                                            <?php if (!$is_tomorrow_race): ?>
                                            <div class="runner-card-kv"><span>Win%</span><b class="<?php echo (floatval(str_replace('%', '', $win_percentage)) >= 60) ? 'green-text' : ''; ?>"><?php echo esc_html($win_percentage); ?></b></div>
                                            <div class="runner-card-kv"><span>Pla%</span><b class="<?php echo (floatval(str_replace('%', '', $place_percentage)) >= 60) ? 'green-text' : ''; ?>"><?php echo esc_html($place_percentage); ?></b></div>
                                            <?php endif; ?>
                                            <div class="runner-card-kv"><span>Form</span><b><?php echo esc_html($form); ?></b></div>
                                            <div class="runner-card-kv"><span>DSLR</span><b class="<?php echo (floatval($dslr) >= 50) ? 'neg' : ''; ?>"><?php echo esc_html($dslr); ?></b></div>
                                        </section>
                                        <section>
                                            <h4>Profile</h4>
                                            <div class="runner-card-kv"><span>Weight</span><b><?php echo esc_html($weight_formatted); ?></b></div>
                                            <div class="runner-card-kv"><span>C/D/G</span><b><?php echo esc_html($wins_display); ?></b></div>
                                            <div class="runner-card-kv"><span>LBF</span><b class="<?php echo (floatval($lbf) >= 1) ? 'green-text' : ''; ?>"><?php echo esc_html($lbf); ?></b></div>
                                            <?php if ($show_maturity_edge && $maturity_edge): ?>
                                            <div class="runner-card-kv"><span>Mat</span><b class="pos" title="<?php echo esc_attr($maturity_edge['tooltip']); ?>"><?php echo esc_html(($maturity_edge['score'] > 0 ? '+' : '') . $maturity_edge['score']); ?></b></div>
                                            <?php endif; ?>
                                            <div class="runner-card-kv"><span>Class</span><b class="<?php echo esc_attr($cls_val_class); ?>"><?php echo esc_html($cls); ?></b></div>
                                            <div class="runner-card-kv"><span>OR +/-</span><b class="<?php echo esc_attr($or_val_class); ?>"><?php echo esc_html($or_diff); ?></b></div>
                                        </section>
                                        <section>
                                            <h4>Pedigree</h4>
                                            <div class="runner-card-kv">
                                                <span>Lin5</span>
                                                <b title="<?php echo esc_attr($sire_5y_title); ?>">
                                                    <span class="lin5-cell">
                                                        <span class="lin5-cell__pct<?php echo ($sire_5y_pct !== null && $sire_5y_pct >= 14) ? ' lin5-cell__pct--hi' : ''; ?>"><?php echo esc_html($sire_5y_display); ?></span>
                                                        <span class="lin5-quality-badge <?php echo esc_attr($sire_5y_quality['class']); ?>"><?php echo esc_html($sire_5y_quality['label']); ?></span>
                                                    </span>
                                                </b>
                                            </div>
                                            <div class="runner-card-kv"><span>DI / CD</span><b class="blue" title="<?php echo esc_attr($dosage_tooltip); ?>"><?php echo esc_html($dosage_kv); ?></b></div>
                                        </section>
                                        <section>
                                            <h4>Connections</h4>
                                            <div class="runner-card-kv">
                                                <span>Jockey</span>
                                                <b><?php echo esc_html($runner->jockey_name ?: 'N/A'); ?><?php if ($runner->jockey_claim): ?> <span class="runner-card-wide__sub">(<?php echo esc_html($runner->jockey_claim); ?>lb)</span><?php endif; ?></b>
                                            </div>
                                            <div class="runner-card-kv"><span>Trainer</span><b><?php
                                                $tc_profile_code = function_exists('fhor_tc_race_profile_code')
                                                    ? fhor_tc_race_profile_code(!empty($is_national_hunt))
                                                    : null;
                                                if (function_exists('fhor_tc_trainer_link_html')) {
                                                    echo fhor_tc_trainer_link_html($runner->trainer_name ?: 'N/A', $tc_profile_code);
                                                } else {
                                                    echo esc_html($runner->trainer_name ?: 'N/A');
                                                }
                                            ?></b></div>
                                            <div class="runner-card-kv"><span>Course 5Y</span><b><?php echo esc_html($trainer_course_kv); ?></b></div>
                                            <div class="runner-card-kv"><span>RTF</span><b class="<?php echo (floatval(str_replace('%', '', $rft_percentage)) >= 40) ? 'green-text' : ''; ?>"><?php echo esc_html($rft_percentage); ?></b></div>
                                        </section>
                                    </div>
                                </div>
                                <button type="button" class="toggle-details-btn runner-card-wide__det" data-runner-index="<?php echo esc_attr($index); ?>" aria-expanded="false" aria-controls="runner-details-<?php echo esc_attr($index); ?>">
                                    <span class="toggle-icon" aria-hidden="true">▼</span>
                                    <span class="toggle-details-btn__label">Details</span>
                                </button>
                                <div class="runner-card-wide__more" id="runner-details-<?php echo esc_attr($index); ?>">
