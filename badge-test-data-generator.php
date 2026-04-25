<?php
/**
 * Badge Test Data Generator
 * Generates comprehensive test data for all badge types in the horse racing system
 * 
 * Usage:
 * 1. Include this file in your theme or plugin
 * 2. Use [badge_test_display] shortcode to view all badge variations
 * 3. Use generate_badge_test_data() to get programmatic test data
 * 4. Call directly: http://yoursite.com/path/to/badge-test-data-generator.php
 */

// Detect if running standalone (direct call) or as WordPress include
define('BADGE_TEST_STANDALONE', !function_exists('add_shortcode'));

// If standalone, load WordPress
if (BADGE_TEST_STANDALONE) {
    // Try to find and load WordPress
    $wp_load_paths = [
        __DIR__ . '/wp-load.php',
        __DIR__ . '/../wp-load.php',
        __DIR__ . '/../../wp-load.php',
        __DIR__ . '/../../../wp-load.php',
    ];
    
    $wp_loaded = false;
    foreach ($wp_load_paths as $wp_load) {
        if (file_exists($wp_load)) {
            require_once($wp_load);
            $wp_loaded = true;
            break;
        }
    }
    
    // If WordPress not found, run in pure standalone mode
    if (!$wp_loaded) {
        // Define minimal WordPress-like functions for standalone mode
        if (!function_exists('esc_html')) {
            function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
        }
        if (!function_exists('esc_attr')) {
            function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
        }
    }
}

// ==============================================
// MAIN TEST DATA GENERATOR
// ==============================================

/**
 * Generate comprehensive badge test data
 * Returns array of test scenarios covering all badge types
 */
function generate_badge_test_data() {
    return [
        'position_badges' => generate_position_badge_tests(),
        'speed_rating_badges' => generate_speed_rating_badge_tests(),
        'win_badges' => generate_win_badge_tests(),
        'maturity_edge_badges' => generate_maturity_edge_badge_tests(),
        'non_runner_badges' => generate_non_runner_badge_tests(),
        'cloth_number_badges' => generate_cloth_number_badge_tests(),
        'handicap_badges' => generate_handicap_badge_tests(),
        'combined_scenarios' => generate_combined_badge_scenarios(),
        'edge_cases' => get_badge_edge_cases(),
    ];
}

// ==============================================
// INDIVIDUAL BADGE GENERATORS
// ==============================================

/**
 * Generate position badge test data
 */
function generate_position_badge_tests() {
    return [
        // Winners (Gold)
        ['position' => 1, 'distance_beaten' => 0, 'label' => '1st Place (Winner)'],
        ['position' => 1, 'distance_beaten' => 0, 'label' => '1st Place (Dead Heat)', 'note' => 'Joint winner'],
        
        // Second Place (Silver)
        ['position' => 2, 'distance_beaten' => 0.5, 'label' => '2nd Place (0.5L)'],
        ['position' => 2, 'distance_beaten' => 2.0, 'label' => '2nd Place (2L)'],
        ['position' => 2, 'distance_beaten' => 5.5, 'label' => '2nd Place (5.5L)'],
        
        // Third Place (Bronze)
        ['position' => 3, 'distance_beaten' => 1.0, 'label' => '3rd Place (1L)'],
        ['position' => 3, 'distance_beaten' => 3.5, 'label' => '3rd Place (3.5L)'],
        ['position' => 3, 'distance_beaten' => 8.0, 'label' => '3rd Place (8L)'],
        
        // Other positions
        ['position' => 4, 'distance_beaten' => 10.5, 'label' => '4th Place'],
        ['position' => 5, 'distance_beaten' => 12.0, 'label' => '5th Place'],
        ['position' => 10, 'distance_beaten' => 25.5, 'label' => '10th Place'],
        ['position' => 15, 'distance_beaten' => 40.0, 'label' => '15th Place'],
        ['position' => 20, 'distance_beaten' => 60.0, 'label' => '20th Place (Last)'],
    ];
}

/**
 * Generate speed rating badge test data
 */
