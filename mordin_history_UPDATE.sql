-- Rebuilds mordin_race_class, mordin_runner_perf, mordin_trainer_group_sr (7-year flat window).
-- Requires: Task 1 functions, mordin_class_matrix (Task 2), sr_results, daily_races_beta join.
-- distance_beaten is margin-to-horse-ahead (not cumulative); see mordin_class_NOTES.md.

DELIMITER $$
DROP PROCEDURE IF EXISTS `mordin_history_UPDATE`$$
CREATE PROCEDURE `mordin_history_UPDATE`()
BEGIN
    DECLARE v_from DATE DEFAULT DATE_SUB(CURDATE(), INTERVAL 7 YEAR);

    DROP TEMPORARY TABLE IF EXISTS tmp_mordin_runner_margins;
    CREATE TEMPORARY TABLE tmp_mordin_runner_margins (
        race_id INT NOT NULL,
        runner_id INT NOT NULL,
        finish_pos_int INT UNSIGNED NOT NULL,
        in_race_comment TEXT,
        trainer_name VARCHAR(100),
        unfinished VARCHAR(50),
        finish_position VARCHAR(10),
        lengths_behind_winner DECIMAL(10,4),
        PRIMARY KEY (runner_id, race_id),
        INDEX idx_tmr_race (race_id)
    ) AS
    SELECT
        hrunb.race_id,
        hrunb.runner_id,
        CAST(hrunb.finish_position AS UNSIGNED) AS finish_pos_int,
        hrunb.in_race_comment,
        hrunb.trainer_name,
        hrunb.unfinished,
        hrunb.finish_position,
        SUM(
            CASE
                WHEN CAST(hrunb.finish_position AS UNSIGNED) > 1 THEN IFNULL(hrunb.distance_beaten, 0)
                ELSE 0
            END
        ) OVER (
            PARTITION BY hrunb.race_id
            ORDER BY CAST(hrunb.finish_position AS UNSIGNED)
            ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
        ) AS lengths_behind_winner
    FROM historic_runners_beta hrunb
    JOIN historic_races_beta hracb ON hracb.race_id = hrunb.race_id
    WHERE hracb.meeting_date >= v_from
      AND hracb.race_type = 'Flat'
      AND hrunb.finish_position REGEXP '^[0-9]+$';

    DROP TEMPORARY TABLE IF EXISTS tmp_mordin_finish;
    CREATE TEMPORARY TABLE tmp_mordin_finish (PRIMARY KEY (race_id)) AS
    SELECT
        m.race_id,
        MAX(CASE WHEN m.finish_pos_int = 1 THEN m.runner_id END) AS winner_runner_id,
        MAX(CASE WHEN m.finish_pos_int = 1 THEN m.in_race_comment END) AS winner_comment,
        MIN(CASE WHEN m.finish_pos_int = 2 THEN m.lengths_behind_winner END) AS db_2nd,
        MIN(CASE WHEN m.finish_pos_int = 3 THEN m.lengths_behind_winner END) AS db_3rd,
        SUM(CASE WHEN m.finish_pos_int > 1 AND m.lengths_behind_winner <= 2 THEN 1 ELSE 0 END) AS within_2l
    FROM tmp_mordin_runner_margins m
    GROUP BY m.race_id;

    DROP TABLE IF EXISTS mordin_race_class;
    CREATE TABLE mordin_race_class (
        race_id INT NOT NULL PRIMARY KEY,
        meeting_date DATE,
        course VARCHAR(50),
        country_group VARCHAR(8),
        race_title VARCHAR(255),
        class_num TINYINT,
        handicap TINYINT,
        age_range VARCHAR(20),
        distance_yards INT,
        prize_pos_1 DECIMAL(12,2),
        tier_code VARCHAR(12),
        tier_rank TINYINT,
        foreign_unquantified TINYINT NOT NULL DEFAULT 0,
        us_lower_graded TINYINT NOT NULL DEFAULT 0,
        is_2yo_only TINYINT NOT NULL DEFAULT 0,
        is_low_tier_juv TINYINT NOT NULL DEFAULT 0,
        maiden_tier TINYINT,
        winner_runner_id INT,
        winning_margin DECIMAL(6,2),
        runners_within_2l INT,
        lengths_2nd_to_3rd DECIMAL(6,2),
        comp_level TINYINT,
        easy_bonus TINYINT NOT NULL DEFAULT 0,
        race_mcr DECIMAL(5,1),
        winner_speed_rating INT,
        INDEX idx_mrc_date (meeting_date),
        INDEX idx_mrc_tier (tier_code, comp_level)
    );

    INSERT INTO mordin_race_class (
        race_id, meeting_date, course, country_group, race_title, class_num, handicap, age_range,
        distance_yards, prize_pos_1, tier_code, tier_rank, foreign_unquantified, us_lower_graded,
        is_2yo_only, is_low_tier_juv, maiden_tier, winner_runner_id, winning_margin, runners_within_2l,
        lengths_2nd_to_3rd, comp_level, easy_bonus, race_mcr, winner_speed_rating
    )
    SELECT
        c.race_id, c.meeting_date, c.course, c.country_group, c.race_title, c.class_num, c.handicap, c.age_range,
        c.distance_yards, c.prize_pos_1, c.tier_code, mordin_tier_rank(c.tier_code),
        IF(c.tier_code = 'UNQ', 1, 0),
        IF(c.country_group = 'USA' AND c.raw_tier IN ('G2', 'G3'), 1, 0),
        mordin_is_2yo_only(c.age_range),
        mordin_is_low_tier_juv(c.race_title, c.handicap, c.age_range),
        mordin_maiden_tier(c.race_title, c.prize_pos_1),
        c.winner_runner_id, c.db_2nd, c.within_2l,
        IF(c.db_2nd IS NOT NULL AND c.db_3rd IS NOT NULL, c.db_3rd - c.db_2nd, NULL),
        c.comp_level,
        mordin_easy_bonus(c.comp_level, c.db_2nd, c.winner_comment),
        m.mcr,
        sr.speed_rating
    FROM (
        SELECT b.*,
               mordin_intl_tier(b.raw_tier, b.country_group) AS tier_code,
               mordin_comp_level(b.db_2nd, b.within_2l, b.winner_comment) AS comp_level
        FROM (
            SELECT
                hracb.race_id, hracb.meeting_date, hracb.course,
                mordin_country_group(dracb.country) AS country_group,
                dracb.race_title,
                mordin_class_num(COALESCE(dracb.class, CAST(hracb.class AS CHAR))) AS class_num,
                hracb.handicap,
                dracb.age_range,
                hracb.distance_yards,
                dracb.prize_pos_1,
                mordin_tier_code(dracb.race_title, COALESCE(dracb.class, CAST(hracb.class AS CHAR)), hracb.handicap) AS raw_tier,
                f.winner_runner_id, f.winner_comment, f.db_2nd, f.db_3rd, f.within_2l
            FROM historic_races_beta hracb
            JOIN tmp_mordin_finish f ON f.race_id = hracb.race_id
            LEFT JOIN daily_races_beta dracb ON dracb.race_id = hracb.race_id
        ) b
    ) c
    LEFT JOIN mordin_class_matrix m ON m.tier_code = c.tier_code AND m.comp_level = c.comp_level
    LEFT JOIN sr_results sr ON sr.race_id = c.race_id AND sr.runner_id = c.winner_runner_id;

    DROP TEMPORARY TABLE IF EXISTS tmp_mordin_finish;

    DROP TABLE IF EXISTS mordin_runner_perf;
    CREATE TABLE mordin_runner_perf (
        runner_id INT NOT NULL,
        race_id INT NOT NULL,
        meeting_date DATE,
        course VARCHAR(50),
        trainer_name VARCHAR(100),
        class_num TINYINT,
        handicap TINYINT,
        distance_yards INT,
        tier_code VARCHAR(12),
        tier_rank TINYINT,
        foreign_unquantified TINYINT NOT NULL DEFAULT 0,
        us_lower_graded TINYINT NOT NULL DEFAULT 0,
        is_low_tier_juv TINYINT NOT NULL DEFAULT 0,
        maiden_tier TINYINT,
        finish_pos INT,
        lengths_to_winner DECIMAL(6,2),
        lengths_2nd_to_3rd DECIMAL(6,2),
        mordin_class_rating DECIMAL(5,1),
        PRIMARY KEY (runner_id, race_id),
        INDEX idx_mrp_race (race_id),
        INDEX idx_mrp_runner_date (runner_id, meeting_date)
    );

    INSERT IGNORE INTO mordin_runner_perf (
        runner_id, race_id, meeting_date, course, trainer_name, class_num, handicap, distance_yards,
        tier_code, tier_rank, foreign_unquantified, us_lower_graded, is_low_tier_juv, maiden_tier,
        finish_pos, lengths_to_winner, lengths_2nd_to_3rd, mordin_class_rating
    )
    SELECT
        p.runner_id, p.race_id, p.meeting_date, p.course, p.trainer_name, p.class_num, p.handicap, p.distance_yards,
        p.tier_code, p.tier_rank, p.foreign_unquantified, p.us_lower_graded, p.is_low_tier_juv, p.maiden_tier,
        p.finish_pos, p.lengths_to_winner, p.lengths_2nd_to_3rd,
        mordin_perf_mcr(p.race_mcr, p.easy_bonus, p.finish_pos, p.lengths_to_winner, p.distance_yards)
    FROM (
        SELECT
            hrunb.runner_id, mrc.race_id, mrc.meeting_date, mrc.course, hrunb.trainer_name,
            mrc.class_num, mrc.handicap, mrc.distance_yards, mrc.tier_code, mrc.tier_rank,
            mrc.foreign_unquantified, mrc.us_lower_graded, mrc.is_low_tier_juv, mrc.maiden_tier,
            mrc.lengths_2nd_to_3rd, mrc.race_mcr, mrc.easy_bonus,
            marg.finish_pos_int AS finish_pos,
            marg.lengths_behind_winner AS lengths_to_winner
        FROM mordin_race_class mrc
        JOIN historic_runners_beta hrunb ON hrunb.race_id = mrc.race_id
        JOIN tmp_mordin_runner_margins marg ON marg.race_id = hrunb.race_id AND marg.runner_id = hrunb.runner_id
        WHERE hrunb.runner_id IS NOT NULL
          AND (hrunb.unfinished != 'Non-Runner' OR hrunb.unfinished IS NULL)
    ) p;

    DROP TEMPORARY TABLE IF EXISTS tmp_mordin_runner_margins;

    DROP TABLE IF EXISTS mordin_trainer_group_sr;
    CREATE TABLE mordin_trainer_group_sr (
        trainer_name VARCHAR(100) NOT NULL PRIMARY KEY,
        group_runs INT NOT NULL,
        group_wins INT NOT NULL,
        rolling_5yr_group_strike_rate DECIMAL(5,1),
        is_group_elite TINYINT NOT NULL DEFAULT 0
    );

    INSERT INTO mordin_trainer_group_sr (trainer_name, group_runs, group_wins, rolling_5yr_group_strike_rate, is_group_elite)
    SELECT
        trainer_name,
        COUNT(*),
        SUM(finish_pos = 1),
        ROUND(100 * SUM(finish_pos = 1) / COUNT(*), 1),
        IF(COUNT(*) >= 20 AND 100 * SUM(finish_pos = 1) / COUNT(*) >= 15, 1, 0)
    FROM mordin_runner_perf
    WHERE tier_rank <= 4
      AND meeting_date >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)
      AND trainer_name IS NOT NULL AND trainer_name != ''
    GROUP BY trainer_name;

    SELECT CONCAT(
        'mordin_history_UPDATE completed — races: ', (SELECT COUNT(*) FROM mordin_race_class),
        ', runs: ', (SELECT COUNT(*) FROM mordin_runner_perf),
        ' at ', NOW()
    ) AS status;
END $$
DELIMITER ;
