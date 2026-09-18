<?php
/**
 * Pedigree dosage analytics: Chefs-de-Race lookup, 4-generation scoring,
 * Dosage Index (DI) and Center of Distribution (CD).
 */

if (!defined('ABSPATH') && php_sapi_name() !== 'cli') {
    exit;
}

require_once __DIR__ . '/pedigree-dosage-chefs.php';

if (!function_exists('bricks_dosage_generation_points')) {
    /**
     * @return array<int, int> generation => points
     */
    function bricks_dosage_generation_points() {
        return [
            1 => 16,
            2 => 8,
            3 => 4,
            4 => 2,
        ];
    }
}

if (!function_exists('bricks_dosage_normalize_name_key')) {
    function bricks_dosage_normalize_name_key($name) {
        $name = is_string($name) ? $name : '';
        $name = str_replace(["\xC2\xA0", '’', '‘', '`'], [" ", "'", "'", "'"], $name);
        $name = preg_replace('/\s*\([^)]*\)\s*$/u', '', $name);
        $name = strtolower(trim(preg_replace('/\s+/u', ' ', $name)));
        return $name;
    }
}

if (!function_exists('bricks_dosage_name_key_aliases')) {
    /**
     * @return string[]
     */
    function bricks_dosage_name_key_aliases($name) {
        $base = bricks_dosage_normalize_name_key($name);
        if ($base === '') {
            return [];
        }
        $stripped = strtolower(trim(preg_replace("/[^a-z0-9]+/i", ' ', $base)));
        $stripped = trim(preg_replace('/\s+/', ' ', $stripped));
        $keys = [$base];
        if ($stripped !== '' && $stripped !== $base) {
            $keys[] = $stripped;
        }
        $no_roman = trim(preg_replace('/\s+(ii|iii|iv)$/i', '', $base));
        if ($no_roman !== '' && $no_roman !== $base) {
            $keys[] = $no_roman;
        }
        return array_values(array_unique($keys));
    }
}

if (!function_exists('bricks_dosage_empty_profile')) {
    /**
     * @return array{B:float,I:float,C:float,S:float,P:float}
     */
    function bricks_dosage_empty_profile() {
        return ['B' => 0.0, 'I' => 0.0, 'C' => 0.0, 'S' => 0.0, 'P' => 0.0];
    }
}

if (!function_exists('bricks_dosage_profile_from_array')) {
    /**
     * Accept [B,I,C,S,P] or associative B/I/C/S/P.
     *
     * @param array<int|string, mixed> $profile
     * @return array{B:float,I:float,C:float,S:float,P:float}
     */
    function bricks_dosage_profile_from_array(array $profile) {
        $out = bricks_dosage_empty_profile();
        if (array_keys($profile) === range(0, count($profile) - 1) && count($profile) >= 5) {
            $out['B'] = floatval($profile[0]);
            $out['I'] = floatval($profile[1]);
            $out['C'] = floatval($profile[2]);
            $out['S'] = floatval($profile[3]);
            $out['P'] = floatval($profile[4]);
            return $out;
        }
        foreach (['B', 'I', 'C', 'S', 'P'] as $key) {
            if (isset($profile[$key])) {
                $out[$key] = floatval($profile[$key]);
            }
        }
        return $out;
    }
}

if (!function_exists('bricks_dosage_calculate_metrics')) {
    /**
     * DI = (B + I + C/2) / (C/2 + S + P)
     * CD = ((2*B) + I - S - (2*P)) / (B + I + C + S + P)
     *
     * Divide-by-zero: stamina wing of 0 returns DI = INF (or null placeholder when no points).
     *
     * @param array<int|string, mixed> $profile
     * @return array{
     *   profile: array{B:float,I:float,C:float,S:float,P:float},
     *   di: float|null,
     *   cd: float|null,
     *   di_is_infinite: bool,
     *   total_points: float,
     *   display: string
     * }
     */
    function bricks_dosage_calculate_metrics(array $profile) {
        $dp = bricks_dosage_profile_from_array($profile);
        $b = $dp['B'];
        $i = $dp['I'];
        $c = $dp['C'];
        $s = $dp['S'];
        $p = $dp['P'];

        $total = $b + $i + $c + $s + $p;
        $speed_wing = $b + $i + ($c / 2.0);
        $stamina_wing = ($c / 2.0) + $s + $p;

        $di = null;
        $di_is_infinite = false;
        if ($stamina_wing == 0.0) {
            if ($speed_wing > 0.0) {
                $di = INF;
                $di_is_infinite = true;
            }
        } else {
            $di = $speed_wing / $stamina_wing;
        }

        $cd = null;
        if ($total != 0.0) {
            $cd = ((2.0 * $b) + $i - $s - (2.0 * $p)) / $total;
        }

        return [
            'profile' => $dp,
            'di' => $di,
            'cd' => $cd,
            'di_is_infinite' => $di_is_infinite,
            'total_points' => $total,
            'display' => bricks_dosage_format_display($di, $cd, $di_is_infinite),
        ];
    }
}