function generate_speed_rating_badge_tests() {
    return [
        // Excellent (≥80)
        ['fsr' => 100, 'label' => 'FSr 100 (Exceptional)', 'class' => 'speed-excellent'],
        ['fsr' => 95, 'label' => 'FSr 95 (Excellent)', 'class' => 'speed-excellent'],
        ['fsr' => 90, 'label' => 'FSr 90 (Excellent)', 'class' => 'speed-excellent'],
        ['fsr' => 85, 'label' => 'FSr 85 (Excellent)', 'class' => 'speed-excellent'],
        ['fsr' => 80, 'label' => 'FSr 80 (Excellent - Boundary)', 'class' => 'speed-excellent'],
        
        // Good (70-79)
        ['fsr' => 79, 'label' => 'FSr 79 (Good - Boundary)', 'class' => 'speed-good'],
        ['fsr' => 75, 'label' => 'FSr 75 (Good)', 'class' => 'speed-good'],
        ['fsr' => 70, 'label' => 'FSr 70 (Good)', 'class' => 'speed-good'],
        
        // Average (60-69)
        ['fsr' => 69, 'label' => 'FSr 69 (Average - Boundary)', 'class' => 'speed-average'],
        ['fsr' => 65, 'label' => 'FSr 65 (Average)', 'class' => 'speed-average'],
        ['fsr' => 60, 'label' => 'FSr 60 (Average)', 'class' => 'speed-average'],
        
        // Low (<60)
        ['fsr' => 59, 'label' => 'FSr 59 (Below Average)', 'class' => 'speed-low'],
        ['fsr' => 50, 'label' => 'FSr 50 (Low)', 'class' => 'speed-low'],
        ['fsr' => 30, 'label' => 'FSr 30 (Very Low)', 'class' => 'speed-low'],
        
        // Missing/Null
        ['fsr' => null, 'label' => 'FSr N/A (No data)', 'class' => 'speed-low'],
        ['fsr' => 0, 'label' => 'FSr 0 (Zero)', 'class' => 'speed-low'],
    ];
}

/**
 * Generate win badge test data
 */
function generate_win_badge_tests() {
    return [
        // Course & Distance (CD)
        ['candd_winner' => 1, 'label' => 'CD (1 win)'],
        ['candd_winner' => 2, 'label' => 'CDx2 (2 wins)'],
        ['candd_winner' => 3, 'label' => 'CDx3 (3 wins)'],
        ['candd_winner' => 5, 'label' => 'CDx5 (5 wins)'],
        
        // Course only (C)
        ['course_winner' => 1, 'candd_winner' => 0, 'label' => 'C (1 win)'],
        ['course_winner' => 2, 'candd_winner' => 0, 'label' => 'Cx2 (2 wins)'],
        ['course_winner' => 4, 'candd_winner' => 0, 'label' => 'Cx4 (4 wins)'],
        
        // Distance only (D)
        ['distance_winner' => 1, 'candd_winner' => 0, 'label' => 'D (1 win)'],
        ['distance_winner' => 2, 'candd_winner' => 0, 'label' => 'Dx2 (2 wins)'],
        ['distance_winner' => 3, 'candd_winner' => 0, 'label' => 'Dx3 (3 wins)'],
        
        // Going wins (G)
        ['going_prev_wins' => 1, 'label' => 'G (1 win)'],
        ['going_prev_wins' => 2, 'label' => 'Gx2 (2 wins)'],
        ['going_prev_wins' => 5, 'label' => 'Gx5 (5 wins)'],
        
        // Last Beaten Favorite (LBF)
        ['beaten_favourite' => 1, 'label' => 'LBF (1 time)'],
        ['beaten_favourite' => 2, 'label' => 'LBFx2 (2 times)'],
        ['beaten_favourite' => 4, 'label' => 'LBFx4 (4 times)'],
        
        // Multiple combinations
        ['candd_winner' => 2, 'going_prev_wins' => 3, 'label' => 'CDx2, Gx3'],
        ['course_winner' => 1, 'distance_winner' => 1, 'going_prev_wins' => 2, 'label' => 'C, D, Gx2'],
        ['candd_winner' => 1, 'beaten_favourite' => 2, 'label' => 'CD, LBFx2'],
        ['course_winner' => 3, 'going_prev_wins' => 2, 'beaten_favourite' => 1, 'label' => 'Cx3, Gx2, LBF'],
        
        // No wins
        ['label' => 'No wins (-)'],
    ];
}

/**
 * Generate maturity edge badge test data (2YO Flat races only)
 */
function generate_maturity_edge_badge_tests() {
    return [
        // Strong Edge (+4 to +5)
        ['score' => 5, 'label' => 'Strong Edge', 'class' => 'strong', 'birth_month' => 2, 'race_month' => 4],
        ['score' => 4, 'label' => 'Strong Edge', 'class' => 'strong', 'birth_month' => 3, 'race_month' => 5],
        
        // Edge (+2 to +3)
        ['score' => 3, 'label' => 'Edge', 'class' => 'positive', 'birth_month' => 2, 'race_month' => 8],
        ['score' => 2, 'label' => 'Edge', 'class' => 'positive', 'birth_month' => 1, 'race_month' => 4],
        
        // Neutral (-1 to +1)
        ['score' => 1, 'label' => 'Neutral', 'class' => 'neutral', 'birth_month' => 4, 'race_month' => 7],
        ['score' => 0, 'label' => 'Neutral', 'class' => 'neutral', 'birth_month' => 5, 'race_month' => 6],
        ['score' => -1, 'label' => 'Neutral', 'class' => 'neutral', 'birth_month' => 5, 'race_month' => 9],
        
        // Risk (-2 to -3)
        ['score' => -2, 'label' => 'Risk', 'class' => 'caution', 'birth_month' => 6, 'race_month' => 5],
        ['score' => -3, 'label' => 'Risk', 'class' => 'caution', 'birth_month' => 7, 'race_month' => 4],
        
        // High Risk (-4 or lower)
        ['score' => -4, 'label' => 'High Risk', 'class' => 'negative', 'birth_month' => 8, 'race_month' => 4],
        ['score' => -5, 'label' => 'High Risk', 'class' => 'negative', 'birth_month' => 9, 'race_month' => 5],
        ['score' => -6, 'label' => 'High Risk', 'class' => 'negative', 'birth_month' => 10, 'race_month' => 6],
        ['score' => -8, 'label' => 'High Risk', 'class' => 'negative', 'birth_month' => 12, 'race_month' => 4],
    ];
}

