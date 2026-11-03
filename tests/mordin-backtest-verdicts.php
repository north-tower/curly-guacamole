<?php
/**
 * Task 10 — CLI runner for Mordin backtest batch.
 *
 *   wp eval-file wp-content/themes/curly-guacamole/tests/mordin-backtest-verdicts.php -- --out=task10-report.md
 *
 * @package Fhorsite
 */

if (!defined('ABSPATH')) {
    $argv_boot = $argv ?? [];
    $wp_root = getenv('WP_ROOT') ?: '';
    foreach ($argv_boot as $arg) {
        if (strpos($arg, '--wp-root=') === 0) {
            $wp_root = substr($arg, 10);
        }
    }
    if ($wp_root === '' || !is_file($wp_root . '/wp-load.php')) {
        fwrite(STDERR, "WordPress not loaded. Use: wp eval-file .../mordin-backtest-verdicts.php\n");
        fwrite(STDERR, "Or: WP_ROOT=/path/to/wp php tests/mordin-backtest-verdicts.php\n");
        exit(1);
    }
    require $wp_root . '/wp-load.php';
}

$theme = get_stylesheet_directory();
require_once $theme . '/inc/mordin-backtest-batch.php';

if (!function_exists('fhor_sb_run_backtest')) {
    if (is_file($theme . '/inc/pedigree-dosage.php')) {
        require_once $theme . '/inc/pedigree-dosage.php';
    }
    if (is_file($theme . '/inc/mordin-class.php')) {
        require_once $theme . '/inc/mordin-class.php';
    }
    if (is_file($theme . '/inc/system-builder.php')) {
        require_once $theme . '/inc/system-builder.php';
    }
}

/**
 * @return array<string, string>
 */
function mordin_bt_cli_args(array $argv) {
    $out = ['out' => '', 'user' => '', 'days' => '1825'];
    foreach ($argv as $arg) {
        if (strpos($arg, '--out=') === 0) {
            $out['out'] = substr($arg, 6);
        } elseif (strpos($arg, '--user=') === 0) {
            $out['user'] = substr($arg, 7);
        } elseif (strpos($arg, '--days=') === 0) {
            $out['days'] = substr($arg, 7);
        }
    }
    return $out;
}

function mordin_bt_set_cli_user($login) {
    if ($login !== '') {
        $u = get_user_by('login', $login);
        if (!$u) {
            $u = get_user_by('email', $login);
        }
        if ($u) {
            wp_set_current_user($u->ID);
            return $u->user_login;
        }
        fwrite(STDERR, "Warning: user not found: {$login}\n");
    }
    $admins = get_users(['role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC']);
    if ($admins) {
        wp_set_current_user($admins[0]->ID);
        return $admins[0]->user_login;
    }
    return '(none)';
}

$cli = mordin_bt_cli_args($argv ?? []);
$days = max(7, min(1825, (int) $cli['days']));
$as_user = mordin_bt_set_cli_user($cli['user']);

$result = mordin_bt_run_batch($days);
if (empty($result['ok'])) {
    fwrite(STDERR, 'Abort: ' . ($result['error'] ?? 'unknown') . "\n");
    exit(1);
}

$rows_out = $result['rows'];
$meta = $result['meta'];
$markdown = $result['markdown'];

echo "Mordin Task 10 backtest batch\n";
echo "User: {$as_user} · premium=" . (!empty($meta['premium']) ? 'yes' : 'no') . " · days={$meta['days']}\n";
echo str_repeat('-', 72) . "\n";
foreach ($rows_out as $row) {
    $s = $row['scenario'];
    $w = $row['with'];
    $b = $row['baseline'];
    echo sprintf(
        "%-28s %5d %5.1f%% %6.1f %6.1f %5d %12s\n",
        $s['key'],
        $w['bets'],
        $w['win_rate'],
        $w['roi'],
        $b['roi'],
        $row['removed']['removed_bets'],
        $row['verdict']['badge']
    );
}

if ($cli['out'] !== '') {
    $path = $cli['out'];
    if (!preg_match('#^[a-zA-Z]:\\\\#', $path) && ($path === '' || $path[0] !== '/')) {
        $path = get_stylesheet_directory() . '/' . ltrim($path, '/');
    }
    file_put_contents($path, $markdown);
    echo "\nWrote report: {$path}\n";
}

echo "\n--- NOTES table ---\n\n" . $result['notes_table_markdown'] . "\n";
exit(0);