if (!function_exists('bricks_dosage_format_number')) {
    function bricks_dosage_format_number($value, $infinite = false) {
        if ($infinite || (is_float($value) && is_infinite($value))) {
            return 'Inf';
        }
        if ($value === null || (is_float($value) && is_nan($value))) {
            return '—';
        }
        return number_format((float) $value, 2, '.', '');
    }
}

if (!function_exists('bricks_dosage_format_display')) {
    function bricks_dosage_format_display($di, $cd, $di_is_infinite = false) {
        return 'DI: ' . bricks_dosage_format_number($di, $di_is_infinite)
            . ' | CD: ' . bricks_dosage_format_number($cd, false);
    }
}

if (!function_exists('bricks_dosage_profile_from_ancestors')) {
    /**
     * @param array<int, array{name?:string,generation?:int}> $ancestors
     * @param array<string, array{name:string,g1:string,g2:?string}> $chefs
     * @return array{B:float,I:float,C:float,S:float,P:float}
     */
    function bricks_dosage_profile_from_ancestors(array $ancestors, array $chefs) {
        $profile = bricks_dosage_empty_profile();
        $gen_points = bricks_dosage_generation_points();

        foreach ($ancestors as $ancestor) {
            $name = isset($ancestor['name']) ? (string) $ancestor['name'] : '';
            $gen = isset($ancestor['generation']) ? intval($ancestor['generation']) : 0;
            if ($name === '' || !isset($gen_points[$gen])) {
                continue;
            }
            $chef = bricks_dosage_find_chef($name, $chefs);
            if (!$chef) {
                continue;
            }
            $points = (float) $gen_points[$gen];
            $g1 = $chef['g1'];
            $g2 = $chef['g2'];
            if ($g2) {
                $half = $points / 2.0;
                $profile[$g1] += $half;
                $profile[$g2] += $half;
            } else {
                $profile[$g1] += $points;
            }
        }

        return $profile;
    }
}

if (!function_exists('bricks_dosage_find_chef')) {
    /**
     * @param array<string, array{name:string,g1:string,g2:?string}> $chefs
     * @return array{name:string,g1:string,g2:?string}|null
     */
    function bricks_dosage_find_chef($name, array $chefs) {
        foreach (bricks_dosage_name_key_aliases($name) as $key) {
            if (isset($chefs[$key])) {
                return $chefs[$key];
            }
        }
        return null;
    }
}

if (!function_exists('bricks_dosage_index_chefs')) {
    /**
     * @param array<int, array{0:string,1:string,2:?string}>|null $catalog
     * @return array<string, array{name:string,g1:string,g2:?string}>
     */
    function bricks_dosage_index_chefs($catalog = null) {
        $catalog = is_array($catalog) ? $catalog : bricks_dosage_chefs_catalog();
        $index = [];
        foreach ($catalog as $row) {
            $name = isset($row[0]) ? (string) $row[0] : '';
            $g1 = isset($row[1]) ? (string) $row[1] : '';
            $g2 = isset($row[2]) && $row[2] !== '' ? (string) $row[2] : null;
            if ($name === '' || $g1 === '') {
                continue;
            }
            $chef = ['name' => $name, 'g1' => $g1, 'g2' => $g2];
            foreach (bricks_dosage_name_key_aliases($name) as $key) {
                if (!isset($index[$key])) {
                    $index[$key] = $chef;
                }
            }
        }
        return $index;
    }
}

if (!function_exists('bricks_dosage_table_name')) {
    function bricks_dosage_table_name() {
        return 'chefs_de_race';
    }
}

