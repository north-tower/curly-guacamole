DELIMITER $$
-- Bet Tracker storage (WordPress theme also creates these on first load via inc/bet-tracker.php).
-- On WordPress, table names use $table_prefix from wp-config (e.g. wpdu_user_bets). Replace `user_bets`
-- and `user_bet_legs` below with your prefixed names if running this by hand in phpMyAdmin.
CREATE PROCEDURE `user_bets_UPDATE`()
BEGIN
    CREATE TABLE IF NOT EXISTS `user_bets` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `user_id` bigint(20) unsigned NOT NULL,
        `placed_at` datetime NOT NULL,
        `bet_type` varchar(32) NOT NULL DEFAULT '',
        `each_way` tinyint(1) NOT NULL DEFAULT 0,
        `ew_fraction` decimal(10,6) NOT NULL DEFAULT 0.250000,
        `stake_mode` varchar(16) NOT NULL DEFAULT 'auto',
        `manual_total` decimal(12,2) NOT NULL DEFAULT 0.00,
        `total_stake` decimal(12,2) NOT NULL DEFAULT 0.00,
        `unit_stake` decimal(12,2) NOT NULL DEFAULT 0.00,
        `returns_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
        `profit` decimal(12,2) NOT NULL DEFAULT 0.00,
        `result` varchar(16) NOT NULL DEFAULT 'pending',
        `line_count` smallint(5) unsigned NOT NULL DEFAULT 1,
        `system_name` varchar(190) NOT NULL DEFAULT '',
        `system_id` varchar(64) NOT NULL DEFAULT '',
        `course` varchar(190) NOT NULL DEFAULT '',
        `selection_label` varchar(255) NOT NULL DEFAULT '',
        `odds_display` varchar(190) NOT NULL DEFAULT '',
        `note` varchar(500) NOT NULL DEFAULT '',
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `user_placed` (`user_id`, `placed_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS `user_bet_legs` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `bet_id` bigint(20) unsigned NOT NULL,
        `user_id` bigint(20) unsigned NOT NULL,
        `leg_index` smallint(5) unsigned NOT NULL DEFAULT 0,
        `horse_name` varchar(190) NOT NULL DEFAULT '',
        `course` varchar(190) NOT NULL DEFAULT '',
        `odds_input` varchar(32) NOT NULL DEFAULT '',
        `odds_decimal` decimal(10,4) NOT NULL DEFAULT 0.0000,
        `result` varchar(16) NOT NULL DEFAULT 'pending',
        PRIMARY KEY (`id`),
        KEY `bet_id` (`bet_id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
END$$
DELIMITER ;
