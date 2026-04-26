<?php
/**
 * Daily Race Comment History Page Implementation
 */

// ==============================================
// RACE COMMENT HELPER FUNCTIONS
// ==============================================

if (!function_exists('get_race_comment_details')) {
    function get_race_comment_details($race_id) {
        global $wpdb;
        $race_id = intval($race_id);
        if ($race_id <= 0) return null;

        $key = 'race_comment_details_' . $race_id;
        $cached = get_transient($key);
        if ($cached !== false) return $cached;

        // Get race details from daily_race_comment_history
        $race_details = $wpdb->get_row($wpdb->prepare(
            "SELECT meeting_date, race_type, going, class
             FROM daily_race_comment_history 
             WHERE race_id = %d 
             LIMIT 1",
            $race_id
        ));

        if ($race_details) {
            set_transient($key, $race_details, 2 * HOUR_IN_SECONDS);
        }
        return $race_details;
    }
}

if (!function_exists('get_race_comment_runners')) {
    function get_race_comment_runners($race_id) {
        global $wpdb;
        $race_id = intval($race_id);
        if ($race_id <= 0) return [];

        $key = 'race_comment_runners_' . $race_id;
        $cached = get_transient($key);
        if ($cached !== false) return $cached;

        // Get all runners for this race from daily_race_comment_history
        $runners = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                runner_id,
                name,
                finish_position,
                distance_beaten,
                official_rating,
                wt_speed_rating,
                legacy_speed_rating,
                speed_rating,
                in_race_comment,
                form_figures,
                race_type,
                going,
                class
             FROM daily_race_comment_history 
             WHERE race_id = %d 
             ORDER BY finish_position ASC, name ASC",
            $race_id
        ));

        if (!empty($runners)) {
            set_transient($key, $runners, 2 * HOUR_IN_SECONDS);
        }
        return $runners;
    }
}

// ==============================================
// RACE COMMENT SHORTCODE
// ==============================================