/**
 * Generate non-runner badge test data
 */
function generate_non_runner_badge_tests() {
    return [
        ['is_non_runner' => true, 'reason' => 'Withdrawn', 'label' => 'Non-Runner (Withdrawn)'],
        ['is_non_runner' => true, 'reason' => 'Vet Cert', 'label' => 'Non-Runner (Vet Cert)'],
        ['is_non_runner' => true, 'reason' => 'Going', 'label' => 'Non-Runner (Going)'],
        ['is_non_runner' => true, 'reason' => 'Self Cert', 'label' => 'Non-Runner (Self Cert)'],
        ['is_non_runner' => true, 'reason' => 'Unknown', 'label' => 'Non-Runner (Unknown)'],
        ['is_non_runner' => false, 'label' => 'Active Runner'],
    ];
}

/**
 * Generate cloth number badge test data
 */
function generate_cloth_number_badge_tests() {
    return [
        ['cloth_number' => 1, 'label' => 'Cloth #1'],
        ['cloth_number' => 5, 'label' => 'Cloth #5'],
        ['cloth_number' => 10, 'label' => 'Cloth #10'],
        ['cloth_number' => 15, 'label' => 'Cloth #15'],
        ['cloth_number' => 20, 'label' => 'Cloth #20'],
        ['cloth_number' => 25, 'label' => 'Cloth #25'],
        ['cloth_number' => 30, 'label' => 'Cloth #30'],
        ['cloth_number' => 99, 'label' => 'Cloth #99 (Reserve)'],
    ];
}

/**
 * Generate handicap badge test data
 */
function generate_handicap_badge_tests() {
    return [
        ['handicap' => 1, 'label' => 'Handicap'],
        ['handicap' => 0, 'label' => 'Non-Handicap'],
        ['handicap' => null, 'label' => 'N/A (Not specified)'],
    ];
}

/**
 * Generate combined badge scenarios (realistic race situations)
 */
function generate_combined_badge_scenarios() {
    return [
        [
            'name' => 'Top-Rated Winner',
            'runner' => [
                'position' => 1,
                'fsr' => 95,
                'candd_winner' => 2,
                'cloth_number' => 3,
                'handicap' => 1,
                'is_non_runner' => false,
            ]
        ],
        [
            'name' => 'Close Second with Good Form',
            'runner' => [
                'position' => 2,
                'distance_beaten' => 0.5,
                'fsr' => 88,
                'course_winner' => 1,
                'going_prev_wins' => 2,
                'cloth_number' => 7,
                'handicap' => 1,
            ]
        ],
        [
            'name' => '2YO Flat Winner with Maturity Edge',
            'runner' => [
                'position' => 1,
                'fsr' => 82,
                'cloth_number' => 5,
                'maturity_edge' => ['score' => 4, 'label' => 'Strong Edge', 'class' => 'strong'],
                'handicap' => 0,
            ]
        ],
        [
            'name' => 'Non-Runner (Former Favorite)',
            'runner' => [
                'fsr' => 91,
                'candd_winner' => 3,
                'beaten_favourite' => 2,
                'cloth_number' => 1,
                'is_non_runner' => true,
                'handicap' => 1,
            ]
        ],
        [
            'name' => 'Outsider Third Place',
            'runner' => [
                'position' => 3,
                'distance_beaten' => 5.5,
                'fsr' => 65,
                'cloth_number' => 15,
                'handicap' => 1,
            ]
        ],
        [
            'name' => 'Well Beaten Favorite',
            'runner' => [
                'position' => 10,
                'distance_beaten' => 25.0,
                'fsr' => 92,
                'candd_winner' => 1,
                'beaten_favourite' => 0,
                'cloth_number' => 2,
                'handicap' => 1,
            ]
        ],
    ];
}

/**
 * Get edge cases for badge testing
 */