if (!function_exists('bricks_dosage_ensure_table')) {
    function bricks_dosage_ensure_table() {
        if (!isset($GLOBALS['wpdb']) || !is_object($GLOBALS['wpdb'])) {
            return false;
        }
        global $wpdb;
        $table = bricks_dosage_table_name();
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists === $table) {
            return true;
        }
        $sql = "CREATE TABLE `{$table}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `Sire_Name` VARCHAR(128) NOT NULL,
            `Aptitudinal_Group_1` ENUM('B','I','C','S','P') NOT NULL,
            `Aptitudinal_Group_2` ENUM('B','I','C','S','P') NULL DEFAULT NULL,
            `name_key` VARCHAR(128) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_chefs_name_key` (`name_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $wpdb->query($sql);
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }
}

if (!function_exists('bricks_dosage_seed_table')) {
    function bricks_dosage_seed_table($force = false) {
        if (!isset($GLOBALS['wpdb']) || !is_object($GLOBALS['wpdb'])) {
            return 0;
        }
        if (!bricks_dosage_ensure_table()) {
            return 0;
        }
        global $wpdb;
        $table = bricks_dosage_table_name();
        $count = intval($wpdb->get_var("SELECT COUNT(*) FROM `{$table}`"));
        $catalog = bricks_dosage_chefs_catalog();
        if (!$force && $count >= count($catalog)) {
            return $count;
        }
        if ($force) {
            $wpdb->query("TRUNCATE TABLE `{$table}`");
        }
        $values = [];
        $params = [];
        foreach ($catalog as $row) {
            $name = $row[0];
            $g1 = $row[1];
            $g2 = $row[2];
            $key = bricks_dosage_normalize_name_key($name);
            $values[] = '(%s, %s, NULLIF(%s, \'\'), %s)';
            $params[] = $name;
            $params[] = $g1;
            $params[] = $g2 === null ? '' : $g2;
            $params[] = $key;
        }
        if (!empty($values)) {
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO `{$table}` (`Sire_Name`, `Aptitudinal_Group_1`, `Aptitudinal_Group_2`, `name_key`) VALUES "
                . implode(',', $values),
                ...$params
            ));
        }
        return intval($wpdb->get_var("SELECT COUNT(*) FROM `{$table}`"));
    }
}

if (!function_exists('bricks_dosage_chefs_lookup')) {
    /**
     * @return array<string, array{name:string,g1:string,g2:?string}>
     */
    function bricks_dosage_chefs_lookup() {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $from_db = [];
        if (isset($GLOBALS['wpdb']) && is_object($GLOBALS['wpdb'])) {
            bricks_dosage_seed_table(false);
            global $wpdb;
            $table = bricks_dosage_table_name();
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
                $rows = $wpdb->get_results("SELECT `Sire_Name`, `Aptitudinal_Group_1`, `Aptitudinal_Group_2` FROM `{$table}`");
                if (is_array($rows)) {
                    foreach ($rows as $row) {
                        $from_db[] = [
                            (string) $row->Sire_Name,
                            (string) $row->Aptitudinal_Group_1,
                            ($row->Aptitudinal_Group_2 !== null && $row->Aptitudinal_Group_2 !== '')
                                ? (string) $row->Aptitudinal_Group_2
                                : null,
                        ];
                    }
                }
            }
        }

        $cache = bricks_dosage_index_chefs(!empty($from_db) ? $from_db : null);
        return $cache;
    }
}

if (!function_exists('bricks_dosage_horse_from_row')) {
    /**
     * @param object|array<string, mixed> $row
     * @return array{runner_id:int,name:string,sire_id:int,sire_name:string,dam_id:int,dam_name:string,dam_sire_id:int,dam_sire_name:string}
     */
    function bricks_dosage_horse_from_row($row) {
        $get = static function ($row, $keys) {
            foreach ((array) $keys as $key) {
                if (is_object($row) && isset($row->{$key}) && $row->{$key} !== '' && $row->{$key} !== null) {
                    return $row->{$key};
                }
                if (is_array($row) && isset($row[$key]) && $row[$key] !== '' && $row[$key] !== null) {
                    return $row[$key];
                }
            }
            return '';
        };

        return [
            'runner_id' => intval($get($row, ['runner_id', 'id'])),
            'name' => trim((string) $get($row, ['name', 'runner_name', 'horse_name'])),
            'sire_id' => intval($get($row, ['sire_id'])),
            'sire_name' => trim((string) $get($row, ['sire_name', 'sire'])),
            'dam_id' => intval($get($row, ['dam_id'])),
            'dam_name' => trim((string) $get($row, ['dam_name', 'dam'])),
            'dam_sire_id' => intval($get($row, ['dam_sire_id'])),
            'dam_sire_name' => trim((string) $get($row, ['dam_sire_name', 'damsire', 'damsire_name'])),
        ];
    }
}