function race_comment_history_shortcode($atts) {
    $atts = shortcode_atts(['race_id' => ''], $atts);
    $race_id = bricks_decode_entity_id($atts['race_id'], 'race_comment');

    // Fallback to query vars if attributes are missing
    if (!$race_id) {
        $race_id = bricks_decode_entity_id(get_query_var('race_comment_id'), 'race_comment');
        if (!$race_id && !empty($_SERVER['REQUEST_URI'])) {
            if (preg_match('/race-comments\/([A-Za-z0-9_-]+)/', $_SERVER['REQUEST_URI'], $m)) {
                $race_id = bricks_decode_entity_id($m[1], 'race_comment');
            }
        }
    }

    if (!$race_id) {
        return '<div style="color:red;padding:20px;">Error: Race ID is required</div>';
    }

    // Get race details and runners
    $race_details = get_race_comment_details($race_id);
    $runners = get_race_comment_runners($race_id);
    
    // Note: daily_race_comment_history does not have a 'course' column
    $course_features = null;

    if (!$race_details) {
        return '<div style="color:red;padding:20px;">Race not found with ID: ' . esc_html($race_id) . '</div>';
    }

    ob_start();
    ?>
    <style>
        .race-comment-container {
            background: #f8f9fa;
            min-height: 100vh;
            padding: 0;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .race-comment-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
            padding: 20px;
        }
        
        .race-comment-header-card {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border-radius: 16px;
            margin-bottom: 24px;
            padding: 0;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            overflow: hidden;
        }
        
        .race-comment-header-top {
            background: rgba(0,0,0,0.2);
            padding: 24px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .race-comment-title {
            color: #ffffff;
            font-size: 28px;
            font-weight: 800;
            margin: 0 0 8px 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
        }
        
        .race-comment-subtitle {
            color: rgba(255,255,255,0.8);
            font-size: 14px;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .race-comment-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            padding: 24px;
        }
        
        .race-detail-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        
        .race-detail-item:hover {
            background: rgba(255,255,255,0.15);
            transform: translateY(-2px);
        }
        
        .race-detail-icon {
            font-size: 24px;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
        }
        
        .race-detail-content {
            flex: 1;
        }
        
        .race-detail-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.8;
            margin-bottom: 4px;
            color: rgba(255,255,255,0.8);
        }
        
        .race-detail-value {
            font-size: 16px;
            font-weight: 700;
            color: #ffffff;
        }
        
        .runners-comment-section {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.1);
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .runners-comment-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 24px;
            border-bottom: 2px solid #dee2e6;
        }
        
        .runners-comment-title {
            margin: 0 0 8px 0;
            color: #1e293b;
            font-size: 28px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .runners-comment-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        .runners-comment-table thead {
            background: linear-gradient(180deg, #f8f9fa 0%, #f1f3f5 100%);
        }
        
        .runners-comment-table th {
            padding: 16px 12px;
            text-align: left;
            font-weight: 700;
            color: #1e293b;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #dee2e6;
            white-space: nowrap;
        }
        
        .runner-comment-row {
            transition: all 0.2s ease;
            border-bottom: 1px solid #f1f3f5;
        }
        
        .runner-comment-row:hover {
            background: #f8f9fa;
            transform: scale(1.01);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .runner-comment-row:nth-child(even) {
            background: #f8f9fa;
        }
        
        .runner-comment-row:nth-child(even):hover {
            background: #f1f3f5;
        }
        
        .runners-comment-table td {
            padding: 16px 12px;
            color: #334155;
            border-bottom: none;
        }
        
        .comment-row {
            background: rgba(59, 130, 246, 0.05) !important;
        }
        
        .comment-row:hover {
            background: rgba(59, 130, 246, 0.08) !important;
        }
        
        .comment-box {
            background: rgba(59, 130, 246, 0.1);
            border-left: 4px solid #3b82f6;
            padding: 16px;
            margin: 8px 0;
            border-radius: 0 8px 8px 0;
        }
        
        .comment-label {
            color: #2563eb;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            margin-bottom: 6px;
            display: block;
        }
        
        .comment-text {
            color: #374151;
            font-size: 13px;
            line-height: 1.6;
        }
        
        .position-1 { color: #10b981; font-weight: 800; }
        .position-2-3 { color: #f59e0b; font-weight: 700; }
        .position-other { color: #6b7280; }
        
        .speed-excellent {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 6px 10px;
            border-radius: 6px;
            font-weight: 800;
            font-size: 12px;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }
        
        .speed-good {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 6px 10px;
            border-radius: 6px;
            font-weight: 800;
            font-size: 12px;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
        }
        
        .speed-average {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 6px 10px;
            border-radius: 6px;
            font-weight: 800;
            font-size: 12px;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
        }

        .horse-name-link {
            color: #2563eb !important;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .horse-name-link:hover {
            color: #1d4ed8 !important;
            text-decoration: underline;
        }
        
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }
        
        .no-data-icon {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .race-comment-container {
                padding: 0;
            }
            
            .race-comment-wrapper {
                padding: 12px;
            }
            
            .race-comment-header-card {
                border-radius: 12px;
                margin-bottom: 20px;
            }
            
            .race-comment-header-top {
                padding: 16px;
            }

            .race-comment-title {
                font-size: 20px;
            }
            
            .race-comment-subtitle {
                font-size: 12px;
            }

            .race-comment-details-grid {
                grid-template-columns: 1fr;
                gap: 12px;
                padding: 16px;
            }
            
            .race-detail-item {
                padding: 10px;
            }
            
            .race-detail-icon {
                font-size: 20px;
            }
            
            .race-detail-value {
                font-size: 14px;
            }
            
            .runners-comment-section {
                border-radius: 12px;
            }
            
            .runners-comment-header {
                padding: 16px;
            }
            
            .runners-comment-title {
                font-size: 20px;
            }
            
            .runners-comment-section {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .runners-comment-table {
                font-size: 12px;
                min-width: 1000px; /* Ensure table doesn't get too compressed */
            }

            .runners-comment-table th,
            .runners-comment-table td {
                padding: 10px 8px;
            }
            
            .runners-comment-table th {
                font-size: 10px;
            }
            
            .comment-box {
                padding: 12px;
            }
            
            .comment-text {
                font-size: 12px;
            }
            
            .speed-excellent,
            .speed-good,
            .speed-average {
                font-size: 11px;
                padding: 4px 8px;
            }
        }
        
        @media (max-width: 480px) {
            .race-comment-wrapper {
                padding: 8px;
            }
            
            .race-comment-header-top {
                padding: 12px;
            }
            
            .race-comment-title {
                font-size: 18px;
            }
            
            .race-comment-details-grid {
                padding: 12px;
            }
            
            .runners-comment-header {
                padding: 12px;
            }
            
            .runners-comment-title {
                font-size: 18px;
            }
            
            .runners-comment-table {
                font-size: 11px;
            }
            
            .runners-comment-table th,
            .runners-comment-table td {
                padding: 8px 6px;
            }
        }
    </style>

    <div class="race-comment-container">
        <div class="race-comment-wrapper">
            <!-- Race Header Card -->
            <div class="race-comment-header-card">
                <div class="race-comment-header-top">
                    <h1 class="race-comment-title">🏁 Race Comments</h1>
                    <p class="race-comment-subtitle">Complete race comments and runner analysis</p>
                </div>
                
                <div class="race-comment-details-grid">
                    <div class="race-detail-item">
                        <div class="race-detail-icon">📅</div>
                        <div class="race-detail-content">
                            <div class="race-detail-label">Date</div>
                            <div class="race-detail-value"><?php echo esc_html(date('l, d M Y', strtotime($race_details->meeting_date))); ?></div>
                        </div>
                    </div>


                    <div class="race-detail-item">
                        <div class="race-detail-icon">🌤️</div>
                        <div class="race-detail-content">
                            <div class="race-detail-label">Going</div>
                            <div class="race-detail-value"><?php echo esc_html($race_details->going ?: 'N/A'); ?></div>
                        </div>
                    </div>

                    <div class="race-detail-item">
                        <div class="race-detail-icon">🏆</div>
                        <div class="race-detail-content">
                            <div class="race-detail-label">Class & Type</div>
                            <div class="race-detail-value">
                                Class <?php echo esc_html($race_details->class ?: 'N/A'); ?> • <?php echo esc_html($race_details->race_type ?: 'N/A'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Runners Comments Section -->
            <?php if (!empty($runners)): ?>
            <div class="runners-comment-section">
                <div class="runners-comment-header">
                    <h2 class="runners-comment-title">
                        <span>💬</span>
                        Race Comments & Analysis
                        <span style="background:rgba(59,130,246,0.2);color:#2563eb;padding:4px 12px;border-radius:8px;font-size:14px;margin-left:auto;">
                            <?php echo count($runners); ?> runners
                        </span>
                    </h2>
                </div>
                
                <div class="race-table-wrapper">
                    <table class="runners-comment-table">
                        <thead>
                            <tr>
                                <th style="text-align:center;">Pos</th>
                                <th>Horse</th>
                                <th style="text-align:center;">OR</th>
                                <th style="text-align:center;">SR</th>
                                <th>Form</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($runners as $index => $runner): ?>
                            <!-- Main Runner Row -->
                            <tr class="runner-comment-row">
                                <td style="text-align:center;font-weight:bold;">
                                    <?php 
                                    $pos = intval($runner->finish_position);
                                    $position_class = '';
                                    if ($pos === 1) $position_class = 'position-1';
                                    elseif ($pos <= 3) $position_class = 'position-2-3';
                                    else $position_class = 'position-other';
                                    
                                    $position = $runner->finish_position ?: 'N/A';
                                    ?>
                                    <span class="<?php echo $position_class; ?>">
                                        <?php echo esc_html($position); ?>
                                    </span>
                                    <?php if ($runner->distance_beaten && $runner->distance_beaten > 0): ?>
                                        <div style="font-size:10px;color:#6b7280;margin-top:2px;">
                                            <?php echo esc_html($runner->distance_beaten . 'L'); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <?php if ($runner->runner_id): ?>
                                        <a href="<?php echo esc_url(bricks_horse_history_url($runner->runner_id)); ?>" 
                                           class="horse-name-link"
                                           title="View <?php echo esc_attr($runner->name); ?>'s complete racing history">
                                            <?php echo esc_html($runner->name ?: 'N/A'); ?>
                                        </a>
                                    <?php else: ?>
                                        <div style="font-weight:700;color:#111827;font-size:14px;"><?php echo esc_html($runner->name ?: 'N/A'); ?></div>
                                    <?php endif; ?>
                                    <?php
                                    echo bricks_tracker_render_horse_widget(
                                        $runner->name ?: '',
                                        [
                                            'race_id' => $race_comment_id,
                                            'race_date' => isset($race_details->meeting_date) ? $race_details->meeting_date : '',
                                            'race_time' => isset($race_details->race_time) ? $race_details->race_time : '',
                                            'course' => isset($race_details->course) ? $race_details->course : ''
                                        ],
                                        [
                                            'show_latest_flag' => true,
                                            'wrapper_class' => 'tracker-comment-row'
                                        ]
                                    );
                                    ?>
                                </td>
                                
                                <td style="text-align:center;font-weight:700;color:#059669;">
                                    <?php echo esc_html($runner->official_rating ?: '-'); ?>
                                </td>
                                
                                <td style="text-align:center;">
                                    <?php 
                                    $speed_rating = $runner->speed_rating ?: $runner->wt_speed_rating ?: $runner->legacy_speed_rating;
                                    if ($speed_rating): 
                                        $sr = intval($speed_rating);
                                        $speed_class = '';
                                        if ($sr >= 80) $speed_class = 'speed-excellent';
                                        elseif ($sr >= 70) $speed_class = 'speed-good';
                                        elseif ($sr >= 60) $speed_class = 'speed-average';
                                    ?>
                                        <span class="<?php echo $speed_class; ?>">
                                            <?php echo esc_html($speed_rating); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:#9ca3af;">-</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td style="font-family:monospace;font-size:11px;color:#059669;">
                                    <?php echo esc_html($runner->form_figures ? substr($runner->form_figures, 0, 10) : '-'); ?>
                                </td>
                            </tr>
                            
                            <!-- Comment Row -->
                            <tr class="comment-row">
                                <td colspan="9" style="padding:0 12px 16px 12px;">
                                    <?php if ($runner->in_race_comment): ?>
                                        <div class="comment-box">
                                            <span class="comment-label">Race Comment:</span>
                                            <div class="comment-text"><?php echo esc_html($runner->in_race_comment); ?></div>
                                        </div>
                                    <?php else: ?>
                                        <div style="background:rgba(107,114,128,0.1);border-left:4px solid #6b7280;padding:12px 16px;margin:8px 0;border-radius:0 8px 8px 0;">
                                            <span style="color:#6b7280;font-size:12px;font-style:italic;">No race comment available</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="runners-comment-section">
                <div class="no-data">
                    <div class="no-data-icon">🔍</div>
                    <h3 style="color:#6b7280;margin:0 0 8px 0;">No runners found</h3>
                    <p style="color:#9ca3af;margin:0;">No runner comments are available for this race.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    return $content;
}

// Register the shortcode
add_shortcode('race_comment_history', 'race_comment_history_shortcode');