function get_badge_edge_cases() {
    return [
        'boundary_values' => [
            ['fsr' => 80, 'label' => 'FSr exactly 80 (excellent boundary)'],
            ['fsr' => 79, 'label' => 'FSr exactly 79 (good boundary)'],
            ['fsr' => 70, 'label' => 'FSr exactly 70 (average boundary)'],
            ['fsr' => 60, 'label' => 'FSr exactly 60 (low boundary)'],
        ],
        'null_values' => [
            ['position' => null, 'label' => 'Null position'],
            ['fsr' => null, 'label' => 'Null FSr'],
            ['candd_winner' => null, 'label' => 'Null CD wins'],
            ['cloth_number' => null, 'label' => 'Null cloth number'],
        ],
        'zero_values' => [
            ['position' => 0, 'label' => 'Zero position'],
            ['fsr' => 0, 'label' => 'Zero FSr'],
            ['candd_winner' => 0, 'label' => 'Zero CD wins'],
        ],
        'extreme_values' => [
            ['position' => 99, 'distance_beaten' => 100.0, 'label' => 'Last by 100 lengths'],
            ['fsr' => 120, 'label' => 'FSr 120 (exceptional)'],
            ['candd_winner' => 20, 'label' => 'CD winner 20 times'],
            ['cloth_number' => 999, 'label' => 'Cloth #999 (invalid)'],
        ],
        'special_cases' => [
            ['position' => '1=', 'label' => 'Dead heat for 1st'],
            ['position' => '2=', 'label' => 'Dead heat for 2nd'],
            ['fsr' => '-', 'label' => 'FSr dash (no data)'],
            ['fsr' => 'N/A', 'label' => 'FSr N/A string'],
        ],
    ];
}

// ==============================================
// RUNNER GENERATOR
// ==============================================

/**
 * Generate a single test runner with specified properties
 * 
 * @param array $properties Custom properties for the runner
 * @return array Runner data array
 */
function generate_test_runner($properties = []) {
    $defaults = [
        'runner_id' => rand(1000, 9999),
        'name' => 'Test Horse ' . rand(1, 100),
        'cloth_number' => rand(1, 20),
        'position' => rand(1, 15),
        'distance_beaten' => rand(0, 300) / 10,
        'fsr' => rand(50, 100),
        'official_rating' => rand(60, 120),
        'candd_winner' => rand(0, 3),
        'course_winner' => rand(0, 2),
        'distance_winner' => rand(0, 2),
        'going_prev_wins' => rand(0, 3),
        'beaten_favourite' => rand(0, 2),
        'handicap' => rand(0, 1),
        'is_non_runner' => false,
        'jockey_name' => 'Test Jockey',
        'trainer_name' => 'Test Trainer',
        'weight_pounds' => rand(120, 140),
        'forecast_price' => rand(2, 50) . '/1',
    ];
    
    return array_merge($defaults, $properties);
}

/**
 * Generate a complete race with multiple runners
 * 
 * @param int $num_runners Number of runners to generate
 * @param array $race_properties Race-level properties
 * @return array Race data with runners
 */
function generate_test_race($num_runners = 10, $race_properties = []) {
    $race_defaults = [
        'race_id' => rand(10000, 99999),
        'course' => 'Test Course',
        'race_title' => 'Test Stakes',
        'meeting_date' => date('Y-m-d'),
        'scheduled_time' => date('H:i', strtotime('+2 hours')),
        'distance_yards' => 1760,
        'class' => rand(1, 6),
        'race_type' => 'Flat',
        'going' => 'Good',
        'handicap' => rand(0, 1),
    ];
    
    $race = array_merge($race_defaults, $race_properties);
    
    // Generate runners with varying positions
    $runners = [];
    for ($i = 1; $i <= $num_runners; $i++) {
        $runners[] = generate_test_runner([
            'position' => $i,
            'cloth_number' => $i,
            'distance_beaten' => ($i - 1) * 2.5, // Each runner 2.5L behind previous
            'fsr' => 100 - ($i * 3), // Decreasing ratings
        ]);
    }
    
    $race['runners'] = $runners;
    
    return $race;
}

// ==============================================
// VISUAL DISPLAY SHORTCODE
// ==============================================

/**
 * Shortcode to display badge test data visually
 * Usage: [badge_test_display]
 * or [badge_test_display type="position"] to show specific badge type
 */