if (!function_exists('bricks_dosage_collect_male_ancestors')) {
    /**
     * Walk the 16 male-line positions in a 4-generation pedigree.
     *
     * @param array<string, array{runner_id:int,name:string,sire_id:int,sire_name:string,dam_id:int,dam_name:string,dam_sire_id:int,dam_sire_name:string}> $pedigree_map
     * @return array<int, array{name:string,generation:int}>
     */
    function bricks_dosage_collect_male_ancestors(array $horse, array $pedigree_map) {
        $out = [];
        $lookup = static function ($id, $name) use ($pedigree_map) {
            $id = intval($id);
            if ($id > 0 && isset($pedigree_map['id:' . $id])) {
                return $pedigree_map['id:' . $id];
            }
            $key = bricks_dosage_normalize_name_key($name);
            if ($key !== '' && isset($pedigree_map['name:' . $key])) {
                return $pedigree_map['name:' . $key];
            }
            return null;
        };

        $walk = static function ($current, $gen) use (&$walk, &$out, $lookup) {
            if ($gen > 4 || !is_array($current)) {
                return;
            }
            $sire_name = $current['sire_name'];
            if ($sire_name !== '') {
                $out[] = ['name' => $sire_name, 'generation' => $gen];
                $sire = $lookup($current['sire_id'], $sire_name);
                if ($sire) {
                    $walk($sire, $gen + 1);
                }
            }

            $dam = $lookup($current['dam_id'], $current['dam_name']);
            if ($dam) {
                $walk($dam, $gen + 1);
            } elseif ($current['dam_sire_name'] !== '' && ($gen + 1) <= 4) {
                $out[] = ['name' => $current['dam_sire_name'], 'generation' => $gen + 1];
                $damsire = $lookup($current['dam_sire_id'], $current['dam_sire_name']);
                if ($damsire) {
                    $walk($damsire, $gen + 2);
                }
            }
        };

        $walk($horse, 1);
        return $out;
    }
}

if (!function_exists('bricks_dosage_index_horse')) {
    /**
     * @param array<string, array> $map
     * @param array{runner_id:int,name:string,sire_id:int,sire_name:string,dam_id:int,dam_name:string,dam_sire_id:int,dam_sire_name:string} $horse
     */
    function bricks_dosage_index_horse(array &$map, array $horse) {
        if ($horse['runner_id'] > 0) {
            $map['id:' . $horse['runner_id']] = $horse;
        }
        $name_key = bricks_dosage_normalize_name_key($horse['name']);
        if ($name_key !== '' && !isset($map['name:' . $name_key])) {
            $map['name:' . $name_key] = $horse;
        }
    }
}

