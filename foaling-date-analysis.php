<?php
/**
 * Foaling Date Analysis for 2-Year-Old Racing Performance
 * ========================================================
 * 
 * THESIS (from "After an Early Foal" - The Owner Breeder):
 * In thoroughbred racing, all horses share an official birthday of January 1st.
 * A foal born on Jan 1st vs one born June 30th are both "2 years old" but the
 * January foal is ~6 months more physically mature. This "relative age effect"
 * gives early-born foals a significant advantage in 2YO races, especially
 * early in the season. The advantage should diminish as horses get older.
 *
 * This script backtests that theory against your actual database.
 * 
 * Upload to your theme directory and access via:
 * https://fhor.site/wp-content/themes/Avatar/foaling-date-analysis.php
 */

// Load WordPress
$wp_load_paths = [
    dirname(__FILE__) . '/../../../wp-load.php',
    dirname(__FILE__) . '/../../../../wp-load.php',
];
$loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $loaded = true;
        break;
    }
}
if (!$loaded) {
    die('Could not load WordPress. Please check the path.');
}

global $wpdb;

// ========== STYLES ==========
?>
<!DOCTYPE html>
<html>
<head>
    <title>🐴 Foaling Date Analysis - 2YO Performance</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f0f2f5; color: #1e293b; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        h1 { text-align: center; font-size: 28px; margin-bottom: 8px; color: #1e293b; }
        .subtitle { text-align: center; color: #64748b; margin-bottom: 30px; font-size: 14px; }
        .card { background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 30px; margin-bottom: 24px; }
        .card h2 { font-size: 20px; margin-bottom: 16px; color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
        .card h3 { font-size: 16px; margin: 16px 0 10px; color: #334155; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background: #f1f5f9; padding: 10px 12px; text-align: left; font-weight: 700; color: #475569; border-bottom: 2px solid #e2e8f0; }
        td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; }
        tr:hover { background: #f8fafc; }
        .highlight { background: #f0fdf4 !important; font-weight: 600; }
        .negative { color: #ef4444; }
        .positive { color: #10b981; font-weight: 600; }
        .neutral { color: #64748b; }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px; }
        .stat-box { background: linear-gradient(135deg, #f8fafc, #f1f5f9); border-radius: 8px; padding: 16px; text-align: center; border-left: 4px solid #3b82f6; }
        .stat-box .value { font-size: 28px; font-weight: 800; color: #1e293b; }
        .stat-box .label { font-size: 12px; color: #64748b; text-transform: uppercase; margin-top: 4px; }
        .chart-container { position: relative; height: 400px; margin: 20px 0; }
        .finding { background: #fefce8; border-left: 4px solid #eab308; padding: 16px; border-radius: 0 8px 8px 0; margin: 16px 0; }
        .finding.confirmed { background: #f0fdf4; border-left-color: #22c55e; }
        .finding.disproved { background: #fef2f2; border-left-color: #ef4444; }
        .finding strong { display: block; margin-bottom: 4px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; }
        .badge-early { background: #dbeafe; color: #1d4ed8; }
        .badge-mid { background: #fef3c7; color: #92400e; }
        .badge-late { background: #fee2e2; color: #991b1b; }
        .section-note { font-size: 13px; color: #64748b; font-style: italic; margin-bottom: 16px; }
    </style>
</head>
<body>
<div class="container">
    <h1>🐴 Foaling Date & 2YO Performance Analysis</h1>
    <p class="subtitle">Backtesting the "Relative Age Effect" theory against your database | Generated: <?php echo date('d M Y H:i'); ?></p>

<?php

// ========== ANALYSIS CONTROLS (LOCKED WINDOW FOR REPRODUCIBLE RESULTS) ==========
$default_to = date('Y-m-d', strtotime('yesterday'));
$default_from = date('Y-m-d', strtotime('-5 years', strtotime($default_to)));

$analysis_from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'])
    ? $_GET['from']
    : $default_from;
$analysis_to = isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'])
    ? $_GET['to']
    : $default_to;

// Guard against inverted ranges
if (strtotime($analysis_from) > strtotime($analysis_to)) {
    $tmp = $analysis_from;
    $analysis_from = $analysis_to;
    $analysis_to = $tmp;
}

$analysis_from_sql = esc_sql($analysis_from);
$analysis_to_sql = esc_sql($analysis_to);

// Date parser used across speed table (Date can be d-m-Y or Y-m-d)
$analysis_race_date_expr = "COALESCE(STR_TO_DATE(sp.Date, '%d-%m-%Y'), STR_TO_DATE(sp.Date, '%Y-%m-%d'))";

// Flat-focused filter aligned to the article brief (exclude obvious NH race types)
$flat_filter_sql = "
    AND sp.race_type IS NOT NULL
    AND sp.race_type != ''
    AND LOWER(sp.race_type) NOT LIKE '%hurdle%'
    AND LOWER(sp.race_type) NOT LIKE '%chase%'
    AND LOWER(sp.race_type) NOT LIKE '%nh%'
    AND LOWER(sp.race_type) NOT LIKE '%national hunt%'
";

// Reproducibility note
echo '<div class="card">';
echo '<h2>🎯 Locked Backtest Scope (Article Brief)</h2>';
echo '<p class="section-note">This section is the stable backtest baseline. Other sections below are exploratory and can change as fresh rows are added to live tables.</p>';
echo '<div class="stat-grid">';
echo '<div class="stat-box"><div class="value">' . esc_html($analysis_from) . '</div><div class="label">From Date</div></div>';
echo '<div class="stat-box"><div class="value">' . esc_html($analysis_to) . '</div><div class="label">To Date</div></div>';
echo '<div class="stat-box"><div class="value">2YO</div><div class="label">Age Filter</div></div>';
echo '<div class="stat-box"><div class="value">Flat</div><div class="label">Race-Type Filter</div></div>';
echo '<div class="stat-box"><div class="value">Mar-Oct</div><div class="label">Season Window</div></div>';
echo '</div>';
echo '<p class="section-note">Tip: append <code>?from=YYYY-MM-DD&to=YYYY-MM-DD</code> to freeze any exact comparison period.</p>';
echo '</div>';

// ========== ARTICLE-BRIEF BACKTEST ==========
echo '<div class="card">';
echo '<h2>📌 Backtest: Early vs Late Foals in 2YO Flat Races</h2>';
echo '<p class="section-note">Testing the article hypothesis: early foals (Jan-Feb) should outperform late foals (May+) in 2YO Flat races, with a stronger edge in early season (Mar-Jun).</p>';

$brief_backtest = $wpdb->get_results("
    SELECT
        CASE
            WHEN MONTH(sp.foaling_date) IN (1, 2) THEN 'Early (Jan-Feb)'
            WHEN MONTH(sp.foaling_date) IN (3, 4) THEN 'Mid (Mar-Apr)'
            ELSE 'Late (May+)'
        END AS foal_group,
        CASE
            WHEN MONTH($analysis_race_date_expr) BETWEEN 3 AND 6 THEN 'Early Season (Mar-Jun)'
            WHEN MONTH($analysis_race_date_expr) BETWEEN 7 AND 10 THEN 'Late Season (Jul-Oct)'
            ELSE 'Off Season'
        END AS season_phase,
        COUNT(*) AS total_runs,
        SUM(CASE WHEN hr.finish_position = 1 THEN 1 ELSE 0 END) AS wins,
        ROUND(SUM(CASE WHEN hr.finish_position = 1 THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) AS win_pct,
        SUM(CASE WHEN hr.finish_position <= 3 THEN 1 ELSE 0 END) AS places,
        ROUND(SUM(CASE WHEN hr.finish_position <= 3 THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) AS place_pct,
        ROUND(AVG(sp.fhorsite_rating), 2) AS avg_fsr
    FROM `speed&performance_table` sp
    INNER JOIN `historic_runners` hr
        ON sp.race_id = hr.race_id
       AND sp.runner_id = hr.runner_id
    WHERE sp.age = 2
      AND sp.foaling_date IS NOT NULL
      AND sp.foaling_date != ''
      AND sp.foaling_date != '0000-00-00'
      AND hr.finish_position IS NOT NULL
      AND hr.finish_position > 0
      AND $analysis_race_date_expr IS NOT NULL
      AND $analysis_race_date_expr BETWEEN '{$analysis_from_sql}' AND '{$analysis_to_sql}'
      AND MONTH($analysis_race_date_expr) BETWEEN 3 AND 10
      $flat_filter_sql
    GROUP BY foal_group, season_phase
    HAVING season_phase != 'Off Season'
    ORDER BY FIELD(season_phase, 'Early Season (Mar-Jun)', 'Late Season (Jul-Oct)'),
             FIELD(foal_group, 'Early (Jan-Feb)', 'Mid (Mar-Apr)', 'Late (May+)')
");

if (!empty($brief_backtest)) {
    echo '<table>';
    echo '<tr><th>Season Phase</th><th>Foal Group</th><th>Runs</th><th>Wins</th><th>Win%</th><th>Places</th><th>Place%</th><th>Avg FSr</th></tr>';
    foreach ($brief_backtest as $r) {
        $is_early = strpos($r->foal_group, 'Early') !== false;
        echo '<tr' . ($is_early ? ' class="highlight"' : '') . '>';
        echo '<td>' . esc_html($r->season_phase) . '</td>';
        echo '<td>' . esc_html($r->foal_group) . '</td>';
        echo '<td>' . number_format($r->total_runs) . '</td>';
        echo '<td>' . number_format($r->wins) . '</td>';
        echo '<td>' . esc_html($r->win_pct) . '%</td>';
        echo '<td>' . number_format($r->places) . '</td>';
        echo '<td>' . esc_html($r->place_pct) . '%</td>';
        echo '<td>' . esc_html($r->avg_fsr) . '</td>';
        echo '</tr>';
    }
    echo '</table>';

    $phase_group = [];
    foreach ($brief_backtest as $row) {
        $phase = $row->season_phase;
        $group = $row->foal_group;
        $phase_group[$phase][$group] = floatval($row->win_pct);
    }

    $early_phase_gap = null;
    $late_phase_gap = null;
    if (isset($phase_group['Early Season (Mar-Jun)']['Early (Jan-Feb)']) && isset($phase_group['Early Season (Mar-Jun)']['Late (May+)'])) {
        $early_phase_gap = $phase_group['Early Season (Mar-Jun)']['Early (Jan-Feb)'] - $phase_group['Early Season (Mar-Jun)']['Late (May+)'];
    }
    if (isset($phase_group['Late Season (Jul-Oct)']['Early (Jan-Feb)']) && isset($phase_group['Late Season (Jul-Oct)']['Late (May+)'])) {
        $late_phase_gap = $phase_group['Late Season (Jul-Oct)']['Early (Jan-Feb)'] - $phase_group['Late Season (Jul-Oct)']['Late (May+)'];
    }

    if ($early_phase_gap !== null && $late_phase_gap !== null) {
        if ($early_phase_gap > 0) {
            echo '<div class="finding confirmed">';
            echo '<strong>✅ Backtest signal found</strong>';
            echo 'Early-foal win-rate edge in Mar-Jun: <strong>' . round($early_phase_gap, 2) . ' pts</strong>. ';
            echo 'Late-season edge (Jul-Oct): <strong>' . round($late_phase_gap, 2) . ' pts</strong>. ';
            echo 'This is directionally consistent when the early-season gap is larger.';
            echo '</div>';
        } else {
            echo '<div class="finding disproved">';
            echo '<strong>❌ No early-foal edge in this window</strong>';
            echo 'Early-foal minus late-foal win-rate in Mar-Jun is <strong>' . round($early_phase_gap, 2) . ' pts</strong>.';
            echo '</div>';
        }
    }
} else {
    echo '<p>No qualifying rows for the locked backtest scope. Try widening the date range via query params.</p>';
}

echo '</div>';

// ========== QUERY 1: Data Availability Check ==========
echo '<div class="card">';
echo '<h2>📋 Step 1: Data Availability</h2>';

// Check how many records have foaling_date in speed&performance_table
$sp_total = $wpdb->get_var("SELECT COUNT(*) FROM `speed&performance_table`");
$sp_with_foaling = $wpdb->get_var("SELECT COUNT(*) FROM `speed&performance_table` WHERE foaling_date IS NOT NULL AND foaling_date != '' AND foaling_date != '0000-00-00'");
$sp_2yo = $wpdb->get_var("SELECT COUNT(*) FROM `speed&performance_table` WHERE age = 2");
$sp_2yo_with_foaling = $wpdb->get_var("SELECT COUNT(*) FROM `speed&performance_table` WHERE age = 2 AND foaling_date IS NOT NULL AND foaling_date != '' AND foaling_date != '0000-00-00'");

// Check daily_comment_history
$dch_total = $wpdb->get_var("SELECT COUNT(*) FROM daily_comment_history");
$dch_with_foaling = $wpdb->get_var("SELECT COUNT(*) FROM daily_comment_history WHERE foaling_date IS NOT NULL AND foaling_date != '' AND foaling_date != '0000-00-00'");

// Check historic_runners for finish positions
$hr_total = $wpdb->get_var("SELECT COUNT(*) FROM historic_runners");

// Sample foaling dates to understand format
$sample_dates = $wpdb->get_results("SELECT foaling_date, name, age FROM `speed&performance_table` WHERE foaling_date IS NOT NULL AND foaling_date != '' AND foaling_date != '0000-00-00' AND age = 2 LIMIT 10");

echo '<div class="stat-grid">';
echo '<div class="stat-box"><div class="value">' . number_format($sp_total) . '</div><div class="label">Speed & Perf Records</div></div>';
echo '<div class="stat-box"><div class="value">' . number_format($sp_with_foaling) . '</div><div class="label">With Foaling Date</div></div>';
echo '<div class="stat-box"><div class="value">' . number_format($sp_2yo) . '</div><div class="label">2YO Runners</div></div>';
echo '<div class="stat-box"><div class="value">' . number_format($sp_2yo_with_foaling) . '</div><div class="label">2YO with Foaling Date</div></div>';
echo '<div class="stat-box"><div class="value">' . number_format($dch_total) . '</div><div class="label">Comment History Records</div></div>';
echo '<div class="stat-box"><div class="value">' . number_format($hr_total) . '</div><div class="label">Historic Runners</div></div>';
echo '</div>';

if (!empty($sample_dates)) {
    echo '<h3>Sample Foaling Dates (2YOs)</h3>';
    echo '<table><tr><th>Horse</th><th>Age</th><th>Foaling Date</th><th>Format</th></tr>';
    foreach ($sample_dates as $s) {
        echo '<tr><td>' . esc_html($s->name) . '</td><td>' . esc_html($s->age) . '</td><td>' . esc_html($s->foaling_date) . '</td><td>' . (strtotime($s->foaling_date) ? 'Valid' : 'Needs parsing') . '</td></tr>';
    }
    echo '</table>';
}
echo '</div>';


// ========== QUERY 2: 2YO Win Rate by Birth Month ==========
echo '<div class="card">';
echo '<h2>📊 Step 2: 2YO Win Rate by Birth Month</h2>';
echo '<p class="section-note">Testing: Do early-born foals (Jan-Feb) win more often than late-born foals (May-June) in 2YO races?</p>';

// Join speed&performance_table with historic_runners to get finish positions
// The speed table has foaling_date, age, race_id; historic_runners has finish_position
$birth_month_results = $wpdb->get_results("
    SELECT 
        MONTH(sp.foaling_date) as birth_month,
        COUNT(*) as total_runs,
        SUM(CASE WHEN hr.finish_position = 1 THEN 1 ELSE 0 END) as wins,
        SUM(CASE WHEN hr.finish_position <= 3 THEN 1 ELSE 0 END) as places,
        ROUND(SUM(CASE WHEN hr.finish_position = 1 THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as win_pct,
        ROUND(SUM(CASE WHEN hr.finish_position <= 3 THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as place_pct,
        ROUND(AVG(hr.finish_position), 2) as avg_finish_pos
    FROM `speed&performance_table` sp
    INNER JOIN `historic_runners` hr ON sp.race_id = hr.race_id AND sp.runner_id = hr.runner_id
    WHERE sp.age = 2 
        AND sp.foaling_date IS NOT NULL 
        AND sp.foaling_date != '' 
        AND sp.foaling_date != '0000-00-00'
        AND hr.finish_position IS NOT NULL
        AND hr.finish_position > 0
    GROUP BY MONTH(sp.foaling_date)
    ORDER BY MONTH(sp.foaling_date)
");

// If no results from join, try daily_comment_history instead
if (empty($birth_month_results)) {
    echo '<p class="section-note">⚠️ No results from speed table + historic_runners join. Trying daily_comment_history...</p>';
    
    $birth_month_results = $wpdb->get_results("
        SELECT 
            MONTH(foaling_date) as birth_month,
            COUNT(*) as total_runs,
            SUM(CASE WHEN finish_position = 1 THEN 1 ELSE 0 END) as wins,
            SUM(CASE WHEN finish_position <= 3 THEN 1 ELSE 0 END) as places,
            ROUND(SUM(CASE WHEN finish_position = 1 THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as win_pct,
            ROUND(SUM(CASE WHEN finish_position <= 3 THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as place_pct,
            ROUND(AVG(finish_position), 2) as avg_finish_pos
        FROM daily_comment_history
        WHERE foaling_date IS NOT NULL 
            AND foaling_date != '' 
            AND foaling_date != '0000-00-00'
            AND finish_position IS NOT NULL
            AND finish_position > 0
            AND (age_range LIKE '2%' OR age_range = '2')
        GROUP BY MONTH(foaling_date)
        ORDER BY MONTH(foaling_date)
    ");
}

// Also try with the age column from speed table matching to a results source
if (empty($birth_month_results)) {
    echo '<p class="section-note">⚠️ Trying speed table with prev_runner_win_strike as proxy...</p>';
    
    $birth_month_results = $wpdb->get_results("
        SELECT 
            MONTH(foaling_date) as birth_month,
            COUNT(*) as total_runs,
            ROUND(AVG(prev_runner_win_strike), 2) as avg_win_strike,
            ROUND(AVG(prev_runner_place_strike), 2) as avg_place_strike,
            ROUND(AVG(fhorsite_rating), 1) as avg_fsr,
            ROUND(AVG(CASE WHEN SR_LTO IS NOT NULL AND SR_LTO != '' THEN SR_LTO ELSE NULL END), 1) as avg_sr_lto
        FROM `speed&performance_table`
        WHERE age = 2 
            AND foaling_date IS NOT NULL 
            AND foaling_date != '' 
            AND foaling_date != '0000-00-00'
        GROUP BY MONTH(foaling_date)
        ORDER BY MONTH(foaling_date)
    ");
    
    if (!empty($birth_month_results) && isset($birth_month_results[0]->avg_win_strike)) {
        // Alternative display
        $month_names = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        echo '<table>';
        echo '<tr><th>Birth Month</th><th>Category</th><th>Runs</th><th>Avg Win Strike%</th><th>Avg Place Strike%</th><th>Avg FSr</th><th>Avg SR LTO</th></tr>';
        foreach ($birth_month_results as $r) {
            $m = intval($r->birth_month);
            $cat = ($m <= 2) ? '<span class="badge badge-early">EARLY</span>' : (($m <= 4) ? '<span class="badge badge-mid">MID</span>' : '<span class="badge badge-late">LATE</span>');
            $highlight = ($m <= 2) ? ' class="highlight"' : '';
            echo '<tr' . $highlight . '>';
            echo '<td>' . ($month_names[$m] ?? $m) . '</td>';
            echo '<td>' . $cat . '</td>';
            echo '<td>' . number_format($r->total_runs) . '</td>';
            echo '<td>' . $r->avg_win_strike . '%</td>';
            echo '<td>' . $r->avg_place_strike . '%</td>';
            echo '<td>' . $r->avg_fsr . '</td>';
            echo '<td>' . $r->avg_sr_lto . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        $birth_month_results = null; // Skip the normal display
    }
}

if (!empty($birth_month_results) && isset($birth_month_results[0]->win_pct)) {
    $month_names = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    
    echo '<table>';
    echo '<tr><th>Birth Month</th><th>Category</th><th>Runs</th><th>Wins</th><th>Win%</th><th>Places (Top 3)</th><th>Place%</th><th>Avg Finish Pos</th></tr>';
    
    $chart_labels = [];
    $chart_win_pct = [];
    $chart_place_pct = [];
    $chart_colors = [];
    
    foreach ($birth_month_results as $r) {
        $m = intval($r->birth_month);
        $cat = ($m <= 2) ? '<span class="badge badge-early">EARLY</span>' : (($m <= 4) ? '<span class="badge badge-mid">MID</span>' : '<span class="badge badge-late">LATE</span>');
        $highlight = ($m <= 2) ? ' class="highlight"' : '';
        echo '<tr' . $highlight . '>';
        echo '<td>' . ($month_names[$m] ?? $m) . '</td>';
        echo '<td>' . $cat . '</td>';
        echo '<td>' . number_format($r->total_runs) . '</td>';
        echo '<td>' . number_format($r->wins) . '</td>';
        echo '<td class="' . ($r->win_pct > 15 ? 'positive' : '') . '">' . $r->win_pct . '%</td>';
        echo '<td>' . number_format($r->places) . '</td>';
        echo '<td class="' . ($r->place_pct > 40 ? 'positive' : '') . '">' . $r->place_pct . '%</td>';
        echo '<td>' . $r->avg_finish_pos . '</td>';
        echo '</tr>';
        
        $chart_labels[] = $month_names[$m] ?? $m;
        $chart_win_pct[] = floatval($r->win_pct);
        $chart_place_pct[] = floatval($r->place_pct);
        $chart_colors[] = ($m <= 2) ? '#22c55e' : (($m <= 4) ? '#eab308' : '#ef4444');
    }
    echo '</table>';
    
    // Chart
    echo '<div class="chart-container"><canvas id="birthMonthChart"></canvas></div>';
    echo '<script>
    new Chart(document.getElementById("birthMonthChart"), {
        type: "bar",
        data: {
            labels: ' . json_encode($chart_labels) . ',
            datasets: [{
                label: "Win %",
                data: ' . json_encode($chart_win_pct) . ',
                backgroundColor: ' . json_encode($chart_colors) . ',
                borderWidth: 1
            }, {
                label: "Place %",
                data: ' . json_encode($chart_place_pct) . ',
                backgroundColor: "rgba(59, 130, 246, 0.5)",
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { title: { display: true, text: "2YO Win% & Place% by Birth Month", font: { size: 16 } } },
            scales: { y: { beginAtZero: true, title: { display: true, text: "Percentage (%)" } } }
        }
    });
    </script>';
}

echo '</div>';


// ========== QUERY 3: Early vs Late Foals - Grouped Comparison ==========
echo '<div class="card">';
echo '<h2>📊 Step 3: Early Foals vs Late Foals (Grouped)</h2>';
echo '<p class="section-note">Grouping: EARLY (Jan-Feb) | MID (Mar-Apr) | LATE (May+). Comparing win rates and average ratings.</p>';

$grouped_results = $wpdb->get_results("
    SELECT 
        CASE 
            WHEN MONTH(sp.foaling_date) IN (1, 2) THEN 'EARLY (Jan-Feb)'
            WHEN MONTH(sp.foaling_date) IN (3, 4) THEN 'MID (Mar-Apr)'
            ELSE 'LATE (May+)'
        END as foal_group,
        COUNT(*) as total_runs,
        ROUND(AVG(sp.prev_runner_win_strike), 2) as avg_win_strike,
        ROUND(AVG(sp.prev_runner_place_strike), 2) as avg_place_strike,
        ROUND(AVG(sp.fhorsite_rating), 1) as avg_fsr,
        ROUND(AVG(CASE WHEN sp.SR_LTO IS NOT NULL AND sp.SR_LTO != '' AND sp.SR_LTO != 0 THEN sp.SR_LTO ELSE NULL END), 1) as avg_sr_lto,
        ROUND(AVG(sp.Betwise_speed_rating_LTO), 1) as avg_bsr_lto
    FROM `speed&performance_table` sp
    WHERE sp.age = 2 
        AND sp.foaling_date IS NOT NULL 
        AND sp.foaling_date != '' 
        AND sp.foaling_date != '0000-00-00'
    GROUP BY foal_group
    ORDER BY FIELD(foal_group, 'EARLY (Jan-Feb)', 'MID (Mar-Apr)', 'LATE (May+)')
");

if (!empty($grouped_results)) {
    echo '<table>';
    echo '<tr><th>Foal Group</th><th>Total Runs</th><th>Avg Win Strike%</th><th>Avg Place Strike%</th><th>Avg FSr</th><th>Avg SR LTO</th><th>Avg BSR LTO</th></tr>';
    foreach ($grouped_results as $r) {
        $is_early = strpos($r->foal_group, 'EARLY') !== false;
        echo '<tr' . ($is_early ? ' class="highlight"' : '') . '>';
        echo '<td><strong>' . $r->foal_group . '</strong></td>';
        echo '<td>' . number_format($r->total_runs) . '</td>';
        echo '<td>' . $r->avg_win_strike . '%</td>';
        echo '<td>' . $r->avg_place_strike . '%</td>';
        echo '<td>' . $r->avg_fsr . '</td>';
        echo '<td>' . $r->avg_sr_lto . '</td>';
        echo '<td>' . $r->avg_bsr_lto . '</td>';
        echo '</tr>';
    }
    echo '</table>';

    // Chart
    $g_labels = array_map(function($r) { return $r->foal_group; }, $grouped_results);
    $g_win = array_map(function($r) { return floatval($r->avg_win_strike); }, $grouped_results);
    $g_place = array_map(function($r) { return floatval($r->avg_place_strike); }, $grouped_results);
    $g_fsr = array_map(function($r) { return floatval($r->avg_fsr); }, $grouped_results);
    
    echo '<div class="chart-container"><canvas id="groupedChart"></canvas></div>';
    echo '<script>
    new Chart(document.getElementById("groupedChart"), {
        type: "bar",
        data: {
            labels: ' . json_encode($g_labels) . ',
            datasets: [{
                label: "Avg Win Strike %",
                data: ' . json_encode($g_win) . ',
                backgroundColor: ["#22c55e", "#eab308", "#ef4444"],
                borderWidth: 1
            }, {
                label: "Avg Place Strike %",
                data: ' . json_encode($g_place) . ',
                backgroundColor: ["rgba(34,197,94,0.4)", "rgba(234,179,8,0.4)", "rgba(239,68,68,0.4)"],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { title: { display: true, text: "2YO: Early vs Mid vs Late Foals - Strike Rates", font: { size: 16 } } },
            scales: { y: { beginAtZero: true, title: { display: true, text: "Percentage (%)" } } }
        }
    });
    </script>';
} else {
    echo '<p>No grouped data available.</p>';
}

echo '</div>';


// ========== QUERY 4: Does the Effect Diminish with Age? ==========
echo '<div class="card">';
echo '<h2>📊 Step 4: Does the Advantage Diminish with Age?</h2>';
echo '<p class="section-note">If the theory is correct, early foals should dominate at age 2 but the gap should close at ages 3, 4, 5+.</p>';

$age_comparison = $wpdb->get_results("
    SELECT 
        sp.age,
        CASE 
            WHEN MONTH(sp.foaling_date) IN (1, 2) THEN 'Early (Jan-Feb)'
            WHEN MONTH(sp.foaling_date) IN (3, 4) THEN 'Mid (Mar-Apr)'
            ELSE 'Late (May+)'
        END as foal_group,
        COUNT(*) as total_runs,
        ROUND(AVG(sp.prev_runner_win_strike), 2) as avg_win_strike,
        ROUND(AVG(sp.prev_runner_place_strike), 2) as avg_place_strike,
        ROUND(AVG(sp.fhorsite_rating), 1) as avg_fsr
    FROM `speed&performance_table` sp
    WHERE sp.foaling_date IS NOT NULL 
        AND sp.foaling_date != '' 
        AND sp.foaling_date != '0000-00-00'
        AND sp.age BETWEEN 2 AND 5
    GROUP BY sp.age, foal_group
    ORDER BY sp.age, FIELD(foal_group, 'Early (Jan-Feb)', 'Mid (Mar-Apr)', 'Late (May+)')
");

if (!empty($age_comparison)) {
    echo '<table>';
    echo '<tr><th>Age</th><th>Foal Group</th><th>Runs</th><th>Avg Win Strike%</th><th>Avg Place Strike%</th><th>Avg FSr</th></tr>';
    $prev_age = null;
    foreach ($age_comparison as $r) {
        if ($prev_age !== null && $prev_age != $r->age) {
            echo '<tr><td colspan="6" style="background:#e2e8f0;height:2px;padding:0;"></td></tr>';
        }
        $is_early = strpos($r->foal_group, 'Early') !== false;
        echo '<tr' . ($is_early ? ' class="highlight"' : '') . '>';
        echo '<td><strong>' . $r->age . 'YO</strong></td>';
        echo '<td>' . $r->foal_group . '</td>';
        echo '<td>' . number_format($r->total_runs) . '</td>';
        echo '<td>' . $r->avg_win_strike . '%</td>';
        echo '<td>' . $r->avg_place_strike . '%</td>';
        echo '<td>' . $r->avg_fsr . '</td>';
        echo '</tr>';
        $prev_age = $r->age;
    }
    echo '</table>';

    // Build chart data - gap between early and late by age
    $ages_data = [];
    foreach ($age_comparison as $r) {
        $ages_data[$r->age][$r->foal_group] = floatval($r->avg_win_strike);
    }
    
    $age_labels = [];
    $early_data = [];
    $mid_data = [];
    $late_data = [];
    foreach ($ages_data as $age => $groups) {
        $age_labels[] = $age . 'YO';
        $early_data[] = $groups['Early (Jan-Feb)'] ?? 0;
        $mid_data[] = $groups['Mid (Mar-Apr)'] ?? 0;
        $late_data[] = $groups['Late (May+)'] ?? 0;
    }
    
    echo '<div class="chart-container"><canvas id="ageComparisonChart"></canvas></div>';
    echo '<script>
    new Chart(document.getElementById("ageComparisonChart"), {
        type: "line",
        data: {
            labels: ' . json_encode($age_labels) . ',
            datasets: [{
                label: "Early Foals (Jan-Feb)",
                data: ' . json_encode($early_data) . ',
                borderColor: "#22c55e",
                backgroundColor: "rgba(34,197,94,0.1)",
                borderWidth: 3,
                fill: true,
                tension: 0.3
            }, {
                label: "Mid Foals (Mar-Apr)",
                data: ' . json_encode($mid_data) . ',
                borderColor: "#eab308",
                backgroundColor: "rgba(234,179,8,0.1)",
                borderWidth: 3,
                fill: true,
                tension: 0.3
            }, {
                label: "Late Foals (May+)",
                data: ' . json_encode($late_data) . ',
                borderColor: "#ef4444",
                backgroundColor: "rgba(239,68,68,0.1)",
                borderWidth: 3,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { title: { display: true, text: "Win Strike % by Foal Group Across Ages — Does the Gap Close?", font: { size: 16 } } },
            scales: { y: { beginAtZero: true, title: { display: true, text: "Avg Win Strike %" } } }
        }
    });
    </script>';
} else {
    echo '<p>No age comparison data available.</p>';
}

echo '</div>';


// ========== QUERY 5: Early Season vs Late Season for 2YOs ==========
echo '<div class="card">';
echo '<h2>📊 Step 5: Early vs Late Season Effect for 2YOs</h2>';
echo '<p class="section-note">The article suggests early foals dominate MORE in early-season 2YO races (Mar-Jun) than late-season (Jul-Oct). Testing this...</p>';

// Try both date formats — the Date column can be d-m-Y or Y-m-d
$seasonal_effect = $wpdb->get_results("
    SELECT 
        CASE 
            WHEN MONTH(sp.foaling_date) IN (1, 2) THEN 'Early Foal'
            ELSE 'Late Foal (Mar+)'
        END as foal_type,
        CASE 
            WHEN MONTH(COALESCE(
                STR_TO_DATE(sp.Date, '%d-%m-%Y'),
                STR_TO_DATE(sp.Date, '%Y-%m-%d')
            )) BETWEEN 3 AND 6 THEN 'Early Season (Mar-Jun)'
            WHEN MONTH(COALESCE(
                STR_TO_DATE(sp.Date, '%d-%m-%Y'),
                STR_TO_DATE(sp.Date, '%Y-%m-%d')
            )) BETWEEN 7 AND 10 THEN 'Late Season (Jul-Oct)'
            ELSE 'Off Season'
        END as race_season,
        COUNT(*) as total_runs,
        SUM(CASE WHEN hr.finish_position = 1 THEN 1 ELSE 0 END) as wins,
        ROUND(SUM(CASE WHEN hr.finish_position = 1 THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as win_pct,
        ROUND(AVG(sp.prev_runner_win_strike), 2) as avg_win_strike,
        ROUND(AVG(sp.fhorsite_rating), 1) as avg_fsr
    FROM `speed&performance_table` sp
    LEFT JOIN `historic_runners` hr ON sp.race_id = hr.race_id AND sp.runner_id = hr.runner_id
    WHERE sp.age = 2 
        AND sp.foaling_date IS NOT NULL 
        AND sp.foaling_date != '' 
        AND sp.foaling_date != '0000-00-00'
    GROUP BY foal_type, race_season
    HAVING race_season != 'Off Season'
    ORDER BY FIELD(race_season, 'Early Season (Mar-Jun)', 'Late Season (Jul-Oct)'), foal_type
");

if (!empty($seasonal_effect)) {
    echo '<table>';
    echo '<tr><th>Race Season</th><th>Foal Type</th><th>Runs</th><th>Wins</th><th>Actual Win%</th><th>Avg Win Strike%</th><th>Avg FSr</th></tr>';
    foreach ($seasonal_effect as $r) {
        $is_early_foal = strpos($r->foal_type, 'Early') !== false;
        echo '<tr' . ($is_early_foal ? ' class="highlight"' : '') . '>';
        echo '<td>' . $r->race_season . '</td>';
        echo '<td>' . $r->foal_type . '</td>';
        echo '<td>' . number_format($r->total_runs) . '</td>';
        echo '<td>' . number_format($r->wins) . '</td>';
        echo '<td class="' . ($r->win_pct > 15 ? 'positive' : '') . '">' . $r->win_pct . '%</td>';
        echo '<td>' . $r->avg_win_strike . '%</td>';
        echo '<td>' . $r->avg_fsr . '</td>';
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<p>No seasonal data available. The Date column format may need adjusting.</p>';
}

echo '</div>';


// ========== QUERY 6: Race Type Breakdown ==========
echo '<div class="card">';
echo '<h2>📊 Step 6: Foaling Date Effect by Race Type (Flat vs NH)</h2>';
echo '<p class="section-note">The relative age effect should be strongest in Flat racing (where 2YOs race). Comparing with National Hunt.</p>';

$race_type_effect = $wpdb->get_results("
    SELECT 
        sp.race_type,
        CASE 
            WHEN MONTH(sp.foaling_date) IN (1, 2) THEN 'Early (Jan-Feb)'
            WHEN MONTH(sp.foaling_date) IN (3, 4) THEN 'Mid (Mar-Apr)'
            ELSE 'Late (May+)'
        END as foal_group,
        COUNT(*) as total_runs,
        ROUND(AVG(sp.prev_runner_win_strike), 2) as avg_win_strike,
        ROUND(AVG(sp.fhorsite_rating), 1) as avg_fsr
    FROM `speed&performance_table` sp
    WHERE sp.age = 2
        AND sp.foaling_date IS NOT NULL 
        AND sp.foaling_date != '' 
        AND sp.foaling_date != '0000-00-00'
        AND sp.race_type IS NOT NULL
        AND sp.race_type != ''
    GROUP BY sp.race_type, foal_group
    ORDER BY sp.race_type, FIELD(foal_group, 'Early (Jan-Feb)', 'Mid (Mar-Apr)', 'Late (May+)')
");

if (!empty($race_type_effect)) {
    echo '<table>';
    echo '<tr><th>Race Type</th><th>Foal Group</th><th>Runs</th><th>Avg Win Strike%</th><th>Avg FSr</th></tr>';
    $prev_type = null;
    foreach ($race_type_effect as $r) {
        if ($prev_type !== null && $prev_type != $r->race_type) {
            echo '<tr><td colspan="5" style="background:#e2e8f0;height:2px;padding:0;"></td></tr>';
        }
        $is_early = strpos($r->foal_group, 'Early') !== false;
        echo '<tr' . ($is_early ? ' class="highlight"' : '') . '>';
        echo '<td>' . esc_html($r->race_type) . '</td>';
        echo '<td>' . $r->foal_group . '</td>';
        echo '<td>' . number_format($r->total_runs) . '</td>';
        echo '<td>' . $r->avg_win_strike . '%</td>';
        echo '<td>' . $r->avg_fsr . '</td>';
        echo '</tr>';
        $prev_type = $r->race_type;
    }
    echo '</table>';
} else {
    echo '<p>No race type breakdown available.</p>';
}

echo '</div>';


// ========== QUERY 7: Days Between Foaling Date and Race Date ==========
echo '<div class="card">';
echo '<h2>📊 Step 7: Actual Maturity Gap — Days Old at Race Time</h2>';
echo '<p class="section-note">Rather than just birth month, let\'s look at exact days of age when racing. A Jan foal racing in April is ~120 days older than a May foal.</p>';

$race_date_expr = "COALESCE(STR_TO_DATE(sp.Date, '%d-%m-%Y'), STR_TO_DATE(sp.Date, '%Y-%m-%d'))";
$maturity_gap = $wpdb->get_results("
    SELECT 
        CASE 
            WHEN MONTH(sp.foaling_date) IN (1, 2) THEN 'Early (Jan-Feb)'
            WHEN MONTH(sp.foaling_date) IN (3, 4) THEN 'Mid (Mar-Apr)'
            ELSE 'Late (May+)'
        END as foal_group,
        COUNT(*) as total_runs,
        ROUND(AVG(DATEDIFF($race_date_expr, sp.foaling_date)), 0) as avg_days_old,
        ROUND(MIN(DATEDIFF($race_date_expr, sp.foaling_date)), 0) as min_days_old,
        ROUND(MAX(DATEDIFF($race_date_expr, sp.foaling_date)), 0) as max_days_old,
        SUM(CASE WHEN hr.finish_position = 1 THEN 1 ELSE 0 END) as wins,
        ROUND(SUM(CASE WHEN hr.finish_position = 1 THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as actual_win_pct,
        ROUND(AVG(sp.prev_runner_win_strike), 2) as avg_win_strike,
        ROUND(AVG(sp.fhorsite_rating), 1) as avg_fsr
    FROM `speed&performance_table` sp
    LEFT JOIN `historic_runners` hr ON sp.race_id = hr.race_id AND sp.runner_id = hr.runner_id
    WHERE sp.age = 2
        AND sp.foaling_date IS NOT NULL 
        AND sp.foaling_date != '' 
        AND sp.foaling_date != '0000-00-00'
    GROUP BY foal_group
    ORDER BY FIELD(foal_group, 'Early (Jan-Feb)', 'Mid (Mar-Apr)', 'Late (May+)')
");

if (!empty($maturity_gap)) {
    echo '<table>';
    echo '<tr><th>Foal Group</th><th>Runs</th><th>Avg Days Old</th><th>Youngest</th><th>Oldest</th><th>Wins</th><th>Actual Win%</th><th>Avg Win Strike%</th><th>Avg FSr</th></tr>';
    foreach ($maturity_gap as $r) {
        $is_early = strpos($r->foal_group, 'Early') !== false;
        echo '<tr' . ($is_early ? ' class="highlight"' : '') . '>';
        echo '<td><strong>' . $r->foal_group . '</strong></td>';
        echo '<td>' . number_format($r->total_runs) . '</td>';
        echo '<td>' . number_format($r->avg_days_old) . ' days</td>';
        echo '<td>' . number_format($r->min_days_old) . ' days</td>';
        echo '<td>' . number_format($r->max_days_old) . ' days</td>';
        echo '<td>' . number_format($r->wins) . '</td>';
        echo '<td class="' . ($r->actual_win_pct > 15 ? 'positive' : '') . '">' . $r->actual_win_pct . '%</td>';
        echo '<td>' . $r->avg_win_strike . '%</td>';
        echo '<td>' . $r->avg_fsr . '</td>';
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<p>Maturity gap data not available (date parsing issue).</p>';
}

echo '</div>';


// ========== QUERY 8: Direct from daily_comment_history ==========
echo '<div class="card">';
echo '<h2>📊 Step 8: Cross-Validation via daily_comment_history</h2>';
echo '<p class="section-note">Using finish_position directly from daily_comment_history table to validate findings with a different data source.</p>';

// Check if age_range contains "2" for 2yo horses
$dch_2yo = $wpdb->get_results("
    SELECT 
        MONTH(dch.foaling_date) as birth_month,
        COUNT(*) as total_runs,
        SUM(CASE WHEN dch.finish_position = 1 THEN 1 ELSE 0 END) as wins,
        SUM(CASE WHEN dch.finish_position <= 3 THEN 1 ELSE 0 END) as places,
        ROUND(SUM(CASE WHEN dch.finish_position = 1 THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as win_pct,
        ROUND(SUM(CASE WHEN dch.finish_position <= 3 THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as place_pct,
        ROUND(AVG(dch.finish_position), 2) as avg_finish_pos,
        ROUND(AVG(dch.speed_rating), 1) as avg_speed_rating
    FROM daily_comment_history dch
    WHERE dch.foaling_date IS NOT NULL 
        AND dch.foaling_date != '' 
        AND dch.foaling_date != '0000-00-00'
        AND dch.finish_position IS NOT NULL
        AND dch.finish_position > 0
        AND (dch.age_range LIKE '2%' OR dch.age_range = '2')
    GROUP BY MONTH(dch.foaling_date)
    ORDER BY MONTH(dch.foaling_date)
");

if (!empty($dch_2yo)) {
    $month_names = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    echo '<table>';
    echo '<tr><th>Birth Month</th><th>Category</th><th>Runs</th><th>Wins</th><th>Win%</th><th>Place%</th><th>Avg Finish Pos</th><th>Avg SR</th></tr>';
    
    $dch_labels = [];
    $dch_win_pct = [];
    $dch_colors = [];
    
    foreach ($dch_2yo as $r) {
        $m = intval($r->birth_month);
        $cat = ($m <= 2) ? '<span class="badge badge-early">EARLY</span>' : (($m <= 4) ? '<span class="badge badge-mid">MID</span>' : '<span class="badge badge-late">LATE</span>');
        $highlight = ($m <= 2) ? ' class="highlight"' : '';
        echo '<tr' . $highlight . '>';
        echo '<td>' . ($month_names[$m] ?? $m) . '</td>';
        echo '<td>' . $cat . '</td>';
        echo '<td>' . number_format($r->total_runs) . '</td>';
        echo '<td>' . number_format($r->wins) . '</td>';
        echo '<td class="' . ($r->win_pct > 15 ? 'positive' : '') . '">' . $r->win_pct . '%</td>';
        echo '<td>' . $r->place_pct . '%</td>';
        echo '<td>' . $r->avg_finish_pos . '</td>';
        echo '<td>' . $r->avg_speed_rating . '</td>';
        echo '</tr>';
        
        $dch_labels[] = $month_names[$m] ?? $m;
        $dch_win_pct[] = floatval($r->win_pct);
        $dch_colors[] = ($m <= 2) ? '#22c55e' : (($m <= 4) ? '#eab308' : '#ef4444');
    }
    echo '</table>';
    
    echo '<div class="chart-container"><canvas id="dchChart"></canvas></div>';
    echo '<script>
    new Chart(document.getElementById("dchChart"), {
        type: "bar",
        data: {
            labels: ' . json_encode($dch_labels) . ',
            datasets: [{
                label: "Win % (from race results)",
                data: ' . json_encode($dch_win_pct) . ',
                backgroundColor: ' . json_encode($dch_colors) . ',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { title: { display: true, text: "2YO Actual Win% by Birth Month (daily_comment_history)", font: { size: 16 } } },
            scales: { y: { beginAtZero: true, title: { display: true, text: "Win %" } } }
        }
    });
    </script>';
} else {
    echo '<p>No 2YO data found in daily_comment_history with foaling dates.</p>';
    
    // Debug: Check what age_range values exist
    $age_ranges = $wpdb->get_results("SELECT DISTINCT age_range, COUNT(*) as cnt FROM daily_comment_history WHERE foaling_date IS NOT NULL AND foaling_date != '' GROUP BY age_range ORDER BY cnt DESC LIMIT 20");
    if (!empty($age_ranges)) {
        echo '<h3>Available age_range values (with foaling date):</h3>';
        echo '<table><tr><th>age_range</th><th>Count</th></tr>';
        foreach ($age_ranges as $ar) {
            echo '<tr><td>' . esc_html($ar->age_range) . '</td><td>' . number_format($ar->cnt) . '</td></tr>';
        }
        echo '</table>';
    }
}
echo '</div>';


// ========== QUERY 9: Betting ROI Analysis ==========
echo '<div class="card">';
echo '<h2>💰 Step 9: Betting ROI — Would Backing Early Foals Be Profitable?</h2>';
echo '<p class="section-note">If early foals win more at similar prices, there may be a market inefficiency to exploit in 2YO races.</p>';

$roi_analysis = $wpdb->get_results("
    SELECT 
        CASE 
            WHEN MONTH(sp.foaling_date) IN (1, 2) THEN 'Early (Jan-Feb)'
            WHEN MONTH(sp.foaling_date) IN (3, 4) THEN 'Mid (Mar-Apr)'
            ELSE 'Late (May+)'
        END as foal_group,
        COUNT(*) as total_runs,
        SUM(CASE WHEN hr.finish_position = 1 THEN 1 ELSE 0 END) as wins,
        ROUND(SUM(CASE WHEN hr.finish_position = 1 THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as win_pct,
        ROUND(AVG(hr.starting_price_decimal), 2) as avg_sp_decimal,
        ROUND(AVG(sp.forecast_price_decimal), 2) as avg_forecast_price,
        -- Simple ROI: sum of (SP-1 for wins) minus (1 for losses), divided by total stakes
        ROUND(
            (SUM(CASE WHEN hr.finish_position = 1 AND hr.starting_price_decimal > 0 THEN hr.starting_price_decimal - 1 ELSE -1 END) / COUNT(*)) * 100
        , 2) as roi_pct
    FROM `speed&performance_table` sp
    INNER JOIN `historic_runners` hr ON sp.race_id = hr.race_id AND sp.runner_id = hr.runner_id
    WHERE sp.age = 2 
        AND sp.foaling_date IS NOT NULL 
        AND sp.foaling_date != '' 
        AND sp.foaling_date != '0000-00-00'
        AND hr.finish_position IS NOT NULL
        AND hr.finish_position > 0
        AND hr.starting_price_decimal IS NOT NULL
        AND hr.starting_price_decimal > 0
    GROUP BY foal_group
    ORDER BY FIELD(foal_group, 'Early (Jan-Feb)', 'Mid (Mar-Apr)', 'Late (May+)')
");

if (!empty($roi_analysis)) {
    echo '<table>';
    echo '<tr><th>Foal Group</th><th>Runs</th><th>Wins</th><th>Win%</th><th>Avg SP</th><th>Avg Forecast Price</th><th>ROI %</th></tr>';
    foreach ($roi_analysis as $r) {
        $is_early = strpos($r->foal_group, 'Early') !== false;
        $roi_class = floatval($r->roi_pct) > 0 ? 'positive' : 'negative';
        echo '<tr' . ($is_early ? ' class="highlight"' : '') . '>';
        echo '<td><strong>' . $r->foal_group . '</strong></td>';
        echo '<td>' . number_format($r->total_runs) . '</td>';
        echo '<td>' . number_format($r->wins) . '</td>';
        echo '<td class="' . ($r->win_pct > 15 ? 'positive' : '') . '">' . $r->win_pct . '%</td>';
        echo '<td>' . $r->avg_sp_decimal . '</td>';
        echo '<td>' . $r->avg_forecast_price . '</td>';
        echo '<td class="' . $roi_class . '">' . $r->roi_pct . '%</td>';
        echo '</tr>';
    }
    echo '</table>';
    
    echo '<div class="finding">';
    echo '<strong>💡 Interpretation:</strong> A positive ROI means backing ALL horses in that foal group to £1 level stakes would have been profitable. ';
    echo 'If early foals show a better ROI than late foals, it suggests the market undervalues the maturity advantage.';
    echo '</div>';
} else {
    echo '<p>ROI analysis requires starting_price_decimal data in historic_runners. No matching data found.</p>';
}
echo '</div>';


// ========== QUERY 10: Top individual examples ==========
echo '<div class="card">';
echo '<h2>🏇 Step 10: Notable Early Foal 2YO Winners</h2>';
echo '<p class="section-note">Looking at specific high-profile early-born 2YO winners to illustrate the effect.</p>';

$notable_early_winners = $wpdb->get_results("
    SELECT 
        sp.name as horse_name,
        sp.foaling_date,
        MONTH(sp.foaling_date) as birth_month,
        sp.Date as race_date,
        sp.course,
        sp.race_title,
        sp.class,
        sp.fhorsite_rating,
        hr.finish_position,
        hr.starting_price,
        hr.starting_price_decimal,
        sp.trainer_name,
        sp.jockey_name,
        sp.Distance
    FROM `speed&performance_table` sp
    INNER JOIN `historic_runners` hr ON sp.race_id = hr.race_id AND sp.runner_id = hr.runner_id
    WHERE sp.age = 2
        AND sp.foaling_date IS NOT NULL 
        AND sp.foaling_date != '' 
        AND sp.foaling_date != '0000-00-00'
        AND MONTH(sp.foaling_date) IN (1, 2)
        AND hr.finish_position = 1
    ORDER BY sp.fhorsite_rating DESC
    LIMIT 20
");

if (!empty($notable_early_winners)) {
    echo '<table>';
    echo '<tr><th>Horse</th><th>Foaling Date</th><th>Race Date</th><th>Course</th><th>Race</th><th>Class</th><th>FSr</th><th>SP</th><th>Trainer</th></tr>';
    foreach ($notable_early_winners as $r) {
        echo '<tr>';
        echo '<td><strong>' . esc_html($r->horse_name) . '</strong></td>';
        echo '<td>' . esc_html($r->foaling_date) . '</td>';
        echo '<td>' . esc_html($r->race_date) . '</td>';
        echo '<td>' . esc_html($r->course) . '</td>';
        echo '<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;">' . esc_html($r->race_title) . '</td>';
        echo '<td>' . esc_html($r->class) . '</td>';
        echo '<td>' . esc_html($r->fhorsite_rating) . '</td>';
        echo '<td>' . esc_html($r->starting_price) . '</td>';
        echo '<td>' . esc_html($r->trainer_name) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<p>No early foal 2YO winner data found.</p>';
}
echo '</div>';


// ========== QUERY 11: Database Column Diagnostic ==========
echo '<div class="card">';
echo '<h2>🔧 Step 11: Database Diagnostic</h2>';
echo '<p class="section-note">Showing actual column names and sample data to verify query compatibility.</p>';

// Show speed&performance_table columns
$sp_columns = $wpdb->get_results("SHOW COLUMNS FROM `speed&performance_table`");
echo '<h3>speed&performance_table columns:</h3>';
echo '<div style="max-height:300px;overflow-y:auto;">';
echo '<table>';
echo '<tr><th>#</th><th>Column</th><th>Type</th><th>Key</th></tr>';
foreach ($sp_columns as $i => $col) {
    $highlight = in_array($col->Field, ['foaling_date', 'age', 'Date', 'race_id', 'runner_id', 'name', 'race_type', 'fhorsite_rating', 'prev_runner_win_strike', 'prev_runner_place_strike']) ? ' class="highlight"' : '';
    echo '<tr' . $highlight . '>';
    echo '<td>' . ($i + 1) . '</td>';
    echo '<td>' . esc_html($col->Field) . '</td>';
    echo '<td>' . esc_html($col->Type) . '</td>';
    echo '<td>' . esc_html($col->Key) . '</td>';
    echo '</tr>';
}
echo '</table></div>';

// Show historic_runners columns
$hr_columns = $wpdb->get_results("SHOW COLUMNS FROM `historic_runners`");
echo '<h3>historic_runners columns:</h3>';
echo '<div style="max-height:200px;overflow-y:auto;">';
echo '<table>';
echo '<tr><th>#</th><th>Column</th><th>Type</th><th>Key</th></tr>';
foreach ($hr_columns as $i => $col) {
    $highlight = in_array($col->Field, ['finish_position', 'race_id', 'runner_id', 'starting_price_decimal']) ? ' class="highlight"' : '';
    echo '<tr' . $highlight . '>';
    echo '<td>' . ($i + 1) . '</td>';
    echo '<td>' . esc_html($col->Field) . '</td>';
    echo '<td>' . esc_html($col->Type) . '</td>';
    echo '<td>' . esc_html($col->Key) . '</td>';
    echo '</tr>';
}
echo '</table></div>';

// Show daily_comment_history columns
$dch_columns = $wpdb->get_results("SHOW COLUMNS FROM `daily_comment_history`");
echo '<h3>daily_comment_history columns:</h3>';
echo '<div style="max-height:200px;overflow-y:auto;">';
echo '<table>';
echo '<tr><th>#</th><th>Column</th><th>Type</th><th>Key</th></tr>';
foreach ($dch_columns as $i => $col) {
    $highlight = in_array($col->Field, ['foaling_date', 'finish_position', 'age_range', 'meeting_date', 'race_type', 'speed_rating']) ? ' class="highlight"' : '';
    echo '<tr' . $highlight . '>';
    echo '<td>' . ($i + 1) . '</td>';
    echo '<td>' . esc_html($col->Field) . '</td>';
    echo '<td>' . esc_html($col->Type) . '</td>';
    echo '<td>' . esc_html($col->Key) . '</td>';
    echo '</tr>';
}
echo '</table></div>';

echo '</div>';


// ========== SUMMARY ==========
echo '<div class="card">';
echo '<h2>📝 Summary & Conclusions</h2>';

// Auto-evaluate the thesis using ACTUAL win% from the Backtest join (historic_runners)
$early_actual_win = null;
$late_actual_win = null;
if (!empty($brief_backtest)) {
    $agg = [
        'Early (Jan-Feb)' => ['wins' => 0, 'runs' => 0],
        'Late (May+)'     => ['wins' => 0, 'runs' => 0],
    ];
    foreach ($brief_backtest as $row) {
        $fg = (string)$row->foal_group;
        if (isset($agg[$fg])) {
            $agg[$fg]['wins'] += intval($row->wins ?? 0);
            $agg[$fg]['runs'] += intval($row->total_runs ?? 0);
        }
    }
    if ($agg['Early (Jan-Feb)']['runs'] > 0) {
        $early_actual_win = round(($agg['Early (Jan-Feb)']['wins'] / $agg['Early (Jan-Feb)']['runs']) * 100, 2);
    }
    if ($agg['Late (May+)']['runs'] > 0) {
        $late_actual_win = round(($agg['Late (May+)']['wins'] / $agg['Late (May+)']['runs']) * 100, 2);
    }
}

// Fallback to proxy grouped_results only if actual backtest aggregation is unavailable
if ($early_actual_win === null || $late_actual_win === null) {
    if (!empty($grouped_results)) {
        foreach ($grouped_results as $r) {
            if (strpos($r->foal_group, 'EARLY') !== false) $early_actual_win = floatval($r->avg_win_strike);
            if (strpos($r->foal_group, 'LATE') !== false) $late_actual_win = floatval($r->avg_win_strike);
        }
    }
}

if ($early_actual_win !== null && $late_actual_win !== null) {
    $gap = $early_actual_win - $late_actual_win;
    if ($gap > 2) {
        echo '<div class="finding confirmed">';
        echo '<strong>✅ THESIS CONFIRMED: Early foals outperform late foals in 2YO races</strong>';
        echo 'Early foals (Jan-Feb) have an actual win rate of <strong>' . $early_actual_win . '%</strong> vs <strong>' . $late_actual_win . '%</strong> for late foals (May+), based on historic race results. ';
        echo 'The gap of <strong>' . round($gap, 1) . ' percentage points</strong> supports the Relative Age Effect theory.';
        echo '</div>';
    } elseif ($gap > 0) {
        echo '<div class="finding">';
        echo '<strong>⚠️ MARGINAL SUPPORT: Slight advantage for early foals</strong>';
        echo 'Actual win-rate advantage is ' . round($gap, 1) . ' percentage points over the analysis window.';
        echo '</div>';
    } else {
        echo '<div class="finding disproved">';
        echo '<strong>❌ THESIS NOT SUPPORTED: No advantage found for early foals</strong>';
        echo 'Actual win-rate gap is ' . round($gap, 1) . ' points (negative means late foals performed better).';
        echo '</div>';
    }
}

echo '<h3>What the Article Predicts:</h3>';
echo '<ol style="margin:16px 0;padding-left:20px;line-height:2;">';
echo '<li><strong>Early-born 2YO foals (Jan-Feb) outperform late-born ones (May+)</strong> — measured by win strike rate, place rate, and speed ratings</li>';
echo '<li><strong>The effect diminishes with age</strong> — the gap should narrow at 3YO, 4YO, and be negligible at 5YO</li>';
echo '<li><strong>Early-season 2YO races amplify the effect</strong> — March-June should show a bigger gap than July-October</li>';
echo '<li><strong>Flat racing shows the effect more than National Hunt</strong> — since 2YO racing is predominantly flat</li>';
echo '</ol>';

echo '<h3>If Confirmed — Proposed Frontend Implementation:</h3>';
echo '<ul style="margin:16px 0;padding-left:20px;line-height:2;">';
echo '<li>Add a <strong>"Maturity Edge"</strong> indicator on 2YO race detail pages — a visual badge showing whether each horse is an Early, Mid, or Late foal</li>';
echo '<li>Show a <strong>foaling date badge</strong> (🟢 Early / 🟡 Mid / 🔴 Late) next to each 2YO runner in the Race Runners table</li>';
echo '<li>Calculate a <strong>"Days Older Than Field Avg"</strong> metric — showing how many days older/younger each runner is compared to the average of the field</li>';
echo '<li>Add a <strong>tooltip/info panel</strong> explaining the Relative Age Effect theory to customers</li>';
echo '<li>Only display for <strong>2YO Flat races</strong> (where the effect is meaningful) — auto-detect from race conditions</li>';
echo '<li>Consider a <strong>"Maturity Advantage" chart</strong> in the race analysis section, similar to the existing Trainer % chart</li>';
echo '</ul>';
echo '<h3>⚠️ Important Caveats:</h3>';
echo '<ul style="margin:16px 0;padding-left:20px;line-height:2;color:#64748b;">';
echo '<li>Sample size matters — results are more reliable with 1000+ runners per group</li>';
echo '<li>Correlation ≠ causation — early foals may also come from better breeding programs</li>';
echo '<li>Trainers may intentionally run early foals first, creating selection bias</li>';
echo '<li>The effect may already be priced into the market (check ROI analysis above)</li>';
echo '</ul>';
echo '</div>';

?>

</div>
</body>
</html>