function badge_test_display_shortcode($atts) {
    $atts = shortcode_atts([
        'type' => 'all', // all, position, speed, win, maturity, handicap, combined
    ], $atts);
    
    $test_data = generate_badge_test_data();
    
    ob_start();
    ?>
    
    <style>
        .badge-test-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .badge-section {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .badge-section-title {
            font-size: 24px;
            font-weight: 800;
            color: #1e293b;
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .badge-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }
        
        .badge-test-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 16px;
            border: 2px solid #e5e7eb;
            transition: all 0.2s ease;
        }
        
        .badge-test-item:hover {
            border-color: #3b82f6;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
        }
        
        .badge-test-label {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .badge-display {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .badge-note {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 4px;
            font-style: italic;
        }
        
        /* Position badges */
        .place-badge-1 { 
            background: linear-gradient(135deg, #D5A500 0%, #F4C430 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 800;
            font-size: 14px;
            box-shadow: 0 2px 8px rgba(213, 165, 0, 0.4);
            display: inline-block;
        }
        
        .place-badge-2 { 
            background: linear-gradient(135deg, #B7B7B7 0%, #D3D3D3 100%);
            color: #333;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 800;
            font-size: 14px;
            box-shadow: 0 2px 8px rgba(183, 183, 183, 0.4);
            display: inline-block;
        }
        
        .place-badge-3 { 
            background: linear-gradient(135deg, #A17419 0%, #CD7F32 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 800;
            font-size: 14px;
            box-shadow: 0 2px 8px rgba(161, 116, 25, 0.4);
            display: inline-block;
        }
        
        .place-badge-other {
            color: #6b7280;
            font-weight: 700;
            font-size: 14px;
            padding: 6px 12px;
        }
        
        /* Speed rating badges */
        .speed-excellent {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 8px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            font-weight: 800;
            font-size: 14px;
            box-shadow: 0 3px 10px rgba(16, 185, 129, 0.4);
        }
        
        .speed-good {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 8px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            font-weight: 800;
            font-size: 14px;
            box-shadow: 0 3px 10px rgba(59, 130, 246, 0.4);
        }
        
        .speed-average {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 8px;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            font-weight: 800;
            font-size: 14px;
            box-shadow: 0 3px 10px rgba(245, 158, 11, 0.4);
        }
        
        .speed-low {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 8px;
            color: #6b7280;
            font-weight: 600;
            font-size: 14px;
        }
        
        /* Win badges */
        .win-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            background: #10b981;
            color: white;
            font-size: 11px;
            font-weight: 700;
            margin-right: 4px;
        }
        
        .win-badge-going {
            background: #3b82f6;
        }
        
        .win-badge-lbf {
            background: #f59e0b;
        }
        
        /* Maturity edge badges */
        .maturity-edge-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .maturity-edge-badge.strong {
            background: #dcfce7;
            color: #166534;
        }
        
        .maturity-edge-badge.positive {
            background: #dbeafe;
            color: #1d4ed8;
        }
        
        .maturity-edge-badge.neutral {
            background: #e2e8f0;
            color: #334155;
        }
        
        .maturity-edge-badge.caution {
            background: #fef3c7;
            color: #92400e;
        }
        
        .maturity-edge-badge.negative {
            background: #fee2e2;
            color: #991b1b;
        }
        
        /* Non-runner badge */
        .non-runner-badge {
            display: inline-block;
            padding: 4px 10px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.35);
        }
        
        /* Cloth number badge */
        .cloth-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            font-weight: 800;
            font-size: 16px;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }
        
        /* Handicap badge */
        .handicap-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .handicap-badge.handicap {
            background: #10b981;
            color: white;
        }
        
        .handicap-badge.non-handicap {
            background: #6b7280;
            color: white;
        }
        
        /* Combined scenario card */
        .scenario-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            border: 2px solid #e5e7eb;
            margin-bottom: 16px;
        }
        
        .scenario-title {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 16px 0;
        }
        
        .scenario-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
    </style>
    
    <div class="badge-test-container">
        <h1 style="font-size: 32px; font-weight: 800; color: #1e293b; margin-bottom: 8px;">
            🎨 Badge System Test Display
        </h1>
        <p style="color: #64748b; font-size: 16px; margin-bottom: 32px;">
            Comprehensive visual test of all badge variations in the horse racing system
        </p>
        
        <?php if ($atts['type'] === 'all' || $atts['type'] === 'position'): ?>
        <!-- Position Badges -->
        <div class="badge-section">
            <h2 class="badge-section-title">🏆 Position Badges</h2>
            <div class="badge-grid">
                <?php foreach ($test_data['position_badges'] as $test): ?>
                <div class="badge-test-item">
                    <div class="badge-test-label"><?php echo esc_html($test['label']); ?></div>
                    <div class="badge-display">
                        <?php 
                        $pos = $test['position'];
                        $badge_class = $pos === 1 ? 'place-badge-1' : 
                                      ($pos === 2 ? 'place-badge-2' : 
                                      ($pos === 3 ? 'place-badge-3' : 'place-badge-other'));
                        ?>
                        <span class="<?php echo $badge_class; ?>"><?php echo $pos; ?></span>
                        <?php if (isset($test['distance_beaten']) && $test['distance_beaten'] > 0): ?>
                            <span style="color:#6b7280;font-size:12px;">
                                (<?php echo $test['distance_beaten']; ?>L)
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if (isset($test['note'])): ?>
                    <div class="badge-note"><?php echo esc_html($test['note']); ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($atts['type'] === 'all' || $atts['type'] === 'speed'): ?>
        <!-- Speed Rating Badges -->
        <div class="badge-section">
            <h2 class="badge-section-title">⚡ Speed Rating Badges</h2>
            <div class="badge-grid">
                <?php foreach ($test_data['speed_rating_badges'] as $test): ?>
                <div class="badge-test-item">
                    <div class="badge-test-label"><?php echo esc_html($test['label']); ?></div>
                    <div class="badge-display">
                        <span class="<?php echo esc_attr($test['class']); ?>">
                            <?php echo $test['fsr'] ?? '-'; ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($atts['type'] === 'all' || $atts['type'] === 'win'): ?>
        <!-- Win Badges -->
        <div class="badge-section">
            <h2 class="badge-section-title">🎯 Win Badges</h2>
            <div class="badge-grid">
                <?php foreach ($test_data['win_badges'] as $test): ?>
                <div class="badge-test-item">
                    <div class="badge-test-label"><?php echo esc_html($test['label']); ?></div>
                    <div class="badge-display">
                        <?php
                        $candd = $test['candd_winner'] ?? 0;
                        $course = $test['course_winner'] ?? 0;
                        $distance = $test['distance_winner'] ?? 0;
                        $going = $test['going_prev_wins'] ?? 0;
                        $lbf = $test['beaten_favourite'] ?? 0;
                        
                        if ($candd > 0): ?>
                            <span class="win-badge">CD<?php echo $candd > 1 ? 'x' . $candd : ''; ?></span>
                        <?php endif;
                        
                        if ($course > 0 && $candd == 0): ?>
                            <span class="win-badge">C<?php echo $course > 1 ? 'x' . $course : ''; ?></span>
                        <?php endif;
                        
                        if ($distance > 0 && $candd == 0): ?>
                            <span class="win-badge">D<?php echo $distance > 1 ? 'x' . $distance : ''; ?></span>
                        <?php endif;
                        
                        if ($going > 0): ?>
                            <span class="win-badge win-badge-going">G<?php echo $going > 1 ? 'x' . $going : ''; ?></span>
                        <?php endif;
                        
                        if ($lbf > 0): ?>
                            <span class="win-badge win-badge-lbf">LBF<?php echo $lbf > 1 ? 'x' . $lbf : ''; ?></span>
                        <?php endif;
                        
                        if ($candd == 0 && $course == 0 && $distance == 0 && $going == 0 && $lbf == 0): ?>
                            <span style="color:#9ca3af;">-</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($atts['type'] === 'all' || $atts['type'] === 'maturity'): ?>
        <!-- Maturity Edge Badges -->
        <div class="badge-section">
            <h2 class="badge-section-title">🎂 Maturity Edge Badges (2YO Flat)</h2>
            <div class="badge-grid">
                <?php foreach ($test_data['maturity_edge_badges'] as $test): ?>
                <div class="badge-test-item">
                    <div class="badge-test-label"><?php echo esc_html($test['label']); ?></div>
                    <div class="badge-display">
                        <span class="maturity-edge-badge <?php echo esc_attr($test['class']); ?>">
                            <?php echo ($test['score'] > 0 ? '+' : '') . $test['score']; ?> - <?php echo $test['label']; ?>
                        </span>
                    </div>
                    <div class="badge-note">
                        Birth: Month <?php echo $test['birth_month']; ?>, Race: Month <?php echo $test['race_month']; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($atts['type'] === 'all' || $atts['type'] === 'handicap'): ?>
        <!-- Other Badges -->
        <div class="badge-section">
            <h2 class="badge-section-title">🎪 Other Badges</h2>
            
            <!-- Non-Runner Badges -->
            <h3 style="font-size: 18px; font-weight: 700; margin: 0 0 12px 0;">Non-Runner Badges</h3>
            <div class="badge-grid" style="margin-bottom: 24px;">
                <?php foreach ($test_data['non_runner_badges'] as $test): ?>
                <div class="badge-test-item">
                    <div class="badge-test-label"><?php echo esc_html($test['label']); ?></div>
                    <div class="badge-display">
                        <?php if ($test['is_non_runner']): ?>
                            <span class="non-runner-badge">NR</span>
                        <?php else: ?>
                            <span style="color:#10b981;font-weight:600;">Active</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Cloth Number Badges -->
            <h3 style="font-size: 18px; font-weight: 700; margin: 0 0 12px 0;">Cloth Number Badges</h3>
            <div class="badge-grid" style="margin-bottom: 24px;">
                <?php foreach ($test_data['cloth_number_badges'] as $test): ?>
                <div class="badge-test-item">
                    <div class="badge-test-label"><?php echo esc_html($test['label']); ?></div>
                    <div class="badge-display">
                        <span class="cloth-badge"><?php echo $test['cloth_number']; ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Handicap Badges -->
            <h3 style="font-size: 18px; font-weight: 700; margin: 0 0 12px 0;">Handicap Badges</h3>
            <div class="badge-grid">
                <?php foreach ($test_data['handicap_badges'] as $test): ?>
                <div class="badge-test-item">
                    <div class="badge-test-label"><?php echo esc_html($test['label']); ?></div>
                    <div class="badge-display">
                        <?php if ($test['handicap'] === 1): ?>
                            <span class="handicap-badge handicap">Handicap</span>
                        <?php elseif ($test['handicap'] === 0): ?>
                            <span class="handicap-badge non-handicap">Non-Handicap</span>
                        <?php else: ?>
                            <span style="color:#9ca3af;">N/A</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($atts['type'] === 'all' || $atts['type'] === 'combined'): ?>
        <!-- Combined Scenarios -->
        <div class="badge-section">
            <h2 class="badge-section-title">🎭 Combined Badge Scenarios</h2>
            <?php foreach ($test_data['combined_scenarios'] as $scenario): ?>
            <div class="scenario-card">
                <h3 class="scenario-title"><?php echo esc_html($scenario['name']); ?></h3>
                <div class="scenario-badges">
                    <?php 
                    $r = $scenario['runner'];
                    
                    // Position
                    if (isset($r['position'])):
                        $pos = $r['position'];
                        $badge_class = $pos === 1 ? 'place-badge-1' : 
                                      ($pos === 2 ? 'place-badge-2' : 
                                      ($pos === 3 ? 'place-badge-3' : 'place-badge-other'));
                    ?>
                        <span class="<?php echo $badge_class; ?>"><?php echo $pos; ?></span>
                    <?php endif; ?>
                    
                    <!-- FSr -->
                    <?php if (isset($r['fsr'])): 
                        $fsr = $r['fsr'];
                        $speed_class = $fsr >= 80 ? 'speed-excellent' : 
                                      ($fsr >= 70 ? 'speed-good' : 
                                      ($fsr >= 60 ? 'speed-average' : 'speed-low'));
                    ?>
                        <span class="<?php echo $speed_class; ?>">FSr <?php echo $fsr; ?></span>
                    <?php endif; ?>
                    
                    <!-- Win badges -->
                    <?php if (isset($r['candd_winner']) && $r['candd_winner'] > 0): ?>
                        <span class="win-badge">CD<?php echo $r['candd_winner'] > 1 ? 'x' . $r['candd_winner'] : ''; ?></span>
                    <?php endif; ?>
                    
                    <?php if (isset($r['course_winner']) && $r['course_winner'] > 0): ?>
                        <span class="win-badge">C<?php echo $r['course_winner'] > 1 ? 'x' . $r['course_winner'] : ''; ?></span>
                    <?php endif; ?>
                    
                    <?php if (isset($r['going_prev_wins']) && $r['going_prev_wins'] > 0): ?>
                        <span class="win-badge win-badge-going">G<?php echo $r['going_prev_wins'] > 1 ? 'x' . $r['going_prev_wins'] : ''; ?></span>
                    <?php endif; ?>
                    
                    <?php if (isset($r['beaten_favourite']) && $r['beaten_favourite'] > 0): ?>
                        <span class="win-badge win-badge-lbf">LBF<?php echo $r['beaten_favourite'] > 1 ? 'x' . $r['beaten_favourite'] : ''; ?></span>
                    <?php endif; ?>
                    
                    <!-- Maturity edge -->
                    <?php if (isset($r['maturity_edge'])): ?>
                        <span class="maturity-edge-badge <?php echo $r['maturity_edge']['class']; ?>">
                            <?php echo $r['maturity_edge']['label']; ?>
                        </span>
                    <?php endif; ?>
                    
                    <!-- Cloth number -->
                    <?php if (isset($r['cloth_number'])): ?>
                        <span class="cloth-badge"><?php echo $r['cloth_number']; ?></span>
                    <?php endif; ?>
                    
                    <!-- Non-runner -->
                    <?php if (isset($r['is_non_runner']) && $r['is_non_runner']): ?>
                        <span class="non-runner-badge">NR</span>
                    <?php endif; ?>
                    
                    <!-- Handicap -->
                    <?php if (isset($r['handicap']) && $r['handicap'] === 1): ?>
                        <span class="handicap-badge handicap">Handicap</span>
                    <?php elseif (isset($r['handicap']) && $r['handicap'] === 0): ?>
                        <span class="handicap-badge non-handicap">Non-Handicap</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Edge Cases -->
        <div class="badge-section">
            <h2 class="badge-section-title">⚠️ Edge Cases</h2>
            
            <h3 style="font-size: 18px; font-weight: 700; margin: 0 0 12px 0;">Boundary Values</h3>
            <div class="badge-grid" style="margin-bottom: 24px;">
                <?php foreach ($test_data['edge_cases']['boundary_values'] as $test): ?>
                <div class="badge-test-item">
                    <div class="badge-test-label"><?php echo esc_html($test['label']); ?></div>
                    <div class="badge-display">
                        <?php 
                        $fsr = $test['fsr'];
                        $speed_class = $fsr >= 80 ? 'speed-excellent' : 
                                      ($fsr >= 70 ? 'speed-good' : 
                                      ($fsr >= 60 ? 'speed-average' : 'speed-low'));
                        ?>
                        <span class="<?php echo $speed_class; ?>"><?php echo $fsr; ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <h3 style="font-size: 18px; font-weight: 700; margin: 0 0 12px 0;">Null/Zero/Extreme Values</h3>
            <div class="badge-grid">
                <?php 
                $all_edge_cases = array_merge(
                    $test_data['edge_cases']['null_values'],
                    $test_data['edge_cases']['zero_values'],
                    $test_data['edge_cases']['extreme_values']
                );
                foreach ($all_edge_cases as $test): 
                ?>
                <div class="badge-test-item">
                    <div class="badge-test-label"><?php echo esc_html($test['label']); ?></div>
                    <div class="badge-display">
                        <span style="color:#9ca3af;font-weight:600;">
                            <?php 
                            if (isset($test['position'])) echo 'Pos: ' . ($test['position'] ?? 'null');
                            if (isset($test['fsr'])) echo 'FSr: ' . ($test['fsr'] ?? 'null');
                            if (isset($test['candd_winner'])) echo 'CD: ' . ($test['candd_winner'] ?? 'null');
                            if (isset($test['cloth_number'])) echo 'Cloth: ' . ($test['cloth_number'] ?? 'null');
                            ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Usage Information -->
        <div class="badge-section" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border: 2px solid #3b82f6;">
            <h2 class="badge-section-title">📚 Usage Information</h2>
            <div style="color: #1e40af; line-height: 1.8;">
                <p><strong>Available Shortcode Parameters:</strong></p>
                <ul style="margin: 12px 0; padding-left: 20px;">
                    <li><code>[badge_test_display]</code> - Shows all badge types</li>
                    <li><code>[badge_test_display type="position"]</code> - Position badges only</li>
                    <li><code>[badge_test_display type="speed"]</code> - Speed rating badges only</li>
                    <li><code>[badge_test_display type="win"]</code> - Win badges only</li>
                    <li><code>[badge_test_display type="maturity"]</code> - Maturity edge badges only</li>
                    <li><code>[badge_test_display type="handicap"]</code> - Other badges only</li>
                    <li><code>[badge_test_display type="combined"]</code> - Combined scenarios only</li>
                </ul>
                
                <p style="margin-top: 20px;"><strong>PHP Functions Available:</strong></p>
                <ul style="margin: 12px 0; padding-left: 20px;">
                    <li><code>generate_badge_test_data()</code> - Get all test data as array</li>
                    <li><code>generate_test_runner($properties)</code> - Generate single runner</li>
                    <li><code>generate_test_race($num_runners, $race_properties)</code> - Generate full race</li>
                    <li><code>get_badge_edge_cases()</code> - Get edge case scenarios</li>
                </ul>
            </div>
        </div>
    </div>
    
    <?php
    return ob_get_clean();
}
add_shortcode('badge_test_display', 'badge_test_display_shortcode');

// ==============================================
// EXAMPLE USAGE
// ==============================================

/**
 * Example usage in your code:
 * 
 * // Get all test data
 * $test_data = generate_badge_test_data();
 * 
 * // Generate a specific runner
 * $winner = generate_test_runner([
 *     'position' => 1,
 *     'fsr' => 95,
 *     'candd_winner' => 3
 * ]);
 * 
 * // Generate a full race
 * $race = generate_test_race(12, [
 *     'course' => 'Ascot',
 *     'class' => 1
 * ]);
 * 
 * // Display in a page/post
 * // Add shortcode: [badge_test_display]
 */

// ==============================================
// STANDALONE EXECUTION
// ==============================================

// If called directly, display the badge test interface
if (BADGE_TEST_STANDALONE) {
    // Get filter parameter if provided
    $display_type = isset($_GET['type']) ? $_GET['type'] : 'all';
    
    // Render the page
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Badge System Test Display</title>
        <style>
            body {
                margin: 0;
                padding: 0;
               background: #ffffff;
                min-height: 100vh;
            }
        </style>
    </head>
    <body>
        <?php 
        // Display the badge test interface
        echo badge_test_display_shortcode(['type' => $display_type]);
        ?>
        
        <div class="badge-test-container" style="margin-top: 40px;">
            <div class="badge-section" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                <h2 style="color: white; margin: 0 0 16px 0; font-size: 20px; font-weight: 800;">
                    🔗 Direct Access URLs
                </h2>
                <div style="line-height: 2;">
                    <p><strong>View specific badge types by adding ?type= parameter:</strong></p>
                    <ul style="list-style: none; padding: 0;">
                        <li>🎯 <a href="?type=all" style="color: white; font-weight: 600;">All Badges</a></li>
                        <li>🏆 <a href="?type=position" style="color: white; font-weight: 600;">Position Badges Only</a></li>
                        <li>⚡ <a href="?type=speed" style="color: white; font-weight: 600;">Speed Rating Badges Only</a></li>
                        <li>🎯 <a href="?type=win" style="color: white; font-weight: 600;">Win Badges Only</a></li>
                        <li>🎂 <a href="?type=maturity" style="color: white; font-weight: 600;">Maturity Edge Badges Only</a></li>
                        <li>🎪 <a href="?type=handicap" style="color: white; font-weight: 600;">Other Badges Only</a></li>
                        <li>🎭 <a href="?type=combined" style="color: white; font-weight: 600;">Combined Scenarios Only</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit; // Prevent any further execution
}