if (!function_exists('bricks_dosage_table_has_column')) {
    function bricks_dosage_table_has_column($table, $column) {
        if (!isset($GLOBALS['wpdb']) || !is_object($GLOBALS['wpdb'])) {
            return false;
        }
        global $wpdb;
        static $cache = [];
        $key = $table . '::' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $row = $wpdb->get_row($wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", $column), ARRAY_A);
        $field = isset($row['Field']) ? (string) $row['Field'] : '';
        $cache[$key] = ($field !== '' && strcasecmp($field, $column) === 0);
        return $cache[$key];
    }
}

if (!function_exists('bricks_dosage_table_exists')) {
    function bricks_dosage_table_exists($table) {
        if (!isset($GLOBALS['wpdb']) || !is_object($GLOBALS['wpdb'])) {
            return false;
        }
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }
}

if (!function_exists('bricks_dosage_fetch_horses_by_ids_or_names')) {
    /**
     * @param int[] $ids
     * @param string[] $names
     * @return array<int, array>
     */
    function bricks_dosage_fetch_horses_by_ids_or_names(array $ids, array $names) {
        if (!isset($GLOBALS['wpdb']) || !is_object($GLOBALS['wpdb'])) {
            return [];
        }
        global $wpdb;
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) {
            return $id > 0;
        })));
        $name_keys = [];
        foreach ($names as $name) {
            $key = bricks_dosage_normalize_name_key($name);
            if ($key !== '') {
                $name_keys[$key] = $name;
            }
        }
        if (empty($ids) && empty($name_keys)) {
            return [];
        }

        $found = [];
        $sources = [
            'ancestry_records',
            'horse_pedigree_index',
            'daily_runners_beta',
            'advance_daily_runners_beta',
            'historic_runners_beta',
        ];

        foreach ($sources as $table) {
            if (!bricks_dosage_table_exists($table)) {
                continue;
            }
            $name_col = bricks_dosage_table_has_column($table, 'runner_name') ? 'runner_name' : 'name';
            if (!bricks_dosage_table_has_column($table, $name_col) && !bricks_dosage_table_has_column($table, 'runner_id')) {
                continue;
            }

            $select = ['runner_id'];
            $select[] = "`{$name_col}` AS name";
            foreach (['sire_id', 'sire_name', 'dam_id', 'dam_name', 'dam_sire_id', 'dam_sire_name'] as $col) {
                if (bricks_dosage_table_has_column($table, $col)) {
                    $select[] = "`{$col}`";
                }
            }

            $order_col = '';
            foreach (['race_id', 'loaded_at'] as $candidate) {
                if (bricks_dosage_table_has_column($table, $candidate)) {
                    $order_col = $candidate;
                    $select[] = "`{$order_col}`";
                    break;
                }
            }

            $where = [];
            $params = [];
            if (!empty($ids) && bricks_dosage_table_has_column($table, 'runner_id')) {
                $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                $where[] = "runner_id IN ($placeholders)";
                $params = array_merge($params, $ids);
            }
            if (!empty($name_keys) && bricks_dosage_table_has_column($table, $name_col)) {
                $raw_names = array_values($name_keys);
                $placeholders = implode(',', array_fill(0, count($raw_names), '%s'));
                $where[] = "`{$name_col}` IN ($placeholders)";
                $params = array_merge($params, $raw_names);
            }
            if (empty($where)) {
                continue;
            }

            $sql = 'SELECT ' . implode(', ', $select) . " FROM `{$table}` WHERE (" . implode(' OR ', $where) . ')';
            if ($order_col !== '' && bricks_dosage_table_has_column($table, 'runner_id')) {
                $sql = 'SELECT * FROM (
                    SELECT src.*, ROW_NUMBER() OVER (PARTITION BY runner_id ORDER BY `' . $order_col . '` DESC) rn
                    FROM (' . $sql . ') src
                ) ranked WHERE rn = 1';
            }

            $rows = empty($params)
                ? $wpdb->get_results($sql)
                : $wpdb->get_results($wpdb->prepare($sql, ...$params));
            if ($wpdb->last_error) {
                if (function_exists('bricks_debug_log')) {
                    bricks_debug_log('Dosage fetch error on ' . $table . ': ' . $wpdb->last_error);
                }
                continue;
            }
            if (!is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                $found[] = bricks_dosage_horse_from_row($row);
            }

            if (count($found) >= (count($ids) + count($name_keys))) {
                break;
            }
        }

        return $found;
    }
}

if (!function_exists('bricks_dosage_build_pedigree_map')) {
    /**
     * Batched 4-generation pedigree fetch for a race card.
     *
     * @param object[] $runners
     * @return array<string, array>
     */
    function bricks_dosage_build_pedigree_map(array $runners) {
        $map = [];
        $pending_ids = [];
        $pending_names = [];

        foreach ($runners as $runner) {
            $horse = bricks_dosage_horse_from_row($runner);
            bricks_dosage_index_horse($map, $horse);
            if ($horse['sire_id'] > 0) {
                $pending_ids[] = $horse['sire_id'];
            }
            if ($horse['dam_id'] > 0) {
                $pending_ids[] = $horse['dam_id'];
            }
            if ($horse['dam_sire_id'] > 0) {
                $pending_ids[] = $horse['dam_sire_id'];
            }
            if ($horse['sire_name'] !== '') {
                $pending_names[] = $horse['sire_name'];
            }
            if ($horse['dam_name'] !== '') {
                $pending_names[] = $horse['dam_name'];
            }
            if ($horse['dam_sire_name'] !== '') {
                $pending_names[] = $horse['dam_sire_name'];
            }
        }

        for ($round = 0; $round < 4; $round++) {
            $need_ids = [];
            $need_names = [];
            foreach ($pending_ids as $id) {
                $id = intval($id);
                if ($id > 0 && !isset($map['id:' . $id])) {
                    $need_ids[] = $id;
                }
            }
            foreach ($pending_names as $name) {
                $key = bricks_dosage_normalize_name_key($name);
                if ($key !== '' && !isset($map['name:' . $key])) {
                    $need_names[] = $name;
                }
            }
            $need_ids = array_values(array_unique($need_ids));
            $need_names = array_values(array_unique($need_names));
            if (empty($need_ids) && empty($need_names)) {
                break;
            }

            $fetched = bricks_dosage_fetch_horses_by_ids_or_names($need_ids, $need_names);
            $pending_ids = [];
            $pending_names = [];
            foreach ($fetched as $horse) {
                bricks_dosage_index_horse($map, $horse);
                if ($horse['sire_id'] > 0 && !isset($map['id:' . $horse['sire_id']])) {
                    $pending_ids[] = $horse['sire_id'];
                }
                if ($horse['dam_id'] > 0 && !isset($map['id:' . $horse['dam_id']])) {
                    $pending_ids[] = $horse['dam_id'];
                }
                if ($horse['dam_sire_id'] > 0 && !isset($map['id:' . $horse['dam_sire_id']])) {
                    $pending_ids[] = $horse['dam_sire_id'];
                }
                if ($horse['sire_name'] !== '') {
                    $pending_names[] = $horse['sire_name'];
                }
                if ($horse['dam_name'] !== '') {
                    $pending_names[] = $horse['dam_name'];
                }
                if ($horse['dam_sire_name'] !== '') {
                    $pending_names[] = $horse['dam_sire_name'];
                }
            }
        }

        return $map;
    }
}

if (!function_exists('bricks_dosage_metrics_for_horse')) {
    /**
     * @param array<string, array> $pedigree_map
     * @param array<string, array{name:string,g1:string,g2:?string}> $chefs
     * @return array
     */
    function bricks_dosage_metrics_for_horse(array $horse, array $pedigree_map, array $chefs) {
        $ancestors = bricks_dosage_collect_male_ancestors($horse, $pedigree_map);
        $profile = bricks_dosage_profile_from_ancestors($ancestors, $chefs);
        $metrics = bricks_dosage_calculate_metrics($profile);
        $metrics['ancestors'] = $ancestors;
        $metrics['chef_hits'] = 0;
        foreach ($ancestors as $ancestor) {
            if (bricks_dosage_find_chef($ancestor['name'], $chefs)) {
                $metrics['chef_hits']++;
            }
        }
        $dp = $metrics['profile'];
        $metrics['profile_label'] = sprintf(
            '%s-%s-%s-%s-%s',
            rtrim(rtrim(number_format($dp['B'], 1, '.', ''), '0'), '.'),
            rtrim(rtrim(number_format($dp['I'], 1, '.', ''), '0'), '.'),
            rtrim(rtrim(number_format($dp['C'], 1, '.', ''), '0'), '.'),
            rtrim(rtrim(number_format($dp['S'], 1, '.', ''), '0'), '.'),
            rtrim(rtrim(number_format($dp['P'], 1, '.', ''), '0'), '.')
        );
        $metrics['tooltip'] = 'Dosage Profile [B, I, C, S, P]: ' . $metrics['profile_label']
            . '. Chefs-de-Race in 4-generation male line: ' . intval($metrics['chef_hits']) . '.';
        return $metrics;
    }
}

if (!function_exists('bricks_dosage_metrics_for_runners')) {
    /**
     * Race-card batch: chefs lookup is in-memory; pedigree is at most 4 batched SQL rounds.
     *
     * @param object[] $runners
     * @return array<string, array>
     */
    function bricks_dosage_metrics_for_runners(array $runners) {
        $out = [];
        if (empty($runners)) {
            return $out;
        }

        try {
            $chefs = bricks_dosage_chefs_lookup();
            $map = bricks_dosage_build_pedigree_map($runners);
        } catch (Throwable $e) {
            if (function_exists('bricks_debug_log')) {
                bricks_debug_log('Dosage pedigree lookup failed: ' . $e->getMessage());
            }
            $chefs = bricks_dosage_index_chefs();
            $map = [];
            foreach ($runners as $runner) {
                bricks_dosage_index_horse($map, bricks_dosage_horse_from_row($runner));
            }
        }

        foreach ($runners as $idx => $runner) {
            $horse = bricks_dosage_horse_from_row($runner);
            $metrics = bricks_dosage_metrics_for_horse($horse, $map, $chefs);
            $runner_id = $horse['runner_id'];
            if ($runner_id > 0) {
                $out['id:' . $runner_id] = $metrics;
            }
            $name_key = bricks_dosage_normalize_name_key($horse['name']);
            if ($name_key !== '') {
                $out['name:' . $name_key] = $metrics;
            }
            $out['idx:' . $idx] = $metrics;
        }

        return $out;
    }
}

if (!function_exists('bricks_dosage_metrics_for_runner_row')) {
    /**
     * @param array<string, array> $lookup
     * @param object $runner
     * @param int $index
     * @return array
     */
    function bricks_dosage_metrics_for_runner_row(array $lookup, $runner, $index = 0) {
        $horse = bricks_dosage_horse_from_row($runner);
        if ($horse['runner_id'] > 0 && isset($lookup['id:' . $horse['runner_id']])) {
            return $lookup['id:' . $horse['runner_id']];
        }
        $name_key = bricks_dosage_normalize_name_key($horse['name']);
        if ($name_key !== '' && isset($lookup['name:' . $name_key])) {
            return $lookup['name:' . $name_key];
        }
        if (isset($lookup['idx:' . $index])) {
            return $lookup['idx:' . $index];
        }
        return bricks_dosage_calculate_metrics(bricks_dosage_empty_profile());
    }
}

if (!function_exists('bricks_dosage_self_test')) {
    /**
     * Secretariat verification plus divide-by-zero / split-chef checks.
     *
     * @return array{passed:bool,results:array<int, array{name:string,ok:bool,expected:string,actual:string}>}
     */
    function bricks_dosage_self_test() {
        $results = [];

        $secretariat = bricks_dosage_calculate_metrics([20, 14, 7, 9, 0]);
        $expected_display = 'DI: 3.00 | CD: 0.90';
        $results[] = [
            'name' => 'Secretariat DP [20,14,7,9,0]',
            'ok' => ($secretariat['display'] === $expected_display
                && abs(($secretariat['di'] ?? 0) - 3.0) < 0.0001
                && abs(($secretariat['cd'] ?? 0) - 0.9) < 0.0001),
            'expected' => $expected_display,
            'actual' => $secretariat['display'],
        ];

        $split = bricks_dosage_profile_from_ancestors(
            [['name' => 'Northern Dancer', 'generation' => 1]],
            bricks_dosage_index_chefs()
        );
        $results[] = [
            'name' => 'Gen1 split chef Northern Dancer (B/C) => 8+8',
            'ok' => ($split['B'] == 8.0 && $split['C'] == 8.0 && $split['I'] == 0.0),
            'expected' => 'B=8 C=8',
            'actual' => 'B=' . $split['B'] . ' C=' . $split['C'],
        ];

        $inf = bricks_dosage_calculate_metrics([10, 6, 0, 0, 0]);
        $results[] = [
            'name' => 'Zero stamina wing => Inf',
            'ok' => ($inf['di_is_infinite'] === true && $inf['display'] === 'DI: Inf | CD: 1.63'),
            'expected' => 'DI: Inf | CD: 1.63',
            'actual' => $inf['display'],
        ];

        $empty = bricks_dosage_calculate_metrics([0, 0, 0, 0, 0]);
        $results[] = [
            'name' => 'Empty profile placeholder',
            'ok' => ($empty['display'] === 'DI: — | CD: —'),
            'expected' => 'DI: — | CD: —',
            'actual' => $empty['display'],
        ];

        $passed = true;
        foreach ($results as $row) {
            if (empty($row['ok'])) {
                $passed = false;
                break;
            }
        }

        return ['passed' => $passed, 'results' => $results];
    }
}
